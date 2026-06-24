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

		$backup_courses = isset($data['courses']) ? $data['courses'] : [];
		
		$current_unmatched = [];
		foreach ($current_courses_raw as $c) $current_unmatched[$c['id']] = $c;

		$backup_unmatched = [];
		foreach ($backup_courses as $c) $backup_unmatched[$c['id']] = $c;

		$paired_courses = []; 

		foreach ($backup_unmatched as $b_id => $bc) {
			if (isset($current_unmatched[$b_id])) {
				$paired_courses[] = ['backup' => $bc, 'current' => $current_unmatched[$b_id]];
				unset($backup_unmatched[$b_id]);
				unset($current_unmatched[$b_id]);
			}
		}

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

			if ( $bc['title'] !== $cc['title'] ) {
				$changes['__sz_title__'] = [
					'label'   => 'Cím (Szak megnevezése)',
					'current' => $cc['title'],
					'backup'  => $bc['title']
				];
			}

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

		<div id="sz-restore-loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(240,246,252,0.9); z-index:999999; flex-direction:column; align-items:center; justify-content:center;">
			<span class="dashicons dashicons-update" style="font-size: 60px; width: 60px; height: 60px; color: #2271b1; animation: sz-spin 2s linear infinite;"></span>
			<h2 style="margin-top: 30px; color: #1d2327;">Visszaállítás és Szinkronizáció folyamatban...</h2>
			<p style="font-size: 16px; color: #50575e;">Kérjük, ne zárd be és ne frissítsd az oldalt!</p>
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
				<?php wp_nonce_field( 'szeducate_restore_backup' ); ?>

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

				<div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
					<h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;"><span class="dashicons dashicons-feedback"></span> Képzések Adatbázisa</h3>
					
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
						<button type="submit" class="button button-primary button-large" style="background: #d63638; border-color: #d63638;" onclick="if(confirm('Biztosan végrehajtod a visszaállítást a kiválasztott adatokkal?')) { document.getElementById('sz-restore-loader').style.display='flex'; return true; } else { return false; }">
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

	public function handle_restore_backup() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'szeducate_restore_backup' ) ) wp_die( 'Nincs jogosultságod.' );
		
		$filename = basename( sanitize_text_field( $_POST['file'] ) );
		$filepath = $this->backup_dir . $filename;
		if ( ! file_exists( $filepath ) ) wp_die( 'A fájl nem található.' );

		$content = file_get_contents( $filepath );
		$data = json_decode( $content, true );
		if ( ! is_array( $data ) || ! isset( $data['schema'], $data['clients'], $data['courses'] ) ) wp_die( 'Érvénytelen fájl.' );

		@ini_set( 'memory_limit', '256M' );
		global $wpdb;
		$courses_table = $wpdb->prefix . 'szeducate_courses_data';
		$clients_table = $wpdb->prefix . 'szeducate_clients';

		if ( !empty($_POST['restore_schema']) ) {
			update_option( 'szeducate_local_schema', wp_json_encode( $data['schema'], JSON_UNESCAPED_UNICODE ) );
		}
		if ( !empty($_POST['restore_permissions']) ) {
			update_option( 'szeducate_client_permissions', wp_json_encode( $data['permissions'], JSON_UNESCAPED_UNICODE ) );
		}

		$backup_clients_assoc = []; foreach($data['clients'] as $c) $backup_clients_assoc[$c['id']] = $c;
		
		if ( !empty($_POST['restore_clients']['insert']) ) {
			foreach ( $_POST['restore_clients']['insert'] as $cid ) {
				if ( isset($backup_clients_assoc[$cid]) ) $wpdb->insert( $clients_table, $backup_clients_assoc[$cid] );
			}
		}
		if ( !empty($_POST['restore_clients']['update']) ) {
			foreach ( $_POST['restore_clients']['update'] as $cid ) {
				if ( isset($backup_clients_assoc[$cid]) ) $wpdb->update( $clients_table, $backup_clients_assoc[$cid], ['id' => $cid] );
			}
		}

		$backup_courses_assoc = []; foreach($data['courses'] as $c) $backup_courses_assoc[$c['id']] = $c;
		$current_courses_raw = $wpdb->get_results( "SELECT * FROM $courses_table", ARRAY_A );
		$current_assoc = []; foreach ($current_courses_raw as $c) $current_assoc[$c['id']] = $c;

		$courses_to_delete = $_POST['restore_courses']['delete'] ?? [];
		$courses_to_insert = $_POST['restore_courses']['insert'] ?? [];
		$courses_to_update = $_POST['restore_courses']['update'] ?? [];

		$hub_ids_to_sync = [];
		$hub_ids_to_delete = [];

		foreach ( $courses_to_delete as $cid ) {
			$cid = intval($cid);
			$wpdb->delete( $courses_table, ['id' => $cid] );
			$hub_ids_to_delete[] = $cid;
		}

		foreach ( $courses_to_insert as $backup_id ) {
			$backup_id = intval($backup_id);
			if ( isset($backup_courses_assoc[$backup_id]) ) {
				$insert_data = $backup_courses_assoc[$backup_id];
				unset($insert_data['id']);
				
				$wpdb->insert( $courses_table, $insert_data );
				$hub_ids_to_sync[] = $wpdb->insert_id;
			}
		}

		if ( !empty($courses_to_update) ) {
			$schema_json = get_option( 'szeducate_local_schema', '[]' );
			$schema = json_decode( $schema_json, true );
			$existing_columns = $wpdb->get_col( "DESCRIBE $courses_table", 0 );

			foreach ( $courses_to_update as $combined_id => $fields ) {
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

					$wpdb->update( $courses_table, $db_data, ['id' => $current_id] );
					$hub_ids_to_sync[] = $current_id;
				}
			}
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$clients_table}'" ) == $clients_table ) {
			$all_clients = $wpdb->get_results( "SELECT client_url FROM {$clients_table} WHERE client_url != ''" );
			
			foreach ( $all_clients as $c ) {
				$base_webhook_url = rtrim( $c->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync-course';
				
				foreach ( $hub_ids_to_sync as $h_id ) {
					wp_remote_post( $base_webhook_url, [
						'blocking' => false, 'timeout' => 5,
						'body' => wp_json_encode( ['hub_id' => $h_id] ),
						'headers' => ['Content-Type' => 'application/json']
					] );
				}
				
				foreach ( $hub_ids_to_delete as $h_id ) {
					wp_remote_request( $base_webhook_url . '/' . $h_id, [
						'method' => 'DELETE', 'blocking' => false, 'timeout' => 5,
						'headers' => ['Content-Type' => 'application/json']
					] );
				}
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-backups', 'backup_msg' => 'success_restore' ), admin_url( 'admin.php' ) ) );
		exit;
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
				'time'     => wp_date( 'Y. m. d. H:i:s', filemtime( $file ) )
			);
		}
		return $results;
	}
}