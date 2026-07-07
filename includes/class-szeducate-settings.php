<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Settings {

	private $option_name = 'szeducate_settings';
	private $options;

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_post_szeducate_sync_schema', array( $this, 'sync_schema_from_hub' ) );
		add_action( 'admin_post_szeducate_full_resync', array( $this, 'handle_full_resync' ) );
		add_action( 'admin_post_szeducate_cleanup_orphaned_courses', array( $this, 'handle_cleanup_orphaned_courses' ) );
	}

	public function add_plugin_page() {
		$this->options = get_option( $this->option_name );
		$mode = isset( $this->options['mode'] ) ? $this->options['mode'] : 'client';

		if ( $mode === 'hub' ) {
			add_menu_page( 'SZEducate Hub Beállítások', 'SZEducate (Hub)', 'manage_options', 'szeducate-settings', array( $this, 'create_admin_page' ), 'dashicons-networking', 55 );
			add_submenu_page( 'szeducate-settings', 'Hub Beállítások', 'Beállítások', 'manage_options', 'szeducate-settings', array( $this, 'create_admin_page' ) );
		} else {
			add_options_page( 'SZEducate Architektúra', 'SZEducate', 'manage_options', 'szeducate-settings', array( $this, 'create_admin_page' ) );
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
				<h2>Teljes Szinkronizáció</h2>
				<p>Ha úgy érzed, hogy a Kliens elmaradt a Hub-tól (pl. egy webhook nem érkezett meg), itt manuálisan is lekérheted a Hub-tól a sémát, a jogosultságokat, és minden olyan Képzést, amihez ez a Kliens hozzáférhet.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Ez lekéri és felülírja a helyi Képzés-adatokat a Hub aktuális állapotával. Biztosan folytatod?');">
					<input type="hidden" name="action" value="szeducate_full_resync">
					<?php wp_nonce_field( 'szeducate_full_resync_nonce', '_resync_nonce' ); ?>
					<button type="submit" class="button button-primary">Teljes Szinkronizáció Most</button>
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
	}

	public function sanitize( $input ) {
		$sanitized = array();
		if ( isset( $input['mode'] ) ) $sanitized['mode'] = sanitize_text_field( $input['mode'] );
		if ( isset( $input['hub_url'] ) ) $sanitized['hub_url'] = esc_url_raw( rtrim($input['hub_url'], '/') );
		if ( isset( $input['api_token'] ) ) $sanitized['api_token'] = sanitize_text_field( $input['api_token'] );
		return $sanitized;
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

		wp_safe_redirect( add_query_arg( array( 'page' => 'szeducate-settings', 'resync' => 'success', 'count' => $synced ), admin_url( 'options-general.php' ) ) );
		exit;
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