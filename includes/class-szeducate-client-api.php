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
				return current_user_can( 'edit_sz_courses' ); 
			}
		) );

		register_rest_route( 'szeducate/v1/client', '/sync', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'webhook_sync_schema' ),
			'permission_callback' => array( $this, 'verify_webhook_signature' ),
		) );

		register_rest_route( 'szeducate/v1/client', '/sync-course', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'webhook_sync_course' ),
			'permission_callback' => array( $this, 'verify_webhook_signature' ),
		) );

		register_rest_route( 'szeducate/v1/client', '/sync-course/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'webhook_delete_course' ),
			'permission_callback' => array( $this, 'verify_webhook_signature' ),
		) );

		// Egyetlen kérésben több Képzés átadására (pl. teljes visszaállításnál),
		// hogy ne kelljen soronként külön HTTP kört futtatni a Hub-bal.
		register_rest_route( 'szeducate/v1/client', '/sync-course-batch', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'webhook_sync_course_batch' ),
			'permission_callback' => array( $this, 'verify_webhook_signature' ),
		) );

		// Könnyű "életjel" végpont a Hub -> Kliens kapcsolat teszteléséhez, adatírás nélkül.
		register_rest_route( 'szeducate/v1/client', '/ping', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'handle_ping' ),
			'permission_callback' => array( $this, 'verify_webhook_signature' ),
		) );

		// Verzió-előzmények egy Képzéshez (ki, mikor, mit módosított) + egy korábbi verzió
		// teljes tartalmának lekérése visszaállításhoz.
		register_rest_route( 'szeducate/v1/client', '/course-versions', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_course_versions' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_sz_courses' );
			}
		) );

		register_rest_route( 'szeducate/v1/client', '/course-versions/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_course_version_detail' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_sz_courses' );
			}
		) );
	}

	public function get_course_versions( WP_REST_Request $request ) {
		global $wpdb;
		$post_id = intval( $request->get_param( 'post_id' ) );
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', 'Hiányzó post_id.', array( 'status' => 400 ) );
		}

		$table = $wpdb->prefix . 'szeducate_course_versions';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, title, changed_fields, edited_by, edited_at FROM $table WHERE local_post_id = %d ORDER BY edited_at DESC, id DESC LIMIT 50",
			$post_id
		), ARRAY_A );

		$versions = array();
		foreach ( $rows as $r ) {
			$changed = json_decode( $r['changed_fields'], true );
			$versions[] = array(
				'id'             => intval( $r['id'] ),
				'title'          => $r['title'],
				'changed_fields' => is_array( $changed ) ? $changed : array(),
				'edited_by'      => $r['edited_by'] ? $r['edited_by'] : 'Ismeretlen',
				'edited_at'      => $r['edited_at'],
			);
		}

		return new WP_REST_Response( array( 'success' => true, 'versions' => $versions ), 200 );
	}

	public function get_course_version_detail( WP_REST_Request $request ) {
		global $wpdb;
		$version_id = intval( $request['id'] );
		$post_id = intval( $request->get_param( 'post_id' ) );

		$table = $wpdb->prefix . 'szeducate_course_versions';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $version_id ), ARRAY_A );

		if ( ! $row || ( $post_id && intval( $row['local_post_id'] ) !== $post_id ) ) {
			return new WP_Error( 'not_found', 'A verzió nem található.', array( 'status' => 404 ) );
		}

		$course_data = json_decode( $row['course_data'], true );

		return new WP_REST_Response( array(
			'success'     => true,
			'title'       => $row['title'],
			'course_data' => is_array( $course_data ) ? $course_data : array(),
			'edited_by'   => $row['edited_by'] ? $row['edited_by'] : 'Ismeretlen',
			'edited_at'   => $row['edited_at'],
		), 200 );
	}

	// Egy Képzés-mentés eredményét rögzíti a verzió-előzményekben: mit (mely mezőket)
	// és ki módosított. Csak az ÚJ állapotot tároljuk el (a régi már benne van az előző
	// verzió-sorban), a "changed_fields" a két állapot közti mező-szintű különbség.
	private function record_course_version( $post_id, $title, $new_course_data, $old_title, $old_course_data, $json_blob ) {
		global $wpdb;
		$versions_table = $wpdb->prefix . 'szeducate_course_versions';

		$changed_keys = array();
		$is_first_version = ( $old_course_data === null );
		$old_course_data = is_array( $old_course_data ) ? $old_course_data : array();

		if ( $is_first_version ) {
			$changed_keys[] = '__initial__';
		} else {
			$all_keys = array_unique( array_merge( array_keys( $old_course_data ), array_keys( $new_course_data ) ) );
			foreach ( $all_keys as $k ) {
				$old_val = isset( $old_course_data[ $k ] ) ? $old_course_data[ $k ] : null;
				$new_val = isset( $new_course_data[ $k ] ) ? $new_course_data[ $k ] : null;
				if ( wp_json_encode( $old_val ) !== wp_json_encode( $new_val ) ) {
					$changed_keys[] = $k;
				}
			}
			if ( $old_title !== null && $old_title !== $title ) {
				array_unshift( $changed_keys, '__title__' );
			}
		}

		$current_user = wp_get_current_user();
		$editor_label = $current_user && $current_user->exists()
			? ( $current_user->display_name ? $current_user->display_name : $current_user->user_login )
			: 'Ismeretlen';

		$wpdb->insert( $versions_table, array(
			'local_post_id'  => $post_id,
			'title'          => $title,
			'course_data'    => $json_blob,
			'changed_fields' => wp_json_encode( $changed_keys ),
			'edited_by'      => $editor_label,
			'edited_at'      => current_time( 'mysql' ),
		) );

		// Csak az utolsó 50 verziót őrizzük meg Képzésenként, hogy a tábla ne nőjön a végtelenségig.
		$count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $versions_table WHERE local_post_id = %d", $post_id ) ) );
		if ( $count > 50 ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM $versions_table WHERE local_post_id = %d ORDER BY edited_at ASC LIMIT %d",
				$post_id, $count - 50
			) );
		}
	}

	public function handle_ping( WP_REST_Request $request ) {
		return new WP_REST_Response( array(
			'success' => true,
			'message' => 'pong',
			'site'    => get_bloginfo( 'name' ),
			'time'    => current_time( 'mysql' ),
		), 200 );
	}

	// A Hub -> Kliens webhookokat a Kliens saját (a Hub felé is használt) API tokenjének
	// hash-ével hitelesítjük. A Hub ezt a hash-et MÁR ismeri (ő tárolja, a bejövő kérések
	// ellenőrzésére is ezt használja), így nem kell egy újabb, külön megosztott titkot
	// bevezetni és karbantartani - a meglévő token bármelyik irányban ugyanazt bizonyítja.
	public function verify_webhook_signature( WP_REST_Request $request ) {
		$options = get_option( 'szeducate_settings', array() );
		$configured_token = isset( $options['api_token'] ) ? $options['api_token'] : '';

		if ( empty( $configured_token ) ) {
			return new WP_Error( 'not_configured', 'A Kliens nincs beállítva a Hubhoz.', array( 'status' => 401 ) );
		}

		$incoming_hash = $request->get_header( 'x-szeducate-auth' );
		if ( empty( $incoming_hash ) ) {
			return new WP_Error( 'missing_auth', 'Hiányzó hitelesítés.', array( 'status' => 401 ) );
		}

		$expected_hash = hash( 'sha256', $configured_token );

		if ( ! hash_equals( $expected_hash, $incoming_hash ) ) {
			return new WP_Error( 'invalid_auth', 'Érvénytelen hitelesítés.', array( 'status' => 403 ) );
		}

		return true;
	}

	private function flatten_dynamic_columns( $course_data, $table_name ) {
		global $wpdb;
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		if ( ! is_array( $schema ) ) return array();

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
		$existing_columns = SZEducate_Activator::get_cached_table_columns( $table_name );
		$db_data = array();

		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( empty( $field['is_filterable'] ) ) continue;

				$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
				if ( empty( $key ) || ! in_array( $key, $existing_columns ) ) continue;

				$val = isset( $course_data[ $field['key'] ] ) ? $course_data[ $field['key'] ] : '';

				if ( $field['type'] === 'number' ) {
					$db_data[$key] = $val !== '' ? intval( $val ) : null;
				} elseif ( $field['type'] === 'boolean' || $field['type'] === 'true_false' ) {
					$db_data[$key] = $val ? 1 : 0;
				} elseif ( $field['type'] === 'date' ) {
					$db_data[$key] = ( $val !== '' ) ? date( 'Y-m-d H:i:s', strtotime( $val ) ) : null;
				} else {
					if ( is_array( $val ) ) $val = implode( '; ', $val );
					$db_data[$key] = sanitize_text_field( $val );
				}
			}
		}

		return $db_data;
	}

	private function set_featured_image_from_data( $post_id, $course_data ) {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		if ( ! is_array( $schema ) ) return;

		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( $field['type'] === 'image' && ! empty( $course_data[ $field['key'] ] ) ) {
					$image_url = $course_data[ $field['key'] ];

					$attachment_id = attachment_url_to_postid( $image_url );
					if ( $attachment_id ) {
						set_post_thumbnail( $post_id, $attachment_id );
					} else {
						delete_post_thumbnail( $post_id );
					}
					return;
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

				require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
				SZEducate_Activator::update_database_schema();
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

	public function webhook_delete_course( WP_REST_Request $request ) {
		global $szeducate_is_local_deleting;
		if ( ! empty( $szeducate_is_local_deleting ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Helyi törlés volt, a Webhook ignorálva.' ), 200 );
		}

		$hub_id = intval( $request['id'] );
		if ( ! $hub_id ) return new WP_Error( 'missing_id', 'Hiányzó hub_id.', array( 'status' => 400 ) );

		$deleted = $this->delete_local_course_by_hub_id( $hub_id );

		return new WP_REST_Response( array(
			'success' => true,
			'message' => $deleted ? 'Sikeresen törölve a Kliens gépéről.' : 'Helyben már nem létezett a képzés.'
		), 200 );
	}

	private function delete_local_course_by_hub_id( $hub_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$course = $wpdb->get_row( $wpdb->prepare( "SELECT local_post_id FROM $table_name WHERE hub_id = %d", $hub_id ), ARRAY_A );

		if ( ! $course ) return false;

		if ( ! empty( $course['local_post_id'] ) ) {
			global $szeducate_is_sync_deleting;
			$szeducate_is_sync_deleting = true;
			wp_delete_post( $course['local_post_id'], true );
		}
		$wpdb->delete( $table_name, array( 'hub_id' => $hub_id ) );

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		SZEducate_Client::invalidate_courses_cache();

		return true;
	}

	// --- Több Képzés fogadása és törlése EGY kérésben (pl. teljes visszaállítás esetén) ---
	public function webhook_sync_course_batch( WP_REST_Request $request ) {
		@set_time_limit( 0 );
		$params = $request->get_json_params();
		$courses = isset( $params['courses'] ) && is_array( $params['courses'] ) ? $params['courses'] : array();
		$deletes = isset( $params['deletes'] ) && is_array( $params['deletes'] ) ? $params['deletes'] : array();

		$synced = 0;
		foreach ( $courses as $c ) {
			if ( empty( $c['hub_id'] ) || ! isset( $c['title'] ) || ! isset( $c['course_data'] ) ) continue;

			$ok = $this->update_local_course_from_hub(
				intval( $c['hub_id'] ),
				sanitize_text_field( $c['title'] ),
				is_array( $c['course_data'] ) ? $c['course_data'] : array()
			);
			if ( $ok ) $synced++;
		}

		$deleted = 0;
		foreach ( $deletes as $hub_id ) {
			if ( $this->delete_local_course_by_hub_id( intval( $hub_id ) ) ) $deleted++;
		}

		return new WP_REST_Response( array(
			'success' => true,
			'message' => sprintf( '%d képzés szinkronizálva, %d törölve.', $synced, $deleted ),
			'synced'  => $synced,
			'deleted' => $deleted
		), 200 );
	}

	public function update_local_course_from_hub( $hub_id, $title, $course_data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$json_blob = wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE );
		if ( strlen( $json_blob ) > SZEDUCATE_MAX_COURSE_DATA_SIZE ) {
			SZEducate_Sync_Log::add( 'sync-course', sprintf( 'Kihagyva (Hub ID: %d, "%s"): a Képzés adata túl nagy.', $hub_id, $title ), false );
			return false;
		}

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, local_post_id FROM $table_name WHERE hub_id = %d", $hub_id ), ARRAY_A );

		if ( ! $existing ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, local_post_id FROM $table_name WHERE title = %s LIMIT 1", $title ), ARRAY_A );
		}

		$dynamic_columns = $this->flatten_dynamic_columns( $course_data, $table_name );

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
				array_merge( array( 'title' => $title, 'course_data' => $json_blob, 'hub_id' => $hub_id ), $dynamic_columns ),
				array( 'id' => $existing['id'] )
			);
		} else {
			$local_post_id = wp_insert_post( array(
				'post_title'  => $title,
				'post_type'   => 'sz_course',
				'post_status' => 'publish'
			) );

			$wpdb->insert( $table_name, array_merge( array(
				'local_post_id' => $local_post_id,
				'hub_id'        => $hub_id,
				'title'         => $title,
				'course_data'   => $json_blob
			), $dynamic_columns ) );
		}

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

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		SZEducate_Client::invalidate_courses_cache();

		return true;
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

		if ( strlen( wp_json_encode( $dynamic_data ) ) > SZEDUCATE_MAX_COURSE_DATA_SIZE ) {
			return new WP_Error( 'payload_too_large', 'A Képzés adata túl nagy (max. ' . size_format( SZEDUCATE_MAX_COURSE_DATA_SIZE ) . ').', array( 'status' => 413 ) );
		}

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

			$db_data = array_merge( array(
				'local_post_id' => $saved_post_id,
				'title'         => $title,
				'course_data'   => $json_blob,
			), $this->flatten_dynamic_columns( $dynamic_data, $table_name ) );

			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, hub_id, title, course_data FROM $table_name WHERE local_post_id = %d", $saved_post_id ), ARRAY_A );

			$current_hub_id = 0;
			$old_title = null;
			$old_course_data = null;
			if ( $existing ) {
				$current_hub_id = intval($existing['hub_id']);
				$old_title = $existing['title'];
				$old_course_data = json_decode( $existing['course_data'], true );
				$result = $wpdb->update( $table_name, $db_data, array( 'id' => $existing['id'] ) );
			} else {
				$result = $wpdb->insert( $table_name, $db_data );
			}

			if ( false === $result ) {
				throw new Exception( 'Adatbázis írási hiba az egyedi táblában.' );
			}

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

			$this->record_course_version( $saved_post_id, $title, $dynamic_data, $old_title, $old_course_data, $json_blob );

			$wpdb->query( 'COMMIT' );

			require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
			SZEducate_Client::invalidate_courses_cache();

			$settings = get_option( 'szeducate_settings', array() );
			$hub_url = rtrim( $settings['hub_url'], '/' );
			$api_token = $settings['api_token'];
			$hub_err_msg = '';

			if ( ! empty( $hub_url ) && ! empty( $api_token ) ) {
				$endpoint = $hub_url . '/wp-json/szeducate/v1/hub/courses';
				$payload = array(
					'local_post_id' => $saved_post_id,
					'hub_id'        => $current_hub_id,
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
							$wpdb->update( $table_name, array('hub_id' => intval($body['hub_id'])), array('id' => $existing ? $existing['id'] : $wpdb->insert_id) );
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