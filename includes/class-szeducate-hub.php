<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Hub {

	private $option_name = 'szeducate_api_tokens';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_token_page' ) );
		add_action( 'admin_init', array( $this, 'handle_token_generation' ) );
		add_action( 'admin_init', array( $this, 'handle_token_deletion' ) );
	}

	public function add_token_page() {
		add_submenu_page(
			'szeducate-settings',
			'API Tokenek',
			'API Tokenek',
			'manage_options',
			'szeducate-tokens',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		$tokens = get_option( $this->option_name, array() );
		?>
		<div class="wrap">
			<h1>Kliens API Tokenek Kezelése</h1>
			<p>Itt hozhatsz létre hozzáférési tokeneket a Kliens (Spoke) oldalak számára.</p>

			<?php
			// Ha most generáltunk egy tokent, azt csak egyszer mutatjuk meg!
			$new_token = get_transient( 'szeducate_new_token_display' );
			if ( $new_token ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>ÚJ TOKEN GENERÁLVA!</strong> Kérlek, másold ki most, mert biztonsági okokból többé nem lesz látható:</p><p><code style="font-size:16px;">' . esc_html( $new_token ) . '</code></p></div>';
				delete_transient( 'szeducate_new_token_display' );
			}
			?>

			<div class="card" style="max-width: 400px; padding: 20px; margin-top: 20px;">
				<h3>Új Kliens Token Generálása</h3>
				<form method="post" action="">
					<?php wp_nonce_field( 'generate_szeducate_token', 'szeducate_token_nonce' ); ?>
					<p>
						<label for="client_name">Kliens azonosító neve (pl. Műszaki Kar):</label><br>
						<input type="text" name="client_name" id="client_name" required class="regular-text">
					</p>
					<p>
						<input type="submit" name="generate_token" class="button button-primary" value="Token Generálása">
					</p>
				</form>
			</div>

			<h3 style="margin-top: 40px;">Aktív Tokenek</h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Kliens Neve</th>
						<th>Létrehozva</th>
						<th>Token Hash (SHA-256)</th>
						<th>Művelet</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $tokens ) ) : ?>
						<tr><td colspan="4">Nincsenek aktív tokenek.</td></tr>
					<?php else : ?>
						<?php foreach ( $tokens as $id => $data ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $data['name'] ); ?></strong></td>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $data['created'] ) ); ?></td>
								<td><code style="font-size:11px;"><?php echo esc_html( $data['hash'] ); ?></code></td>
								<td>
									<form method="post" action="" onsubmit="return confirm('Biztosan törlöd a tokent? A kliens azonnal elveszíti a kapcsolatot!');">
										<?php wp_nonce_field( 'delete_szeducate_token_' . $id, 'szeducate_del_token_nonce' ); ?>
										<input type="hidden" name="token_id" value="<?php echo esc_attr( $id ); ?>">
										<input type="submit" name="delete_token" class="button button-link-delete" value="Visszavonás" style="color: #a00;">
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_token_generation() {
		if ( ! isset( $_POST['generate_token'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['szeducate_token_nonce'] ) || ! wp_verify_nonce( $_POST['szeducate_token_nonce'], 'generate_szeducate_token' ) ) {
			wp_die( 'Biztonsági hiba.' );
		}

		$client_name = sanitize_text_field( $_POST['client_name'] );
		if ( empty( $client_name ) ) return;

		// Token generálása (64 karakteres random string)
		$raw_token = bin2hex( random_bytes( 32 ) );
		$hashed_token = hash( 'sha256', $raw_token );
		$token_id = uniqid();

		$tokens = get_option( $this->option_name, array() );
		$tokens[ $token_id ] = array(
			'name'    => $client_name,
			'hash'    => $hashed_token,
			'created' => time(),
		);

		update_option( $this->option_name, $tokens );

		// A nyílt tokent egy transient-be tesszük 60 másodpercre, hogy a render_page ki tudja írni
		set_transient( 'szeducate_new_token_display', $raw_token, 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=szeducate-tokens' ) );
		exit;
	}

	public function handle_token_deletion() {
		if ( ! isset( $_POST['delete_token'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$token_id = sanitize_text_field( $_POST['token_id'] );
		if ( ! isset( $_POST['szeducate_del_token_nonce'] ) || ! wp_verify_nonce( $_POST['szeducate_del_token_nonce'], 'delete_szeducate_token_' . $token_id ) ) {
			wp_die( 'Biztonsági hiba.' );
		}

		$tokens = get_option( $this->option_name, array() );
		if ( isset( $tokens[ $token_id ] ) ) {
			unset( $tokens[ $token_id ] );
			update_option( $this->option_name, $tokens );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=szeducate-tokens' ) );
		exit;
	}
}