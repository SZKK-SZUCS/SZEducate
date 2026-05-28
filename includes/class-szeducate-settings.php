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
		
		// ÚJ: A szinkronizáló gomb form-kezelője
		add_action( 'admin_post_szeducate_sync_schema', array( $this, 'sync_schema_from_hub' ) );
	}

	public function add_plugin_page() {
		add_menu_page(
			'SZEducate Beállítások',
			'SZEducate',
			'manage_options',
			'szeducate-settings',
			array( $this, 'create_admin_page' ),
			'dashicons-networking',
			80
		);

		add_submenu_page(
			'szeducate-settings',
			'Beállítások',
			'Beállítások',
			'manage_options',
			'szeducate-settings',
			array( $this, 'create_admin_page' )
		);
	}

	public function create_admin_page() {
		$this->options = get_option( $this->option_name );
		?>
		<div class="wrap">
			<h1>SZEducate Architektúra Beállítások</h1>
			
			<?php 
			// ÚJ: Golyóálló visszajelzés URL paraméter alapján
			if ( isset( $_GET['sync'] ) ) {
				if ( $_GET['sync'] === 'success' ) {
					echo '<div class="notice notice-success is-dismissible"><p><strong>Siker:</strong> A séma sikeresen letöltve és a taxonómiák frissítve a Hub-ról!</p></div>';
				} elseif ( $_GET['sync'] === 'error' ) {
					$err_msg = isset($_GET['msg']) ? esc_html( urldecode($_GET['msg']) ) : 'Ismeretlen hiba';
					echo '<div class="notice notice-error is-dismissible"><p><strong>Hiba:</strong> ' . $err_msg . '</p></div>';
				}
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
				<p>Kattints az alábbi gombra, hogy a Kliens oldal letöltse a legfrissebb adatlap-felépítést (Sémát) a Hub szerverről.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="szeducate_sync_schema">
					<?php wp_nonce_field( 'szeducate_sync_schema_nonce', '_sync_nonce' ); ?>
					<button type="submit" class="button button-primary" style="background: #007cba;">⬇️ Séma Letöltése a Hub-ról</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function page_init() {
		register_setting( 'szeducate_option_group', $this->option_name, array( $this, 'sanitize' ) );

		add_settings_section( 'szeducate_main_section', 'Alapvető hálózati beállítások', array( $this, 'section_info' ), 'szeducate-settings' );
		add_settings_field( 'mode', 'Futási mód (Node típus)', array( $this, 'mode_callback' ), 'szeducate-settings', 'szeducate_main_section' );
		add_settings_field( 'hub_url', 'Hub URL (Kliens mód esetén)', array( $this, 'hub_url_callback' ), 'szeducate-settings', 'szeducate_main_section' );
		add_settings_field( 'api_token', 'API Token (Kliens mód esetén)', array( $this, 'api_token_callback' ), 'szeducate-settings', 'szeducate_main_section' );
	}

	public function sanitize( $input ) {
		$sanitized = array();
		if ( isset( $input['mode'] ) ) $sanitized['mode'] = sanitize_text_field( $input['mode'] );
		if ( isset( $input['hub_url'] ) ) $sanitized['hub_url'] = esc_url_raw( rtrim($input['hub_url'], '/') );
		if ( isset( $input['api_token'] ) ) $sanitized['api_token'] = sanitize_text_field( $input['api_token'] );
		return $sanitized;
	}

	public function section_info() { echo '<p>Válaszd ki a hálózati szerepkört.</p>'; }

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

	// ÚJ: A tényleges API hívás a Hub felé a sémáért
	public function sync_schema_from_hub() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_sync_nonce'] ) || ! wp_verify_nonce( $_POST['_sync_nonce'], 'szeducate_sync_schema_nonce' ) ) {
			wp_die( 'Biztonsági hiba.' );
		}

		$options = get_option( $this->option_name );
		$endpoint = $options['hub_url'] . '/wp-json/szeducate/v1/hub/schema';

		$response = wp_remote_get( $endpoint, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $options['api_token'] ),
			'timeout' => 15
		) );

		if ( is_wp_error( $response ) ) {
			$redirect = add_query_arg( array( 'page' => 'szeducate-settings', 'sync' => 'error', 'msg' => urlencode($response->get_error_message()) ), admin_url( 'admin.php' ) );
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code === 200 ) {
				$body = wp_remote_retrieve_body( $response );
				update_option( 'szeducate_local_schema', $body );
				
				require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
				$client = new SZEducate_Client();
				$client->register_dynamic_taxonomies();

				$redirect = add_query_arg( array( 'page' => 'szeducate-settings', 'sync' => 'success' ), admin_url( 'admin.php' ) );
			} else {
				$redirect = add_query_arg( array( 'page' => 'szeducate-settings', 'sync' => 'error', 'msg' => urlencode("Hub hiba (Kód: $code)") ), admin_url( 'admin.php' ) );
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}