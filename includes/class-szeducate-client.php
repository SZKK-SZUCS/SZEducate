<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Client {

	public function init() {
		add_action( 'admin_init', array( $this, 'assign_admin_caps' ) );

		add_action( 'init', array( $this, 'register_course_cpt' ) );
		add_action( 'init', array( $this, 'register_dynamic_taxonomies' ) );
		
		add_action( 'szeducate_background_sync', array( $this, 'sync_course_to_hub' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_react_app' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_react_root' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_assets' ) );
		
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 100, 2 );
		
		add_action( 'admin_head', array( $this, 'clean_admin_ui' ) );
		
		add_action( 'pre_get_posts', array( $this, 'filter_courses_by_permissions' ) );
		
		add_action( 'admin_menu', array( $this, 'remove_add_new_menu' ), 999 );
		add_filter( 'post_row_actions', array( $this, 'modify_list_actions' ), 10, 2 );

		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'init', array( $this, 'add_seo_rewrite_rules' ) );

		add_filter( 'bulk_actions-edit-sz_course', array( $this, 'add_custom_bulk_actions' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'bulk_admin_footer_js' ) );
		add_action( 'wp_ajax_szeducate_process_bulk_status', array( $this, 'ajax_process_bulk_status' ) );

		if ( ! wp_next_scheduled( 'szeducate_daily_expiration_check' ) ) {
			wp_schedule_event( time(), 'daily', 'szeducate_daily_expiration_check' );
		}
		add_action( 'szeducate_daily_expiration_check', array( $this, 'process_daily_expirations' ) );

		add_action( 'in_admin_header', array( $this, 'render_advanced_filter_panel' ) );

		add_action( 'rest_api_init', array( $this, 'register_client_endpoints' ) );

		add_filter( 'pre_trash_post', array( $this, 'bypass_trash_for_courses' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'process_course_deletion' ), 10, 1 );
	}

	// Az 'edit_sz_courses' jogosultság korábban KIZÁRÓLAG az Adminisztrátor szerepkörhöz
	// lett hozzáadva - ezáltal a kód több helyen ellenőrzi ugyan a szűkebb jogosultságot,
	// a gyakorlatban mégis csak a teljes site-adminisztrátor tudta valaha ténylegesen
	// elérni. Az Editor (Szerkesztő) szerepkör innentől szintén megkapja, hogy a Képzések
	// kezelése átadható legyen anélkül, hogy valakinek teljes manage_options jogot kellene adni.
	public function assign_admin_caps() {
		$caps = array(
			'edit_sz_course', 'read_sz_course', 'delete_sz_course',
			'edit_sz_courses', 'edit_others_sz_courses', 'publish_sz_courses',
			'read_private_sz_courses', 'delete_sz_courses', 'delete_private_sz_courses',
			'delete_published_sz_courses', 'delete_others_sz_courses',
			'edit_private_sz_courses', 'edit_published_sz_courses'
		);

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( 'edit_sz_courses' ) ) {
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'sz_seo_keyword';
		$vars[] = 'sz_seo_field';
		return $vars;
	}

	public function add_seo_rewrite_rules() {
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/([^/]+)/?$',
			'index.php?pagename=$matches[1]&sz_seo_field=$matches[2]&sz_seo_keyword=$matches[3]',
			'top'
		);
	}

	public function enqueue_list_assets( $hook ) {
		global $typenow;
		if ( $hook === 'edit.php' && $typenow === 'sz_course' ) {
			wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
			wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true );
		}
	}

	public function render_advanced_filter_panel() {
		global $typenow, $wpdb;
		if ( $typenow !== 'sz_course' ) return;

		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		if ( ! is_array( $schema ) ) return;

		$filterable_fields = array();
		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( !empty($field['is_filterable']) && in_array($field['type'], array('select', 'radio', 'checkbox', 'boolean', 'true_false')) ) {
					$filterable_fields[$field['key']] = $field;
				}
			}
		}

		if ( empty($filterable_fields) ) return;

		$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
		$all_courses = self::get_cached_courses_data();

		$permitted_data = array();
		
		foreach ( $all_courses as $c ) {
			$data = json_decode( $c['course_data'], true );
			if ( ! is_array($data) ) continue;

			$has_permission = true;
			if ( !empty( $perms['conditions'] ) && !empty( $perms['conditions']['rules'] ) ) {
				$has_permission = $this->evaluate_conditions( $perms['conditions'], $data );
			}

			if ( $has_permission ) {
				$permitted_data[] = $data;
			}
		}

		$dynamic_options = array();
		foreach ( $filterable_fields as $key => $field ) {
			$dynamic_options[$key] = array();
		}

		foreach ( $permitted_data as $data ) {
			foreach ( $filterable_fields as $key => $field ) {
				if ( isset( $data[$key] ) ) {
					$val = $data[$key];
					if ( $field['type'] === 'boolean' || $field['type'] === 'true_false' ) {
						$str_val = $val ? 'true' : 'false';
						$dynamic_options[$key][$str_val] = $val ? 'Igen' : 'Nem';
					} else {
						$parts = is_array($val) ? $val : explode(';', (string)$val);
						foreach ( $parts as $p ) {
							$p = trim($p);
							if ( $p !== '' ) {
								$dynamic_options[$key][$p] = $p;
							}
						}
					}
				}
			}
		}

		foreach ( $dynamic_options as $key => $opts ) {
			ksort($dynamic_options[$key]);
		}

		?>
		<div id="sz-filter-panel-wrapper" style="display: none; margin-top: 15px;">
			<div id="sz-filter-panel" style="padding: 20px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
				<form method="GET" action="edit.php">
					<input type="hidden" name="post_type" value="sz_course">
					<h3 style="margin-top: 0; margin-bottom: 20px; font-size: 14px;">Speciális Képzés Szűrők</h3>
					
					<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
						<?php foreach ( $filterable_fields as $field_key => $field ) : 
							$options = $dynamic_options[$field_key];
							if ( empty($options) ) continue;
							
							$get_key = 'szf_' . $field_key;
							$selected_vals = isset($_GET[$get_key]) && is_array($_GET[$get_key]) ? $_GET[$get_key] : array();
						?>
						<div style="flex: 1 1 250px; min-width: 250px;">
							<label style="display: block; font-weight: 600; margin-bottom: 6px; color: #50575e;">
								<?php echo esc_html($field['label']); ?>
							</label>
							<select name="<?php echo esc_attr($get_key); ?>[]" multiple="multiple" class="sz-select2" style="width: 100%;" data-placeholder="Válassz...">
								<?php foreach ( $options as $val => $lbl ) : ?>
									<option value="<?php echo esc_attr($val); ?>" <?php echo in_array($val, $selected_vals) ? 'selected' : ''; ?>>
										<?php echo esc_html($lbl); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<?php endforeach; ?>
					</div>
					
					<div style="display: flex; gap: 10px; border-top: 1px solid #f0f0f1; padding-top: 15px;">
						<button type="submit" class="button button-primary">Szűrés Alkalmazása</button>
						<a href="edit.php?post_type=sz_course" class="button">Összes Szűrő Törlése</a>
					</div>
				</form>
			</div>
		</div>

		<script>
			jQuery(document).ready(function($) {
				let screenMetaLinks = $('#screen-meta-links');
				if (!screenMetaLinks.length) {
					$('.wrap').before('<div id="screen-meta-links"></div>');
					screenMetaLinks = $('#screen-meta-links');
				}
				
				let screenMeta = $('#screen-meta');
				if (!screenMeta.length) {
					$('.wrap').before('<div id="screen-meta"></div>');
					screenMeta = $('#screen-meta');
				}

				screenMetaLinks.append(
					'<div class="hide-if-no-js screen-meta-toggle" id="sz-filter-link-wrap">' +
					'<button type="button" id="sz-filter-toggle-btn" class="button show-settings" aria-expanded="false" style="border-radius: 0 0 4px 4px;">Képzés Szűrők</button>' +
					'</div>'
				);

				const panelWrap = $('#sz-filter-panel-wrapper');
				$('.wrap').prepend(panelWrap);

				$(document).on('click', '#sz-filter-toggle-btn', function(e) {
					e.preventDefault();
					panelWrap.slideToggle('fast', function() {
						const isVisible = panelWrap.is(':visible');
						$('#sz-filter-toggle-btn').attr('aria-expanded', isVisible);
					});
				});

				if ($.fn.select2) {
					$('.sz-select2').select2({
						allowClear: true,
						language: {
							noResults: function() { return "Nincs találat"; }
						}
					});
				}
			});
		</script>
		<style>
			#screen-options-link-wrap { display: none !important; }
			.select2-container--default .select2-selection--multiple { border-color: #8c8f94; border-radius: 3px; min-height: 32px; padding-bottom: 2px; }
			.select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
			.select2-container--default .select2-selection--multiple .select2-selection__choice { background-color: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; color: #3c434a; margin-top: 6px; }
			.select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: #d63638; border-right-color: #c3c4c7; }
			.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { background-color: #fbeaea; color: #d63638; }
			.select2-dropdown { border-color: #2271b1; }
			#sz-filter-toggle-btn { background: #f0f6fc; color: #2271b1; border-color: #2271b1; font-weight: 600; }
			#sz-filter-toggle-btn[aria-expanded="true"] { background: #2271b1; color: #fff; border-color: #2271b1; }
		</style>
		<?php
	}

	public function filter_courses_by_permissions( $query ) {
		if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'sz_course' ) {
			
			$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
			$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
			
			$active_filters = array();
			$processed_keys = array(); 
			
			if ( is_array( $schema ) ) {
				foreach ( $schema as $group ) {
					if ( empty( $group['fields'] ) ) continue;
					foreach ( $group['fields'] as $field ) {
						if ( in_array( $field['key'], $processed_keys ) ) continue; 
						$processed_keys[] = $field['key'];
						
						$get_key = 'szf_' . $field['key'];
						if ( !empty($field['is_filterable']) && isset($_GET[$get_key]) && is_array($_GET[$get_key]) ) {
							$clean_vals = array_filter(array_map('sanitize_text_field', $_GET[$get_key]));
							if ( !empty($clean_vals) ) {
								$active_filters[$field['key']] = $clean_vals;
							}
						}
					}
				}
			}

			$all_courses = self::get_cached_courses_data();

			$allowed_ids = array();
			foreach ( $all_courses as $c ) {
				$data = json_decode( $c['course_data'], true );
				if ( ! is_array($data) ) $data = array();

				$has_permission = true;
				if ( !empty( $perms['conditions'] ) && !empty( $perms['conditions']['rules'] ) ) {
					$has_permission = $this->evaluate_conditions( $perms['conditions'], $data );
				}

				$matches_filters = true;
				if ( $has_permission && !empty($active_filters) ) {
					foreach ( $active_filters as $f_key => $f_vals ) {
						$actual_val = isset($data[$f_key]) ? $data[$f_key] : '';
						
						if ( is_bool($actual_val) ) {
							$actual_val = $actual_val ? 'true' : 'false';
						}

						$actual_array = is_array($actual_val) ? $actual_val : explode(';', (string)$actual_val);
						$actual_array = array_map('trim', $actual_array);
						
						$intersection = array_intersect($f_vals, $actual_array);
						if ( empty($intersection) ) {
							$matches_filters = false;
							break; 
						}
					}
				}

				if ( $has_permission && $matches_filters ) {
					$allowed_ids[] = intval( $c['local_post_id'] );
				}
			}

			if ( empty( $allowed_ids ) ) {
				$query->set( 'post__in', array( 0 ) );
			} else {
				$query->set( 'post__in', $allowed_ids );
			}
		}
	}

	public function add_custom_bulk_actions( $bulk_actions ) {
		unset($bulk_actions['trash']);
		unset($bulk_actions['delete']);

		$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
		$actions = isset($perms['actions']) ? $perms['actions'] : array();

		$bulk_actions['szeducate_activate'] = 'Tömeges Aktiválás (Határidővel is)';
		$bulk_actions['szeducate_deactivate'] = 'Tömeges Inaktiválás';
		
		$can_delete = isset($actions['delete']) ? (bool) $actions['delete'] : true;

		if ( $can_delete && current_user_can('edit_sz_courses') ) {
			$bulk_actions['szeducate_delete'] = 'Végleges Törlés (A Hubról is)';
		}

		return $bulk_actions;
	}

	public function bulk_admin_footer_js() {
		global $typenow;
		if ( $typenow !== 'sz_course' ) return;
		?>
		<div id="szeducate-bulk-date-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
			<div style="background:#fff; padding:30px; width:400px; max-width:90%; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
				<h3 style="margin-top:0;">Tömeges Aktiválás</h3>
				<p style="color:#666; font-size:13px; margin-bottom:20px;">
					Kérlek, válaszd ki a naptárból az aktiválás határidejét.<br>
					<strong>Tipp:</strong> Ha határozatlan ideig lesz aktív, hagyd üresen a mezőt!
				</p>
				<input type="date" id="szeducate-bulk-expiry-date" style="width:100%; padding:8px; border:1px solid #8c8f94; border-radius:4px; margin-bottom:20px;">
				<div style="display:flex; justify-content:flex-end; gap:10px;">
					<button type="button" class="button" id="szeducate-bulk-cancel">Mégse</button>
					<button type="button" class="button button-primary" id="szeducate-bulk-confirm">Aktiválás Végrehajtása</button>
				</div>
			</div>
		</div>

		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				const modal = document.getElementById('szeducate-bulk-date-modal');
				const dateInput = document.getElementById('szeducate-bulk-expiry-date');
				const btnConfirm = document.getElementById('szeducate-bulk-confirm');
				const btnCancel = document.getElementById('szeducate-bulk-cancel');
				
				let currentPostIds = [];
				let currentAction = '';

				const todayStr = new Date().toLocaleDateString('en-CA'); 
				dateInput.setAttribute('min', todayStr);

				btnCancel.addEventListener('click', () => {
					modal.style.display = 'none';
					currentPostIds = [];
					currentAction = '';
				});

				btnConfirm.addEventListener('click', () => {
					const dateVal = dateInput.value;

					if (dateVal !== '') {
						const selectedDate = new Date(dateVal);
						const now = new Date(todayStr); 
						
						if (selectedDate < now) {
							alert('Hiba: A határidő nem lehet a múltban!');
							dateInput.focus();
							return;
						}
					}

					modal.style.display = 'none';
					executeBulkAction(currentAction, dateVal, currentPostIds);
				});

				const executeBulkAction = (action, dateVal, postIds) => {
					document.body.style.cursor = 'wait';

					const data = new URLSearchParams();
					data.append('action', 'szeducate_process_bulk_status');
					data.append('bulk_action', action);
					data.append('expiry_date', dateVal.trim());
					data.append('post_ids', JSON.stringify(postIds));
					data.append('_ajax_nonce', '<?php echo wp_create_nonce("szeducate_bulk_nonce"); ?>');

					fetch(ajaxurl, {
						method: 'POST',
						body: data
					}).then(res => res.json()).then(response => {
						if (response.success) {
							alert(response.data);
							location.reload();
						} else {
							alert('Hiba: ' + response.data);
							document.body.style.cursor = 'default';
						}
					}).catch(() => {
						alert('Hálózati hiba történt a kommunikáció során.');
						document.body.style.cursor = 'default';
					});
				};

				const handleBulkAction = (e, selectId) => {
					const select = document.getElementById(selectId);
					if (!select) return;
					const action = select.value;

					if ( action === 'szeducate_activate' || action === 'szeducate_deactivate' || action === 'szeducate_delete' ) {
						e.preventDefault();
						
						const checked = document.querySelectorAll('input[name="post[]"]:checked');
						if (checked.length === 0) {
							alert('Kérlek válassz ki legalább egy képzést!');
							return;
						}

						currentPostIds = Array.from(checked).map(cb => cb.value);
						currentAction = action;

						if ( action === 'szeducate_activate' ) {
							dateInput.value = ''; 
							modal.style.display = 'flex'; 
						} else if ( action === 'szeducate_delete' ) {
							if (confirm('FIGYELEM! Biztosan VÉGLEGESEN törlöd a kijelölt képzéseket? Ez a művelet a Hubról is visszavonhatatlanul letörli őket!')) {
								executeBulkAction(action, '', currentPostIds);
							}
						} else {
							if (confirm('Biztosan inaktiválod a kijelölt képzéseket?')) {
								executeBulkAction(action, '', currentPostIds);
							}
						}
					}
				};

				const btnTop = document.getElementById('doaction');
				const btnBottom = document.getElementById('doaction2');
				if (btnTop) btnTop.addEventListener('click', (e) => handleBulkAction(e, 'bulk-action-selector-top'));
				if (btnBottom) btnBottom.addEventListener('click', (e) => handleBulkAction(e, 'bulk-action-selector-bottom'));
			});
		</script>
		<?php
	}

	public function ajax_process_bulk_status() {
		check_ajax_referer( 'szeducate_bulk_nonce' );
		
		if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Nincs jogosultságod.' );

		$bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
		$expiry_date = isset($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : '';
		$post_ids_json = isset($_POST['post_ids']) ? wp_unslash($_POST['post_ids']) : '[]';
		$post_ids = json_decode($post_ids_json, true);

		if ( empty($post_ids) || ! is_array($post_ids) ) {
			wp_send_json_error( 'Nincsenek kijelölt elemek.' );
		}

		if ( $bulk_action === 'szeducate_delete' ) {
			$deleted_count = 0;
			foreach ( $post_ids as $pid ) {
				wp_delete_post( intval($pid), true ); 
				$deleted_count++;
			}
			wp_send_json_success( "Sikeresen véglegesen törölve: $deleted_count db képzés." );
			return; 
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$status_val = ($bulk_action === 'szeducate_activate') ? 'Aktív' : 'Inaktív';
		$updated_count = 0;

		foreach ( $post_ids as $pid ) {
			$post_id = intval($pid);
			$course = $wpdb->get_row( $wpdb->prepare( "SELECT id, course_data FROM $table_name WHERE local_post_id = %d", $post_id ), ARRAY_A );
			
			if ( $course ) {
				$data = json_decode( $course['course_data'], true );
				if ( ! is_array($data) ) $data = array();

				$data['meghirdetes_allapota'] = $status_val;
				
				if ( $bulk_action === 'szeducate_activate' ) {
					$data['passziv_ettol'] = $expiry_date;
				} else {
					$data['passziv_ettol'] = ''; 
				}

				$wpdb->update(
					$table_name,
					array( 'course_data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ),
					array( 'id' => $course['id'] )
				);

				wp_schedule_single_event( time(), 'szeducate_background_sync', array( $post_id ) );
				$updated_count++;
			}
		}

		wp_send_json_success( "Sikeresen módosítva: $updated_count db képzés állapota." );
	}

	public function remove_add_new_menu() {
		$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
		$actions = isset($perms['actions']) ? $perms['actions'] : array();
		if ( isset($actions['create']) && !$actions['create'] ) {
			remove_submenu_page( 'edit.php?post_type=sz_course', 'post-new.php?post_type=sz_course' );
		}
	}

	public function modify_list_actions( $actions, $post ) {
		if ( $post->post_type === 'sz_course' ) {
			$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
			$perm_actions = isset($perms['actions']) ? $perms['actions'] : array();
			
			unset( $actions['inline hide-if-no-js'] ); 
			unset( $actions['view'] ); 
			unset( $actions['trash'] ); 
			
			if ( isset($perm_actions['edit']) && !$perm_actions['edit'] ) {
				if ( isset( $actions['edit'] ) ) {
					$actions['edit'] = preg_replace( '/>Szerkesztés</', '>Csak olvasható<', $actions['edit'] );
				}
			}

			$can_delete = isset($perm_actions['delete']) ? (bool) $perm_actions['delete'] : true;

			if ( ! $can_delete ) {
				unset( $actions['delete'] );
			} else {
				if ( current_user_can( 'delete_sz_course', $post->ID ) ) {
					$delete_url = wp_nonce_url( admin_url( 'post.php?post=' . $post->ID . '&action=delete' ), 'delete-post_' . $post->ID );
					$actions['delete'] = sprintf(
						'<a href="%s" class="submitdelete" style="color: #d63638;" onclick="return confirm(\'%s\');">%s</a>',
						$delete_url,
						'Biztosan véglegesen törölni akarod? Ez a művelet a Hub-ról is eltünteti a képzést!',
						'Végleges törlés'
					);
				}
			}
		}
		return $actions;
	}

	public function disable_gutenberg( $current_status, $post_type ) {
		return ( $post_type === 'sz_course' ) ? false : $current_status;
	}

	public function register_course_cpt() {
		$args = array(
			'labels'             => array(
				'name'          => 'Képzések',
				'singular_name' => 'Képzés',
				'menu_name'     => 'Képzések',
				'add_new'       => 'Új Képzés',
				'all_items'     => 'Minden Képzés',
			),
			'public'             => true,
			'show_ui'            => true,
			'rewrite'            => array( 'slug' => 'kepzes' ),
			'has_archive'        => true,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'show_in_rest'       => true, 
			'supports'           => array( 'title' ), 
			'capability_type'    => array( 'sz_course', 'sz_courses' ),
			'map_meta_cap'       => true,
		);
		register_post_type( 'sz_course', $args );
	}

	// Az összes Képzés (local_post_id, title, course_data) rövid ideig gyorsítótárazott
	// listája. Több különböző funkció (publikus keresés, publikus lista widget, admin
	// szűrőpanel, jogosultság szerinti szűrés) korábban egymástól függetlenül futtatta le
	// UGYANAZT a teljes tábla-beolvasást minden egyes kérésnél/oldalbetöltésnél - innentől
	// egyet olvasunk be, és csak akkor frissítjük, ha tényleg változott valami.
	public static function get_cached_courses_data() {
		$cached = get_transient( 'szeducate_courses_cache' );
		if ( is_array( $cached ) ) return $cached;

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$rows = $wpdb->get_results( "SELECT local_post_id, title, course_data FROM $table_name", ARRAY_A );

		set_transient( 'szeducate_courses_cache', $rows, 5 * MINUTE_IN_SECONDS );
		return $rows;
	}

	public static function invalidate_courses_cache() {
		delete_transient( 'szeducate_courses_cache' );
	}

	// Azon 'sz_course' bejegyzések ID-jai, amik már NEM tartoznak egyetlen sorhoz sem a
	// wp_szeducate_courses_data táblában (pl. sikertelen cím-párosítás miatt egy korábbi
	// szinkronizáció/visszaállítás során új bejegyzést hozott létre a régi helyett).
	public function get_orphaned_course_post_ids() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) return array();

		$linked_post_ids = $wpdb->get_col( "SELECT local_post_id FROM {$table_name} WHERE local_post_id IS NOT NULL AND local_post_id != 0" );
		$linked_post_ids = array_map( 'intval', $linked_post_ids );

		$all_course_post_ids = get_posts( array(
			'post_type'      => 'sz_course',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		return array_values( array_diff( $all_course_post_ids, $linked_post_ids ) );
	}

	public function register_dynamic_taxonomies() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_taxonomy'] ) && $field['is_taxonomy'] ) {
							$tax_slug = 'sz_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							$rewrite_slug = sanitize_title( $field['label'] ); 

							register_taxonomy( $tax_slug, 'sz_course', array(
								'label'             => $field['label'],
								'hierarchical'      => true,
								'public'            => true,
								'show_ui'           => false, 
								'show_in_rest'      => false,
								'rewrite'           => array( 'slug' => $rewrite_slug ),
							) );
						}
					}
				}
			}
		}
	}

	private function evaluate_conditions( $conditions, $course_data ) {
		if ( empty( $conditions ) || empty( $conditions['rules'] ) ) return true;

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
					case '==': $rule_result = ( $actual_string === $target_value ); break;
					case '!=': $rule_result = ( $actual_string !== $target_value ); break;
					case 'contains': $rule_result = ( strpos( $actual_string, $target_value ) !== false ); break;
				}
				$results[] = $rule_result;
			}
		}

		return $logical_operator === 'AND' ? ! in_array( false, $results, true ) : in_array( true, $results, true );
	}

	public function clean_admin_ui() {
		global $typenow, $pagenow;
		if ( $typenow === 'sz_course' && in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
			
			$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
			$actions = isset($perms['actions']) ? $perms['actions'] : array();
			
			$hide_create = ( isset($actions['create']) && !$actions['create'] );
			$hide_delete = ( isset($actions['delete']) && !$actions['delete'] );

			$css = '<style>';
			if ( $hide_create ) {
				$css .= '.page-title-action, #wp-admin-bar-new-sz_course { display: none !important; } ';
			}
			if ( $hide_delete ) {
				$css .= '.submitdelete, .row-actions .trash { display: none !important; } ';
			}
			$css .= '
				#titlediv, 
				#postdivrich,
				#submitdiv,
				.meta-box-sortables,
				#postbox-container-1, 
				#postbox-container-2, 
				.wrap > h1, 
				.notice, 
				#lost-connection-notice, 
				#local-storage-notice {
					display: none !important;
				}
				#szeducate-react-root {
					margin-top: 20px;
					max-width: 1200px;
					display: block !important;
				}
			</style>';
			echo $css;
		}
	}

	public function enqueue_react_app( $hook ) {
		global $typenow, $wpdb;
		
		if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $typenow === 'sz_course' ) {
			
			wp_enqueue_media();
			wp_enqueue_editor();
			
			$asset_file = SZEDUCATE_PLUGIN_DIR . 'build/index.asset.php';
			if ( file_exists( $asset_file ) ) {
				$assets = require $asset_file;
				wp_enqueue_script(
					'szeducate-react-app',
					SZEDUCATE_PLUGIN_URL . 'build/index.js',
					$assets['dependencies'],
					$assets['version'],
					true
				);
				
				$post_id = get_the_ID();
				$existing_title = '';
				$existing_data = new stdClass();

				if ( $post_id ) {
					$table_name = $wpdb->prefix . 'szeducate_courses_data';
					$course_db = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE local_post_id = %d", $post_id ), ARRAY_A );
					
					if ( $course_db ) {
						$existing_title = $course_db['title'];
						$decoded_data = json_decode( $course_db['course_data'], true );
						if ( is_array( $decoded_data ) ) {
							$existing_data = $decoded_data;
						}
					}
				}

				wp_localize_script( 'szeducate-react-app', 'szEducateData', array(
					'postId'        => $post_id,
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'restUrl'       => esc_url_raw( rest_url( 'szeducate/v1/client/course' ) ),
					'versionsUrl'   => esc_url_raw( rest_url( 'szeducate/v1/client/course-versions' ) ),
					'schema'        => json_decode( get_option( 'szeducate_local_schema', '[]' ), true ),
					'permissions'   => json_decode( get_option( 'szeducate_client_permissions', '{}' ), true ),
					'existingTitle' => $existing_title,
					'existingData'  => $existing_data
				) );
			}
		}
	}

	public function render_react_root( $post ) {
		if ( is_object( $post ) && $post->post_type === 'sz_course' ) {
			echo '<div id="szeducate-react-root"><h2>React betöltése folyamatban...</h2></div>';
		}
	}

	public function sync_course_to_hub( $local_post_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$course = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE local_post_id = %d", $local_post_id ), ARRAY_A );
		if ( ! $course ) return;

		$settings = get_option( 'szeducate_settings', array() );
		$hub_url = rtrim( $settings['hub_url'], '/' );
		$api_token = $settings['api_token'];

		if ( empty( $hub_url ) || empty( $api_token ) ) return;

		$endpoint = $hub_url . '/wp-json/szeducate/v1/hub/courses';

		$payload = array(
			'local_post_id' => $local_post_id,
			'hub_id'        => isset($course['hub_id']) ? intval($course['hub_id']) : 0,
			'title'         => $course['title'],
			'course_data'   => json_decode( $course['course_data'], true )
		);

		$response = wp_remote_post( $endpoint, array(
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 15,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) return; 

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 200 || $code === 201 ) {
			$data = json_decode( $body, true );
			if ( ! empty( $data['hub_id'] ) ) {
				$wpdb->update( 
					$table_name, 
					array( 'hub_id' => intval( $data['hub_id'] ) ), 
					array( 'local_post_id' => $local_post_id ) 
				);
			}
		}
	}

	public function process_daily_expirations() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$courses = $wpdb->get_results( "SELECT id, local_post_id, course_data FROM $table_name", ARRAY_A );
		$today = current_time( 'Y-m-d' );

		foreach ( $courses as $course ) {
			$data = json_decode( $course['course_data'], true );
			if ( ! is_array( $data ) ) continue;

			if ( isset( $data['meghirdetes_allapota'] ) && $data['meghirdetes_allapota'] === 'Aktív' ) {
				if ( ! empty( $data['passziv_ettol'] ) && $data['passziv_ettol'] < $today ) {
					
					$data['meghirdetes_allapota'] = 'Inaktív';
					$data['passziv_ettol'] = '';

					$wpdb->update(
						$table_name,
						array( 'course_data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ),
						array( 'id' => $course['id'] )
					);

					wp_schedule_single_event( time(), 'szeducate_background_sync', array( $course['local_post_id'] ) );
				}
			}
		}
	}

	public function register_client_endpoints() {
		register_rest_route( 'szeducate/v1/client', '/field-options', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_field_options_for_editor' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_sz_courses' ) || current_user_can( 'manage_options' );
			}
		) );

		register_rest_route( 'szeducate/v1/client', '/search', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'ajax_search_courses' ),
			'permission_callback' => '__return_true', 
		) );
	}

	public function get_field_options_for_editor( \WP_REST_Request $request ) {
		global $wpdb;
		$key = sanitize_text_field( $request->get_param( 'key' ) );
		
		if ( empty( $key ) ) return rest_ensure_response( array() );

		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			return rest_ensure_response( array() );
		}

		$results = $wpdb->get_col( "SELECT course_data FROM $table_name" );
		
		$options = array();
		if ( $results ) {
			foreach ( $results as $json ) {
				$data = json_decode( $json, true );
				if ( isset( $data[$key] ) && ! empty( $data[$key] ) ) {
					$val = $data[$key];
					$parts = is_array( $val ) ? $val : explode( ';', (string)$val );
					foreach ( $parts as $p ) {
						$p = trim( $p );
						if ( $p !== '' && ! in_array( $p, $options ) ) {
							$options[] = $p;
						}
					}
				}
			}
		}
		
		sort( $options );
		return rest_ensure_response( $options );
	}

	public function bypass_trash_for_courses( $trash, $post ) {
		if ( $post->post_type === 'sz_course' ) {
			wp_delete_post( $post->ID, true );
			return true;
		}
		return $trash;
	}

	public function process_course_deletion( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'sz_course' ) return;

		if ( !empty($GLOBALS['szeducate_is_sync_deleting']) ) return;

		$perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
		$actions = isset($perms['actions']) ? $perms['actions'] : array();
		
		if ( isset($actions['delete']) && !$actions['delete'] ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error('A karod Jogosultsági Mátrixa jelenleg nem engedélyezi a képzések törlését!');
			} else {
				wp_die( '<h1>Nincs jogosultságod!</h1><p>A karod Jogosultsági Mátrixa jelenleg nem engedélyezi a képzések törlését!</p><a href="javascript:history.back()">Vissza</a>' );
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT hub_id FROM $table_name WHERE local_post_id = %d", $post_id ), ARRAY_A );

		if ( $course && ! empty( $course['hub_id'] ) ) {
			$settings = get_option( 'szeducate_settings', array() );
			$hub_url = rtrim( $settings['hub_url'], '/' );
			$api_token = $settings['api_token'];

			if ( !empty( $hub_url ) && !empty( $api_token ) ) {
				$endpoint = $hub_url . '/wp-json/szeducate/v1/hub/courses/' . $course['hub_id'];
				
				$response = wp_remote_request( $endpoint, array(
					'method'      => 'DELETE',
					'headers'     => array( 'Authorization' => 'Bearer ' . $api_token ),
					'timeout'     => 15,
					'blocking'    => true, 
				) );

				if ( is_wp_error( $response ) ) {
					if ( wp_doing_ajax() ) {
						wp_send_json_error('Hálózati Hiba a Hub elérésekor!');
					} else {
						wp_die( '<h1>Hálózati Hiba</h1><p>Nem sikerült elérni a Hub szervert a törléshez! A képzést nem tudtuk törölni! (' . $response->get_error_message() . ')</p><a href="javascript:history.back()">Vissza</a>' );
					}
				}
				
				$code = wp_remote_retrieve_response_code($response);
				if ( $code !== 200 && $code !== 404 ) { 
					$body = json_decode(wp_remote_retrieve_body($response), true);
					$msg = isset($body['message']) ? $body['message'] : 'Ismeretlen hiba';
					if ( wp_doing_ajax() ) {
						wp_send_json_error('Hub Elutasítva: ' . $msg);
					} else {
						wp_die( '<h1>Hub Elutasítva</h1><p>A központi szerver elutasította a törlést, így helyben is megszakítottuk azt! Oka: ' . esc_html($msg) . '</p><a href="javascript:history.back()">Vissza</a>' );
					}
				}
			}
		}

		$wpdb->delete( $table_name, array( 'local_post_id' => $post_id ) );
		self::invalidate_courses_cache();

		global $szeducate_is_local_deleting;
		$szeducate_is_local_deleting = true;
	}

	public function ajax_search_courses( \WP_REST_Request $request ) {
		global $wpdb;
		$query = trim( sanitize_text_field( $request->get_param('q') ) );
		$query_lower = mb_strtolower( $query, 'UTF-8' );
		
		if ( empty( $query ) || mb_strlen( $query, 'UTF-8' ) < 2 ) {
			return rest_ensure_response( array() );
		}

		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			return rest_ensure_response( array() );
		}

		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$field_labels = array();
		if ( is_array($schema) ) {
			foreach ($schema as $group) {
				if (empty($group['fields'])) continue;
				foreach ($group['fields'] as $f) {
					$field_labels[$f['key']] = $f['label'];
				}
			}
		}

		$courses = self::get_cached_courses_data();

		$course_results = array();
		$category_results = array();
		
		$allowed_category_keys = ['kepzesi_forma', 'kulcsszavak', 'kepzesi_terulet', 'indulas_idoszaka', 'telephely'];

		foreach ( $courses as $course ) {
			$post_id = $course['local_post_id'];
			if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) continue;

			$data = json_decode( $course['course_data'], true );
			if ( ! is_array( $data ) ) continue;

			if ( isset( $data['public'] ) && ( $data['public'] === 0 || $data['public'] === '0' || $data['public'] === false || $data['public'] === 'false' ) ) {
				continue;
			}

			$score = 0;
			$title_lower = mb_strtolower( $course['title'], 'UTF-8' );
			
			if ( $title_lower === $query_lower ) $score += 100;
			elseif ( mb_strpos( $title_lower, $query_lower ) === 0 ) $score += 80;
			elseif ( preg_match( '/\b' . preg_quote( $query, '/' ) . '\b/iu', $course['title'] ) ) $score += 60;
			elseif ( mb_strpos( $title_lower, $query_lower ) !== false ) $score += 40;

			foreach ( $data as $key => $val ) {
				$safe_key = (string) $key;
				if ( is_bool($val) || strpos($safe_key, 'url') !== false ) continue;

				$is_category_field = in_array( $safe_key, $allowed_category_keys );

				$search_text = is_scalar($val) ? (string)$val : wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
				$s_text_lower = mb_strtolower( $search_text, 'UTF-8' );

				if ( mb_strpos( $s_text_lower, $query_lower ) !== false ) {
					
					if ( $is_category_field ) {
						if ( $s_text_lower === $query_lower ) $score += 50;
						else $score += 30;
					} else {
						if ( preg_match( '/\b' . preg_quote( $query, '/' ) . '\b/iu', $search_text ) ) $score += 10;
						else $score += 5;
					}

					if ( $is_category_field && isset($field_labels[$safe_key]) ) {
						$parts = is_array($val) ? $val : explode(';', (string)$val);
						
						foreach ( $parts as $single_val ) {
							$single_val = trim((string)$single_val);
							if ( $single_val === '' ) continue;

							$single_val_lower = mb_strtolower( $single_val, 'UTF-8' );

							if ( mb_strpos( $single_val_lower, $query_lower ) !== false ) {
								$cat_hash = $safe_key . '|' . $single_val;
								if ( !isset($category_results[$cat_hash]) ) {
									$cat_score = 0;
									if ($single_val_lower === $query_lower) $cat_score = 100;
									elseif (mb_strpos($single_val_lower, $query_lower) === 0) $cat_score = 80;
									else $cat_score = 40;

									$category_results[$cat_hash] = array(
										'type'      => 'category',
										'title'     => $single_val,
										'label'     => $field_labels[$safe_key],
										'field_key' => $safe_key,
										'field_val' => $single_val,
										'score'     => $cat_score
									);
								}
							}
						}
					}
				}
			}

			if ( $score > 0 ) {
				$status = isset( $data['meghirdetes_allapota'] ) ? mb_strtolower(trim((string)$data['meghirdetes_allapota']), 'UTF-8') : '';
				$is_active = ( $status === 'aktív' || $status === 'aktiv' );

				$course_results[] = array(
					'type'      => 'course',
					'id'        => $post_id,
					'title'     => $course['title'],
					'url'       => get_permalink( $post_id ),
					'is_active' => $is_active,
					'score'     => $score
				);
			}
		}

		usort( $category_results, function($a, $b) {
			if ( $a['score'] !== $b['score'] ) return $b['score'] - $a['score'];
			return strcmp( $a['title'], $b['title'] );
		});

		usort( $course_results, function($a, $b) {
			if ( $a['score'] !== $b['score'] ) return $b['score'] - $a['score'];
			return strcmp( $a['title'], $b['title'] );
		});

		$final_categories = array_slice( $category_results, 0, 3 );
		$final_courses = array_slice( $course_results, 0, 7 );

		return rest_ensure_response( array_merge( $final_categories, $final_courses ) );
	}
}