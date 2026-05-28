<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Import_Export {

	public function init() {
		// Menü regisztráció
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		
		// Letöltés/Exportálás végpontok
		add_action( 'admin_post_szeducate_download_csv_template', array( $this, 'handle_download_template' ) );
		add_action( 'admin_post_szeducate_export_all_csv', array( $this, 'handle_export_all' ) );

		// Tömeges műveletek (Bulk Actions) a Képzések CPT listájához
		add_filter( 'bulk_actions-edit-sz_course', array( $this, 'register_bulk_export_action' ) );
		add_action( 'load-edit.php', array( $this, 'process_bulk_export_action' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'szeducate-settings',
			'CSV Import / Export',
			'CSV Import / Export',
			'manage_options',
			'szeducate-import',
			array( $this, 'render_page' )
		);
	}

	// Tömeges művelet regisztrálása a lenyíló menübe
	public function register_bulk_export_action( $bulk_actions ) {
		$bulk_actions['szeducate_export_selected'] = 'Exportálás CSV-be';
		return $bulk_actions;
	}

	// Tömeges művelet feldolgozása (mielőtt a WP HTML-t küldene)
	public function process_bulk_export_action() {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action = $wp_list_table->current_action();

		if ( $action === 'szeducate_export_selected' && isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Nincs jogosultságod.' );
			
			$post_ids = array_map( 'intval', $_REQUEST['post'] );
			$this->generate_csv( $post_ids, false );
		}
	}

	public function handle_download_template() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
		$this->generate_csv( array(), true );
	}

	public function handle_export_all() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
		$this->generate_csv( array(), false );
	}

	// Közös CSV generáló motor (Sablonhoz és Exportáláshoz is)
	private function generate_csv( $post_ids = array(), $is_template_only = false ) {
		global $wpdb;

		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		
		$headers = array( 'Cím (Szak megnevezése)' );
		$keys    = array( 'title' );
		$help    = array( '[Kötelező! Szöveges mező]' );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						$headers[] = $field['label'] . ' [' . $field['key'] . ']';
						$keys[] = $field['key'];

						$help_text = '';
						switch ( $field['type'] ) {
							case 'boolean':
								$help_text = '[Kapcsoló: írd be, hogy "true" vagy "false"]';
								break;
							case 'select':
							case 'radio':
								$help_text = '[Válassz egyet: ' . ( !empty($field['options']) ? $field['options'] : '' ) . ']';
								break;
							case 'checkbox':
							case 'multiselect':
								$help_text = '[Több is lehet (VESSZŐVEL elválasztva): ' . ( !empty($field['options']) ? $field['options'] : '' ) . ']';
								break;
							case 'number':
								$help_text = '[Csak szám]';
								break;
							case 'date':
								$help_text = '[Dátum: ÉÉÉÉ-HH-NN]';
								break;
							default:
								$help_text = '[Szöveges mező]';
								break;
						}
						$help[] = $help_text;
					}
				}
			}
		}

		$filename = $is_template_only ? 'szeducate_sablon.csv' : 'szeducate_export_' . date('Ymd_His') . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		
		$output = fopen( 'php://output', 'w' );
		fputs( $output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );
		
		fputcsv( $output, $headers, ';' );
		fputcsv( $output, $keys, ';' );
		fputcsv( $output, $help, ';' );

		// Ha nem csak sablont kérünk, beletöltjük az adatokat
		if ( ! $is_template_only ) {
			$table_name = $wpdb->prefix . 'szeducate_courses_data';
			
			if ( empty( $post_ids ) ) {
				// Összes adat lekérése
				$results = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
			} else {
				// Csak a kijelölt azonosítók lekérése (biztonságosan)
				$ids_string = implode( ',', $post_ids );
				$results = $wpdb->get_results( "SELECT * FROM $table_name WHERE local_post_id IN ($ids_string)", ARRAY_A );
			}

			if ( $results ) {
				foreach ( $results as $row ) {
					$course_data = json_decode( $row['course_data'], true );
					if ( ! is_array( $course_data ) ) $course_data = array();

					$csv_row = array();
					foreach ( $keys as $key ) {
						if ( $key === 'title' ) {
							$csv_row[] = $row['title'];
						} else {
							$val = isset( $course_data[$key] ) ? $course_data[$key] : '';
							
							// Típusok visszakonvertálása olvasható stringgé
							if ( is_bool( $val ) ) {
								$csv_row[] = $val ? 'true' : 'false';
							} elseif ( is_array( $val ) ) {
								$csv_row[] = implode( ', ', $val );
							} else {
								$csv_row[] = $val;
							}
						}
					}
					fputcsv( $output, $csv_row, ';' );
				}
			}
		}

		fclose( $output );
		exit;
	}

	// Importáló és Letöltő UI
	public function render_page() {
		// ... [A korábbi Importáló kód deklarációi megegyeznek, itt csak az elejét módosítom a gombokkal]
		$parsed_data = null;
		$error_msg = '';

		if ( isset( $_POST['submit_csv'] ) && isset( $_FILES['csv_file'] ) ) {
			if ( $_FILES['csv_file']['error'] === UPLOAD_ERR_OK ) {
				$file_tmp = $_FILES['csv_file']['tmp_name'];
				$handle = fopen( $file_tmp, 'r' );
				if ( $handle !== false ) {
					$labels = fgetcsv( $handle, 10000, ';' ); 
					$keys   = fgetcsv( $handle, 10000, ';' ); 
					$help   = fgetcsv( $handle, 10000, ';' ); 
					$keys[0] = preg_replace( '/^[\xef\xbb\xbf]/', '', $keys[0] );

					if ( $keys && in_array( 'title', $keys ) ) {
						$parsed_data = array();
						while ( ( $row = fgetcsv( $handle, 10000, ';' ) ) !== false ) {
							if ( empty( array_filter( $row ) ) ) continue; 
							$course = array( 'course_data' => array() );
							foreach ( $row as $index => $value ) {
								$key = $keys[$index] ?? null;
								if ( ! $key ) continue;
								
								if ( $key === 'title' ) {
									$course['title'] = $value;
								} else {
									if ( strtolower(trim($value)) === 'true' ) {
										$course['course_data'][$key] = true;
									} elseif ( strtolower(trim($value)) === 'false' ) {
										$course['course_data'][$key] = false;
									} else {
										$course['course_data'][$key] = $value;
									}
								}
							}
							if ( ! empty( $course['title'] ) ) {
								global $wpdb;
								$existing_id = $wpdb->get_var( $wpdb->prepare( 
									"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'sz_course' AND post_status != 'trash' LIMIT 1", 
									$course['title'] 
								) );
								$course['local_post_id'] = $existing_id ? intval( $existing_id ) : 0;
								$parsed_data[] = $course;
							}
						}
					} else {
						$error_msg = 'Hibás fájlstruktúra.';
					}
					fclose( $handle );
				}
			} else {
				$error_msg = 'Feltöltési hiba történt.';
			}
		}
		?>
		<div class="wrap">
			<h1>Tömeges CSV Import / Export</h1>

			<!-- ÚJ: Kezelőpanel a letöltésekhez -->
			<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px; display: flex; gap: 20px;">
				<div style="flex: 1;">
					<h3>📥 Üres Sablon</h3>
					<p>Töltsd le az üres adatszerkezetet az új képzések tömeges kitöltéséhez.</p>
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=szeducate_download_csv_template' ) ); ?>" class="button button-secondary">Sablon Letöltése</a>
				</div>
				<div style="border-left: 1px solid #ddd; padding-left: 20px; flex: 1;">
					<h3>📦 Összes Exportálása</h3>
					<p>Az adatbázisban lévő <strong>minden</strong> képzés kimentése Excel-barát CSV formátumba.</p>
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=szeducate_export_all_csv' ) ); ?>" class="button button-primary" style="background:#007cba;">Összes Exportálása CSV-be</a>
				</div>
			</div>

			<?php if ( $error_msg ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
			<?php endif; ?>

			<?php if ( $parsed_data === null ) : ?>
				<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
					<h3>Fájl Importálása</h3>
					<p>Töltsd fel a kitöltött (vagy korábban exportált és módosított) sablont. A fájlt pontosvesszővel (;) tagolt UTF-8 CSV formátumban kell elmenteni.</p>
					<form method="post" enctype="multipart/form-data">
						<input type="file" name="csv_file" accept=".csv" required>
						<p class="submit">
							<input type="submit" name="submit_csv" class="button button-primary" value="Fájl elemzése és Importálás">
						</p>
					</form>
				</div>
			<?php else : ?>
				<!-- AJAX Importáló (ugyanaz mint eddig) -->
				<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
					<h3>Feldolgozás Folyamatban...</h3>
					<p>Talált bejegyzések: <strong><?php echo count( $parsed_data ); ?></strong> db.</p>
					<div style="width: 100%; background: #eee; height: 20px; border-radius: 3px; margin-bottom: 20px;">
						<div id="import-progress-bar" style="width: 0%; background: #007cba; height: 100%; border-radius: 3px; transition: width 0.3s;"></div>
					</div>
					<p id="import-status-text">Készülj fel az importálásra...</p>
					<textarea id="import-log" readonly style="width: 100%; height: 150px; font-family: monospace; font-size: 11px; background: #f9f9f9; padding: 10px;"></textarea>
				</div>

				<script>
				document.addEventListener('DOMContentLoaded', function() {
					const courses = <?php echo wp_json_encode( $parsed_data ); ?>;
					const restUrl = '<?php echo esc_url_raw( rest_url( 'szeducate/v1/client/course' ) ); ?>';
					const nonce = '<?php echo wp_create_nonce( 'wp_rest' ); ?>';
					
					const progressBar = document.getElementById('import-progress-bar');
					const statusText = document.getElementById('import-status-text');
					const logArea = document.getElementById('import-log');

					let currentIndex = 0;
					const total = courses.length;

					function logMsg(msg) {
						logArea.value += msg + '\n';
						logArea.scrollTop = logArea.scrollHeight;
					}

					async function processNext() {
						if (currentIndex >= total) {
							statusText.innerHTML = '<strong>✅ Importálás befejezve!</strong>';
							progressBar.style.background = '#46b450';
							return;
						}

						const course = courses[currentIndex];
						const progressPct = Math.round(((currentIndex + 1) / total) * 100);
						progressBar.style.width = progressPct + '%';
						
						const actionText = course.local_post_id > 0 ? 'Frissítés' : 'Létrehozás';
						statusText.innerText = `Feldolgozás: ${currentIndex + 1} / ${total} - [${actionText}] ${course.title}`;

						try {
							const response = await fetch(restUrl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
								body: JSON.stringify({
									local_post_id: course.local_post_id,
									title: course.title,
									course_data: course.course_data
								})
							});
							
							const data = await response.json();
							if (data.success) {
								logMsg(`[OK - ${actionText}] ${course.title}`);
							} else {
								logMsg(`[HIBA] ${course.title} - ${data.message || data.code}`);
							}
						} catch (e) {
							logMsg(`[HÁLÓZATI HIBA] ${course.title}`);
						}

						currentIndex++;
						setTimeout(processNext, 200); 
					}

					logMsg('Importálás indítása...');
					processNext();
				});
				</script>
			<?php endif; ?>
		</div>
		<?php
	}
}