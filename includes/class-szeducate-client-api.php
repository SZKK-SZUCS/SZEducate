<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Client_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {
		register_rest_route( 'szeducate/v1/client', '/course', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_course_data' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' ); 
			}
		) );

		register_rest_route( 'szeducate/v1/client', '/sync', array(
			'methods'             => WP_REST_Server::CREATABLE, 
			'callback'            => array( $this, 'webhook_sync_schema' ),
			'permission_callback' => '__return_true', 
		) );

		register_rest_route( 'szeducate/v1/client', '/sync-course', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'webhook_sync_course' ),
			'permission_callback' => '__return_true', 
		) );
	}

	// --- ÚJ: KIEMELT KÉP AUTOMATIZÁLÁSA ---
	private function set_featured_image_from_data( $post_id, $course_data ) {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		if ( ! is_array( $schema ) ) return;

		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( $field['type'] === 'image' && ! empty( $course_data[ $field['key'] ] ) ) {
					$image_url = $course_data[ $field['key'] ];
					// Megkeressük a média ID-t az URL alapján
					$attachment_id = attachment_url_to_postid( $image_url );
					if ( $attachment_id ) {
						set_post_thumbnail( $post_id, $attachment_id );
					} else {
						delete_post_thumbnail( $post_id );
					}
					return; // Csak az első képet állítjuk be
				}
			}
		}
	}

	public function webhook_sync_schema( WP_REST_Request $request ) {
		$options = get_option( 'szeducate_settings', array() );
		
		if ( empty( $options['hub_url'] ) || empty( $options['api_token'] ) ) {
			return new WP_Error( 'not_configured', 'A Kliens nincs beállítva a Hubhoz.', array( 'status' => 400 ) );
		}

		$endpoint = rtrim( $options['hub_url'], '/' ) . '/wp-json/szeducate/v1/hub/schema';

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

			require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
			$client = new SZEducate_Client();
			$client->register_dynamic_taxonomies();
			
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Sikeres automatikus szinkronizáció a Hub utasítására.' ), 200 );
		}

		return new WP_Error( 'hub_error', 'Hub hiba (Kód: ' . $code . ')', array( 'status' => 500 ) );
	}

	public function webhook_sync_course( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$hub_id = isset( $params['hub_id'] ) ? intval( $params['hub_id'] ) : 0;
		
		if ( ! $hub_id ) return new WP_Error( 'missing_id', 'Hiányzó hub_id.', array( 'status' => 400 ) );
		
		$options = get_option( 'szeducate_settings', array() );
		if ( empty( $options['hub_url'] ) || empty( $options['api_token'] ) ) {
			return new WP_Error( 'not_configured', 'Nincs beállítva a Hub.', array( 'status' => 400 ) );
		}

		$endpoint = rtrim( $options['hub_url'], '/' ) . '/wp-json/szeducate/v1/hub/courses/' . $hub_id;

		$response = wp_remote_get( $endpoint, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 15
		) );

		if ( is_wp_error( $response ) ) return new WP_Error( 'sync_failed', $response->get_error_message(), array( 'status' => 500 ) );

		$code = wp_remote_retrieve_response_code( $response );
		
		if ( $code === 403 || $code === 404 ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Ignorálva: Nincs jogosultság a lekéréshez vagy törölve lett.' ), 200 );
		}
		
		if ( $code === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			if ( isset( $data['hub_id'] ) && isset( $data['title'] ) && isset( $data['course_data'] ) ) {
				$this->update_local_course_from_hub( $data['hub_id'], $data['title'], $data['course_data'] );
				return new WP_REST_Response( array( 'success' => true, 'message' => 'Sikeres valós idejű szinkronizáció.' ), 200 );
			}
		}
		return new WP_Error( 'hub_error', 'Hub hiba (Kód: ' . $code . ')', array( 'status' => 500 ) );
	}

	private function update_local_course_from_hub( $hub_id, $title, $course_data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, local_post_id FROM $table_name WHERE hub_id = %d", $hub_id ), ARRAY_A );
		
		if ( ! $existing ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, local_post_id FROM $table_name WHERE title = %s LIMIT 1", $title ), ARRAY_A );
		}
		
		$json_blob = wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE );

		$local_post_id = 0;
		if ( $existing ) {
			$local_post_id = $existing['local_post_id'];
			if ( $local_post_id ) {
				wp_update_post( array(
					'ID'         => $local_post_id,
					'post_title' => $title
				) );
			}
			$wpdb->update( 
				$table_name, 
				array( 'title' => $title, 'course_data' => $json_blob, 'hub_id' => $hub_id ), 
				array( 'id' => $existing['id'] ) 
			);
		} else {
			$local_post_id = wp_insert_post( array(
				'post_title'  => $title,
				'post_type'   => 'sz_course',
				'post_status' => 'publish'
			) );
			
			$wpdb->insert( $table_name, array(
				'local_post_id' => $local_post_id,
				'hub_id'        => $hub_id,
				'title'         => $title,
				'course_data'   => $json_blob
			) );
		}

		// Kiemelt kép beállítása PULL szinkronkor
		$this->set_featured_image_from_data( $local_post_id, $course_data );

		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		if ( is_array( $schema ) && $local_post_id > 0 ) {
			foreach ( $schema as $group ) {
				if (empty($group['fields'])) continue;
				foreach ( $group['fields'] as $field ) {
					if ( ! empty( $field['is_taxonomy'] ) ) {
						$tax_slug = 'sz_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
						$term_val = isset( $course_data[ $field['key'] ] ) ? sanitize_text_field( $course_data[ $field['key'] ] ) : '';
						
						if ( ! empty( $term_val ) ) {
							wp_set_object_terms( $local_post_id, $term_val, $tax_slug, false );
						} else {
							wp_set_object_terms( $local_post_id, array(), $tax_slug, false );
						}
					}
				}
			}
		}
	}

	public function save_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();

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

			// Kiemelt kép beállítása a React-os mentéskor
			$this->set_featured_image_from_data( $saved_post_id, $dynamic_data );

			$schema_json = get_option( 'szeducate_local_schema', '[]' );
			$schema = json_decode( $schema_json, true );
			if ( is_array( $schema ) ) {
				foreach ( $schema as $group ) {
					if (empty($group['fields'])) continue;
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

			$settings = get_option( 'szeducate_settings', array() );
			$hub_url = rtrim( $settings['hub_url'], '/' );
			$api_token = $settings['api_token'];
			$hub_err_msg = '';

			if ( ! empty( $hub_url ) && ! empty( $api_token ) ) {
				$endpoint = $hub_url . '/wp-json/szeducate/v1/hub/courses';
				$payload = array(
					'local_post_id' => $saved_post_id,
					'title'         => $title,
					'course_data'   => $dynamic_data
				);

				$response = wp_remote_post( $endpoint, array(
					'headers'     => array(
						'Authorization' => 'Bearer ' . $api_token,
						'Content-Type'  => 'application/json',
					),
					'body'        => wp_json_encode( $payload ),
					'timeout'     => 15,
				) );

				if ( is_wp_error( $response ) ) {
					$hub_err_msg = 'Hálózati hiba a Hub felé: ' . $response->get_error_message();
				} else {
					$code = wp_remote_retrieve_response_code( $response );
					$body = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( $code !== 200 && $code !== 201 ) {
						$hub_err_msg = 'Hub elutasítva (Kód ' . $code . '): ' . (isset($body['message']) ? $body['message'] : 'Ismeretlen hiba');
					} else {
						if ( !empty($body['hub_id']) ) {
							$wpdb->update( $table_name, array('hub_id' => intval($body['hub_id'])), array('id' => $existing ? $existing : $wpdb->insert_id) );
						}
					}
				}
			} else {
				$hub_err_msg = 'A Hub URL vagy Token nincs beállítva.';
			}

			if ( $hub_err_msg ) {
				return new WP_REST_Response( array( 
					'success' => false, 
					'message' => 'Helyben mentve, DE a Hub hibát jelzett: ' . $hub_err_msg,
					'local_post_id' => $saved_post_id 
				), 400 );
			}

			return new WP_REST_Response( array( 
				'success' => true, 
				'message' => 'Sikeres mentés és Hub szinkronizáció!',
				'local_post_id' => $saved_post_id 
			), 200 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db_transaction_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}