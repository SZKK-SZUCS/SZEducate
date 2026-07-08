<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Hub-oldali áttekintő oldal: minden Képzés, azok gazdája, aktuális szinkron-láthatósága
// és teljes tárolt adata egy helyen, hibakereséshez és általános adatbázis-belátáshoz. Innen
// szerkeszthető, archiválható, törölhető és manuálisan (újra)szinkronizálható is egy Képzés.
class SZEducate_Courses_Overview {

	private $courses_table;
	private $clients_table;

	public function __construct() {
		global $wpdb;
		$this->courses_table = $wpdb->prefix . 'szeducate_courses_data';
		$this->clients_table = $wpdb->prefix . 'szeducate_clients';
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	public function add_page() {
		add_submenu_page(
			'szeducate-settings',
			'Képzések Áttekintése',
			'Képzések',
			'manage_options',
			'szeducate-courses',
			array( $this, 'render_page' )
		);
	}

	// ------------------------------------------------------------------
	// Műveletek: archiválás, visszaállítás, törlés, manuális szinkron, szerkesztés mentése
	// ------------------------------------------------------------------
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'szeducate-courses' ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		global $wpdb;

		if ( isset( $_GET['action'], $_GET['course_id'] ) && $_GET['action'] === 'archive_course' ) {
			$course_id = intval( $_GET['course_id'] );
			check_admin_referer( 'archive_course_' . $course_id );

			$wpdb->update( $this->courses_table, array( 'status' => 'archived' ), array( 'id' => $course_id ) );
			$this->dispatch_delete_to_all_clients( $course_id );

			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=archived' ) );
			exit;
		}

		if ( isset( $_GET['action'], $_GET['course_id'] ) && $_GET['action'] === 'unarchive_course' ) {
			$course_id = intval( $_GET['course_id'] );
			check_admin_referer( 'unarchive_course_' . $course_id );

			$wpdb->update( $this->courses_table, array( 'status' => 'publish' ), array( 'id' => $course_id ) );
			wp_schedule_single_event( time(), 'szeducate_dispatch_course_webhook', array( $course_id ) );

			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=unarchived' ) );
			exit;
		}

		if ( isset( $_GET['action'], $_GET['course_id'] ) && $_GET['action'] === 'delete_course_admin' ) {
			$course_id = intval( $_GET['course_id'] );
			check_admin_referer( 'delete_course_admin_' . $course_id );

			$wpdb->delete( $this->courses_table, array( 'id' => $course_id ) );
			$this->dispatch_delete_to_all_clients( $course_id );

			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=deleted' ) );
			exit;
		}

		if ( isset( $_GET['action'], $_GET['course_id'] ) && $_GET['action'] === 'sync_course' ) {
			$course_id = intval( $_GET['course_id'] );
			check_admin_referer( 'sync_course_' . $course_id );

			wp_schedule_single_event( time(), 'szeducate_dispatch_course_webhook', array( $course_id ) );

			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=sync_started' ) );
			exit;
		}

		if ( isset( $_POST['szeducate_sync_filtered'] ) && check_admin_referer( 'szeducate_sync_filtered_action' ) ) {
			$ids = isset( $_POST['course_ids'] ) ? array_map( 'intval', (array) $_POST['course_ids'] ) : array();
			$ids = array_slice( array_unique( array_filter( $ids ) ), 0, 200 );

			foreach ( $ids as $id ) {
				wp_schedule_single_event( time(), 'szeducate_dispatch_course_webhook', array( $id ) );
			}

			$redirect_qs = isset( $_POST['return_qs'] ) ? '&' . wp_unslash( $_POST['return_qs'] ) : '';
			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=bulk_sync_started' . $redirect_qs ) );
			exit;
		}

		if ( isset( $_POST['szeducate_save_course_edit'] ) ) {
			$this->save_course_edit();
		}
	}

	// Egy Képzés törlés-webhookjának kiküldése MINDEN klienshez (nincs "kérő kliens", akit ki kellene zárni).
	private function dispatch_delete_to_all_clients( $course_id ) {
		wp_schedule_single_event( time(), 'szeducate_dispatch_delete_webhook', array( $course_id, 0 ) );
	}

	private function get_flat_schema_fields( $schema ) {
		$fields = array();
		if ( ! is_array( $schema ) ) return $fields;

		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( ! empty( $field['is_archived'] ) ) continue;
				if ( empty( $field['key'] ) ) continue;
				$field['_group_label'] = isset( $group['label'] ) ? $group['label'] : ( isset( $group['name'] ) ? $group['name'] : '' );
				$fields[] = $field;
			}
		}
		return $fields;
	}

	private function save_course_edit() {
		check_admin_referer( 'szeducate_edit_course_action' );

		global $wpdb;
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->courses_table} WHERE id = %d", $course_id ), ARRAY_A );

		if ( ! $existing ) {
			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=not_found' ) );
			exit;
		}

		$course_data = json_decode( $existing['course_data'], true );
		if ( ! is_array( $course_data ) ) $course_data = array();

		$schema = json_decode( get_option( 'szeducate_schema', '[]' ), true );
		$fields = $this->get_flat_schema_fields( $schema );

		$posted_simple = isset( $_POST['course_data'] ) ? wp_unslash( $_POST['course_data'] ) : array();
		$posted_raw    = isset( $_POST['course_data_raw'] ) ? wp_unslash( $_POST['course_data_raw'] ) : array();
		$raw_json_error = false;

		foreach ( $fields as $field ) {
			$key  = $field['key'];
			$type = isset( $field['type'] ) ? $field['type'] : 'text';

			switch ( $type ) {
				case 'links':
				case 'image':
				case 'repeater':
					if ( isset( $posted_raw[ $key ] ) && trim( $posted_raw[ $key ] ) !== '' ) {
						$decoded = json_decode( $posted_raw[ $key ], true );
						if ( json_last_error() === JSON_ERROR_NONE ) {
							$course_data[ $key ] = $decoded;
						} else {
							$raw_json_error = true;
						}
					}
					break;

				case 'boolean':
				case 'true_false':
					$course_data[ $key ] = isset( $posted_simple[ $key ] );
					break;

				case 'checkbox':
					$vals = isset( $posted_simple[ $key ] ) && is_array( $posted_simple[ $key ] ) ? $posted_simple[ $key ] : array();
					$course_data[ $key ] = array_map( 'sanitize_text_field', $vals );
					break;

				case 'number':
					$val = isset( $posted_simple[ $key ] ) ? $posted_simple[ $key ] : '';
					$course_data[ $key ] = ( $val !== '' && is_numeric( $val ) ) ? ( strpos( $val, '.' ) !== false ? floatval( $val ) : intval( $val ) ) : '';
					break;

				case 'wysiwyg':
					$course_data[ $key ] = isset( $posted_simple[ $key ] ) ? wp_kses_post( $posted_simple[ $key ] ) : '';
					break;

				case 'textarea':
					$course_data[ $key ] = isset( $posted_simple[ $key ] ) ? sanitize_textarea_field( $posted_simple[ $key ] ) : '';
					break;

				case 'email':
					$course_data[ $key ] = isset( $posted_simple[ $key ] ) ? sanitize_email( $posted_simple[ $key ] ) : '';
					break;

				case 'url':
					$course_data[ $key ] = isset( $posted_simple[ $key ] ) ? esc_url_raw( $posted_simple[ $key ] ) : '';
					break;

				case 'date':
				case 'select':
				case 'radio':
				case 'text':
				default:
					$course_data[ $key ] = isset( $posted_simple[ $key ] ) ? sanitize_text_field( $posted_simple[ $key ] ) : '';
					break;
			}
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $existing['title'];

		if ( strlen( wp_json_encode( $course_data ) ) > SZEDUCATE_MAX_COURSE_DATA_SIZE ) {
			wp_redirect( admin_url( 'admin.php?page=szeducate-courses&action=edit_course&course_id=' . $course_id . '&message=too_large' ) );
			exit;
		}

		$current_user = wp_get_current_user();
		$db_data = array(
			'title'       => $title,
			'course_data' => wp_json_encode( $course_data, JSON_UNESCAPED_UNICODE ),
			'updated_at'  => current_time( 'mysql' ),
			'updated_by'  => 'Hub admin: ' . $current_user->user_login,
		);

		$existing_columns = SZEducate_Activator::get_cached_table_columns( $this->courses_table );
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( empty( $field['is_filterable'] ) ) continue;
					$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
					if ( empty( $key ) || ! in_array( $key, $existing_columns ) ) continue;

					$val = isset( $course_data[ $field['key'] ] ) ? $course_data[ $field['key'] ] : '';

					if ( $field['type'] === 'number' ) {
						$db_data[ $key ] = $val !== '' ? intval( $val ) : null;
					} elseif ( $field['type'] === 'boolean' || $field['type'] === 'true_false' ) {
						$db_data[ $key ] = $val ? 1 : 0;
					} elseif ( $field['type'] === 'date' ) {
						if ( $val !== '' ) {
							$parsed_date = strtotime( $val );
							$db_data[ $key ] = $parsed_date !== false ? date( 'Y-m-d H:i:s', $parsed_date ) : null;
						} else {
							$db_data[ $key ] = null;
						}
					} else {
						if ( is_array( $val ) ) $val = implode( '; ', $val );
						$db_data[ $key ] = sanitize_text_field( $val );
					}
				}
			}
		}

		$wpdb->update( $this->courses_table, $db_data, array( 'id' => $course_id ) );
		wp_schedule_single_event( time(), 'szeducate_dispatch_course_webhook', array( $course_id ) );

		$message = $raw_json_error ? 'saved_with_json_error' : 'saved';
		wp_redirect( admin_url( 'admin.php?page=szeducate-courses&message=' . $message ) );
		exit;
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

	// Az utolsó néhány perzisztens naplósor, ami kifejezetten erre a Hub ID-ra vonatkozik -
	// gyors, string-alapú keresés, mivel a napló bejegyzések szövegesen tartalmazzák az ID-t.
	private function get_log_lines_for_hub_id( $hub_id, $limit = 5 ) {
		$needle = 'Hub ID: ' . $hub_id . ')';
		$matches = array();

		foreach ( SZEducate_Sync_Log::get_recent( 300 ) as $entry ) {
			if ( strpos( $entry['message'], $needle ) !== false ) {
				$matches[] = $entry;
				if ( count( $matches ) >= $limit ) break;
			}
		}

		return $matches;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit_course' && isset( $_GET['course_id'] ) ) {
			$this->render_edit_form( intval( $_GET['course_id'] ) );
			return;
		}

		$this->render_list();
	}

	// ------------------------------------------------------------------
	// Szerkesztő nézet (séma-vezérelt űrlap)
	// ------------------------------------------------------------------
	private function render_edit_form( $course_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->courses_table} WHERE id = %d", $course_id ), ARRAY_A );

		if ( ! $row ) {
			echo '<div class="wrap"><h1>Képzés szerkesztése</h1><p>A Képzés nem található.</p></div>';
			return;
		}

		$course_data = json_decode( $row['course_data'], true );
		if ( ! is_array( $course_data ) ) $course_data = array();

		$schema = json_decode( get_option( 'szeducate_schema', '[]' ), true );
		$grouped = array();
		foreach ( $this->get_flat_schema_fields( $schema ) as $field ) {
			$grouped[ $field['_group_label'] ][] = $field;
		}

		?>
		<style>
			.sz-edit-field { margin-bottom: 16px; }
			.sz-edit-field label.f-label { display: block; font-weight: 600; margin-bottom: 4px; }
			.sz-edit-field .f-input { width: 100%; max-width: 520px; }
			.sz-edit-field textarea.f-input { min-height: 90px; }
			.sz-edit-group { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 18px 20px; margin-bottom: 18px; }
			.sz-edit-group h2 { margin-top: 0; font-size: 15px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
		</style>
		<div class="wrap">
			<h1>Képzés szerkesztése: <?php echo esc_html( $row['title'] ); ?></h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=szeducate-courses' ) ); ?>">&larr; Vissza a listához</a>
				&mdash; Utoljára módosította: <strong><?php echo esc_html( $row['updated_by'] ? $row['updated_by'] : 'Ismeretlen' ); ?></strong>, <?php echo esc_html( $row['updated_at'] ); ?>
			</p>

			<?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'too_large' ) : ?>
				<div class="notice notice-error"><p>A mentés sikertelen: a Képzés adata túllépi a megengedett méretkorlátot (<?php echo esc_html( size_format( SZEDUCATE_MAX_COURSE_DATA_SIZE ) ); ?>).</p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'szeducate_edit_course_action' ); ?>
				<input type="hidden" name="szeducate_save_course_edit" value="1">
				<input type="hidden" name="course_id" value="<?php echo esc_attr( $row['id'] ); ?>">

				<div class="sz-edit-group">
					<h2>Alapadatok</h2>
					<div class="sz-edit-field">
						<label class="f-label" for="sz-title">Cím</label>
						<input type="text" class="f-input" id="sz-title" name="title" value="<?php echo esc_attr( $row['title'] ); ?>" required>
					</div>
				</div>

				<?php foreach ( $grouped as $group_label => $group_fields ) : ?>
					<div class="sz-edit-group">
						<h2><?php echo esc_html( $group_label ? $group_label : 'Egyéb mezők' ); ?></h2>
						<?php foreach ( $group_fields as $field ) : ?>
							<div class="sz-edit-field">
								<?php $this->render_field_input( $field, isset( $course_data[ $field['key'] ] ) ? $course_data[ $field['key'] ] : '' ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<p class="submit">
					<button type="submit" class="button button-primary">Mentés és szinkronizálás a kliensekhez</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=szeducate-courses' ) ); ?>" class="button">Mégse</a>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_field_input( $field, $value ) {
		$key   = $field['key'];
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';
		$label = isset( $field['label'] ) ? $field['label'] : $key;
		$input_id = 'sz-f-' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $key ) );

		echo '<label class="f-label" for="' . esc_attr( $input_id ) . '">' . esc_html( $label ) . ' <span style="font-weight:400;color:#999;">(' . esc_html( $key ) . ')</span></label>';

		switch ( $type ) {
			case 'boolean':
			case 'true_false':
				echo '<label><input type="checkbox" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']" value="1" ' . checked( (bool) $value, true, false ) . '> Bekapcsolva</label>';
				break;

			case 'number':
				echo '<input type="number" step="any" class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
				break;

			case 'date':
				echo '<input type="date" class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
				break;

			case 'email':
				echo '<input type="email" class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
				break;

			case 'url':
				echo '<input type="url" class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
				break;

			case 'textarea':
				echo '<textarea class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>';
				break;

			case 'wysiwyg':
				wp_editor( is_string( $value ) ? $value : '', $input_id, array(
					'textarea_name' => 'course_data[' . $key . ']',
					'textarea_rows' => 8,
					'media_buttons' => false,
				) );
				break;

			case 'select':
			case 'radio':
				$options = isset( $field['options'] ) ? array_map( 'trim', explode( ';', $field['options'] ) ) : array();
				echo '<select class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']">';
				echo '<option value="">— nincs kiválasztva —</option>';
				foreach ( $options as $opt ) {
					if ( $opt === '' ) continue;
					echo '<option value="' . esc_attr( $opt ) . '" ' . selected( $value, $opt, false ) . '>' . esc_html( $opt ) . '</option>';
				}
				echo '</select>';
				break;

			case 'checkbox':
				$options = isset( $field['options'] ) ? array_map( 'trim', explode( ';', $field['options'] ) ) : array();
				$selected_vals = is_array( $value ) ? $value : ( $value !== '' ? explode( ';', $value ) : array() );
				foreach ( $options as $opt ) {
					if ( $opt === '' ) continue;
					$checked = in_array( trim( $opt ), array_map( 'trim', $selected_vals ), true );
					echo '<label style="margin-right:14px; display:inline-block;"><input type="checkbox" name="course_data[' . esc_attr( $key ) . '][]" value="' . esc_attr( $opt ) . '" ' . checked( $checked, true, false ) . '> ' . esc_html( $opt ) . '</label>';
				}
				break;

			case 'links':
			case 'image':
			case 'repeater':
				echo '<p style="font-size:12px; color:#888; margin:2px 0 6px;">Ez a mezőtípus (' . esc_html( $type ) . ') csak nyers JSON formában szerkeszthető itt.</p>';
				echo '<textarea class="f-input" style="min-height:110px; font-family:monospace; font-size:12px;" name="course_data_raw[' . esc_attr( $key ) . ']">' . esc_textarea( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</textarea>';
				break;

			case 'text':
			default:
				echo '<input type="text" class="f-input" id="' . esc_attr( $input_id ) . '" name="course_data[' . esc_attr( $key ) . ']" value="' . esc_attr( is_array( $value ) ? implode( '; ', $value ) : $value ) . '">';
				break;
		}
	}

	// ------------------------------------------------------------------
	// Lista nézet
	// ------------------------------------------------------------------
	private function render_list() {
		global $wpdb;

		$per_page = 30;
		$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$owner_filter = isset( $_GET['owner'] ) ? sanitize_text_field( wp_unslash( $_GET['owner'] ) ) : '';
		$visibility_filter = isset( $_GET['visibility'] ) ? sanitize_text_field( wp_unslash( $_GET['visibility'] ) ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$all_clients = $wpdb->get_results( "SELECT id, client_name, enabled, permissions FROM {$this->clients_table} ORDER BY client_name ASC" );
		$clients_by_id = array();
		$enabled_clients = array();
		foreach ( $all_clients as $c ) {
			$clients_by_id[ $c->id ] = $c;
			if ( intval( $c->enabled ) === 1 ) $enabled_clients[] = $c;
		}

		$where = array( '1=1' );
		$params = array();

		if ( $search !== '' ) {
			$where[] = 'c.title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( $owner_filter === 'none' ) {
			$where[] = 'c.owner_client_id IS NULL';
		} elseif ( $owner_filter !== '' ) {
			$where[] = 'c.owner_client_id = %d';
			$params[] = intval( $owner_filter );
		}

		if ( $status_filter === 'archived' ) {
			$where[] = "c.status = 'archived'";
		} elseif ( $status_filter === 'publish' ) {
			$where[] = "c.status != 'archived'";
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$this->courses_table} c WHERE $where_sql";
		$total_matching = $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql );

		$total_courses    = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->courses_table}" ) );
		$orphaned_courses = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->courses_table} WHERE owner_client_id IS NULL" ) );
		$archived_courses = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->courses_table} WHERE status = 'archived'" ) );

		$offset = ( $paged - 1 ) * $per_page;
		$list_sql = "SELECT c.* FROM {$this->courses_table} c WHERE $where_sql ORDER BY c.updated_at DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		// Sémalapú mezőcímkék (key => label) a részletes nézethez, hogy ne csak nyers kulcsokat lássunk.
		$schema = json_decode( get_option( 'szeducate_schema', '[]' ), true );
		$field_labels = array();
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['key'] ) ) {
							$field_labels[ $field['key'] ] = isset( $field['label'] ) ? $field['label'] : $field['key'];
						}
					}
				}
			}
		}

		$courses = array();
		$no_visibility_count = 0;
		$all_ids_on_page = array();

		foreach ( $rows as $row ) {
			$course_data = json_decode( $row['course_data'], true );
			if ( ! is_array( $course_data ) ) $course_data = array();

			// Archivált Képzés eltávolítás-értesítést kapott minden klienstől, tehát
			// gyakorlatilag egyiknél sem érhető el - a jelölés is ezt tükrözi.
			$visible_to = array();
			if ( $row['status'] !== 'archived' ) {
				foreach ( $enabled_clients as $c ) {
					$perms = json_decode( $c->permissions, true );
					$conditions = isset( $perms['conditions'] ) ? $perms['conditions'] : array();
					if ( $this->evaluate_conditions( $conditions, $course_data ) ) {
						$visible_to[] = $c;
					}
				}
			}

			if ( empty( $visible_to ) && $row['status'] !== 'archived' ) $no_visibility_count++;
			$all_ids_on_page[] = intval( $row['id'] );

			$courses[] = array(
				'row'         => $row,
				'course_data' => $course_data,
				'visible_to'  => $visible_to,
			);
		}

		if ( $visibility_filter === 'none' ) {
			$courses = array_filter( $courses, function( $c ) { return empty( $c['visible_to'] ); } );
		}

		$total_pages = max( 1, ceil( $total_matching / $per_page ) );
		$table_columns = SZEducate_Activator::get_cached_table_columns( $this->courses_table );

		$current_qs = remove_query_arg( array( 'paged' ), $_SERVER['QUERY_STRING'] ?? '' );

		?>
		<style>
			.sz-stats-row { display: flex; gap: 16px; margin: 20px 0; flex-wrap: wrap; }
			.sz-stat-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 14px 18px; min-width: 150px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
			.sz-stat-card .sz-stat-num { font-size: 24px; font-weight: 700; line-height: 1.2; }
			.sz-stat-card .sz-stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: .03em; }
			.sz-stat-card.warn .sz-stat-num { color: #d63638; }
			.sz-filter-bar { display: flex; gap: 10px; align-items: center; margin: 15px 0; flex-wrap: wrap; }
			.sz-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin: 1px 3px 1px 0; white-space: nowrap; }
			.sz-badge-green { background: #edfaef; color: #1a7f37; border: 1px solid #b4e3bc; }
			.sz-badge-red { background: #fcedee; color: #d63638; border: 1px solid #f3c6c8; }
			.sz-badge-grey { background: #f0f0f1; color: #555; border: 1px solid #dcdcde; }
			.sz-badge-amber { background: #fff8e5; color: #996800; border: 1px solid #f0dca0; }
			.sz-course-toggle { cursor: pointer; color: #2271b1; text-decoration: none; font-size: 12px; }
			.sz-detail-row td { background: #f9f9f9; padding: 15px 20px !important; }
			.sz-detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px 20px; margin-bottom: 15px; }
			.sz-detail-grid .k { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: .03em; }
			.sz-detail-grid .v { font-size: 13px; color: #222; word-break: break-word; }
			.sz-raw-json { max-height: 260px; overflow: auto; background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; font-size: 12px; }
			.sz-log-line { font-size: 12px; padding: 3px 0; border-bottom: 1px dotted #ddd; }
			.sz-columns-box { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; margin: 15px 0; font-size: 12px; color: #555; }
			.sz-columns-box code { background: #f0f0f1; padding: 1px 5px; border-radius: 2px; }
			.sz-row-actions a, .sz-row-actions button { display: inline-block; margin: 1px 3px 1px 0; }
			.sz-archived-row { opacity: 0.6; }
		</style>

		<div class="wrap">
			<h1>Képzések Áttekintése</h1>
			<p>A Hub adatbázisában tárolt összes Képzés, azok gazdája (melyik kar hozta létre), utolsó módosítója és aktuális kliens-láthatósága (a jogosultsági feltételek alapján, élőben kiszámítva). Innen szerkesztheted, archiválhatod, törölheted és manuálisan (újra)szinkronizálhatod is a Képzéseket.</p>

			<?php
			if ( isset( $_GET['message'] ) ) {
				$notices = array(
					'archived'           => array( 'warning', 'Képzés archiválva - eltávolítás-értesítés kiküldve a klienseknek (a Hub-on az adat megmaradt).' ),
					'unarchived'         => array( 'success', 'Képzés visszaállítva - újraszinkronizálás elindítva a klienseknek.' ),
					'deleted'            => array( 'success', 'Képzés véglegesen törölve a Hub-ról, törlés-értesítés kiküldve minden kliensnek.' ),
					'sync_started'       => array( 'success', 'Szinkronizáció elindítva a háttérben.' ),
					'bulk_sync_started'  => array( 'success', 'A listázott Képzések szinkronizálása elindítva a háttérben.' ),
					'saved'              => array( 'success', 'Képzés elmentve és szinkronizálás elindítva a klienseknek.' ),
					'saved_with_json_error' => array( 'warning', 'Képzés elmentve, de egy vagy több nyers JSON mező érvénytelen volt, ezért azok NEM változtak.' ),
					'not_found'          => array( 'error', 'A Képzés nem található.' ),
				);
				if ( isset( $notices[ $_GET['message'] ] ) ) {
					list( $type, $text ) = $notices[ $_GET['message'] ];
					echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
				}
			}
			?>

			<div class="sz-stats-row">
				<div class="sz-stat-card">
					<div class="sz-stat-num"><?php echo esc_html( $total_courses ); ?></div>
					<div class="sz-stat-label">Összes Képzés</div>
				</div>
				<div class="sz-stat-card">
					<div class="sz-stat-num"><?php echo esc_html( count( $all_clients ) ); ?></div>
					<div class="sz-stat-label">Regisztrált kliens</div>
				</div>
				<div class="sz-stat-card">
					<div class="sz-stat-num"><?php echo esc_html( count( $enabled_clients ) ); ?></div>
					<div class="sz-stat-label">Aktív kliens</div>
				</div>
				<div class="sz-stat-card <?php echo $orphaned_courses > 0 ? 'warn' : ''; ?>">
					<div class="sz-stat-num"><?php echo esc_html( $orphaned_courses ); ?></div>
					<div class="sz-stat-label">Gazda nélküli Képzés</div>
				</div>
				<div class="sz-stat-card">
					<div class="sz-stat-num"><?php echo esc_html( $archived_courses ); ?></div>
					<div class="sz-stat-label">Archivált Képzés</div>
				</div>
				<div class="sz-stat-card <?php echo $no_visibility_count > 0 ? 'warn' : ''; ?>">
					<div class="sz-stat-num"><?php echo esc_html( $no_visibility_count ); ?> <span style="font-size:12px; font-weight:400; color:#888;">/ oldal</span></div>
					<div class="sz-stat-label">Egyetlen kliens sem látja</div>
				</div>
			</div>

			<div class="sz-columns-box">
				<strong>Adatbázis tábla:</strong> <code><?php echo esc_html( $this->courses_table ); ?></code> &mdash;
				<strong>Oszlopok:</strong>
				<?php foreach ( $table_columns as $col ) : ?>
					<code><?php echo esc_html( $col ); ?></code>
				<?php endforeach; ?>
			</div>

			<form method="get" class="sz-filter-bar">
				<input type="hidden" name="page" value="szeducate-courses">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Keresés cím alapján..." class="regular-text">

				<select name="owner">
					<option value="">Minden gazda</option>
					<option value="none" <?php selected( $owner_filter, 'none' ); ?>>Nincs gazda</option>
					<?php foreach ( $all_clients as $c ) : ?>
						<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $owner_filter, (string) $c->id ); ?>><?php echo esc_html( $c->client_name ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="status">
					<option value="">Minden állapot</option>
					<option value="publish" <?php selected( $status_filter, 'publish' ); ?>>Csak aktív</option>
					<option value="archived" <?php selected( $status_filter, 'archived' ); ?>>Csak archivált</option>
				</select>

				<select name="visibility">
					<option value="">Mind (láthatóságtól függetlenül)</option>
					<option value="none" <?php selected( $visibility_filter, 'none' ); ?>>Csak amit egy aktív kliens sem lát</option>
				</select>

				<button type="submit" class="button">Szűrés</button>
				<?php if ( $search !== '' || $owner_filter !== '' || $visibility_filter !== '' || $status_filter !== '' ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=szeducate-courses' ) ); ?>" class="button">Szűrők törlése</a>
				<?php endif; ?>
			</form>

			<?php if ( ! empty( $all_ids_on_page ) ) : ?>
				<form method="post" style="margin-bottom: 10px;" onsubmit="return confirm('Biztosan (újra)szinkronizálod az ezen az oldalon listázott <?php echo esc_js( count( $all_ids_on_page ) ); ?> Képzést minden aktív kliens felé?');">
					<?php wp_nonce_field( 'szeducate_sync_filtered_action' ); ?>
					<input type="hidden" name="szeducate_sync_filtered" value="1">
					<input type="hidden" name="return_qs" value="<?php echo esc_attr( $current_qs ); ?>">
					<?php foreach ( $all_ids_on_page as $cid ) : ?>
						<input type="hidden" name="course_ids[]" value="<?php echo esc_attr( $cid ); ?>">
					<?php endforeach; ?>
					<button type="submit" class="button">Oldalon listázott Képzések szinkronizálása (<?php echo esc_html( count( $all_ids_on_page ) ); ?> db)</button>
				</form>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th style="width: 60px;">Hub ID</th>
						<th>Cím</th>
						<th style="width: 150px;">Gazda</th>
						<th style="width: 90px;">Állapot</th>
						<th style="width: 140px;">Utoljára frissítve</th>
						<th style="width: 160px;">Utoljára módosította</th>
						<th>Kliens által elérhető</th>
						<th style="width: 210px;">Műveletek</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $courses ) ) : ?>
						<tr><td colspan="8">Nincs a szűrésnek megfelelő Képzés.</td></tr>
					<?php else : ?>
						<?php foreach ( $courses as $entry ) :
							$row = $entry['row'];
							$owner = isset( $clients_by_id[ $row['owner_client_id'] ] ) ? $clients_by_id[ $row['owner_client_id'] ] : null;
							$detail_id = 'sz-course-detail-' . $row['id'];
							$is_archived = $row['status'] === 'archived';

							$edit_url = admin_url( 'admin.php?page=szeducate-courses&action=edit_course&course_id=' . $row['id'] );
							$sync_url = wp_nonce_url( admin_url( 'admin.php?page=szeducate-courses&action=sync_course&course_id=' . $row['id'] ), 'sync_course_' . $row['id'] );
							$archive_url = wp_nonce_url( admin_url( 'admin.php?page=szeducate-courses&action=archive_course&course_id=' . $row['id'] ), 'archive_course_' . $row['id'] );
							$unarchive_url = wp_nonce_url( admin_url( 'admin.php?page=szeducate-courses&action=unarchive_course&course_id=' . $row['id'] ), 'unarchive_course_' . $row['id'] );
							$delete_url = wp_nonce_url( admin_url( 'admin.php?page=szeducate-courses&action=delete_course_admin&course_id=' . $row['id'] ), 'delete_course_admin_' . $row['id'] );
						?>
							<tr class="<?php echo $is_archived ? 'sz-archived-row' : ''; ?>">
								<td>#<?php echo esc_html( $row['id'] ); ?></td>
								<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
								<td>
									<?php if ( $owner ) : ?>
										<?php echo esc_html( $owner->client_name ); ?>
										<?php if ( intval( $owner->enabled ) === 0 ) : ?>
											<span class="sz-badge sz-badge-red">felfüggesztve</span>
										<?php endif; ?>
									<?php else : ?>
										<span class="sz-badge sz-badge-grey">nincs gazda</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $is_archived ) : ?>
										<span class="sz-badge sz-badge-amber">archivált</span>
									<?php else : ?>
										<?php echo esc_html( $row['status'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row['updated_at'] ); ?></td>
								<td><?php echo esc_html( $row['updated_by'] ? $row['updated_by'] : '—' ); ?></td>
								<td>
									<?php if ( empty( $entry['visible_to'] ) ) : ?>
										<span class="sz-badge sz-badge-red">egyik aktív kliens sem</span>
									<?php else : ?>
										<?php foreach ( $entry['visible_to'] as $vc ) : ?>
											<span class="sz-badge sz-badge-green"><?php echo esc_html( $vc->client_name ); ?></span>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td class="sz-row-actions">
									<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Szerkesztés</a>
									<a href="<?php echo esc_url( $sync_url ); ?>" class="button button-small">Szinkron</a>
									<?php if ( $is_archived ) : ?>
										<a href="<?php echo esc_url( $unarchive_url ); ?>" class="button button-small">Visszaállítás</a>
									<?php else : ?>
										<a href="<?php echo esc_url( $archive_url ); ?>" class="button button-small" onclick="return confirm('Archiválod ezt a Képzést? A klienseknél eltávolítás-értesítés megy ki, a Hub-on az adat megmarad.');">Archiválás</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete" style="color:#d63638;" onclick="return confirm('Biztosan VÉGLEGESEN törlöd ezt a Képzést a Hub-ról? Ez minden kliensnél is törlés-értesítést indít, és nem vonható vissza!');">Törlés</a>
									<br>
									<a href="#" class="sz-course-toggle" data-target="<?php echo esc_attr( $detail_id ); ?>">Részletek &#9660;</a>
								</td>
							</tr>
							<tr id="<?php echo esc_attr( $detail_id ); ?>" class="sz-detail-row" style="display:none;">
								<td colspan="8">
									<div class="sz-detail-grid">
										<div><div class="k">Hub ID</div><div class="v">#<?php echo esc_html( $row['id'] ); ?></div></div>
										<div><div class="k">Kliens oldali post ID</div><div class="v"><?php echo esc_html( $row['local_post_id'] ? $row['local_post_id'] : '—' ); ?></div></div>
										<div><div class="k">Gazda kliens ID</div><div class="v"><?php echo esc_html( $row['owner_client_id'] ? $row['owner_client_id'] : '—' ); ?></div></div>
										<div><div class="k">Állapot</div><div class="v"><?php echo esc_html( $row['status'] ); ?></div></div>
										<div><div class="k">Utoljára frissítve</div><div class="v"><?php echo esc_html( $row['updated_at'] ); ?></div></div>
										<div><div class="k">Utoljára módosította</div><div class="v"><?php echo esc_html( $row['updated_by'] ? $row['updated_by'] : '—' ); ?></div></div>
									</div>

									<h4 style="margin-bottom: 6px;">Mezőadatok</h4>
									<div class="sz-detail-grid">
										<?php if ( empty( $entry['course_data'] ) ) : ?>
											<div class="v">Nincs tárolt mezőadat.</div>
										<?php else : ?>
											<?php foreach ( $entry['course_data'] as $key => $val ) :
												$label = isset( $field_labels[ $key ] ) ? $field_labels[ $key ] : $key;
												$display = is_array( $val ) ? implode( ', ', $val ) : (string) $val;
											?>
												<div>
													<div class="k"><?php echo esc_html( $label ); ?> <span style="color:#bbb;">(<?php echo esc_html( $key ); ?>)</span></div>
													<div class="v"><?php echo esc_html( $display !== '' ? $display : '—' ); ?></div>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>

									<details style="margin-bottom: 15px;">
										<summary style="cursor:pointer; color:#2271b1; font-size:12px;">Nyers JSON megtekintése</summary>
										<pre class="sz-raw-json"><?php echo esc_html( wp_json_encode( $entry['course_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
									</details>

									<h4 style="margin-bottom: 6px;">Legutóbbi szinkron-napló bejegyzések ehhez a Képzéshez</h4>
									<?php $log_lines = $this->get_log_lines_for_hub_id( $row['id'] ); ?>
									<?php if ( empty( $log_lines ) ) : ?>
										<p style="font-size:12px; color:#888;">Nincs kapcsolódó naplóbejegyzés a legutóbbi 300 esemény között.</p>
									<?php else : ?>
										<?php foreach ( $log_lines as $line ) : ?>
											<div class="sz-log-line">
												<span style="color:#888;"><?php echo esc_html( $line['time'] ); ?></span>
												<span class="sz-badge <?php echo $line['success'] ? 'sz-badge-green' : 'sz-badge-red'; ?>"><?php echo esc_html( $line['type'] ); ?></span>
												<?php echo esc_html( $line['message'] ); ?>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div style="margin-top: 15px;">
					<?php
					$base_args = array(
						'page' => 'szeducate-courses',
						's' => $search,
						'owner' => $owner_filter,
						'visibility' => $visibility_filter,
						'status' => $status_filter,
					);
					for ( $i = 1; $i <= $total_pages; $i++ ) :
						$link_args = array_merge( $base_args, array( 'paged' => $i ) );
						$url = add_query_arg( $link_args, admin_url( 'admin.php' ) );
					?>
						<?php if ( $i === $paged ) : ?>
							<strong style="margin-right: 8px;"><?php echo esc_html( $i ); ?></strong>
						<?php else : ?>
							<a href="<?php echo esc_url( $url ); ?>" style="margin-right: 8px;"><?php echo esc_html( $i ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</div>

		<script>
			document.querySelectorAll( '.sz-course-toggle' ).forEach( function( btn ) {
				btn.addEventListener( 'click', function( e ) {
					e.preventDefault();
					var row = document.getElementById( this.getAttribute( 'data-target' ) );
					if ( row ) {
						row.style.display = ( row.style.display === 'none' || ! row.style.display ) ? 'table-row' : 'none';
					}
				} );
			} );
		</script>
		<?php
	}
}
