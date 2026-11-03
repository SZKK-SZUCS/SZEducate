<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'SZEDUCATE_CONTACT_LINK_TEST' ) ) {
	exit;
}

// Kapcsolattartó-adatok (e-mail, telefonszám) hivatkozássá alakítása + a "másolás a
// vágólapra" ikon. A Dynamic Tag-ek (Képzés Adat / Képzés Link-URL) használják közösen.
//
// Miért kell: az esc_url() a séma nélküli értékhez ("a@b.hu", "+36 30 123 4567") automatikusan
// "http://"-t fűz. Az Elementor "Előtte" (before) mezőjébe írt "mailto:" / "tel:" így
// "mailto:http://a@b.hu" / "tel:http://+3630..." lett, ami nem működő hivatkozás.
class SZEducate_Contact_Link {

	/**
	 * Egy érték besorolása: 'email' | 'tel' | 'url'.
	 * 'email' / 'tel' esetén a 'value' a SÉMA NÉLKÜLI, tisztított érték, a 'scheme' a hozzá
	 * való előtag. 'url' esetén a 'value' az eredeti (trimmelt) szöveg - azt az esc_url intézi.
	 *
	 * @param mixed $raw
	 * @return array{kind:string,value:string,scheme:string}
	 */
	public static function classify( $raw ) {
		$v = is_string( $raw ) || is_int( $raw ) || is_float( $raw ) ? trim( (string) $raw ) : '';

		// Már sémával ellátott érték (mailto: / tel: / https: ...) - érintetlenül hagyjuk.
		if ( $v === '' || preg_match( '/^[a-z][a-z0-9+.\-]*:/i', $v ) ) {
			return array( 'kind' => 'url', 'value' => $v, 'scheme' => '' );
		}

		if ( filter_var( $v, FILTER_VALIDATE_EMAIL ) ) {
			return array( 'kind' => 'email', 'value' => $v, 'scheme' => 'mailto:' );
		}

		// Telefonszám: csak számjegy és a szokásos tagolók, legalább 6 számjegy. A "tel:"
		// hivatkozásba szóköz / kötőjel / zárójel nélküli alak kell (a "+" csak az elején marad).
		if ( preg_match( '/^\+?[\d\s().\/\-]+$/', $v ) ) {
			$digits = preg_replace( '/\D/', '', $v );
			if ( strlen( $digits ) >= 6 ) {
				return array( 'kind' => 'tel', 'value' => ( $v[0] === '+' ? '+' : '' ) . $digits, 'scheme' => 'tel:' );
			}
		}

		return array( 'kind' => 'url', 'value' => $v, 'scheme' => '' );
	}

	/**
	 * A "másolás" ikon HTML-je. Szándékosan <span> (nem <button>/<svg>): a legtöbb Elementor
	 * widget wp_kses_post-on engedi át a dinamikus tartalmat, ami a <button>-t / <svg>-t
	 * kidobná. Az ikont a CSS rajzolja, a billentyűzet-elérhetőséget (tabindex) a JS adja.
	 *
	 * @param string $text A vágólapra kerülő szöveg.
	 * @return string
	 */
	public static function copy_button( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( $text === '' ) return '';

		self::enqueue_assets();

		return '<span class="szeducate-copy" role="button" data-szeducate-copy="' . esc_attr( $text ) . '"'
			. ' title="Másolás a vágólapra" aria-label="Másolás a vágólapra"></span>';
	}

	private static function enqueue_assets() {
		// Utólag, a renderelés közben is sorba állítható: a WP a láblécben írja ki.
		wp_enqueue_style( 'szeducate-copy', SZEDUCATE_PLUGIN_URL . 'assets/copy-button.css', array(), SZEDUCATE_VERSION );
		wp_enqueue_script( 'szeducate-copy', SZEDUCATE_PLUGIN_URL . 'assets/copy-button.js', array(), SZEDUCATE_VERSION, true );
	}
}
