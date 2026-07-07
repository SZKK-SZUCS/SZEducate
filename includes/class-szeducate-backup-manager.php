<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Backup_Manager {

	private $backup_dir;

	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->backup_dir = $upload_dir['basedir'] . '/szeducate-backups/';
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_backup_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'setup_directory' ) );
		
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'szeducate_automated_backup_cron', array( $this, 'perform_automated_backup' ) );
		add_action( 'update_option_szeducate_backup_settings', array( $this, 'update_cron_schedule' ), 10, 2 );
		
		add_action( 'admin_post_szeducate_manual_backup', array( $this, 'handle_manual_backup' ) );
		add_action( 'admin_post_szeducate_restore_backup', array( $this, 'handle_restore_backup' ) );
		add_action( 'admin_post_szeducate_delete_backup', array( $this, 'handle_delete_backup' ) );
		add_action( 'admin_post_szeducate_download_backup', array( $this, 'handle_download_backup' ) );
		add_action( 'admin_post_szeducate_upload_backup', array( $this, 'handle_upload_backup' ) );
		add_action( 'wp_ajax_szeducate_restore_progress', array( $this, 'ajax_get_restore_progress' ) );
		add_action( 'wp_ajax_szeducate_abort_restore', array( $this, 'ajax_abort_restore' ) );
		add_action( 'szeducate_process_restore_webhooks', array( $this, 'process_restore_webhooks' ), 10, 3 );
	}

	public function ajax_get_restore_progress() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Nincs jogosultságod.', 403 );
		check_ajax_referer( 'szeducate_restore_progress', 'nonce' );

		$key = isset( $_GET['key'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_GET['key'] ) : '';
		if ( empty( $key ) ) wp_send_json_error( 'Hiányzó kulcs.' );

		wp_send_json_success( SZEducate_Sync_Log::progress_get( $key ) );
	}

	// --- Vészleállítás gomb: csak egy jelzőzászlót állít be, amit a futó visszaállítás ciklusai olvasnak ---
	public function ajax_abort_restore() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Nincs jogosultságod.', 403 );
		check_ajax_referer( 'szeducate_restore_progress', 'nonce' );

		$key = isset( $_POST['key'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_POST['key'] ) : '';
		if ( empty( $key ) ) wp_send_json_error( 'Hiányzó kulcs.' );

		SZEducate_Sync_Log::request_abort( $key );
		SZEducate_Sync_Log::progress_log( $key, 'Vészleállítás kérve - a folyamat a legközelebbi ellenőrzési pontnál megszakad és visszavonásra kerül...' );

		wp_send_json_success();
	}

	// --- Ha vészleállítást kértek, azonnal visszavonjuk a tranzakciót és leállunk ---
	private function abort_restore_if_requested( $progress_key ) {
		if ( ! SZEducate_Sync_Log::abort_requested( $progress_key ) ) return;

		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
		SZEducate_Sync_Log::clear_abort( $progress_key );
		SZEducate_Sync_Log::progress_log( $progress_key, 'VÉSZLEÁLLÍTÁS VÉGREHAJTVA: a visszaállítás megszakadt, minden eddigi adatbázis-változás visszavonva.' );
		SZEducate_Sync_Log::progress_finish( $progress_key, true );

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'aborted_restore' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function add_backup_menu() {
		$main_settings = get_option( 'szeducate_settings' );
		if ( isset( $main_settings['mode'] ) && $main_settings['mode'] === 'hub' ) {
			add_submenu_page( 'szeducate-settings', 'Biztonsági Mentések', 'Mentések', 'manage_options', 'szeducate-backups', array( $this, 'render_backup_page' ) );
		}
	}

	public function register_settings() {
		register_setting( 'szeducate_backup_group', 'szeducate_backup_settings' );

		add_settings_section( 'szeducate_backup_main', 'Helyi Mentés (Lokális)', null, 'szeducate-backups' );
		add_settings_field( 'backup_frequency', 'Mentés gyakorisága', array( $this, 'cb_frequency' ), 'szeducate-backups', 'szeducate_backup_main' );
		add_settings_field( 'backup_retention', 'Megtartott mentések száma', array( $this, 'cb_retention' ), 'szeducate-backups', 'szeducate_backup_main' );

		add_settings_section( 'szeducate_backup_cloud', '<span class="dashicons dashicons-cloud" style="vertical-align: text-bottom;"></span> Microsoft OneDrive (Felhő)', array( $this, 'cb_cloud_desc' ), 'szeducate-backups' );
		add_settings_field( 'onedrive_tenant_id', 'Directory (Tenant) ID', array( $this, 'cb_od_tenant' ), 'szeducate-backups', 'szeducate_backup_cloud' );
		add_settings_field( 'onedrive_client_id', 'Application (Client) ID', array( $this, 'cb_od_client' ), 'szeducate-backups', 'szeducate_backup_cloud' );
		add_settings_field( 'onedrive_client_secret', 'Client Secret (Titkos kulcs)', array( $this, 'cb_od_secret' ), 'szeducate-backups', 'szeducate_backup_cloud' );
		add_settings_field( 'onedrive_user_email', 'Cél OneDrive Email címe', array( $this, 'cb_od_email' ), 'szeducate-backups', 'szeducate_backup_cloud' );
	}

	// --- Vizuális Felület ---
	public function render_backup_page() {
		if ( isset( $_GET['inspect_file'] ) ) {
			$this->render_inspection_mode( sanitize_text_field( $_GET['inspect_file'] ) );
			return;
		}

		$backups = $this->get_backup_files();
		$backup_token = get_option( 'szeducate_hub_backup_token' );
		if ( empty( $backup_token ) ) {
			$backup_token = wp_generate_password( 32, false );
			update_option( 'szeducate_hub_backup_token', $backup_token );
		}

		$cron_url = site_url( '/wp-json/szeducate/v1/hub/backup' );
		$cron_command = sprintf( 'curl -X POST -H "X-Backup-Token: %s" "%s"', $backup_token, $cron_url );
		?>
		<!-- Töltőképernyő Visszaállításhoz -->
		<div id="sz-restore-loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(240,246,252,0.9); z-index:999999; flex-direction:column; align-items:center; justify-content:center;">
			<span class="dashicons dashicons-update" style="font-size: 60px; width: 60px; height: 60px; color: #2271b1; animation: sz-spin 2s linear infinite;"></span>
			<h2 style="margin-top: 30px; color: #1d2327;">Visszaállítás és Szinkronizáció folyamatban...</h2>
			<p style="font-size: 16px; color: #50575e;">Kérjük, ne zárd be és ne frissítsd az oldalt!</p>
			<style>@keyframes sz-spin { 100% { transform: rotate(360deg); } }</style>
		</div>

		<div class="wrap">
			<h1 class="wp-heading-inline"><span class="dashicons dashicons-database" style="font-size: 28px; width: 28px; height: 28px; margin-top: 2px;"></span> SZEducate Biztonsági Mentések (Hub)</h1>
			<hr class="wp-header-end">

			<?php 
			if ( isset( $_GET['backup_msg'] ) ) {
				if ( $_GET['backup_msg'] === 'success_create' ) echo '<div class="notice notice-success is-dismissible"><p>A mentés sikeresen elkészült!</p></div>';
				if ( $_GET['backup_msg'] === 'success_restore' ) echo '<div class="notice notice-success is-dismissible"><p><strong>Siker:</strong> A kiválasztott elemek tökéletesen visszaállítva, és a kliensek frissítése megkezdődött!</p></div>';
				if ( $_GET['backup_msg'] === 'success_delete' ) echo '<div class="notice notice-info is-dismissible"><p>A kiválasztott fájl véglegesen törölve.</p></div>';
				if ( $_GET['backup_msg'] === 'success_upload' ) echo '<div class="notice notice-success is-dismissible"><p>A mentés feltöltve a szerverre. Kattints az Ellenőrzés gombra a tartalom megtekintéséhez.</p></div>';
				if ( $_GET['backup_msg'] === 'partial_restore' ) {
					$restore_errors = get_transient( 'szeducate_restore_errors' );
					delete_transient( 'szeducate_restore_errors' );
					echo '<div class="notice notice-error is-dismissible"><p><strong>Figyelem:</strong> A visszaállítás részben sikertelen volt. Az alábbi elemeket nem sikerült visszaírni az adatbázisba:</p><ul style="list-style:disc; margin-left:20px;">';
					if ( is_array( $restore_errors ) ) {
						foreach ( $restore_errors as $err ) {
							echo '<li>' . esc_html( $err ) . '</li>';
						}
					}
					echo '</ul></div>';
				}
				if ( $_GET['backup_msg'] === 'aborted_restore' ) echo '<div class="notice notice-warning is-dismissible"><p><strong>Megszakítva:</strong> A visszaállítást vészleállítással megszakítottad, minden addigi adatbázis-változás vissza lett vonva. A rendszer a mentés előtti állapotban maradt.</p></div>';
			}
			?>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					
					<div id="post-body-content">
						
						<div class="postbox">
							<div class="postbox-header"><h2 class="hndle"><span><span class="dashicons dashicons-cloud-saved"></span> Új Mentés Létrehozása</span></h2></div>
							<div class="inside">
								<p>Készíts egy teljes pillanatképet a rendszer (Sémák, Kliensek, Képzések) jelenlegi állapotáról.</p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="szeducate_manual_backup">
									<?php wp_nonce_field( 'szeducate_manual_backup' ); ?>
									<button type="submit" class="button button-primary button-hero">Pillanatkép Létrehozása Most</button>
								</form>
							</div>
						</div>

						<div class="postbox">
							<div class="postbox-header"><h2 class="hndle"><span><span class="dashicons dashicons-upload"></span> Fájl Manuális Feltöltése</span></h2></div>
							<div class="inside">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
									<input type="hidden" name="action" value="szeducate_upload_backup">
									<?php wp_nonce_field( 'szeducate_upload_backup' ); ?>
									<div style="display: flex; gap: 10px; align-items: center;">
										<input type="file" name="backup_file" accept=".json" required>
										<button type="submit" class="button">Szerverre Töltés</button>
									</div>
								</form>
							</div>
						</div>

						<div class="postbox">
							<div class="postbox-header"><h2 class="hndle"><span><span class="dashicons dashicons-list-view"></span> Archívum (Lokálisan tárolt fájlok)</span></h2></div>
							<div class="inside">
								<table class="wp-list-table widefat fixed striped">
									<thead><tr><th>Létrehozás Ideje</th><th>Fájlnév</th><th>Méret</th><th>Műveletek</th></tr></thead>
									<tbody>
										<?php if ( empty( $backups ) ) : ?>
											<tr><td colspan="4">Jelenleg nincsenek biztonsági mentések a szerveren.</td></tr>
										<?php else : ?>
											<?php foreach ( $backups as $backup ) : 
												$inspect_url = admin_url( 'admin.php?page=szeducate-backups&inspect_file=' . urlencode( $backup['filename'] ) );
												$download_url = wp_nonce_url( admin_url( 'admin-post.php?action=szeducate_download_backup&file=' . urlencode( $backup['filename'] ) ), 'szeducate_download_backup' );
												$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=szeducate_delete_backup&file=' . urlencode( $backup['filename'] ) ), 'szeducate_delete_backup' );
											?>
											<tr>
												<td><strong><?php echo esc_html( $backup['time'] ); ?></strong></td>
												<td><code style="background: none; padding: 0;"><?php echo esc_html( $backup['filename'] ); ?></code></td>
												<td><?php echo esc_html( $backup['size'] ); ?></td>
												<td>
													<a href="<?php echo esc_url( $inspect_url ); ?>" class="button button-small button-primary" style="margin-right: 5px;"><span class="dashicons dashicons-search" style="margin-top:4px;"></span> Elemzés és Visszaállítás</a>
													<a href="<?php echo esc_url( $download_url ); ?>" class="button button-small">Letöltés</a>
													<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link-delete" style="color: #a00;">Törlés</a>
												</td>
											</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>

						<?php $sync_log = SZEducate_Sync_Log::get_recent( 30 ); ?>
						<div class="postbox">
							<div class="postbox-header"><h2 class="hndle"><span><span class="dashicons dashicons-clock"></span> Kliens-szinkronizáció napló (legutóbbi 30 esemény)</span></h2></div>
							<div class="inside">
								<p class="description">Itt látod, hogy a Hub sikeresen küldte-e ki a Képzés-frissítéseket/törléseket a Klienseknek (élő mentés, szerkesztés vagy visszaállítás során), és ha nem, miért.</p>
								<?php if ( empty( $sync_log ) ) : ?>
									<p>Még nem történt naplózott szinkronizációs esemény.</p>
								<?php else : ?>
									<table class="wp-list-table widefat fixed striped">
										<thead><tr><th style="width:140px;">Időpont</th><th style="width:110px;">Típus</th><th style="width:70px;">Eredmény</th><th>Üzenet</th></tr></thead>
										<tbody>
											<?php foreach ( $sync_log as $entry ) : ?>
												<tr>
													<td><?php echo esc_html( $entry['time'] ); ?></td>
													<td><code style="background:none; padding:0;"><?php echo esc_html( $entry['type'] ); ?></code></td>
													<td>
														<?php if ( ! empty( $entry['success'] ) ) : ?>
															<span style="color:#46b450;">OK</span>
														<?php else : ?>
															<span style="color:#d63638; font-weight:600;">Hiba</span>
														<?php endif; ?>
													</td>
													<td><?php echo esc_html( $entry['message'] ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<div class="postbox-header"><h2 class="hndle"><span><span class="dashicons dashicons-admin-settings"></span> Mentési Beállítások</span></h2></div>
							<div class="inside" id="szeducate-settings-container">
								<form method="post" action="options.php">
									<?php settings_fields( 'szeducate_backup_group' ); ?>
									<?php do_settings_sections( 'szeducate-backups' ); ?>
									<hr style="margin: 20px 0;">
									<?php submit_button( 'Minden Beállítás Mentése', 'primary', 'submit', false ); ?>
								</form>
							</div>
						</div>

						<div class="postbox">
							<div class="postbox-header"><h2 class="hndle"><span><span class="dashicons dashicons-rest-api"></span> Szerveroldali (IT) Időzítő</span></h2></div>
							<div class="inside">
								<div style="margin-top: 10px;">
									<p style="margin-bottom: 2px;"><strong>API Végpont:</strong></p>
									<input type="text" value="<?php echo esc_url( $cron_url ); ?>" readonly style="width: 100%; font-family: monospace; font-size: 11px; background: #f0f0f1; border-color: #8c8f94; box-shadow: none;" />
									
									<p style="margin: 10px 0 2px 0;"><strong>Teszt (Másolható cURL parancs):</strong></p>
									<div style="display: flex; gap: 5px;">
										<input type="text" id="szeducate_cron_cmd" value="<?php echo esc_attr( $cron_command ); ?>" readonly style="flex: 1; font-family: monospace; font-size: 11px; background: #f0f0f1; border-color: #8c8f94; box-shadow: none;" />
										<button type="button" class="button" onclick="copyCronCmd()">Másolás</button>
									</div>
									<span id="copy_msg" style="display:none; color: green; font-size: 11px; margin-top: 5px;">A parancs a vágólapon!</span>
								</div>
								<script>
								function copyCronCmd() {
									var copyText = document.getElementById("szeducate_cron_cmd");
									copyText.select();
									copyText.setSelectionRange(0, 99999);
									document.execCommand('copy');
									var msg = document.getElementById("copy_msg");
									msg.style.display = "inline-block";
									setTimeout(function(){ msg.style.display = "none"; }, 2000);
									window.getSelection().removeAllRanges();
								}
								</script>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>

		<style> 
			.postbox-header { padding: 0 12px; border-bottom: 1px solid #ccd0d4; } 
			#szeducate-settings-container .form-table { margin-bottom: 0; }
			#szeducate-settings-container .form-table th,
			#szeducate-settings-container .form-table td { display: block; width: 100%; padding: 2px 0 10px 0; }
			#szeducate-settings-container .form-table th { padding-bottom: 4px; font-weight: 600; color: #1d2327; }
			#szeducate-settings-container .form-table td input[type="text"],
			#szeducate-settings-container .form-table td input[type="password"],
			#szeducate-settings-container .form-table td input[type="email"],
			#szeducate-settings-container .form-table td input[type="number"],
			#szeducate-settings-container .form-table td select { width: 100%; max-width: 100%; box-sizing: border-box; }
		</style>
		<?php
	}

	private function recursive_ksort( &$array ) {
		if ( is_array( $array ) ) {
			ksort( $array );
			foreach ( $array as &$value ) {
				if ( is_array( $value ) ) {
					$this->recursive_ksort( $value );
				}
			}
		}
	}

	// --- INSPECTION MODE (Hibrid Párosító Motor) ---
	public function render_inspection_mode( $filename ) {
		$filepath = $this->backup_dir . basename( $filename );
		if ( ! file_exists( $filepath ) ) {
			echo '<div class="wrap"><h2>Hiba</h2><p>A fájl nem található.</p><a href="admin.php?page=szeducate-backups" class="button">Vissza a listához</a></div>';
			return;
		}

		$data = json_decode( file_get_contents( $filepath ), true );
		if ( ! is_array( $data ) || ! isset( $data['courses'] ) ) {
			echo '<div class="wrap"><h2>Hiba</h2><p>A fájl érvénytelen, vagy nem tartalmaz Képzés adatokat.</p><a href="admin.php?page=szeducate-backups" class="button">Vissza a listához</a></div>';
			return;
		}

		global $wpdb;
		$courses_table = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';
		
		$current_courses_raw = $wpdb->get_var( "SHOW TABLES LIKE '$courses_table'" ) == $courses_table ? $wpdb->get_results( "SELECT * FROM $courses_table", ARRAY_A ) : [];
		$current_clients_raw = $wpdb->get_var( "SHOW TABLES LIKE '$clients_table'" ) == $clients_table ? $wpdb->get_results( "SELECT * FROM $clients_table", ARRAY_A ) : [];
		
		$current_schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$current_perms = json_decode( get_option( 'szeducate_client_permissions', '[]' ), true );

		$backup_schema = $data['schema'] ?? [];
		$backup_perms = $data['permissions'] ?? [];
		$backup_clients = $data['clients'] ?? [];

		$this->recursive_ksort($current_schema);
		$this->recursive_ksort($backup_schema);
		$this->recursive_ksort($current_perms);
		$this->recursive_ksort($backup_perms);

		$schema_changed = ( wp_json_encode($backup_schema) !== wp_json_encode($current_schema) );
		$perms_changed  = ( wp_json_encode($backup_perms) !== wp_json_encode($current_perms) );

		// Kliens Diff
		$backup_clients_assoc = []; foreach($backup_clients as $c) $backup_clients_assoc[$c['id']] = $c;
		$current_clients_assoc = []; foreach($current_clients_raw as $c) $current_clients_assoc[$c['id']] = $c;
		$clients_changed_list = [];
		$clients_missing_list = array_keys(array_diff_key($backup_clients_assoc, $current_clients_assoc));

		foreach ( array_intersect_key($backup_clients_assoc, $current_clients_assoc) as $cid => $bc ) {
			$cc = $current_clients_assoc[$cid];
			if ( wp_json_encode($bc) !== wp_json_encode($cc) ) {
				$clients_changed_list[] = $cid;
			}
		}

		// Képzések Diff (HIBRID PÁROSÍTÁS: ID + CÍM ALAPJÁN)
		$backup_courses = isset($data['courses']) ? $data['courses'] : [];
		
		$current_unmatched = [];
		foreach ($current_courses_raw as $c) $current_unmatched[$c['id']] = $c;

		$backup_unmatched = [];
		foreach ($backup_courses as $c) $backup_unmatched[$c['id']] = $c;

		$paired_courses = []; 

		// 1. Kör: Párosítás pontos ID alapján (Címmódosítások lekövetése miatt)
		foreach ($backup_unmatched as $b_id => $bc) {
			if (isset($current_unmatched[$b_id])) {
				$paired_courses[] = ['backup' => $bc, 'current' => $current_unmatched[$b_id]];
				unset($backup_unmatched[$b_id]);
				unset($current_unmatched[$b_id]);
			}
		}

		// 2. Kör: Párosítás Cím alapján (A Hard Reset miatti ID elcsúszások lekövetése miatt)
		foreach ($backup_unmatched as $b_id => $bc) {
			foreach ($current_unmatched as $c_id => $cc) {
				if ($bc['title'] === $cc['title']) {
					$paired_courses[] = ['backup' => $bc, 'current' => $cc];
					unset($backup_unmatched[$b_id]);
					unset($current_unmatched[$c_id]);
					break; 
				}
			}
		}

		$to_be_restored = array_values($backup_unmatched); 
		$to_be_lost = array_values($current_unmatched); 
		
		$modified_courses = [];
		foreach ( $paired_courses as $pair ) {
			$bc = $pair['backup'];
			$cc = $pair['current'];
			
			$changes = [];

			// 1. Cím változás ellenőrzése
			if ( $bc['title'] !== $cc['title'] ) {
				$changes['__sz_title__'] = [
					'label'   => 'Cím (Szak megnevezése)',
					'current' => $cc['title'],
					'backup'  => $bc['title']
				];
			}

			// 2. Adatlap tartalom változás ellenőrzése
			$bc_data = is_string($bc['course_data']) ? json_decode($bc['course_data'], true) : $bc['course_data'];
			$cc_data = is_string($cc['course_data']) ? json_decode($cc['course_data'], true) : $cc['course_data'];
			
			if (!is_array($bc_data)) $bc_data = [];
			if (!is_array($cc_data)) $cc_data = [];
			
			$all_keys = array_unique(array_merge(array_keys($bc_data), array_keys($cc_data)));
			foreach ($all_keys as $k) {
				$val_b = isset($bc_data[$k]) ? $bc_data[$k] : null;
				$val_c = isset($cc_data[$k]) ? $cc_data[$k] : null;
				
				if (is_array($val_b)) $this->recursive_ksort($val_b);
				if (is_array($val_c)) $this->recursive_ksort($val_c);
				
				if ( wp_json_encode($val_b) !== wp_json_encode($val_c) ) {
					$format_val = function($val) {
						if ($val === null || $val === '') return '<i>(Üres)</i>';
						if (is_bool($val)) return $val ? 'Igaz' : 'Hamis';
						if (is_scalar($val)) return esc_html((string)$val);
						return esc_html(wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
					};
					$changes[$k] = [
						'label'   => $k,
						'current' => $format_val($val_c),
						'backup'  => $format_val($val_b)
					];
				}
			}
			
			if ( !empty($changes) ) {
				$combined_id = $cc['id'] . '|' . $bc['id']; 
				
				$modified_courses[$combined_id] = [
					'current_title' => $cc['title'],
					'backup_title'  => $bc['title'],
					'changes'       => $changes
				];
			}
		}

		$backup_timestamp = $data['timestamp'] ?? 'Ismeretlen';
		if ( $backup_timestamp !== 'Ismeretlen' ) {
			$backup_timestamp = wp_date( 'Y. m. d. H:i:s', strtotime($backup_timestamp) );
		}

		$restore_url = wp_nonce_url( admin_url( 'admin-post.php?action=szeducate_restore_backup&file=' . urlencode( $filename ) ), 'szeducate_restore_backup' );
		?>

		<!-- Töltőképernyő -->
		<div id="sz-restore-loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(240,246,252,0.95); z-index:999999; flex-direction:column; align-items:center; justify-content:center;">
			<span class="dashicons dashicons-update" style="font-size: 60px; width: 60px; height: 60px; color: #2271b1; animation: sz-spin 2s linear infinite;"></span>
			<h2 style="margin-top: 30px; color: #1d2327;">Visszaállítás és Szinkronizáció folyamatban...</h2>
			<p style="font-size: 16px; color: #50575e;">Kérjük, ne zárd be és ne frissítsd az oldalt!</p>
			<div id="sz-restore-log-wrap" style="width: 600px; max-width: 90vw; margin-top: 15px;">
				<div id="sz-restore-log-status" style="font-size: 13px; color: #646970; margin-bottom: 6px; text-align:center;">Kapcsolódás a folyamatjelzőhöz...</div>
				<pre id="sz-restore-log" style="background:#1d2327; color:#c3e6cb; font-size:12px; line-height:1.6; padding:15px; border-radius:4px; height:220px; overflow-y:auto; text-align:left; white-space:pre-wrap; box-shadow: 0 2px 8px rgba(0,0,0,0.15);"></pre>
			</div>
			<button type="button" id="sz-restore-abort-btn" class="button" style="margin-top: 15px; background:#fff; border-color:#d63638; color:#d63638; font-weight:600;" onclick="szAbortRestore();">
				<span class="dashicons dashicons-dismiss" style="margin-top:4px;"></span> Vészleállítás (minden változás visszavonása)
			</button>
			<style>@keyframes sz-spin { 100% { transform: rotate(360deg); } }</style>
		</div>

		<div class="wrap">
			<h1 class="wp-heading-inline">Mentés Elemzése és Visszaállítás: <code><?php echo esc_html( basename($filename) ); ?></code></h1>
			<a href="admin.php?page=szeducate-backups" class="page-title-action">Vissza a mentésekhez</a>
			<hr class="wp-header-end">
			
			<p class="description">Alább pontosan kiválaszthatod, hogy mely elemeket vagy mely adatmezőket szeretnéd visszaállítani ebből a mentésből. A be nem jelölt elemek érintetlenül maradnak az élő adatbázisban!</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="sz-restore-form">
				<input type="hidden" name="action" value="szeducate_restore_backup">
				<input type="hidden" name="file" value="<?php echo esc_attr( basename($filename) ); ?>">
				<input type="hidden" name="progress_key" id="sz-progress-key" value="">
				<?php wp_nonce_field( 'szeducate_restore_backup' ); ?>

				<!-- 1. Rendszer beállítások -->
				<div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
					<h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><span class="dashicons dashicons-admin-generic"></span> Rendszerszintű beállítások</h3>
					<table class="form-table" style="margin-top:0;">
						<tr>
							<th style="width: 200px;">Adatlap-felépítés (Séma):</th>
							<td>
								<?php if ($schema_changed) : ?>
									<label style="color:#d63638; font-weight:bold;"><input type="checkbox" name="restore_schema" value="1" checked> Séma visszaállítása a mentett állapotra</label>
								<?php else : ?>
									<span style="color:#46b450;"><span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;"></span> Nincs változás</span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th style="width: 200px;">Hálózati Jogosultságok:</th>
							<td>
								<?php if ($perms_changed) : ?>
									<label style="color:#d63638; font-weight:bold;"><input type="checkbox" name="restore_permissions" value="1" checked> Jogosultságok visszaállítása a mentett állapotra</label>
								<?php else : ?>
									<span style="color:#46b450;"><span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;"></span> Nincs változás</span>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>

				<!-- 2. Kliensek -->
				<?php if ( !empty($clients_changed_list) || !empty($clients_missing_list) ) : ?>
				<div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
					<h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><span class="dashicons dashicons-networking"></span> Kliensek (Node-ok) Szinkronizálása</h3>
					<div style="display:flex; flex-direction:column; gap:8px; margin-top:10px;">
						<?php foreach($clients_missing_list as $cid): $c = $backup_clients_assoc[$cid]; ?>
							<label style="color:#007cba;"><input type="checkbox" name="restore_clients[insert][]" value="<?php echo $cid; ?>" checked> <strong>Létrehozás:</strong> <?php echo esc_html($c['client_name'] . ' (' . $c['client_url'] . ')'); ?></label>
						<?php endforeach; ?>
						<?php foreach($clients_changed_list as $cid): $c = $backup_clients_assoc[$cid]; ?>
							<label style="color:#f56e28;"><input type="checkbox" name="restore_clients[update][]" value="<?php echo $cid; ?>" checked> <strong>Felülírás:</strong> <?php echo esc_html($c['client_name'] . ' (' . $c['client_url'] . ')'); ?></label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- 3. Képzések -->
				<div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
					<h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><span class="dashicons dashicons-feedback"></span> Képzések Adatbázisa</h3>
					
					<!-- Módosított (Részleges) -->
					<?php if ( !empty($modified_courses) ) : ?>
					<div style="margin-top: 20px;">
						<h4 style="color: #f56e28; margin-top:0; font-size:15px; display:flex; justify-content:space-between;">
							<span><span class="dashicons dashicons-edit"></span> Felülírásra kerülő képzések (Részleges módosítás)</span>
							<a href="#" class="button button-small" onclick="szToggleCheckboxes(this, '.sz-cb-mod'); return false;">Mind Kijelöl/Töröl</a>
						</h4>
						<div style="margin-top:10px; border:1px solid #c3c4c7; background:#fafafa; border-radius:3px;">
							<?php foreach ( $modified_courses as $combined_id => $mod ) : ?>
								<details style="border-bottom: 1px solid #c3c4c7; padding:0;">
									<summary style="padding: 12px 15px; font-weight: 600; cursor: pointer; color: #f56e28; outline:none;">
										<?php echo esc_html( $mod['current_title'] ); ?>
										<?php if ( $mod['current_title'] !== $mod['backup_title'] ) echo ' <span style="color:#646970; font-weight:normal;">(Mentett név: ' . esc_html( $mod['backup_title'] ) . ')</span>'; ?>
									</summary>
									<div style="padding: 15px; background: #fff;">
										<table class="wp-list-table widefat striped">
											<thead><tr><th style="width:5%;">Visszaállít</th><th style="width:20%;">Mező</th><th style="width:37%;">Jelenlegi Élő Adat (Elvész)</th><th style="width:37%;">Mentésben szereplő Adat</th></tr></thead>
											<tbody>
												<?php foreach($mod['changes'] as $fkey => $diff): ?>
												<tr>
													<td><input type="checkbox" class="sz-cb-mod" name="restore_courses[update][<?php echo $combined_id; ?>][<?php echo base64_encode($fkey); ?>]" value="1" checked></td>
													<td><strong><?php echo esc_html($diff['label']); ?></strong></td>
													<td style="color:#d63638; white-space:pre-wrap; font-family:monospace; font-size:12px;"><?php echo $diff['current']; ?></td>
													<td style="color:#46b450; white-space:pre-wrap; font-family:monospace; font-size:12px;"><?php echo $diff['backup']; ?></td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</details>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<div style="display: flex; gap: 20px; margin-top: 30px; flex-wrap: wrap;">
						<!-- Törlésre kerülő -->
						<div style="flex: 1; min-width: 45%;">
							<h4 style="color: #d63638; margin-top:0; font-size:15px; display:flex; justify-content:space-between;">
								<span><span class="dashicons dashicons-trash"></span> Törlésre ítélt képzések (Mentés óta újak)</span>
								<a href="#" class="button button-small" onclick="szToggleCheckboxes(this, '.sz-cb-del'); return false;">Mind Kijelöl/Töröl</a>
							</h4>
							<?php if ( empty($to_be_lost) ) : ?>
								<p style="color: #46b450; font-weight:600;">Nincs ilyen adat.</p>
							<?php else : ?>
								<div style="margin-top:10px; border:1px solid #c3c4c7; background:#fff; border-radius:3px; padding:10px; max-height: 250px; overflow-y:auto;">
									<?php foreach ( $to_be_lost as $course ) : ?>
										<label style="display:block; padding:5px 0; border-bottom:1px solid #eee; color:#d63638;">
											<input type="checkbox" class="sz-cb-del" name="restore_courses[delete][]" value="<?php echo $course['id']; ?>" checked>
											<strong><?php echo esc_html( $course['title'] ); ?></strong> (Letöröljük)
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						
						<!-- Visszaállítandó (Insert) -->
						<div style="flex: 1; min-width: 45%;">
							<h4 style="color: #007cba; margin-top:0; font-size:15px; display:flex; justify-content:space-between;">
								<span><span class="dashicons dashicons-undo"></span> Visszaállítandó képzések (Mentés óta töröltek)</span>
								<a href="#" class="button button-small" onclick="szToggleCheckboxes(this, '.sz-cb-ins'); return false;">Mind Kijelöl/Töröl</a>
							</h4>
							<?php if ( empty($to_be_restored) ) : ?>
								<p style="color: #646970; font-weight:600;">Nincs ilyen adat.</p>
							<?php else : ?>
								<div style="margin-top:10px; border:1px solid #c3c4c7; background:#fff; border-radius:3px; padding:10px; max-height: 250px; overflow-y:auto;">
									<?php foreach ( $to_be_restored as $course ) : ?>
										<label style="display:block; padding:5px 0; border-bottom:1px solid #eee; color:#007cba;">
											<input type="checkbox" class="sz-cb-ins" name="restore_courses[insert][]" value="<?php echo $course['id']; ?>" checked>
											<strong><?php echo esc_html( $course['title'] ); ?></strong> (Újra létrehozzuk)
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
						<button type="submit" class="button button-primary button-large" style="background: #d63638; border-color: #d63638;" onclick="return szStartRestore();">
							<span class="dashicons dashicons-warning" style="margin-top: 4px;"></span> Kijelölt Elemek Visszaállítása
						</button>
					</div>
				</div>
			</form>
		</div>

		<script>
			function szToggleCheckboxes(btn, selector) {
				var checkboxes = document.querySelectorAll(selector);
				var allChecked = true;
				checkboxes.forEach(function(cb) { if (!cb.checked) allChecked = false; });
				checkboxes.forEach(function(cb) { cb.checked = !allChecked; });
			}

			// A visszaállítás egyetlen, blokkoló admin-post kérésként fut le a szerveren,
			// ezért a folyamat közben egy külön, párhuzamos AJAX lekérdezéssel olvassuk ki
			// a valós idejű naplót - így látszik, hogy a folyamat nem állt le, hanem dolgozik.
			var szProgressPollTimer = null;
			var szCurrentProgressKey = null;
			var szAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			var szProgressNonce = '<?php echo esc_js( wp_create_nonce( 'szeducate_restore_progress' ) ); ?>';

			function szStartRestore() {
				if ( ! confirm('Biztosan végrehajtod a visszaállítást a kiválasztott adatokkal?') ) return false;

				szCurrentProgressKey = 'sz_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
				document.getElementById('sz-progress-key').value = szCurrentProgressKey;
				document.getElementById('sz-restore-loader').style.display = 'flex';

				var logEl = document.getElementById('sz-restore-log');
				var statusEl = document.getElementById('sz-restore-log-status');
				var shownLines = 0;

				szProgressPollTimer = setInterval(function() {
					fetch(szAjaxUrl + '?action=szeducate_restore_progress&key=' + encodeURIComponent(szCurrentProgressKey) + '&nonce=' + szProgressNonce)
						.then(function(r) { return r.json(); })
						.then(function(res) {
							if (!res || !res.success || !res.data) return;
							var state = res.data;
							statusEl.textContent = state.done ? (state.error ? 'Befejezve, hibákkal - az oldal mindjárt frissül...' : 'Befejezve, az oldal mindjárt frissül...') : 'Folyamatban, él a kapcsolat a szerverrel...';

							if (state.lines && state.lines.length > shownLines) {
								for (var i = shownLines; i < state.lines.length; i++) {
									logEl.textContent += '[' + state.lines[i].time + '] ' + state.lines[i].message + '\n';
								}
								shownLines = state.lines.length;
								logEl.scrollTop = logEl.scrollHeight;
							}

							if (state.done) {
								clearInterval(szProgressPollTimer);
							}
						})
						.catch(function() {
							statusEl.textContent = 'A folyamatjelző pillanatnyilag nem érhető el, de a visszaállítás a háttérben tovább fut...';
						});
				}, 1200);

				return true;
			}

			function szAbortRestore() {
				if ( ! szCurrentProgressKey ) return;
				if ( ! confirm('Biztosan megszakítod a visszaállítást? Az eddig elvégzett adatbázis-változtatások VISSZAVONÁSRA kerülnek!') ) return;

				var btn = document.getElementById('sz-restore-abort-btn');
				btn.disabled = true;
				btn.textContent = 'Megszakítás kérve, kérlek várj...';

				var body = new URLSearchParams();
				body.set('action', 'szeducate_abort_restore');
				body.set('key', szCurrentProgressKey);
				body.set('nonce', szProgressNonce);

				fetch(szAjaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
			}
		</script>

		<style>
			details > summary { list-style: none; }
			details > summary::-webkit-details-marker { display: none; }
			details > summary::before { content: '\f344'; font-family: dashicons; display: inline-block; margin-right: 5px; color: #8c8f94; transition: transform 0.2s ease; vertical-align: text-bottom; }
			details[open] > summary::before { transform: rotate(90deg); }
			details:last-child { border-bottom: none !important; }
		</style>
		<?php
	}

	private function record_restore_error( $progress_key, array &$restore_errors, $message ) {
		$restore_errors[] = $message;
		SZEducate_Sync_Log::progress_log( $progress_key, 'HIBA: ' . $message );
	}

	// --- RÉSZLEGES VISSZAÁLLÍTÁS FELDOLGOZÁSA ---
	public function handle_restore_backup() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'szeducate_restore_backup' ) ) wp_die( 'Nincs jogosultságod.' );

		$progress_key = ! empty( $_POST['progress_key'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_POST['progress_key'] ) : 'sz_' . uniqid();
		SZEducate_Sync_Log::clear_abort( $progress_key );
		SZEducate_Sync_Log::progress_reset( $progress_key );
		SZEducate_Sync_Log::progress_log( $progress_key, 'Visszaállítás elindítva...' );

		$filename = basename( sanitize_text_field( $_POST['file'] ) );
		$filepath = $this->backup_dir . $filename;
		if ( ! file_exists( $filepath ) ) {
			SZEducate_Sync_Log::progress_log( $progress_key, 'HIBA: A mentési fájl nem található.' );
			SZEducate_Sync_Log::progress_finish( $progress_key, true );
			wp_die( 'A fájl nem található.' );
		}

		$content = file_get_contents( $filepath );
		$data = json_decode( $content, true );
		if ( ! is_array( $data ) || ! isset( $data['schema'], $data['clients'], $data['courses'] ) ) {
			SZEducate_Sync_Log::progress_log( $progress_key, 'HIBA: Érvénytelen mentési fájl formátum.' );
			SZEducate_Sync_Log::progress_finish( $progress_key, true );
			wp_die( 'Érvénytelen fájl.' );
		}

		@ini_set( 'memory_limit', '256M' );
		@set_time_limit( 0 );
		global $wpdb;
		$courses_table = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';
		$restore_errors = [];

		// Minden adatbázis-írás egyetlen tranzakcióban fut. Ha vészleállítást kérnek,
		// a teljes tranzakciót visszavonjuk (ROLLBACK) - a webhook-küldés emiatt is a
		// legvégén, egy külön háttérfolyamatban történik, sosem a tranzakción belül.
		$wpdb->query( 'START TRANSACTION' );

		SZEducate_Sync_Log::progress_log( $progress_key, 'Fájl beolvasva és ellenőrizve. Rendszerbeállítások feldolgozása...' );
		$this->abort_restore_if_requested( $progress_key );

		// 1. Rendszer beállítások
		if ( !empty($_POST['restore_schema']) ) {
			update_option( 'szeducate_local_schema', wp_json_encode( $data['schema'], JSON_UNESCAPED_UNICODE ) );
			SZEducate_Sync_Log::progress_log( $progress_key, 'Séma visszaállítva.' );
		}
		if ( !empty($_POST['restore_permissions']) ) {
			update_option( 'szeducate_client_permissions', wp_json_encode( $data['permissions'], JSON_UNESCAPED_UNICODE ) );
			SZEducate_Sync_Log::progress_log( $progress_key, 'Hálózati jogosultságok visszaállítva.' );
		}

		$this->abort_restore_if_requested( $progress_key );

		// 2. Kliensek
		$backup_clients_assoc = []; foreach($data['clients'] as $c) $backup_clients_assoc[$c['id']] = $c;
		SZEducate_Sync_Log::progress_log( $progress_key, 'Kliensek feldolgozása...' );

		if ( !empty($_POST['restore_clients']['insert']) ) {
			foreach ( $_POST['restore_clients']['insert'] as $cid ) {
				if ( isset($backup_clients_assoc[$cid]) ) {
					$result = $wpdb->insert( $clients_table, $backup_clients_assoc[$cid] );
					if ( $result === false ) {
						$this->record_restore_error( $progress_key, $restore_errors, sprintf( 'Kliens létrehozása sikertelen ("%s"): %s', $backup_clients_assoc[$cid]['client_name'] ?? $cid, $wpdb->last_error ) );
					} else {
						SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Kliens létrehozva: "%s".', $backup_clients_assoc[$cid]['client_name'] ?? $cid ) );
					}
				}
			}
		}
		if ( !empty($_POST['restore_clients']['update']) ) {
			foreach ( $_POST['restore_clients']['update'] as $cid ) {
				if ( isset($backup_clients_assoc[$cid]) ) {
					$result = $wpdb->update( $clients_table, $backup_clients_assoc[$cid], ['id' => $cid] );
					if ( $result === false && $wpdb->last_error ) {
						$this->record_restore_error( $progress_key, $restore_errors, sprintf( 'Kliens frissítése sikertelen ("%s"): %s', $backup_clients_assoc[$cid]['client_name'] ?? $cid, $wpdb->last_error ) );
					} else {
						SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Kliens frissítve: "%s".', $backup_clients_assoc[$cid]['client_name'] ?? $cid ) );
					}
				}
			}
		}

		// 3. Képzések
		$backup_courses_assoc = []; foreach($data['courses'] as $c) $backup_courses_assoc[$c['id']] = $c;
		$current_courses_raw = $wpdb->get_results( "SELECT * FROM $courses_table", ARRAY_A );
		$current_assoc = []; foreach ($current_courses_raw as $c) $current_assoc[$c['id']] = $c;

		$courses_to_delete = $_POST['restore_courses']['delete'] ?? [];
		$courses_to_insert = $_POST['restore_courses']['insert'] ?? [];
		$courses_to_update = $_POST['restore_courses']['update'] ?? [];

		$hub_ids_to_sync = [];
		$hub_ids_to_delete = [];
		$existing_columns = SZEducate_Activator::get_cached_table_columns( $courses_table );

		SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Képzések feldolgozása (%d törlés, %d újrafelvétel, %d módosítás)...', count($courses_to_delete), count($courses_to_insert), count($courses_to_update) ) );
		$this->abort_restore_if_requested( $progress_key );

		// Törlés (A jelenlegi Élő ID-t használja)
		foreach ( $courses_to_delete as $cid ) {
			$this->abort_restore_if_requested( $progress_key );
			$cid = intval($cid);
			$result = $wpdb->delete( $courses_table, ['id' => $cid] );
			if ( $result === false ) {
				$this->record_restore_error( $progress_key, $restore_errors, sprintf( 'Törlés sikertelen (ID: %d): %s', $cid, $wpdb->last_error ) );
				continue;
			}
			SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Törölve: ID %d.', $cid ) );
			$hub_ids_to_delete[] = $cid;
		}

		// Újrafelvétel (A Mentés ID-jét kapjuk meg)
		foreach ( $courses_to_insert as $backup_id ) {
			$this->abort_restore_if_requested( $progress_key );
			$backup_id = intval($backup_id);
			if ( isset($backup_courses_assoc[$backup_id]) ) {
				$insert_data = $backup_courses_assoc[$backup_id];
				unset($insert_data['id']); // Fontos! Töröljük a régi ID-t, és hagyjuk, hogy a MySQL generáljon egy újat, így elkerüljük a konfliktusokat!

				// Csak azokat az oszlopokat tartjuk meg, amik ténylegesen léteznek az élő táblában.
				// A mentés óta a séma (dinamikus oszlopok) megváltozhatott, ezért a nyers sor nem illeszthető be közvetlenül.
				$insert_data = array_intersect_key( $insert_data, array_flip( $existing_columns ) );

				$result = $wpdb->insert( $courses_table, $insert_data );
				if ( $result === false ) {
					$this->record_restore_error( $progress_key, $restore_errors, sprintf( 'Újrafelvétel sikertelen ("%s"): %s', $insert_data['title'] ?? ( 'mentés ID ' . $backup_id ), $wpdb->last_error ) );
					continue;
				}
				SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Újra felvéve: "%s".', $insert_data['title'] ?? $backup_id ) );
				$hub_ids_to_sync[] = $wpdb->insert_id;
			}
		}

		// Részleges módosítás (A hibrid ID alapján dolgozunk: Aktuális ID | Mentés ID)
		if ( !empty($courses_to_update) ) {
			$schema_json = get_option( 'szeducate_local_schema', '[]' );
			$schema = json_decode( $schema_json, true );

			foreach ( $courses_to_update as $combined_id => $fields ) {
				$this->abort_restore_if_requested( $progress_key );
				$ids = explode('|', $combined_id);
				$current_id = intval($ids[0]);
				$backup_id = intval($ids[1]);

				if ( isset($backup_courses_assoc[$backup_id]) && isset($current_assoc[$current_id]) ) {
					$bc = $backup_courses_assoc[$backup_id];
					$cc = $current_assoc[$current_id];

					$cc_data = is_string($cc['course_data']) ? json_decode($cc['course_data'], true) : $cc['course_data'];
					$bc_data = is_string($bc['course_data']) ? json_decode($bc['course_data'], true) : $bc['course_data'];
					if (!is_array($cc_data)) $cc_data = [];
					if (!is_array($bc_data)) $bc_data = [];

					$new_title = $cc['title'];

					foreach ( $fields as $b64_key => $is_checked ) {
						$fkey = base64_decode($b64_key);
						if ( $fkey === '__sz_title__' ) {
							$new_title = $bc['title'];
						} else {
							if ( array_key_exists($fkey, $bc_data) ) {
								$cc_data[$fkey] = $bc_data[$fkey];
							} else {
								unset($cc_data[$fkey]);
							}
						}
					}

					$db_data = [
						'title' => $new_title,
						'course_data' => wp_json_encode($cc_data, JSON_UNESCAPED_UNICODE)
					];

					// Lapított oszlopok szinkronizálása a Sémából
					if ( is_array( $schema ) ) {
						foreach ( $schema as $group ) {
							if ( ! empty( $group['fields'] ) ) {
								foreach ( $group['fields'] as $field ) {
									if ( ! empty( $field['is_filterable'] ) ) {
										$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
										if ( empty( $key ) || ! in_array( $key, $existing_columns ) ) continue;

										$val = isset( $cc_data[ $field['key'] ] ) ? $cc_data[ $field['key'] ] : '';
										
										if ( $field['type'] === 'number' ) {
											$db_data[$key] = $val !== '' ? intval( $val ) : null;
										} elseif ( $field['type'] === 'boolean' || $field['type'] === 'true_false' ) {
											$db_data[$key] = $val ? 1 : 0;
										} elseif ( $field['type'] === 'date' ) {
											$db_data[$key] = ($val !== '') ? date( 'Y-m-d H:i:s', strtotime( $val ) ) : null;
										} else {
											if ( is_array( $val ) ) $val = implode( '; ', $val );
											$db_data[$key] = sanitize_text_field( $val );
										}
									}
								}
							}
						}
					}

					$result = $wpdb->update( $courses_table, $db_data, ['id' => $current_id] );
					if ( $result === false ) {
						$this->record_restore_error( $progress_key, $restore_errors, sprintf( 'Módosítás sikertelen ("%s"): %s', $new_title, $wpdb->last_error ) );
						continue;
					}
					SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Módosítva: "%s".', $new_title ) );
					$hub_ids_to_sync[] = $current_id;
				}
			}
		}

		$this->abort_restore_if_requested( $progress_key );

		// Minden adatbázis-írás rendben lezajlott - a tranzakciót itt véglegesítjük.
		// A kliens-webhookok kiküldése ezután, egy külön háttérfolyamatban indul, tehát
		// vészleállítás esetén sosem küldünk ki (már véglegesített) adatokra épülő értesítést.
		$wpdb->query( 'COMMIT' );
		SZEducate_Sync_Log::progress_log( $progress_key, 'Adatbázis-változások véglegesítve.' );

		// 4. Webhookok kiküldése a Klienseknek - a HÁTTÉRBEN (cronon keresztül), hogy egy lassan
		// válaszoló vagy elérhetetlen kliens ne akassza meg (és ne is futtassa időtúllépésbe) magát
		// a visszaállítási kérést. A tényleges küldés/naplózás a process_restore_webhooks()-ban történik.
		if ( ( ! empty( $hub_ids_to_sync ) || ! empty( $hub_ids_to_delete ) ) && $wpdb->get_var( "SHOW TABLES LIKE '{$clients_table}'" ) == $clients_table ) {
			wp_schedule_single_event( time(), 'szeducate_process_restore_webhooks', array( $hub_ids_to_sync, $hub_ids_to_delete, $progress_key ) );
			SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Kliensek értesítése a háttérben elindítva (%d frissítés/új, %d törlés). A részletes eredmény a Kliens-szinkronizáció naplóban követhető.', count( $hub_ids_to_sync ), count( $hub_ids_to_delete ) ) );
		}

		if ( ! empty( $restore_errors ) ) {
			set_transient( 'szeducate_restore_errors', $restore_errors, 5 * MINUTE_IN_SECONDS );
			SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Visszaállítás befejezve, %d hibával.', count( $restore_errors ) ) );
			SZEducate_Sync_Log::progress_finish( $progress_key, true );
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'partial_restore' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		SZEducate_Sync_Log::progress_log( $progress_key, 'Visszaállítás sikeresen befejeződött.' );
		SZEducate_Sync_Log::progress_finish( $progress_key, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'success_restore' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// --- Kliens-webhookok kiküldése a HÁTTÉRBEN (WP-Cron), a visszaállítási kérésen kívül.
	// Klienenként EGYETLEN kötegelt (batch) kérést küldünk, ahelyett hogy képzésenként
	// külön HTTP kört futtatnánk - így 98 képzés is másodpercek alatt célba ér, nem percek/órák alatt.
	public function process_restore_webhooks( $hub_ids_to_sync, $hub_ids_to_delete, $progress_key ) {
		@set_time_limit( 0 );
		global $wpdb;
		$courses_table = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$clients_table}'" ) != $clients_table ) return;

		$all_clients = $wpdb->get_results( "SELECT id, client_name, client_url, api_token, permissions FROM {$clients_table} WHERE client_url != '' AND enabled = 1" );

		// Az imént szinkronizált képzések teljes adatát egyben lekérjük, hogy a batch
		// kérésbe a Hub egyből a kész adatot tudja betenni - a kliensnek nem kell
		// visszakérdeznie soronként a Hub-tól.
		$synced_courses = array();
		if ( ! empty( $hub_ids_to_sync ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $hub_ids_to_sync ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, course_data FROM $courses_table WHERE id IN ($placeholders)", $hub_ids_to_sync ), ARRAY_A );
			foreach ( $rows as $row ) {
				$decoded = json_decode( $row['course_data'], true );
				$synced_courses[ $row['id'] ] = array(
					'title'       => $row['title'],
					'course_data' => is_array( $decoded ) ? $decoded : array(),
				);
			}
		}

		SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Kliensek kötegelt (batch) értesítése megkezdve, PÁRHUZAMOSAN, a háttérben (%d kliens)...', count( $all_clients ) ) );

		// Először minden klienshez összeállítjuk a saját (jogosultság szerint szűrt)
		// kötegét, majd MIND egyszerre küldjük ki - nem egymás után várva meg őket.
		$requests = array();
		$client_by_key = array();
		$payload_counts = array();

		foreach ( $all_clients as $c ) {
			$c_perms = json_decode( $c->permissions, true );
			$c_conditions = isset( $c_perms['conditions'] ) ? $c_perms['conditions'] : array();

			// Csak azokat a képzéseket tesszük a kötegbe, amikhez a kliens jogosultsági feltételei szerint hozzáférhet.
			$courses_payload = array();
			$skipped = 0;
			foreach ( $hub_ids_to_sync as $h_id ) {
				if ( ! isset( $synced_courses[ $h_id ] ) ) continue;

				if ( ! $this->evaluate_conditions( $c_conditions, $synced_courses[ $h_id ]['course_data'] ) ) {
					$skipped++;
					continue;
				}

				$courses_payload[] = array(
					'hub_id'      => $h_id,
					'title'       => $synced_courses[ $h_id ]['title'],
					'course_data' => $synced_courses[ $h_id ]['course_data'],
				);
			}

			if ( $skipped > 0 ) {
				SZEducate_Sync_Log::progress_log( $progress_key, sprintf( 'Kihagyva: "%s" nem jogosult %d képzésre.', $c->client_name, $skipped ) );
			}

			if ( empty( $courses_payload ) && empty( $hub_ids_to_delete ) ) continue;

			$key = $c->id;
			$client_by_key[ $key ] = $c;
			$payload_counts[ $key ] = count( $courses_payload );
			$requests[ $key ] = array(
				'url'     => rtrim( $c->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync-course-batch',
				'method'  => 'POST',
				'body'    => wp_json_encode( array( 'courses' => $courses_payload, 'deletes' => $hub_ids_to_delete ) ),
				'headers' => array( 'Content-Type' => 'application/json', 'X-SZEducate-Auth' => $c->api_token ),
				'timeout' => 60,
			);
		}

		$results = SZEducate_Sync_Log::parallel_requests( $requests );

		foreach ( $results as $key => $res ) {
			$c = $client_by_key[ $key ];
			$count = $payload_counts[ $key ];

			if ( $res['error'] ) {
				$msg = sprintf( 'Sikertelen kötegelt küldés ("%s", %d képzés): %s', $c->client_name, $count, $res['error'] );
				SZEducate_Sync_Log::add( 'restore-push', $msg, false );
				SZEducate_Sync_Log::progress_log( $progress_key, $msg );
			} elseif ( $res['code'] < 200 || $res['code'] >= 300 ) {
				$msg = sprintf( 'Hiba a kötegelt küldésnél ("%s"): HTTP %d', $c->client_name, $res['code'] );
				SZEducate_Sync_Log::add( 'restore-push', $msg, false );
				SZEducate_Sync_Log::progress_log( $progress_key, $msg );
			} else {
				$msg = sprintf( 'Kötegelt küldés kész ("%s"): %d képzés elküldve, %d törlés.', $c->client_name, $count, count( $hub_ids_to_delete ) );
				SZEducate_Sync_Log::add( 'restore-push', $msg, true );
				SZEducate_Sync_Log::progress_log( $progress_key, $msg );
			}
		}

		SZEducate_Sync_Log::progress_log( $progress_key, 'Kliensek háttérbeli értesítése befejeződött.' );
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

	public function handle_delete_backup() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'szeducate_delete_backup' ) ) wp_die( 'Nincs jogosultságod.' );
		
		$filename = basename( sanitize_text_field( $_GET['file'] ) );
		$filepath = $this->backup_dir . $filename;
		if ( file_exists( $filepath ) ) unlink( $filepath );

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'success_delete' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_download_backup() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'szeducate_download_backup' ) ) wp_die( 'Nincs jogosultságod.' );
		
		$filename = basename( sanitize_text_field( $_GET['file'] ) );
		$filepath = $this->backup_dir . $filename;
		if ( ! file_exists( $filepath ) ) wp_die( 'A fájl nem található.' );

		header('Content-Description: File Transfer');
		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($filepath));
		readfile($filepath);
		exit;
	}

	public function get_backup_files() {
		$this->setup_directory();
		$files = glob( $this->backup_dir . '*.json' );
		if ( ! $files ) return array();

		usort( $files, function( $a, $b ) { return filemtime( $b ) - filemtime( $a ); });

		$results = array();
		foreach ( $files as $file ) {
			$results[] = array(
				'filename' => basename( $file ),
				'size'     => size_format( filesize( $file ) ),
				// JAVÍTÁS: A helyi fájl módosítási idejét is wp_date-el kérjük le az időzóna miatt
				'time'     => wp_date( 'Y. m. d. H:i:s', filemtime( $file ) )
			);
		}
		return $results;
	}

	public function cb_frequency() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		$freq = $opts['backup_frequency'] ?? 'none';
		echo '<select name="szeducate_backup_settings[backup_frequency]">
			<option value="none" ' . selected( $freq, 'none', false ) . '>Kikapcsolva</option>
			<option value="szeducate_hourly" ' . selected( $freq, 'szeducate_hourly', false ) . '>Óránként (WP Cron)</option>
			<option value="szeducate_daily_three" ' . selected( $freq, 'szeducate_daily_three', false ) . '>Napi 3x (08:00, 12:00, 16:00) (WP Cron)</option>
			<option value="daily" ' . selected( $freq, 'daily', false ) . '>Naponta egyszer (WP Cron)</option>
			<option value="szeducate_weekly" ' . selected( $freq, 'szeducate_weekly', false ) . '>Hetente egyszer (WP Cron)</option>
			<option value="szeducate_monthly" ' . selected( $freq, 'szeducate_monthly', false ) . '>Havonta egyszer (WP Cron)</option>
		</select>';

		$next_run = wp_next_scheduled( 'szeducate_automated_backup_cron' );
		if ( $next_run && $freq !== 'none' ) {
			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );
			
			if ( $freq === 'szeducate_daily_three' ) {
				$current_timestamp = current_time( 'timestamp' );
				$current_hour = (int) wp_date( 'H', $current_timestamp );
				
				$next_valid_hour = 8;
				$add_days = 0;

				if ($current_hour < 8) { $next_valid_hour = 8; }
				elseif ($current_hour < 12) { $next_valid_hour = 12; }
				elseif ($current_hour < 16) { $next_valid_hour = 16; }
				else { $next_valid_hour = 8; $add_days = 1; }

				$tomorrow_str = wp_date( 'Y-m-d', strtotime( "+{$add_days} days", $current_timestamp ) );
				$next_actual_run = strtotime( "{$tomorrow_str} " . str_pad($next_valid_hour, 2, '0', STR_PAD_LEFT) . ":00:00" );
				$local_time = wp_date( "{$date_format} {$time_format}", $next_actual_run );
			} else {
				$local_time = wp_date( "{$date_format} {$time_format}", $next_run );
			}
			
			echo '<p class="description" style="color:#007cba; margin-top:8px;"><strong><span class="dashicons dashicons-clock" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span> Következő várható mentés:</strong> ' . esc_html( $local_time ) . '</p>';
		} elseif ( $freq !== 'none' ) {
			echo '<p class="description" style="color:#d63638; margin-top:8px;"><strong><span class="dashicons dashicons-warning" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span> Figyelem:</strong> Az időzítő nem aktív. Kérlek, mentsd el a beállításokat!</p>';
		}
	}
	public function cb_retention() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		$val = $opts['backup_retention'] ?? 10;
		echo '<input type="number" name="szeducate_backup_settings[backup_retention]" value="' . esc_attr($val) . '" class="small-text" min="1" max="100" />';
		echo '<p class="description">A régebbi mentések automatikusan törlődnek a szerverről.</p>';
	}
	public function cb_cloud_desc() {
		echo '<p class="description">A Graph API összekapcsolásával minden mentés (a manuális és az automatikus is) egy másolata azonnal felkerül a megadott felhasználó OneDrive tárhelyére, a <code>SZEducate_Backups</code> mappába.</p>';
	}
	public function cb_od_tenant() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		echo '<input type="text" name="szeducate_backup_settings[onedrive_tenant_id]" value="' . esc_attr($opts['onedrive_tenant_id'] ?? '') . '" placeholder="pl: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />';
	}
	public function cb_od_client() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		echo '<input type="text" name="szeducate_backup_settings[onedrive_client_id]" value="' . esc_attr($opts['onedrive_client_id'] ?? '') . '" placeholder="pl: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />';
	}
	public function cb_od_secret() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		echo '<input type="password" name="szeducate_backup_settings[onedrive_client_secret]" value="' . esc_attr($opts['onedrive_client_secret'] ?? '') . '" placeholder="Value (Nem a Secret ID!)" />';
	}
	public function cb_od_email() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		echo '<input type="email" name="szeducate_backup_settings[onedrive_user_email]" value="' . esc_attr($opts['onedrive_user_email'] ?? '') . '" placeholder="pl. admin@sze.hu" />';
	}

	public function add_cron_schedules( $schedules ) {
		$schedules['szeducate_hourly']  = array( 'interval' => 3600, 'display' => 'Óránként' );
		$schedules['szeducate_weekly']  = array( 'interval' => 604800, 'display' => 'Hetente egyszer' );
		$schedules['szeducate_monthly'] = array( 'interval' => 2592000, 'display' => 'Havonta egyszer' );
		return $schedules;
	}

	public function setup_directory() {
		if ( ! file_exists( $this->backup_dir ) ) {
			wp_mkdir_p( $this->backup_dir );
			file_put_contents( $this->backup_dir . '.htaccess', 'deny from all' );
			file_put_contents( $this->backup_dir . 'index.php', '<?php // Silence is golden' );
		}
	}

	public function update_cron_schedule( $old_value, $new_value ) {
		$main_settings = get_option( 'szeducate_settings' );
		$mode = isset( $main_settings['mode'] ) ? $main_settings['mode'] : 'client';
		wp_clear_scheduled_hook( 'szeducate_automated_backup_cron' );

		if ( $mode === 'hub' && !empty( $new_value['backup_frequency'] ) && $new_value['backup_frequency'] !== 'none' ) {
			$hook_interval = $new_value['backup_frequency'];
			if ( $hook_interval === 'szeducate_daily_three' ) {
				$hook_interval = 'szeducate_hourly';
			}
			wp_schedule_event( time(), $hook_interval, 'szeducate_automated_backup_cron' );
		}
	}

	public function perform_automated_backup() {
		$main_settings = get_option( 'szeducate_settings' );
		if ( isset( $main_settings['mode'] ) && $main_settings['mode'] === 'hub' ) {
			$opts = get_option( 'szeducate_backup_settings', [] );
			$freq = $opts['backup_frequency'] ?? 'none';

			if ( $freq === 'szeducate_daily_three' ) {
				$current_hour = current_time( 'H' ); 
				if ( ! in_array( $current_hour, ['08', '12', '16'], true ) ) {
					return; 
				}
			}

			$this->create_backup( 'auto' );
			$this->clean_old_backups();
		}
	}

	public function handle_manual_backup() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'szeducate_manual_backup' ) ) wp_die( 'Nincs jogosultságod.' );
		$this->create_backup( 'manual' );
		$this->clean_old_backups();
		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'success_create' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_upload_backup() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'szeducate_upload_backup' ) ) wp_die( 'Nincs jogosultságod.' );
		if ( empty( $_FILES['backup_file']['name'] ) ) wp_die( 'Nem választottál ki fájlt!' );

		$file = $_FILES['backup_file'];
		$ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
		if ( strtolower( $ext ) !== 'json' ) wp_die( 'Csak .json kiterjesztésű fájlt tölthetsz fel!' );

		$this->setup_directory();
		$filename = sanitize_file_name( $file['name'] );
		$filepath = $this->backup_dir . $filename;

		if ( move_uploaded_file( $file['tmp_name'], $filepath ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'success_upload' ), admin_url( 'admin.php' ) ) );
		} else {
			wp_die( 'Hiba történt a fájl feltöltése során. Ellenőrizd a szerver jogosultságait!' );
		}
		exit;
	}

	private function create_backup( $type = 'auto' ) {
		global $wpdb;
		$this->setup_directory();

		$data = array(
			'timestamp'   => current_time('mysql'),
			'type'        => $type,
			'schema'      => json_decode( get_option( 'szeducate_local_schema', '[]' ), true ),
			'permissions' => json_decode( get_option( 'szeducate_client_permissions', '[]' ), true ),
			'clients'     => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}szeducate_clients", ARRAY_A ),
			'courses'     => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}szeducate_courses_data", ARRAY_A )
		);

		$json_data = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$json_data = json_encode( $data ); 
		}

		$filename = 'szeducate_backup_' . date('Y-m-d_H-i-s') . '_' . $type . '.json';
		$filepath = $this->backup_dir . $filename;
		file_put_contents( $filepath, $json_data );

		$this->upload_to_onedrive( $filepath, $filename );
	}

	private function upload_to_onedrive( $filepath, $filename ) {
		$opts = get_option( 'szeducate_backup_settings', [] );
		if ( empty($opts['onedrive_tenant_id']) || empty($opts['onedrive_client_id']) || empty($opts['onedrive_client_secret']) || empty($opts['onedrive_user_email']) ) return false; 

		$auth_url = "https://login.microsoftonline.com/{$opts['onedrive_tenant_id']}/oauth2/v2.0/token";
		$auth_response = wp_remote_post( $auth_url, array(
			'body' => array(
				'client_id'     => $opts['onedrive_client_id'],
				'client_secret' => $opts['onedrive_client_secret'],
				'scope'         => 'https://graph.microsoft.com/.default',
				'grant_type'    => 'client_credentials'
			)
		));

		if ( is_wp_error( $auth_response ) ) return false;
		$auth_body = json_decode( wp_remote_retrieve_body( $auth_response ), true );
		if ( empty( $auth_body['access_token'] ) ) return false;

		$upload_url = "https://graph.microsoft.com/v1.0/users/{$opts['onedrive_user_email']}/drive/root:/SZEducate_Backups/{$filename}:/content";
		$upload_response = wp_remote_request( $upload_url, array(
			'method'  => 'PUT',
			'headers' => array( 'Authorization' => 'Bearer ' . $auth_body['access_token'], 'Content-Type'  => 'application/json' ),
			'body'    => file_get_contents( $filepath ),
			'timeout' => 60 
		));

		return true;
	}

	private function clean_old_backups() {
		$opts = get_option( 'szeducate_backup_settings', [] );
		$retention = isset( $opts['backup_retention'] ) ? intval( $opts['backup_retention'] ) : 10;
		if ( $retention <= 0 ) return;

		$files = glob( $this->backup_dir . '*.json' );
		if ( count( $files ) > $retention ) {
			usort( $files, function( $a, $b ) { return filemtime( $b ) - filemtime( $a ); });
			$files_to_delete = array_slice( $files, $retention );
			foreach ( $files_to_delete as $file ) unlink( $file );
		}
	}
}