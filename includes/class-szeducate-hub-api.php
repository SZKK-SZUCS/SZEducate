<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Hub_API {

	private $current_client = null;

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_action( 'szeducate_dispatch_course_webhook', array( $this, 'dispatch_course_webhook' ), 10, 2 );
		add_action( 'szeducate_dispatch_course_webhook_batch', array( $this, 'dispatch_course_webhook_batch' ) );
		add_action( 'szeducate_dispatch_delete_webhook', array( $this, 'dispatch_delete_webhook' ), 10, 2 );
	}

	// Sok Képzés egyszerre történő szinkronizálásakor (pl. tömeges migráció után) NEM az
	// egyedi "csak értesítés, majd a kliens hívja vissza a Hub-ot az adatért" (pull-back)
	// mintát ismétli Képzésenként - az ugyanis sok egyidejű eseménynél könnyen elakad, mert a
	// kliens visszahívása UGYANAHHOZ a Hub-hoz fut be, ami épp a kötegelt háttérfolyamatával
	// van elfoglalva. Ehelyett a TELJES adatot egyetlen kérésben, egyenesen ki-PUSH-olja
	// minden kliensnek (a már meglévő /client/sync-course-batch végponton), klienenként
	// egy-egy párhuzamos kérésben - visszahívás nélkül, ezért nem alakulhat ki ez a fajta elakadás.
	public function dispatch_course_webhook_batch( $hub_ids ) {
		if ( ! is_array( $hub_ids ) || empty( $hub_ids ) ) return;

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';

		$hub_ids = array_map( 'intval', $hub_ids );
		$placeholders = implode( ',', array_fill( 0, count( $hub_ids ), '%d' ) );
		$courses = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, course_data, updated_by, updated_at FROM $table_name WHERE id IN ($placeholders)", $hub_ids ), ARRAY_A );
		if ( empty( $courses ) ) return;

		$all_clients = $wpdb->get_results( "SELECT id, client_name, client_url, api_token, permissions FROM {$clients_table} WHERE client_url != '' AND enabled = 1" );

		$requests = array();
		$client_by_key = array();
		$count_by_key = array();

		foreach ( $all_clients as $c ) {
			$c_perms = json_decode( $c->permissions, true );
			$c_conditions = isset( $c_perms['conditions'] ) ? $c_perms['conditions'] : array();

			$allowed_courses = array();
			foreach ( $courses as $course ) {
				$course_data = json_decode( $course['course_data'], true );
				if ( ! is_array( $course_data ) ) $course_data = array();

				if ( ! $this->evaluate_conditions( $c_conditions, $course_data ) ) continue;

				$allowed_courses[] = array(
					'hub_id'      => intval( $course['id'] ),
					'title'       => $course['title'],
					'course_data' => $course_data,
					'updated_by'  => $course['updated_by'],
					'updated_at'  => $course['updated_at'],
				);
			}

			if ( empty( $allowed_courses ) ) continue;

			$key = $c->id;
			$client_by_key[ $key ] = $c;
			$count_by_key[ $key ] = count( $allowed_courses );
			$requests[ $key ] = array(
				'url'     => rtrim( $c->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync-course-batch',
				'method'  => 'POST',
				'body'    => wp_json_encode( array( 'courses' => $allowed_courses ) ),
				'headers' => array( 'Content-Type' => 'application/json', 'X-SZEducate-Auth' => $c->api_token ),
				// A köteg sok Képzést is tartalmazhat, és mivel ez már nem igényel visszahívást
				// a kliens felől, bőven megengedhető egy hosszabb határidő.
				'timeout' => 60,
			);
		}

		$results = SZEducate_Sync_Log::parallel_requests( $requests );

		foreach ( $results as $key => $res ) {
			$c = $client_by_key[ $key ];
			$n = $count_by_key[ $key ];
			if ( $res['error'] ) {
				SZEducate_Sync_Log::add( 'push-course-batch', sprintf( 'Sikertelen kötegelt küldés ("%s", %d Képzés): %s', $c->client_name, $n, $res['error'] ), false );
			} elseif ( $res['code'] >= 200 && $res['code'] < 300 ) {
				SZEducate_Sync_Log::add( 'push-course-batch', sprintf( 'Sikeres kötegelt küldés ("%s", %d Képzés).', $c->client_name, $n ), true );
			} else {
				SZEducate_Sync_Log::add( 'push-course-batch', sprintf( 'Hiba ("%s", %d Képzés): HTTP %d', $c->client_name, $n, $res['code'] ), false );
			}
		}
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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_single_course' ),
				'permission_callback' => array( $this, 'verify_bearer_token' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_course' ),
				'permission_callback' => array( $this, 'verify_bearer_token' ),
			)
		) );

		register_rest_route( 'szeducate/v1/hub', '/backup', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'generate_backup' ),
			'permission_callback' => '__return_true',
		) );

		// A hívó kliens saját, jogosultsági feltételei szerint szűrt teljes képzés-listája -
		// a Kliens ezt használja a manuális "Teljes szinkronizáció" gombnál.
		register_rest_route( 'szeducate/v1/hub', '/courses-mine', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_my_courses' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		// Kliens-független, ÁTFOGÓ szerkesztési előzmény egy Képzéshez - bármelyik kliens
		// kérheti, a Hub ezt egy helyen, minden kliens írását látva vezeti.
		register_rest_route( 'szeducate/v1/hub', '/course-versions', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_course_versions' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		register_rest_route( 'szeducate/v1/hub', '/course-versions/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_course_version_detail' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		// Kliensek közötti (Hub-mediált) szerkesztési zár - jelzi, ha egy Képzést épp
		// valaki más szerkeszt egy MÁSIK kliens oldalán.
		register_rest_route( 'szeducate/v1/hub', '/course-lock', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'acquire_course_lock' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		// Listanézethez: több Képzés zár-állapota egyszerre, zár (meg)szerzése nélkül.
		register_rest_route( 'szeducate/v1/hub', '/course-locks-status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'get_course_locks_status' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );
	}

	public function get_my_courses( WP_REST_Request $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$client = $this->current_client;
		$permissions = json_decode( $client['permissions'], true );
		$conditions = isset( $permissions['conditions'] ) ? $permissions['conditions'] : array();

		$rows = $wpdb->get_results( "SELECT id, title, course_data FROM $table_name", ARRAY_A );
		$courses = array();

		foreach ( $rows as $row ) {
			$course_data = json_decode( $row['course_data'], true );
			if ( ! is_array( $course_data ) ) $course_data = array();

			if ( ! $this->evaluate_conditions( $conditions, $course_data ) ) continue;

			$courses[] = array(
				'hub_id'      => intval( $row['id'] ),
				'title'       => $row['title'],
				'course_data' => $course_data,
			);
		}

		return new WP_REST_Response( array( 'success' => true, 'courses' => $courses ), 200 );
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

		if ( $client && isset( $client['enabled'] ) && intval( $client['enabled'] ) === 0 ) {
			return new WP_Error( 'client_suspended', 'A kliens hozzáférése fel van függesztve.', array( 'status' => 403 ) );
		}

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
			'course_data' => $course_data,
			'updated_by'  => $course['updated_by'],
			'updated_at'  => $course['updated_at'],
		), 200 );
	}

	// --- Kliens-független szerkesztési előzmény: minden Képzésre egyetlen, közös lista fut
	// a Hub-on, hub_id-vel kulcsolva - bármelyik jogosult kliens ugyanazt látja, függetlenül
	// attól, hogy melyik kliens (vagy melyik felhasználó, melyik site-on) mentett utoljára.
	public function get_course_versions( WP_REST_Request $request ) {
		global $wpdb;
		$hub_id = intval( $request->get_param( 'hub_id' ) );
		if ( ! $hub_id ) return new WP_Error( 'missing_hub_id', 'Hiányzó hub_id.', array( 'status' => 400 ) );

		if ( ! $this->client_may_access_course( $hub_id ) ) {
			return new WP_Error( 'forbidden', 'Nincs jogosultsága a kliensnek ehhez a képzéshez.', array( 'status' => 403 ) );
		}

		$versions_table = $wpdb->prefix . 'szeducate_course_versions';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, title, changed_fields, edited_by, edited_at FROM $versions_table WHERE hub_id = %d ORDER BY edited_at DESC, id DESC LIMIT 50",
			$hub_id
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
		$hub_id = intval( $request->get_param( 'hub_id' ) );

		$versions_table = $wpdb->prefix . 'szeducate_course_versions';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $versions_table WHERE id = %d", $version_id ), ARRAY_A );

		if ( ! $row || ( $hub_id && intval( $row['hub_id'] ) !== $hub_id ) ) {
			return new WP_Error( 'not_found', 'A verzió nem található.', array( 'status' => 404 ) );
		}

		if ( ! $this->client_may_access_course( intval( $row['hub_id'] ) ) ) {
			return new WP_Error( 'forbidden', 'Nincs jogosultsága a kliensnek ehhez a képzéshez.', array( 'status' => 403 ) );
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

	// A hívó kliens jogosultsági feltételei szerint hozzáférhet-e a Képzés JELENLEGI
	// állapotához - ugyanaz az ellenőrzés, mint amit get_single_course() is használ,
	// csak megosztva, hogy a verzió-végpontok is újra tudják hasznosítani.
	private function client_may_access_course( $hub_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE id = %d", $hub_id ), ARRAY_A );
		if ( ! $course ) return false;

		$course_data = json_decode( $course['course_data'], true );
		if ( ! is_array( $course_data ) ) $course_data = array();

		$client = $this->current_client;
		$permissions = json_decode( $client['permissions'], true );
		$conditions = isset( $permissions['conditions'] ) ? $permissions['conditions'] : array();

		return $this->evaluate_conditions( $conditions, $course_data );
	}

	// Egy Képzés-mentés eredményét rögzíti a KÖZÖS, kliens-független verzió-előzményekben.
	// Csak az ÚJ állapotot tároljuk el (a régi már benne van az előző verzió-sorban), a
	// "changed_fields" a két állapot közti mező-szintű különbség.
	private function record_course_version( $hub_id, $title, $new_course_data, $old_title, $old_course_data, $editor_label ) {
		global $wpdb;
		$versions_table = $wpdb->prefix . 'szeducate_course_versions';

		$changed_keys = array();
		$is_first_version = ( $old_course_data === null );

		if ( $is_first_version ) {
			$changed_keys[] = '__initial__';
		} else {
			$old_course_data = is_array( $old_course_data ) ? $old_course_data : array();
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

		$wpdb->insert( $versions_table, array(
			'hub_id'         => $hub_id,
			'title'          => $title,
			'course_data'    => wp_json_encode( $new_course_data, JSON_UNESCAPED_UNICODE ),
			'changed_fields' => wp_json_encode( $changed_keys ),
			'edited_by'      => $editor_label,
			'edited_at'      => current_time( 'mysql' ),
		) );

		// Csak az utolsó 50 verziót őrizzük meg Képzésenként, hogy a tábla ne nőjön a végtelenségig.
		$count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $versions_table WHERE hub_id = %d", $hub_id ) ) );
		if ( $count > 50 ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM $versions_table WHERE hub_id = %d ORDER BY edited_at ASC LIMIT %d",
				$hub_id, $count - 50
			) );
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
		$hub_id_param = isset( $params['hub_id'] ) ? intval( $params['hub_id'] ) : 0;
		$course_data = isset( $params['course_data'] ) && is_array( $params['course_data'] ) ? $params['course_data'] : array();

		if ( strlen( wp_json_encode( $course_data ) ) > SZEDUCATE_MAX_COURSE_DATA_SIZE ) {
			return new WP_Error( 'payload_too_large', 'A beküldött Képzés adata túl nagy (max. ' . size_format( SZEDUCATE_MAX_COURSE_DATA_SIZE ) . ').', array( 'status' => 413 ) );
		}

		$existing_row = null;

		if ( $hub_id_param > 0 ) {
			$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d LIMIT 1", $hub_id_param ), ARRAY_A );
		}

		if ( ! $existing_row ) {
			// A cím szerinti párosítást a kliens saját (korábban létrehozott), illetve a még
			// gazda nélküli (owner_client_id IS NULL - pl. a migráció előtti) sorokra
			// korlátozzuk, hogy két kar azonos nevű Képzése ne tudja felülírni egymást.
			$existing_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $table_name WHERE title = %s AND (owner_client_id = %d OR owner_client_id IS NULL) LIMIT 1",
				$title, $client['id']
			), ARRAY_A );
		}

		// A jogosultsági feltételeket MINDIG a ténylegesen érintett rekordhoz viszonyítva
		// ellenőrizzük: meglévő sor esetén a MÁR MENTETT adathoz képest (hogy ne lehessen egy
		// meghamisított beküldött adattal mást felülírni), új sor esetén - nincs mihez
		// viszonyítani - a beküldött adathoz képest.
		$condition_check_data = $course_data;
		if ( $existing_row ) {
			$decoded_existing = json_decode( $existing_row['course_data'], true );
			$condition_check_data = is_array( $decoded_existing ) ? $decoded_existing : array();
		}

		if ( ! $this->evaluate_conditions( $conditions, $condition_check_data ) ) {
			return new WP_Error( 'forbidden_record', 'Nincs jogosultsága a kliensnek ezt a képzést beküldeni.', array( 'status' => 403 ) );
		}

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

		// A ténylegesen szerkesztő felhasználó neve a Kliens oldaláról érkezik (nem
		// kötelező - régebbi/eltérő kliens verzió esetén csak a kliens neve marad) - ez
		// jelenik meg mindenhol "ki módosította utoljára" gyanánt, TÖBBI kliensen is.
		$editor_name = isset( $params['edited_by'] ) ? sanitize_text_field( $params['edited_by'] ) : '';
		$editor_label = 'Kliens: ' . $client['client_name'] . ( $editor_name !== '' ? ' (' . $editor_name . ')' : '' );

		// A verzió-előzményekhez a MÉG felül nem írt régi állapotot kell megőrizni.
		$old_title = $existing_row ? $existing_row['title'] : null;
		$old_course_data = $existing_row ? $existing_course_data : null;

		$db_data = array(
			'title'         => $title,
			'local_post_id' => $local_post_id,
			'course_data'   => wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE ),
			'status'        => 'publish',
			'updated_by'    => $editor_label,
			'updated_at'    => current_time( 'mysql' ),
		);

		$schema_json = get_option( 'szeducate_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		$existing_columns = SZEducate_Activator::get_cached_table_columns( $table_name );

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
			// A gazda (owner_client_id) csak létrehozáskor kerül rögzítésre - a szak innentől
			// mindig ehhez a kliens gazdához marad kötve, még akkor is, ha később más
			// (jogosultan) frissíti a jogosultsági feltételei szerint.
			$db_data['owner_client_id'] = $client['id'];

			$inserted = $wpdb->insert( $table_name, $db_data );
			if ( $inserted === false && $wpdb->last_error ) {
				return new WP_Error( 'db_error', 'MySQL hiba: ' . $wpdb->last_error, array( 'status' => 500 ) );
			}
			$hub_id = $wpdb->insert_id;
			$wpdb->update( $table_name, array( 'hub_id' => $hub_id ), array( 'id' => $hub_id ) );
			$message = 'Sikeresen létrehozva a Hub-on!';
		}

		$this->record_course_version( $hub_id, $title, $course_data, $old_title, $old_course_data, $editor_label );

		// A kliensek értesítése a HÁTTÉRBEN (cronon keresztül) történik, hogy egy lassan válaszoló
		// vagy elérhetetlen kliens ne lassítsa/akassza meg minden egyes Képzés mentését a Hub-on.
		$clients_table = $wpdb->prefix . 'szeducate_clients';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$clients_table}'" ) == $clients_table ) {
			wp_schedule_single_event( time(), 'szeducate_dispatch_course_webhook', array( $hub_id, $client['id'] ) );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'message' => $message,
			'hub_id'  => $hub_id
		), 200 );
	}

	// --- Kliens-webhook kiküldése a HÁTTÉRBEN (WP-Cron), a kérésen kívül.
	// Minden érintett klienshez EGYSZERRE (párhuzamosan) küldjük ki az értesítést, nem
	// egymás után - így egy lassú/elérhetetlen kliens nem lassítja le a többiek értesítését.
	// $exclude_client_id: az a kliens, aki EZT a mentést maga küldte be (receive_course_data) -
	// őt NEM értesítjük vissza, mert a saját mentése válaszából már megkapja/rögzíti a hub_id-t.
	// Enélkül egy versenyhelyzet állhatna elő: ha ez a visszahívás gyorsabban futna le, mint
	// ahogy a beküldő kliens a saját válaszából rögzíti a hub_id-t, a kliens még nem ismerné fel
	// a saját, épp az imént létrehozott rekordját, és egy MÁSODIK (duplikált) helyi Képzést hozna
	// létre ugyanahhoz a hub_id-hoz.
	public function dispatch_course_webhook( $hub_id, $exclude_client_id = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';

		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE id = %d", $hub_id ), ARRAY_A );
		if ( ! $course ) return;

		$course_data = json_decode( $course['course_data'], true );
		if ( ! is_array( $course_data ) ) $course_data = array();

		$all_clients = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, client_name, client_url, api_token, permissions FROM {$clients_table} WHERE client_url != '' AND enabled = 1 AND id != %d",
			$exclude_client_id
		) );

		$requests = array();
		$client_by_key = array();

		foreach ( $all_clients as $c ) {
			// A jogosultsági szabályokat MINDIG a webhook kiküldése előtt ellenőrizzük,
			// hogy a kliens ne kapjon (és ne is próbáljon visszahúzni) számára tiltott képzést.
			$c_perms = json_decode( $c->permissions, true );
			$c_conditions = isset( $c_perms['conditions'] ) ? $c_perms['conditions'] : array();

			if ( ! $this->evaluate_conditions( $c_conditions, $course_data ) ) {
				SZEducate_Sync_Log::add( 'push-course', sprintf( 'Kihagyva ("%s", Hub ID: %d): a kliens jogosultsági feltételei nem engedik.', $c->client_name, $hub_id ), true );
				continue;
			}

			$key = $c->id;
			$client_by_key[ $key ] = $c;
			$requests[ $key ] = array(
				'url'     => rtrim( $c->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync-course',
				'method'  => 'POST',
				'body'    => wp_json_encode( array( 'hub_id' => $hub_id ) ),
				'headers' => array( 'Content-Type' => 'application/json', 'X-SZEducate-Auth' => $c->api_token ),
				'timeout' => 8,
			);
		}

		$results = SZEducate_Sync_Log::parallel_requests( $requests );

		foreach ( $results as $key => $res ) {
			$c = $client_by_key[ $key ];
			if ( $res['error'] ) {
				SZEducate_Sync_Log::add( 'push-course', sprintf( 'Sikertelen küldés ("%s", Hub ID: %d): %s', $c->client_name, $hub_id, $res['error'] ), false );
			} elseif ( $res['code'] >= 200 && $res['code'] < 300 ) {
				SZEducate_Sync_Log::add( 'push-course', sprintf( 'Sikeres küldés ("%s", Hub ID: %d).', $c->client_name, $hub_id ), true );
			} else {
				SZEducate_Sync_Log::add( 'push-course', sprintf( 'Hiba ("%s", Hub ID: %d): HTTP %d', $c->client_name, $hub_id, $res['code'] ), false );
			}
		}
	}

	public function delete_course( WP_REST_Request $request ) {
		global $wpdb;
		$hub_id = intval( $request['id'] );
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$client = $this->current_client;
		$permissions = json_decode( $client['permissions'], true );
		$actions = isset( $permissions['actions'] ) ? $permissions['actions'] : array();
		$conditions = isset( $permissions['conditions'] ) ? $permissions['conditions'] : array();

		if ( isset( $actions['delete'] ) && $actions['delete'] === false ) {
			return new WP_Error( 'forbidden_delete', 'A kliensnek nincs engedélye törlésre.', array( 'status' => 403 ) );
		}

		$course = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $hub_id ), ARRAY_A );
		if ( ! $course ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Képzés már törölve vagy nem létezik.' ), 200 );
		}

		// A törlési jogosultság önmagában nem elég - a törlendő Képzésnek meg is kell felelnie
		// a kliens hozzáférési feltételeinek, különben egy "csak a saját karához" korlátozott
		// kliens is törölhetne bármilyen, máshova tartozó Képzést.
		$course_data = json_decode( $course['course_data'], true );
		if ( ! is_array( $course_data ) ) $course_data = array();

		if ( ! $this->evaluate_conditions( $conditions, $course_data ) ) {
			return new WP_Error( 'forbidden_record', 'Nincs jogosultsága a kliensnek ehhez a képzéshez.', array( 'status' => 403 ) );
		}

		$deleted = $wpdb->delete( $table_name, array( 'id' => $hub_id ) );

		if ( $deleted !== false ) {
			// A törlés-értesítés is a HÁTTÉRBEN megy ki, ugyanazon okból, mint a mentésnél.
			$clients_table = $wpdb->prefix . 'szeducate_clients';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$clients_table}'" ) == $clients_table ) {
				wp_schedule_single_event( time(), 'szeducate_dispatch_delete_webhook', array( $hub_id, $client['id'] ) );
			}
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Sikeresen törölve a Hub-ról és értesítés kiküldve a TÖBBI kliensnek.' ), 200 );
		}

		return new WP_Error( 'db_error', 'Sikertelen törlés az adatbázisból.', array( 'status' => 500 ) );
	}

	// --- Kliens törlés-webhook kiküldése a HÁTTÉRBEN (WP-Cron), a kérésen kívül, párhuzamosan ---
	public function dispatch_delete_webhook( $hub_id, $exclude_client_id ) {
		global $wpdb;
		$clients_table = $wpdb->prefix . 'szeducate_clients';

		$all_clients = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, client_name, client_url, api_token FROM {$clients_table} WHERE client_url != '' AND enabled = 1 AND id != %d",
			$exclude_client_id
		) );

		$requests = array();
		$client_by_key = array();

		foreach ( $all_clients as $c ) {
			$key = $c->id;
			$client_by_key[ $key ] = $c;
			$requests[ $key ] = array(
				'url'     => rtrim( $c->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync-course/' . $hub_id,
				'method'  => 'DELETE',
				'headers' => array( 'Content-Type' => 'application/json', 'X-SZEducate-Auth' => $c->api_token ),
				'timeout' => 8,
			);
		}

		$results = SZEducate_Sync_Log::parallel_requests( $requests );

		foreach ( $results as $key => $res ) {
			$c = $client_by_key[ $key ];
			if ( $res['error'] ) {
				SZEducate_Sync_Log::add( 'delete-course', sprintf( 'Sikertelen törlés-értesítés ("%s", Hub ID: %d): %s', $c->client_name, $hub_id, $res['error'] ), false );
			} elseif ( $res['code'] < 200 || $res['code'] >= 300 ) {
				SZEducate_Sync_Log::add( 'delete-course', sprintf( 'Hiba a törlés-értesítésnél ("%s", Hub ID: %d): HTTP %d', $c->client_name, $hub_id, $res['code'] ), false );
			}
		}
	}

	// --- Kliensek közötti szerkesztési zár egy Képzéshez, tranziens-alapú (nincs külön
	// tábla - automatikusan lejár, ha a szerkesztő becsukja a lapot vagy elveszti a
	// kapcsolatot, mert a Kliens csak ameddig a szerkesztő képernyő nyitva van, újítja meg
	// rendszeresen). Egy időben csak EGY (kliens, felhasználó) pár tarthatja a zárat.
	public function acquire_course_lock( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$hub_id = isset( $params['hub_id'] ) ? intval( $params['hub_id'] ) : 0;
		$user = isset( $params['user'] ) ? sanitize_text_field( $params['user'] ) : 'Ismeretlen';
		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : 'acquire';

		if ( ! $hub_id ) {
			return new WP_Error( 'missing_hub_id', 'Hiányzó hub_id.', array( 'status' => 400 ) );
		}

		$client = $this->current_client;
		$lock_key = 'szeducate_lock_' . $hub_id;
		$current = get_transient( $lock_key );
		$is_same_holder = $current && intval( $current['client_id'] ) === intval( $client['id'] ) && $current['user'] === $user;

		if ( $action === 'release' ) {
			if ( $is_same_holder ) delete_transient( $lock_key );
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		if ( $current && ! $is_same_holder ) {
			return new WP_REST_Response( array(
				'success'          => true,
				'locked'           => true,
				'locked_by_client' => $current['client_name'],
				'locked_by_user'   => $current['user'],
				'locked_at'        => $current['acquired_at'],
			), 200 );
		}

		// Szabad volt, vagy már mi tartjuk - (meg)szerezzük / megújítjuk a zárat.
		set_transient( $lock_key, array(
			'client_id'   => $client['id'],
			'client_name' => $client['client_name'],
			'user'        => $user,
			'acquired_at' => $is_same_holder ? $current['acquired_at'] : current_time( 'mysql' ),
		), 150 ); // 2,5 perc - a Kliens ennél gyakrabban (kb. percenként) újítja meg, amíg nyitva van a szerkesztő.

		return new WP_REST_Response( array( 'success' => true, 'locked' => false ), 200 );
	}

	// Több Képzés zár-állapotának egyszerre történő lekérdezése (csak "kukucskálás" -
	// SOSEM szerez vagy újít meg zárat), hogy a Kliens listanézete egyetlen kéréssel
	// tudja jelezni az összes sorban, ha valamelyiket épp máshol szerkesztik.
	public function get_course_locks_status( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$hub_ids = isset( $params['hub_ids'] ) && is_array( $params['hub_ids'] ) ? array_map( 'intval', $params['hub_ids'] ) : array();
		$hub_ids = array_slice( array_unique( array_filter( $hub_ids ) ), 0, 200 );

		$locks = array();
		foreach ( $hub_ids as $hub_id ) {
			$current = get_transient( 'szeducate_lock_' . $hub_id );
			if ( $current ) {
				$locks[ $hub_id ] = array(
					'locked_by_client' => $current['client_name'],
					'locked_by_user'   => $current['user'],
				);
			}
		}

		return new WP_REST_Response( array( 'success' => true, 'locks' => $locks ), 200 );
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