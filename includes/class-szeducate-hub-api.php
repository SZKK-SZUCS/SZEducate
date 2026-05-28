<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Hub_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {
		register_rest_route( 'szeducate/v1/hub', '/schema', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_schema' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		register_rest_route( 'szeducate/v1/hub', '/courses', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( $this, 'receive_course_data' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );
	}

	public function verify_bearer_token( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'authorization' );
		
		if ( empty( $auth_header ) || ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return new WP_Error( 'missing_token', 'Hiányzó Bearer token.', array( 'status' => 401 ) );
		}

		$incoming_token = $matches[1];
		$incoming_hash = hash( 'sha256', $incoming_token );

		$stored_tokens = get_option( 'szeducate_api_tokens', array() );

		foreach ( $stored_tokens as $token_data ) {
			if ( hash_equals( $token_data['hash'], $incoming_hash ) ) {
				return true;
			}
		}

		return new WP_Error( 'invalid_token', 'Érvénytelen API token.', array( 'status' => 403 ) );
	}

	public function get_schema( WP_REST_Request $request ) {
		$schema = get_option( 'szeducate_schema', '[]' );
		return new WP_REST_Response( json_decode( $schema ), 200 );
	}

	// ÚJ: A tényleges adatbázisba mentő logika a Hub-on
	public function receive_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$params = $request->get_json_params();

		// Alapvető validáció
		if ( empty( $params['title'] ) ) {
			return new WP_Error( 'missing_data', 'A képzés címe hiányzik.', array( 'status' => 400 ) );
		}

		$title = sanitize_text_field( $params['title'] );
		$local_post_id = isset( $params['local_post_id'] ) ? intval( $params['local_post_id'] ) : 0;
		$course_data = isset( $params['course_data'] ) && is_array( $params['course_data'] ) ? $params['course_data'] : array();

		// Sémából kinyerjük az indexelt (szűrhető) mezőket
		$schema_json = get_option( 'szeducate_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		$db_data = array(
			'title'         => $title,
			'local_post_id' => $local_post_id,
			'course_data'   => wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE ),
			'status'        => 'publish'
		);

		// Dedikált MySQL oszlopok feltöltése, ha az indexelés be van pipálva a sémában
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_filterable'] ) && $field['is_filterable'] ) {
							$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							if ( empty( $key ) ) continue;

							$val = isset( $course_data[ $field['key'] ] ) ? $course_data[ $field['key'] ] : '';
							
							// Típuskonverzió SQL-hez
							if ( $field['type'] === 'number' ) {
								$db_data[$key] = $val !== '' ? intval( $val ) : null;
							} elseif ( $field['type'] === 'boolean' ) {
								$db_data[$key] = $val ? 1 : 0;
							} else {
								// Szöveges vagy checkbox (multiselect)
								if ( is_array( $val ) ) {
									$val = implode( ', ', $val );
								}
								$db_data[$key] = sanitize_text_field( $val );
							}
						}
					}
				}
			}
		}

		// Megnézzük, létezik-e már ez a képzés a Hub-on (a cím alapján)
		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE title = %s LIMIT 1", $title ) );

		if ( $existing_id ) {
			// Frissítés
			$wpdb->update( $table_name, $db_data, array( 'id' => $existing_id ) );
			$hub_id = $existing_id;
			$message = 'Sikeresen frissítve a Hub-on!';
		} else {
			// Új beszúrás
			$wpdb->insert( $table_name, $db_data );
			$hub_id = $wpdb->insert_id;
			
			// Mivel ez a Hub, a saját belső 'id'-je lesz a hivatalos 'hub_id' is. 
			$wpdb->update( $table_name, array( 'hub_id' => $hub_id ), array( 'id' => $hub_id ) );
			
			$message = 'Sikeresen létrehozva a Hub-on!';
		}

		// Visszaküldjük a Hub ID-t a Kliensnek, amit ő a saját táblájába szépen lement
		return new WP_REST_Response( array( 
			'success' => true, 
			'message' => $message,
			'hub_id'  => $hub_id
		), 200 );
	}
}