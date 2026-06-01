<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Hub_API {

	private $current_client = null;

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
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'receive_course_data' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );
	}

	public function verify_bearer_token( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'authorization' );
		
		if ( empty( $auth_header ) || ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return new WP_Error( 'missing_token', 'Hiányzó Bearer token.', array( 'status' => 401 ) );
		}

		$incoming_token = trim( sanitize_text_field( $matches[1] ) );
		$incoming_hash = hash( 'sha256', $incoming_token ); 
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_clients';
		
		$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE api_token = %s", $incoming_hash ), ARRAY_A );

		if ( $client ) {
			$this->current_client = $client;
			return true;
		}

		return new WP_Error( 'invalid_token', 'Érvénytelen vagy visszavont API token.', array( 'status' => 403 ) );
	}

	public function get_schema( WP_REST_Request $request ) {
		$schema = json_decode( get_option( 'szeducate_schema', '[]' ), true );
		$client = $this->current_client;
		$permissions = array();

		if ( $client && is_array( $schema ) ) {
			$permissions = json_decode( $client['permissions'], true );
			$fields_perms = isset( $permissions['fields'] ) ? $permissions['fields'] : array();

			foreach ( $schema as &$group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as &$field ) {
						if ( isset( $fields_perms[ $field['key'] ] ) && $fields_perms[ $field['key'] ] === 'readonly' ) {
							$field['is_readonly'] = true;
						}
					}
				}
			}
		}

		// ÚJ: A Séma mellé csomagoljuk a Kliens jogosultságait is!
		return new WP_REST_Response( array(
			'schema'      => $schema,
			'permissions' => $permissions
		), 200 );
	}

	/**
	 * Rekurzív függvény a beágyazott feltételek kiértékelésére
	 */
	private function evaluate_conditions( $conditions, $course_data ) {
		if ( empty( $conditions ) || empty( $conditions['rules'] ) ) {
			return true; // Nincs korlátozó szabály
		}

		$logical_operator = isset( $conditions['logical_operator'] ) ? $conditions['logical_operator'] : 'AND';
		$results = array();

		foreach ( $conditions['rules'] as $rule ) {
			if ( isset( $rule['logical_operator'] ) ) {
				// Al-csoport kiértékelése
				$results[] = $this->evaluate_conditions( $rule, $course_data );
			} else {
				// Szimpla szabály kiértékelése
				$field = isset( $rule['field'] ) ? $rule['field'] : '';
				$operator = isset( $rule['operator'] ) ? $rule['operator'] : '==';
				$target_value = isset( $rule['value'] ) ? $rule['value'] : '';

				$actual_value = isset( $course_data[ $field ] ) ? $course_data[ $field ] : '';
				// Ha a bejövő adat tömb (pl. checkbox), sztringgé alakítjuk az összehasonlításhoz
				$actual_string = is_array( $actual_value ) ? implode( ',', $actual_value ) : (string) $actual_value;

				$rule_result = true;
				switch ( $operator ) {
					case '==':
						$rule_result = ( $actual_string === $target_value );
						break;
					case '!=':
						$rule_result = ( $actual_string !== $target_value );
						break;
					case 'contains':
						$rule_result = ( strpos( $actual_string, $target_value ) !== false );
						break;
				}
				$results[] = $rule_result;
			}
		}

		if ( $logical_operator === 'AND' ) {
			return ! in_array( false, $results, true );
		} else { // OR
			return in_array( true, $results, true );
		}
	}

	public function receive_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$client = $this->current_client;
		
		if ( ! $client ) {
			return new WP_Error( 'unauthorized', 'Nem azonosítható kliens.', array( 'status' => 401 ) );
		}
		
		$permissions = json_decode( $client['permissions'], true );
		$actions = isset( $permissions['actions'] ) ? $permissions['actions'] : array( 'create' => true, 'edit' => true );
		$conditions = isset( $permissions['conditions'] ) ? $permissions['conditions'] : array();
		$fields_perms = isset( $permissions['fields'] ) ? $permissions['fields'] : array();

		$params = $request->get_json_params();

		if ( empty( $params['title'] ) ) {
			return new WP_Error( 'missing_data', 'A képzés címe hiányzik.', array( 'status' => 400 ) );
		}

		$title = sanitize_text_field( $params['title'] );
		$local_post_id = isset( $params['local_post_id'] ) ? intval( $params['local_post_id'] ) : 0;
		$course_data = isset( $params['course_data'] ) && is_array( $params['course_data'] ) ? $params['course_data'] : array();

		// --- 1. JOGOSULTSÁG: REKORDSZINTŰ SZŰRÉS ---
		if ( ! $this->evaluate_conditions( $conditions, $course_data ) ) {
			return new WP_Error( 'forbidden_record', 'Nincs jogosultsága a kliensnek ezen paraméterekkel rendelkező képzést beküldeni.', array( 'status' => 403 ) );
		}

		// Keresés, hogy létezik-e már a képzés
		$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE title = %s LIMIT 1", $title ), ARRAY_A );

		// --- 2. JOGOSULTSÁG: CRUD AKCIÓK ---
		if ( $existing_row ) {
			if ( empty( $actions['edit'] ) ) {
				return new WP_Error( 'forbidden_edit', 'A kliensnek nincs engedélye meglévő képzések felülírására.', array( 'status' => 403 ) );
			}
		} else {
			if ( empty( $actions['create'] ) ) {
				return new WP_Error( 'forbidden_create', 'A kliensnek nincs engedélye új képzések létrehozására.', array( 'status' => 403 ) );
			}
		}

		// --- 3. JOGOSULTSÁG: MEZŐSZINTŰ VÉDELEM (READ-ONLY) ---
		$existing_course_data = array();
		if ( $existing_row && !empty( $existing_row['course_data'] ) ) {
			$existing_course_data = json_decode( $existing_row['course_data'], true );
		}

		foreach ( $fields_perms as $field_key => $perm_type ) {
			if ( $perm_type === 'readonly' ) {
				if ( isset( $existing_course_data[ $field_key ] ) ) {
					// Ha van régi adat, azzal felülírjuk a bejövőt (védjük a módosítástól)
					$course_data[ $field_key ] = $existing_course_data[ $field_key ];
				} else {
					// Ha új adat lenne, elvesszük a jogosultságot a beállításához
					unset( $course_data[ $field_key ] );
				}
			}
		}

		// --- ADATBÁZIS MENTÉS INNENTŐL ---
		$schema_json = get_option( 'szeducate_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		$db_data = array(
			'title'         => $title,
			'local_post_id' => $local_post_id,
			'course_data'   => wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE ),
			'status'        => 'publish'
		);

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_filterable'] ) && $field['is_filterable'] ) {
							$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							if ( empty( $key ) ) continue;

							$val = isset( $course_data[ $field['key'] ] ) ? $course_data[ $field['key'] ] : '';
							
							if ( $field['type'] === 'number' ) {
								$db_data[$key] = $val !== '' ? intval( $val ) : null;
							} elseif ( $field['type'] === 'boolean' ) {
								$db_data[$key] = $val ? 1 : 0;
							} else {
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

		if ( $existing_row ) {
			$wpdb->update( $table_name, $db_data, array( 'id' => $existing_row['id'] ) );
			$hub_id = $existing_row['id'];
			$message = 'Sikeresen frissítve a Hub-on! (Jogosultságok érvényesítve)';
		} else {
			$wpdb->insert( $table_name, $db_data );
			$hub_id = $wpdb->insert_id;
			$wpdb->update( $table_name, array( 'hub_id' => $hub_id ), array( 'id' => $hub_id ) );
			$message = 'Sikeresen létrehozva a Hub-on! (Jogosultságok érvényesítve)';
		}

		return new WP_REST_Response( array( 
			'success' => true, 
			'message' => $message,
			'hub_id'  => $hub_id
		), 200 );
	}
}