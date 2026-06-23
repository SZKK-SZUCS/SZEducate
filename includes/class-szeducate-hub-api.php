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

		register_rest_route( 'szeducate/v1/hub', '/courses/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_single_course' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		register_rest_route( 'szeducate/v1/hub', '/backup', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'generate_backup' ),
			'permission_callback' => '__return_true',
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

		if ( is_array( $schema ) ) {
			$permissions = $client ? json_decode( $client['permissions'], true ) : array();
			$fields_perms = isset( $permissions['fields'] ) ? $permissions['fields'] : array();

			foreach ( $schema as &$group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					$active_fields = array();
					
					foreach ( $group['fields'] as &$field ) {
						if ( ! empty( $field['is_archived'] ) && $field['is_archived'] === true ) {
							continue;
						}
						
						if ( isset( $fields_perms[ $field['key'] ] ) && $fields_perms[ $field['key'] ] === 'readonly' ) {
							$field['is_readonly'] = true;
						}
						
						$active_fields[] = $field;
					}
					$group['fields'] = $active_fields;
				}
			}
		}

		return new WP_REST_Response( array(
			'schema'      => $schema,
			'permissions' => $permissions
		), 200 );
	}

	private function evaluate_conditions( $conditions, $course_data ) {
		if ( empty( $conditions ) || empty( $conditions['rules'] ) ) {
			return true; 
		}

		$logical_operator = isset( $conditions['logical_operator'] ) ? $conditions['logical_operator'] : 'AND';
		$results = array();

		foreach ( $conditions['rules'] as $rule ) {
			if ( isset( $rule['logical_operator'] ) ) {
				$results[] = $this->evaluate_conditions( $rule, $course_data );
			} else {
				$field = isset( $rule['field'] ) ? $rule['field'] : '';
				$operator = isset( $rule['operator'] ) ? $rule['operator'] : '==';
				$target_value = isset( $rule['value'] ) ? $rule['value'] : '';

				$actual_value = isset( $course_data[ $field ] ) ? $course_data[ $field ] : '';
				$actual_string = is_array( $actual_value ) ? implode( ';', $actual_value ) : (string) $actual_value;

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

		return $logical_operator === 'AND' ? ! in_array( false, $results, true ) : in_array( true, $results, true );
	}

	public function get_single_course( WP_REST_Request $request ) {
		global $wpdb;
		$hub_id = intval( $request['id'] );
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $hub_id ), ARRAY_A );
		if ( ! $course ) return new WP_Error( 'not_found', 'Képzés nem található.', array( 'status' => 404 ) );
		
		$course_data = json_decode( $course['course_data'], true );
		
		$client = $this->current_client;
		$permissions = json_decode( $client['permissions'], true );
		$conditions = isset( $permissions['conditions'] ) ? $permissions['conditions'] : array();
		
		if ( ! $this->evaluate_conditions( $conditions, $course_data ) ) {
			return new WP_Error( 'forbidden', 'Nincs jogosultsága a kliensnek ehhez a képzéshez.', array( 'status' => 403 ) );
		}
		
		return new WP_REST_Response( array(
			'hub_id'      => $course['id'],
			'title'       => $course['title'],
			'course_data' => $course_data
		), 200 );
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

		if ( ! $this->evaluate_conditions( $conditions, $course_data ) ) {
			return new WP_Error( 'forbidden_record', 'Nincs jogosultsága a kliensnek ezt a képzést beküldeni.', array( 'status' => 403 ) );
		}

		$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE title = %s LIMIT 1", $title ), ARRAY_A );

		if ( $existing_row ) {
			if ( empty( $actions['edit'] ) ) {
				return new WP_Error( 'forbidden_edit', 'A kliensnek nincs engedélye felülírásra.', array( 'status' => 403 ) );
			}
		} else {
			if ( empty( $actions['create'] ) ) {
				return new WP_Error( 'forbidden_create', 'A kliensnek nincs engedélye új létrehozására.', array( 'status' => 403 ) );
			}
		}

		$existing_course_data = array();
		if ( $existing_row && !empty( $existing_row['course_data'] ) ) {
			$existing_course_data = json_decode( $existing_row['course_data'], true );
		}

		foreach ( $fields_perms as $field_key => $perm_type ) {
			if ( $perm_type === 'readonly' ) {
				if ( isset( $existing_course_data[ $field_key ] ) ) {
					$course_data[ $field_key ] = $existing_course_data[ $field_key ];
				} else {
					unset( $course_data[ $field_key ] );
				}
			}
		}

		$db_data = array(
			'title'         => $title,
			'local_post_id' => $local_post_id,
			'course_data'   => wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE ),
			'status'        => 'publish'
		);

		$schema_json = get_option( 'szeducate_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		$existing_columns = $wpdb->get_col( "DESCRIBE $table_name", 0 );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_filterable'] ) && $field['is_filterable'] ) {
							$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							if ( empty( $key ) || ! in_array( $key, $existing_columns ) ) continue;

							$val = isset( $course_data[ $field['key'] ] ) ? $course_data[ $field['key'] ] : '';
							
							if ( $field['type'] === 'number' ) {
								$db_data[$key] = $val !== '' ? intval( $val ) : null;
							} elseif ( $field['type'] === 'boolean' ) {
								$db_data[$key] = $val ? 1 : 0;
							} elseif ( $field['type'] === 'date' ) {
								if ( $val !== '' ) {
									$parsed_date = strtotime( $val );
									$db_data[$key] = $parsed_date !== false ? date( 'Y-m-d H:i:s', $parsed_date ) : null;
								} else {
									$db_data[$key] = null;
								}
							} else {
								if ( is_array( $val ) ) {
									$val = implode( '; ', $val );
								}
								$db_data[$key] = sanitize_text_field( $val );
							}
						}
					}
				}
			}
		}

		if ( $existing_row ) {
			$updated = $wpdb->update( $table_name, $db_data, array( 'id' => $existing_row['id'] ) );
			if ( $updated === false && $wpdb->last_error ) {
				return new WP_Error( 'db_error', 'MySQL hiba: ' . $wpdb->last_error, array( 'status' => 500 ) );
			}
			$hub_id = $existing_row['id'];
			$message = 'Sikeresen frissítve a Hub-on!';
		} else {
			$inserted = $wpdb->insert( $table_name, $db_data );
			if ( $inserted === false && $wpdb->last_error ) {
				return new WP_Error( 'db_error', 'MySQL hiba: ' . $wpdb->last_error, array( 'status' => 500 ) );
			}
			$hub_id = $wpdb->insert_id;
			$wpdb->update( $table_name, array( 'hub_id' => $hub_id ), array( 'id' => $hub_id ) );
			$message = 'Sikeresen létrehozva a Hub-on!';
		}

		$clients_table = $wpdb->prefix . 'szeducate_clients';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$clients_table}'" ) == $clients_table ) {
			$all_clients = $wpdb->get_results( "SELECT client_url FROM {$clients_table} WHERE client_url != ''" );
			foreach ( $all_clients as $c ) {
				$webhook_url = rtrim( $c->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync-course';
				wp_remote_post( $webhook_url, array(
					'blocking' => false,
					'timeout'  => 5,
					'body'     => wp_json_encode( array( 'hub_id' => $hub_id ) ),
					'headers'  => array( 'Content-Type' => 'application/json' )
				) );
			}
		}

		return new WP_REST_Response( array( 
			'success' => true, 
			'message' => $message,
			'hub_id'  => $hub_id
		), 200 );
	}

	public function generate_backup( WP_REST_Request $request ) {
		$incoming_token = $request->get_header( 'x-backup-token' );
		$saved_token    = get_option( 'szeducate_hub_backup_token' );

		if ( empty( $saved_token ) ) {
			$saved_token = wp_generate_password( 32, false );
			update_option( 'szeducate_hub_backup_token', $saved_token );
		}

		if ( empty( $incoming_token ) || $incoming_token !== $saved_token ) {
			return new WP_Error( 'unauthorized_backup', 'Érvénytelen vagy hiányzó X-Backup-Token fejléc.', array( 'status' => 401 ) );
		}

		global $wpdb;
		$courses_table = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';

		$courses = $wpdb->get_results( "SELECT * FROM $courses_table", ARRAY_A );
		$clients = array();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$clients_table'" ) == $clients_table ) {
			$clients = $wpdb->get_results( "SELECT id, client_url, permissions FROM $clients_table", ARRAY_A );
		}

		$schema = json_decode( get_option( 'szeducate_schema', '[]' ), true );

		$backup_data = array(
			'timestamp' => current_time( 'mysql' ),
			'schema'    => $schema,
			'clients'   => $clients,
			'courses'   => $courses
		);

		return new WP_REST_Response( $backup_data, 200 );
	}
}