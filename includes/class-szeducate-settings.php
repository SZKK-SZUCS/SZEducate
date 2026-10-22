<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Settings {

	private $option_name = 'szeducate_settings';
	private $options;
	private $page_hook_suffix;

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_post_szeducate_sync_schema', array( $this, 'sync_schema_from_hub' ) );
		add_action( 'admin_post_szeducate_full_resync', array( $this, 'handle_full_resync' ) );
		add_action( 'admin_post_szeducate_scan_stale', array( $this, 'handle_scan_stale' ) );
		add_action( 'admin_post_szeducate_prune_stale', array( $this, 'handle_prune_stale' ) );
		add_action( 'admin_post_szeducate_cleanup_orphaned_courses', array( $this, 'handle_cleanup_orphaned_courses' ) );
		add_action( 'wp_ajax_szeducate_ping_hub', array( $this, 'ajax_ping_hub' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader' ) );
	}

	// A médiatár-választót (wp.media) csak ezen az oldalon töltjük be - az "Kiemelt
	// képzések" widget alapértelmezett képéhez kell (lásd featured_default_image_callback()).
	public function enqueue_media_uploader( $hook ) {
		if ( ! $this->page_hook_suffix || $hook !== $this->page_hook_suffix ) return;
		wp_enqueue_media();
	}

	// A Kliens oldalról egy hitelesített, adatírás nélküli kérést küld a Hub felé, hogy
	// gyorsan ellenőrizhető legyen a hub_url/api_token pár érvényessége és elérhetősége.
	public function ajax_ping_hub() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Nincs jogosultságod.' );
		check_ajax_referer( 'szeducate_ping_hub', 'nonce' );

		$options = get_option( $this->option_name, array() );
		if ( empty( $options['hub_url'] ) || empty( $options['api_token'] ) ) {
			wp_send_json_error( 'A Hub URL vagy Token nincs beállítva.' );
		}

		$start = microtime( true );
		$response = wp_remote_get( rtrim( $options['hub_url'], '/' ) . '/wp-json/szeducate/v1/hub/schema', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 10,
		) );
		$elapsed_ms = round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( sprintf( 'Nem elérhető (%s)', $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			wp_send_json_success( sprintf( 'Elérhető - %d ms', $elapsed_ms ) );
		}

		wp_send_json_error( sprintf( 'HTTP %d (%d ms)', $code, $elapsed_ms ) );
	}

	public function add_plugin_page() {
		$this->options = get_option( $this->option_name );
		$mode = isset( $this->options['mode'] ) ? $this->options['mode'] : 'client';

		if ( $mode === 'hub' ) {
			add_menu_page( 'SZEducate Hub Beállítások', 'SZEducate (Hub)', 'manage_options', 'szeducate-settings', array( $this, 'create_admin_page' ), 'dashicons-networking', 55 );
			$this->page_hook_suffix = add_submenu_page( 'szeducate-settings', 'Hub Beállítások', 'Beállítások', 'manage_options', 'szeducate-settings', array( $this, 'create_admin_page' ) );
		} else {
			$this->page_hook_suffix = add_options_page( 'SZEducate Architektúra', 'SZEducate', 'manage_options', 'szeducate-settings', array( $this, 'create_admin_page' ) );
		}
	}

	public function create_admin_page() {
		$this->options = get_option( $this->option_name );
		?>
		<div class="wrap">
			<h1>SZEducate Architektúra Beállítások</h1>
			<?php
			if ( isset( $_GET['sync'] ) ) {
				if ( $_GET['sync'] === 'success' ) echo '<div class="notice notice-success is-dismissible"><p><strong>Siker:</strong> A séma letöltve!</p></div>';
				elseif ( $_GET['sync'] === 'error' ) echo '<div class="notice notice-error is-dismissible"><p><strong>Hiba:</strong> ' . esc_html( urldecode($_GET['msg']) ) . '</p></div>';
			}
			if ( isset( $_GET['resync'] ) ) {
				if ( $_GET['resync'] === 'success' ) echo '<div class="notice notice-success is-dismissible"><p><strong>Siker:</strong> Teljes szinkronizáció lefutott, ' . intval( $_GET['count'] ?? 0 ) . ' képzés feldolgozva.</p></div>';
				elseif ( $_GET['resync'] === 'error' ) echo '<div class="notice notice-error is-dismissible"><p><strong>Hiba:</strong> ' . esc_html( urldecode($_GET['msg']) ) . '</p></div>';
			}
			$this->render_prune_notice();
				if ( isset( $_GET['cleanup'] ) && $_GET['cleanup'] === 'success' ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>Siker:</strong> ' . intval( $_GET['count'] ?? 0 ) . ' elárvult Képzés-bejegyzés véglegesen törölve.</p></div>';
			}
			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'szeducate_option_group' );
				do_settings_sections( 'szeducate-settings' );
				submit_button( 'Beállítások Mentése' );
				?>
			</form>

			<?php if ( isset($this->options['mode']) && $this->options['mode'] === 'client' && !empty($this->options['hub_url']) && !empty($this->options['api_token']) ) : ?>
				<hr>
				<h2>Séma Szinkronizálása</h2>
				<p>A Kliens oldal letölti a legfrissebb adatlap-felépítést a Hub szerverről.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="szeducate_sync_schema">
					<?php wp_nonce_field( 'szeducate_sync_schema_nonce', '_sync_nonce' ); ?>
					<button type="submit" class="button button-primary">Séma Letöltése a Hub-ról</button>
				</form>

				<hr>
				<h2>Kapcsolat Tesztelése</h2>
				<p>Gyorsan ellenőrizheted, hogy a beállított Hub URL és API Token érvényes és elérhető-e, anélkül hogy bármit szinkronizálnál.</p>
				<button type="button" id="sz-ping-hub-btn" class="button">Kapcsolat Tesztelése</button>
				<span id="sz-ping-hub-result" style="margin-left:10px; font-weight:600;"></span>
				<script>
					document.getElementById('sz-ping-hub-btn').addEventListener('click', function() {
						const btn = this;
						const resultEl = document.getElementById('sz-ping-hub-result');
						btn.disabled = true;
						resultEl.style.color = '#888';
						resultEl.textContent = 'Tesztelés...';

						const body = new URLSearchParams();
						body.set('action', 'szeducate_ping_hub');
						body.set('nonce', '<?php echo esc_js( wp_create_nonce( 'szeducate_ping_hub' ) ); ?>');

						fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
							.then(r => r.json())
							.then(res => {
								resultEl.style.color = res.success ? '#46b450' : '#d63638';
								resultEl.textContent = res.data || (res.success ? 'OK' : 'Hiba');
							})
							.catch(() => {
								resultEl.style.color = '#d63638';
								resultEl.textContent = 'Hálózati hiba';
							})
							.finally(() => { btn.disabled = false; });
					});
				</script>

				<hr>
				<h2>Teljes Szinkronizáció</h2>
				<p>Ha úgy érzed, hogy a Kliens elmaradt a Hub-tól (pl. egy webhook nem érkezett meg), itt manuálisan is lekérheted a Hub-tól a sémát, a jogosultságokat, és minden olyan Képzést, amihez ez a Kliens hozzáférhet.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Ez lekéri és felülírja a helyi Képzés-adatokat a Hub aktuális állapotával. Biztosan folytatod?');">
					<input type="hidden" name="action" value="szeducate_full_resync">
					<?php wp_nonce_field( 'szeducate_full_resync_nonce', '_resync_nonce' ); ?>
					<button type="submit" class="button button-primary">Teljes Szinkronizáció Most</button>
				</form>

					<hr>
					<h2>Nem engedélyezett és elavult Képzések</h2>
					<p>A Teljes Szinkronizáció csak beír és frissít, nem töröl. Itt megkeresheted azokat a helyi Képzéseket, amelyek (a) a Hubtól kapott jogosultsági mátrix szerint már nem tartoznak ehhez a Kliens-hez, vagy (b) a Hub engedélyezett listáján nem szerepelnek. A keresés semmit nem töröl: az eredményt látod, és külön megerősítéssel törölhető. A törlés CSAK ezen a Kliensen történik, a Hub adatát nem érinti.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="szeducate_scan_stale">
						<?php wp_nonce_field( 'szeducate_scan_stale_nonce', '_scan_nonce' ); ?>
						<button type="submit" class="button">Keresés (nem töröl)</button>
					</form>

				<hr>
				<h2>Elárvult Képzés-bejegyzések</h2>
				<?php
				require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
				$orphaned_ids = ( new SZEducate_Client() )->get_orphaned_course_post_ids();
				?>
				<?php if ( empty( $orphaned_ids ) ) : ?>
					<p style="color:#46b450;">Nincs elárvult (a szinkronizált Képzés-adatokhoz már nem kapcsolódó) bejegyzés.</p>
				<?php else : ?>
					<p><strong><?php echo count( $orphaned_ids ); ?> db</strong> olyan Képzés-bejegyzés (<code>wp_posts</code>) található, amely már nem tartozik egyetlen szinkronizált Képzéshez sem - ezek jellemzően korábbi, sikertelenül párosított szinkronizációk/visszaállítások maradványai, és biztonságosan törölhetők.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Biztosan véglegesen törlöd a(z) <?php echo count( $orphaned_ids ); ?> elárvult bejegyzést? Ez nem vonható vissza!');">
						<input type="hidden" name="action" value="szeducate_cleanup_orphaned_courses">
						<?php wp_nonce_field( 'szeducate_cleanup_orphaned_nonce', '_cleanup_nonce' ); ?>
						<button type="submit" class="button" style="color:#d63638; border-color:#d63638;">Elárvult Bejegyzések Törlése (<?php echo count( $orphaned_ids ); ?> db)</button>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public function page_init() {
		register_setting( 'szeducate_option_group', $this->option_name, array( $this, 'sanitize' ) );

		add_settings_section( 'szeducate_main_section', 'Alapvető hálózati beállítások', null, 'szeducate-settings' );
		add_settings_field( 'mode', 'Futási mód (Node típus)', array( $this, 'mode_callback' ), 'szeducate-settings', 'szeducate_main_section' );
		add_settings_field( 'hub_url', 'Hub URL (Kliens esetén)', array( $this, 'hub_url_callback' ), 'szeducate-settings', 'szeducate_main_section' );
		add_settings_field( 'api_token', 'API Token (Kliens esetén)', array( $this, 'api_token_callback' ), 'szeducate-settings', 'szeducate_main_section' );

		add_settings_section( 'szeducate_featured_section', 'Kiemelt Képzések widget', null, 'szeducate-settings' );
		add_settings_field( 'featured_default_image', 'Alapértelmezett kép (kép nélküli képzésekhez)', array( $this, 'featured_default_image_callback' ), 'szeducate-settings', 'szeducate_featured_section' );

		add_settings_section( 'szeducate_uninstall_section', 'Eltávolítás', null, 'szeducate-settings' );
		add_settings_field( 'purge_on_uninstall', 'Adatok törlése a plugin eltávolításakor', array( $this, 'purge_on_uninstall_callback' ), 'szeducate-settings', 'szeducate_uninstall_section' );
	}

	public function sanitize( $input ) {
		$sanitized = array();
		if ( isset( $input['mode'] ) ) $sanitized['mode'] = sanitize_text_field( $input['mode'] );
		if ( isset( $input['hub_url'] ) ) $sanitized['hub_url'] = esc_url_raw( rtrim($input['hub_url'], '/') );
		if ( isset( $input['api_token'] ) ) $sanitized['api_token'] = sanitize_text_field( $input['api_token'] );
		$sanitized['featured_default_image_id'] = isset( $input['featured_default_image_id'] ) ? intval( $input['featured_default_image_id'] ) : 0;
		$sanitized['purge_on_uninstall'] = ! empty( $input['purge_on_uninstall'] );
		return $sanitized;
	}

	// A "Kiemelt Képzések" widget ezt a képet mutatja azoknál a képzéseknél, amiknek
	// nincs saját kiemelt képe (has_post_thumbnail() hamis) - a cím ilyenkor is
	// rákerül overlay-ként, mint a rendes fotóknál.
	public function featured_default_image_callback() {
		$image_id  = ! empty( $this->options['featured_default_image_id'] ) ? intval( $this->options['featured_default_image_id'] ) : 0;
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div id="szeducate-featured-default-image-wrap">
			<img id="szeducate-featured-default-image-preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width:200px; max-height:120px; display:<?php echo $image_url ? 'block' : 'none'; ?>; object-fit:cover; margin-bottom:8px; border:1px solid #dcdcde; border-radius:4px;">
			<input type="hidden" id="szeducate-featured-default-image-id" name="szeducate_settings[featured_default_image_id]" value="<?php echo esc_attr( (string) $image_id ); ?>">
			<br>
			<button type="button" class="button" id="szeducate-featured-default-image-select">Kép kiválasztása</button>
			<button type="button" class="button" id="szeducate-featured-default-image-remove" style="<?php echo $image_id ? '' : 'display:none;'; ?>">Eltávolítás</button>
			<p class="description">Csak azoknál a képzéseknél jelenik meg, amikhez a séma "Kép" mezője nincs kitöltve.</p>
		</div>
		<script type="text/javascript">
			(function() {
				var frame;
				var selectBtn = document.getElementById('szeducate-featured-default-image-select');
				var removeBtn = document.getElementById('szeducate-featured-default-image-remove');
				var idInput   = document.getElementById('szeducate-featured-default-image-id');
				var preview   = document.getElementById('szeducate-featured-default-image-preview');

				selectBtn.addEventListener('click', function(e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({
						title: 'Alapértelmezett kép kiválasztása',
						button: { text: 'Kiválasztás' },
						library: { type: 'image' },
						multiple: false
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						idInput.value = attachment.id;
						preview.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
						preview.style.display = 'block';
						removeBtn.style.display = 'inline-block';
					});
					frame.open();
				});

				removeBtn.addEventListener('click', function(e) {
					e.preventDefault();
					idInput.value = '';
					preview.style.display = 'none';
					removeBtn.style.display = 'none';
				});
			})();
		</script>
		<?php
	}

	public function mode_callback() {
		$mode = isset( $this->options['mode'] ) ? $this->options['mode'] : 'client';
		?>
		<select name="szeducate_settings[mode]" id="mode">
			<option value="client" <?php selected( $mode, 'client' ); ?>>Kliens (Megjelenítés és szerkesztés)</option>
			<option value="hub" <?php selected( $mode, 'hub' ); ?>>Hub (Központi adatszerver)</option>
		</select>
		<?php
	}

	public function hub_url_callback() {
		$hub_url = isset( $this->options['hub_url'] ) ? $this->options['hub_url'] : '';
		printf( '<input type="url" id="hub_url" name="szeducate_settings[hub_url]" value="%s" class="regular-text" />', esc_attr( $hub_url ) );
	}

	public function api_token_callback() {
		$api_token = isset( $this->options['api_token'] ) ? $this->options['api_token'] : '';
		printf( '<input type="password" id="api_token" name="szeducate_settings[api_token]" value="%s" class="regular-text" />', esc_attr( $api_token ) );
	}

	public function purge_on_uninstall_callback() {
		$checked = ! empty( $this->options['purge_on_uninstall'] );
		?>
		<label>
			<input type="checkbox" name="szeducate_settings[purge_on_uninstall]" value="1" <?php checked( $checked ); ?>
				onclick="if (this.checked && !confirm('Biztosan bekapcsolod? Ha ez be van jelölve MENTÉSKOR, akkor a plugin törlésekor (Beépülő modulok > Törlés) MINDEN SZEducate adat véglegesen törlődik: az egyedi adatbázis-táblák (Képzések, Kliensek), és minden beállítás. Ez nem vonható vissza.')) { this.checked = false; }">
			Törléskor az egyedi táblák (Képzések, Kliensek) és az összes beállítás véglegesen törlődjön
		</label>
		<p class="description">Alapértelmezetten (kikapcsolva) a plugin eltávolítása megőrzi az adatokat - így egy véletlen törlés/újratelepítés nem okoz adatvesztést. Csak akkor kapcsold be, ha ezt a telepítést véglegesen le akarod bontani.</p>
		<?php
	}

	public function sync_schema_from_hub() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_sync_nonce'] ) || ! wp_verify_nonce( $_POST['_sync_nonce'], 'szeducate_sync_schema_nonce' ) ) wp_die( 'Biztonsági hiba.' );

		$options = get_option( $this->option_name );
		$result = $this->pull_schema_and_permissions( $options );

		if ( $result !== true ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'sync' => 'error', 'msg' => urlencode( $result ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'sync' => 'success' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	// Séma + jogosultságok letöltése a Hub-ról. Ezt használja mind a "Séma Szinkronizálása",
	// mind a "Teljes Szinkronizáció" gomb, hogy a séma-oldali logika egy helyen éljen.
	// Visszatérési érték: true sikeres esetben, vagy egy hibaüzenet string.
	private function pull_schema_and_permissions( $options ) {
		$response = wp_remote_get( $options['hub_url'] . '/wp-json/szeducate/v1/hub/schema', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 15
		) );

		if ( is_wp_error( $response ) ) return $response->get_error_message();

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) return "Hub hiba (Kód: $code)";

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['schema'] ) ) {
			update_option( 'szeducate_local_schema', wp_json_encode( $data['schema'], JSON_UNESCAPED_UNICODE ) );

			require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
			SZEducate_Activator::update_database_schema();
		}
		if ( isset( $data['permissions'] ) ) {
			update_option( 'szeducate_client_permissions', wp_json_encode( $data['permissions'], JSON_UNESCAPED_UNICODE ) );
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		SZEducate_Client::reset_local_conditions_cache();
		$client = new SZEducate_Client();
		$client->register_dynamic_taxonomies();

		return true;
	}

	public function handle_full_resync() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_resync_nonce'] ) || ! wp_verify_nonce( $_POST['_resync_nonce'], 'szeducate_full_resync_nonce' ) ) wp_die( 'Biztonsági hiba.' );

		@set_time_limit( 0 );
		$options = get_option( $this->option_name );

		if ( empty( $options['hub_url'] ) || empty( $options['api_token'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'resync' => 'error', 'msg' => urlencode( 'A Hub URL vagy Token nincs beállítva.' ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$schema_result = $this->pull_schema_and_permissions( $options );
		if ( $schema_result !== true ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'resync' => 'error', 'msg' => urlencode( 'Séma letöltése sikertelen: ' . $schema_result ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$response = wp_remote_get( $options['hub_url'] . '/wp-json/szeducate/v1/hub/courses-mine', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 60
		) );

		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'resync' => 'error', 'msg' => urlencode( 'Képzések lekérése sikertelen: ' . $response->get_error_message() ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'resync' => 'error', 'msg' => urlencode( "Hub hiba a képzések lekérésekor (Kód: $code)" ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$courses = isset( $body['courses'] ) && is_array( $body['courses'] ) ? $body['courses'] : array();

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client-api.php';
		$client_api = new SZEducate_Client_API();

		$synced = 0;
		foreach ( $courses as $c ) {
			if ( empty( $c['hub_id'] ) || ! isset( $c['title'] ) || ! isset( $c['course_data'] ) ) continue;
			$client_api->update_local_course_from_hub(
				intval( $c['hub_id'] ),
				sanitize_text_field( $c['title'] ),
				is_array( $c['course_data'] ) ? $c['course_data'] : array()
			);
			$synced++;
		}

		// A szinkron után rögtön megkeressük (de NEM töröljük) a nem engedélyezett / elavult
		// helyi Képzéseket - a törlés külön, megerősített lépés.
		$hub_list = array( 'ok' => ! empty( $body['success'] ), 'courses' => $courses, 'error' => '' );
		$stale    = $this->collect_stale_courses( $hub_list );
		$args     = array( 'page' => 'szeducate-settings', 'resync' => 'success', 'count' => $synced );
		$args     = array_merge( $args, $this->store_stale_scan( $stale ) );

		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	private function stale_transient_key() {
		return 'szeducate_stale_scan_' . get_current_user_id();
	}

	// A Hub engedélyezett képzés-listája (courses-mine). 'ok' csak akkor igaz, ha a válasz
	// érvényes és sikeres - egy hibás/üres válasz alapján SOHA nem szabad törölni.
	private function fetch_hub_course_list() {
		$options = get_option( $this->option_name );
		if ( empty( $options['hub_url'] ) || empty( $options['api_token'] ) ) {
			return array( 'ok' => false, 'courses' => array(), 'error' => 'A Hub URL vagy Token nincs beállítva.' );
		}

		$response = wp_remote_get( $options['hub_url'] . '/wp-json/szeducate/v1/hub/courses-mine', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'courses' => array(), 'error' => $response->get_error_message() );
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array( 'ok' => false, 'courses' => array(), 'error' => 'Hub hiba (Kód: ' . wp_remote_retrieve_response_code( $response ) . ')' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) || ! isset( $body['courses'] ) || ! is_array( $body['courses'] ) ) {
			return array( 'ok' => false, 'courses' => array(), 'error' => 'Érvénytelen válasz a Hubtól.' );
		}

		return array( 'ok' => true, 'courses' => $body['courses'], 'error' => '' );
	}

	// Megkeresi a helyi Képzések közül a nem engedélyezetteket / elavultakat. NEM töröl.
	//  (a) a Hubtól kapott mátrix szerint nem engedélyezett (helyi, offline kiértékelés)
	//  (b) hub_id-val rendelkezik, de a Hub aktuális engedélyezett listáján nincs rajta
	// A (b) ellenőrzés csak érvényes, NEM üres Hub-válasznál fut - üres/hibás válasz alapján
	// különben minden helyi képzés "elavultnak" látszana.
	private function collect_stale_courses( $hub_list = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		SZEducate_Client::reset_local_conditions_cache();
		$conditions = SZEducate_Client::get_local_conditions();

		if ( $conditions === null ) {
			return array( 'error' => 'A helyi jogosultsági feltétel sérült - előbb töltsd le újra a Hubról (Séma Letöltése), és csak utána keress.', 'items' => array(), 'notice' => '' );
		}

		if ( $hub_list === null ) {
			$hub_list = $this->fetch_hub_course_list();
		}

		$hub_ids = array();
		$hub_checked = false;
		$notice = '';
		if ( ! empty( $hub_list['ok'] ) ) {
			foreach ( $hub_list['courses'] as $c ) {
				if ( ! empty( $c['hub_id'] ) ) {
					$hub_ids[ intval( $c['hub_id'] ) ] = true;
				}
			}
			$hub_checked = ! empty( $hub_ids );
			if ( ! $hub_checked ) {
				$notice = 'A Hub üres képzés-listát adott, ezért a "nincs a Hub listáján" ellenőrzést biztonsági okból kihagytam.';
			}
		} else {
			$notice = 'A Hub listája nem volt elérhető' . ( ! empty( $hub_list['error'] ) ? ' (' . $hub_list['error'] . ')' : '' ) . ', ezért csak a helyi mátrix szerinti ellenőrzés futott le.';
		}

		$rows = $wpdb->get_results( "SELECT id, hub_id, local_post_id, title, course_data FROM {$table_name}", ARRAY_A );
		$items = array();
		foreach ( $rows as $row ) {
			$data = json_decode( $row['course_data'], true );
			$reason = '';

			if ( ! SZEducate_Conditions::permits( $conditions, is_array( $data ) ? $data : array() ) ) {
				$reason = 'nem engedélyezett a jogosultsági mátrix szerint';
			} elseif ( $hub_checked && intval( $row['hub_id'] ) > 0 && ! isset( $hub_ids[ intval( $row['hub_id'] ) ] ) ) {
				$reason = 'nincs a Hub engedélyezett listáján';
			}

			if ( $reason !== '' ) {
				$items[] = array(
					'id'            => intval( $row['id'] ),
					'hub_id'        => intval( $row['hub_id'] ),
					'local_post_id' => intval( $row['local_post_id'] ),
					'title'         => $row['title'],
					'reason'        => $reason,
				);
			}
		}

		return array( 'error' => '', 'items' => $items, 'notice' => $notice );
	}

	// Elmenti a keresés eredményét (a megerősítéshez), és visszaadja az átirányítás paramétereit.
	private function store_stale_scan( $stale ) {
		if ( ! empty( $stale['error'] ) ) {
			delete_transient( $this->stale_transient_key() );
			return array( 'prune' => 'error', 'msg' => urlencode( $stale['error'] ) );
		}

		if ( empty( $stale['items'] ) ) {
			delete_transient( $this->stale_transient_key() );
			return array( 'prune' => 'none', 'note' => urlencode( $stale['notice'] ) );
		}

		set_transient( $this->stale_transient_key(), $stale, 30 * MINUTE_IN_SECONDS );
		return array( 'prune' => 'found', 'found' => count( $stale['items'] ) );
	}

	public function handle_scan_stale() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_scan_nonce'] ) || ! wp_verify_nonce( $_POST['_scan_nonce'], 'szeducate_scan_stale_nonce' ) ) wp_die( 'Biztonsági hiba.' );

		$options = get_option( $this->option_name );
		$schema_result = $this->pull_schema_and_permissions( $options );
		if ( $schema_result !== true ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'prune' => 'error', 'msg' => urlencode( 'Séma/jogosultságok letöltése sikertelen: ' . $schema_result ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$args = array_merge( array( 'page' => 'szeducate-settings' ), $this->store_stale_scan( $this->collect_stale_courses() ) );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	// A megerősített törlés. A keresés után eltelt idő alatt változhatott az állapot, ezért
	// MINDEN sort újra ellenőrzünk (friss mátrix + friss Hub-lista), és csak azt töröljük, ami
	// a keresésben szerepelt ÉS most is nem engedélyezett/elavult.
	public function handle_prune_stale() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_prune_nonce'] ) || ! wp_verify_nonce( $_POST['_prune_nonce'], 'szeducate_prune_stale_nonce' ) ) wp_die( 'Biztonsági hiba.' );

		$scan = get_transient( $this->stale_transient_key() );
		if ( ! is_array( $scan ) || empty( $scan['items'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'prune' => 'error', 'msg' => urlencode( 'A keresés eredménye lejárt - futtasd újra a keresést.' ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$options = get_option( $this->option_name );
		$schema_result = $this->pull_schema_and_permissions( $options );
		if ( $schema_result !== true ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'prune' => 'error', 'msg' => urlencode( 'Séma/jogosultságok letöltése sikertelen: ' . $schema_result ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$fresh = $this->collect_stale_courses();
		if ( ! empty( $fresh['error'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'prune' => 'error', 'msg' => urlencode( $fresh['error'] ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$confirmed_ids = array();
		foreach ( $scan['items'] as $item ) {
			$confirmed_ids[ $item['id'] ] = true;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$deleted = 0;

		foreach ( $fresh['items'] as $item ) {
			if ( ! isset( $confirmed_ids[ $item['id'] ] ) ) continue;

			// FONTOS: a helyi törlés NEM mehet vissza a Hubra (process_course_deletion alapból
			// DELETE-et küldene a Hubnak) - a "szinkron-törlés" jelző ezt kikapcsolja. A sor
			// törlését ilyenkor nekünk kell elvégeznünk.
			global $szeducate_is_sync_deleting;
			$szeducate_is_sync_deleting = true;
			if ( $item['local_post_id'] > 0 ) {
				wp_delete_post( $item['local_post_id'], true );
			}
			$wpdb->delete( $table_name, array( 'id' => $item['id'] ) );
			$szeducate_is_sync_deleting = false;
			$deleted++;
		}

		SZEducate_Client::invalidate_courses_cache();
		delete_transient( $this->stale_transient_key() );

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'prune' => 'done', 'count' => $deleted ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	// A keresés / törlés eredményének megjelenítése a beállítások oldalon.
	private function render_prune_notice() {
		if ( ! isset( $_GET['prune'] ) ) return;

		$state = sanitize_key( $_GET['prune'] );

		if ( $state === 'error' ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Hiba:</strong> ' . esc_html( urldecode( isset( $_GET['msg'] ) ? wp_unslash( $_GET['msg'] ) : '' ) ) . '</p></div>';
		} elseif ( $state === 'none' ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Rendben:</strong> nincs nem engedélyezett vagy elavult helyi Képzés.</p>';
			if ( ! empty( $_GET['note'] ) ) {
				echo '<p>' . esc_html( urldecode( wp_unslash( $_GET['note'] ) ) ) . '</p>';
			}
			echo '</div>';
		} elseif ( $state === 'done' ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Siker:</strong> ' . intval( $_GET['count'] ?? 0 ) . ' nem engedélyezett/elavult helyi Képzés törölve (a Hub adata érintetlen).</p></div>';
		} elseif ( $state === 'found' ) {
			$scan = get_transient( $this->stale_transient_key() );
			if ( ! is_array( $scan ) || empty( $scan['items'] ) ) return;

			$items = $scan['items'];
			echo '<div class="notice notice-warning"><p><strong>' . count( $items ) . ' db</strong> nem engedélyezett vagy elavult helyi Képzés található (a keresés semmit nem törölt):</p><ul style="list-style:disc; margin-left:20px;">';
			foreach ( array_slice( $items, 0, 20 ) as $item ) {
				echo '<li>' . esc_html( $item['title'] ) . ' &ndash; <em>' . esc_html( $item['reason'] ) . '</em></li>';
			}
			if ( count( $items ) > 20 ) {
				echo '<li>&hellip; és még ' . ( count( $items ) - 20 ) . ' db</li>';
			}
			echo '</ul>';
			if ( ! empty( $scan['notice'] ) ) {
				echo '<p><em>' . esc_html( $scan['notice'] ) . '</em></p>';
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Biztosan törlöd a(z) <?php echo count( $items ); ?> helyi Képzést erről a Kliensről? A Hub adata nem változik, de a helyi bejegyzések véglegesen törlődnek.');">
				<input type="hidden" name="action" value="szeducate_prune_stale">
				<?php wp_nonce_field( 'szeducate_prune_stale_nonce', '_prune_nonce' ); ?>
				<button type="submit" class="button" style="color:#d63638; border-color:#d63638;">Törlés ezen a Kliensen (<?php echo count( $items ); ?> db)</button>
			</form>
			<p></p></div>
			<?php
		}
	}

	public function handle_cleanup_orphaned_courses() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_cleanup_nonce'] ) || ! wp_verify_nonce( $_POST['_cleanup_nonce'], 'szeducate_cleanup_orphaned_nonce' ) ) wp_die( 'Biztonsági hiba.' );

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$orphaned_ids = ( new SZEducate_Client() )->get_orphaned_course_post_ids();

		$deleted = 0;
		foreach ( $orphaned_ids as $post_id ) {
			if ( wp_delete_post( $post_id, true ) ) $deleted++;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'cleanup' => 'success', 'count' => $deleted ), admin_url( 'options-general.php' ) ) );
		exit;
	}
}