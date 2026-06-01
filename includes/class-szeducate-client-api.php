<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Client_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {
		// Meglévő: Képzés mentése a React formból
		register_rest_route( 'szeducate/v1/client', '/course', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_course_data' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' ); 
			}
		) );

		// ÚJ: Webhook végpont, amit a Hub hív meg a szinkronizálás indításához
		register_rest_route( 'szeducate/v1/client', '/sync', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST kérést várunk
			'callback'            => array( $this, 'webhook_sync_schema' ),
			'permission_callback' => '__return_true', // Nem kell auth, mert csak egy trigger (a kliens a saját kulcsával kérdezi le a Hubot)
		) );
	}

	public function webhook_sync_schema( WP_REST_Request $request ) {
		$options = get_option( 'szeducate_settings', array() );
		
		if ( empty( $options['hub_url'] ) || empty( $options['api_token'] ) ) {
			return new WP_Error( 'not_configured', 'A Kliens nincs beállítva a Hubhoz.', array( 'status' => 400 ) );
		}

		$endpoint = rtrim( $options['hub_url'], '/' ) . '/wp-json/szeducate/v1/hub/schema';

		// A Kliens lehívja az adatokat a Hubról a saját tokenjével
		$response = wp_remote_get( $endpoint, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 15
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sync_failed', $response->get_error_message(), array( 'status' => 500 ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			if ( isset( $data['schema'] ) ) {
				update_option( 'szeducate_local_schema', wp_json_encode( $data['schema'], JSON_UNESCAPED_UNICODE ) );
			}
			if ( isset( $data['permissions'] ) ) {
				update_option( 'szeducate_client_permissions', wp_json_encode( $data['permissions'], JSON_UNESCAPED_UNICODE ) );
			}

			// Taxonómiák frissítése a háttérben
			require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
			$client = new SZEducate_Client();
			$client->register_dynamic_taxonomies();
			
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Sikeres automatikus szinkronizáció a Hub utasítására.' ), 200 );
		}

		return new WP_Error( 'hub_error', 'Hub hiba (Kód: ' . $code . ')', array( 'status' => 500 ) );
	}

	public function save_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();

		// Alapvető validáció
		if ( empty( $params['title'] ) ) {
			return new WP_Error( 'missing_title', 'A képzés címe kötelező!', array( 'status' => 400 ) );
		}

		$post_id = ! empty( $params['local_post_id'] ) ? intval( $params['local_post_id'] ) : 0;
		$title = sanitize_text_field( $params['title'] );
		
		$dynamic_data = isset( $params['course_data'] ) ? $params['course_data'] : array();

		$wpdb->query( 'START TRANSACTION' );

		try {
			$post_args = array(
				'post_title'  => $title,
				'post_type'   => 'sz_course',
				'post_status' => 'publish'
			);

			if ( $post_id > 0 ) {
				$post_args['ID'] = $post_id;
				$saved_post_id = wp_update_post( $post_args, true );
			} else {
				$saved_post_id = wp_insert_post( $post_args, true );
			}

			if ( is_wp_error( $saved_post_id ) ) {
				throw new Exception( 'Nem sikerült létrehozni a WP bejegyzést.' );
			}
			
			$table_name = $wpdb->prefix . 'szeducate_courses_data';
			
			$json_blob = wp_json_encode( $dynamic_data, JSON_UNESCAPED_UNICODE );

			$db_data = array(
				'local_post_id' => $saved_post_id,
				'title'         => $title,
				'course_data'   => $json_blob,
			);
			$db_formats = array( '%d', '%s', '%s' );

			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE local_post_id = %d", $saved_post_id ) );
			
			if ( $existing ) {
				$result = $wpdb->update( $table_name, $db_data, array( 'id' => $existing ), $db_formats, array( '%d' ) );
			} else {
				$result = $wpdb->insert( $table_name, $db_data, $db_formats );
			}

			if ( false === $result ) {
				throw new Exception( 'Adatbázis írási hiba az egyedi táblában.' );
			}

			$schema_json = get_option( 'szeducate_local_schema', '[]' );
			$schema = json_decode( $schema_json, true );
			if ( is_array( $schema ) ) {
				foreach ( $schema as $group ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_taxonomy'] ) && isset( $dynamic_data[ $field['key'] ] ) ) {
							$tax_slug = 'sz_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							$term_val = sanitize_text_field( $dynamic_data[ $field['key'] ] );
							
							if ( ! empty( $term_val ) ) {
								wp_set_object_terms( $saved_post_id, $term_val, $tax_slug, false );
							}
						}
					}
				}
			}

			$wpdb->query( 'COMMIT' );

			wp_schedule_single_event( time(), 'szeducate_background_sync', array( $saved_post_id ) );

			return new WP_REST_Response( array( 
				'success' => true, 
				'message' => 'Sikeres lokális mentés! Szinkronizálás a Hub felé folyamatban...',
				'local_post_id' => $saved_post_id 
			), 200 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db_transaction_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}