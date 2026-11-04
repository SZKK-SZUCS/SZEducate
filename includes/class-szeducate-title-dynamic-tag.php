<?php
/**
 * A képzés címe tördelhető formában (Elementor dinamikus tag).
 *
 * A perjeles, hosszú szaknevek (pl. "Élelmezésszervező szakmérnök/szakmenedzser") a perjel
 * után nem törnek: a böngészőnek nincs ott tördelési pontja, ezért vagy kilóg az oszlopból,
 * vagy a szó közepén vág. Itt a "/" után zero-width space (U+200B) kerül, ami láthatatlan,
 * de ott törhet a sor. Tudatosan nem <wbr>: az atomic widgetek szöveges értékét az Elementor
 * escape-eli, a zero-width space viszont sima karakter, ezért minden kimeneten átmegy.
 *
 * A beépített "Bejegyzés címe" (post-title) tag érintetlen marad; ez egy külön, opt-in tag,
 * a már elmentett oldalak nem változnak.
 *
 * @package SZEducate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Képzés Címe – tördelhető" dinamikus tag.
 */
class SZEducate_Title_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * A tag azonosítója (az elmentett oldalak erre hivatkoznak, ne változzon).
	 *
	 * @return string
	 */
	public function get_name() {
		return 'szeducate-course-title';
	}

	/**
	 * A szerkesztőben megjelenő név.
	 *
	 * @return string
	 */
	public function get_title() {
		return 'Képzés Címe – tördelhető (SZEducate)';
	}

	/**
	 * A tag csoportja a szerkesztő listájában.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'szeducate';
	}

	/**
	 * Szöveges mezőkbe köthető (Címsor, Bekezdés).
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * A "/" mögé tördelési pontot tesz. Statikus és tiszta függvény, hogy a viselkedés
	 * külön tesztelhető legyen.
	 *
	 * @param string $title A cím.
	 * @return string
	 */
	public static function make_breakable( $title ) {
		// A "</" (záró HTML-tag) perjelét nem bántjuk, és a már zero-width space-szel követett
		// perjelet nem duplázzuk.
		return preg_replace( '#(?<!<)/(?!\x{200B})#u', "/\xE2\x80\x8B", (string) $title );
	}

	/**
	 * Kiírja a képzés címét.
	 *
	 * @return void
	 */
	public function render() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$title = get_the_title( $post_id );
		if ( '' === $title ) {
			return;
		}

		// A get_the_title már HTML-entitásokat (pl. &amp;) adhat vissza, ezt az Elementor core
		// post-title is wp_kses_post-on át írja ki, ugyanígy járunk el.
		echo wp_kses_post( self::make_breakable( $title ) );
	}
}
