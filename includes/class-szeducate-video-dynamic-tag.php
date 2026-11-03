<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// URL-kategóriás Dynamic Tag: egy "url" / "text" / "email" típusú séma-mező hivatkozását
// adja vissza, bármelyik Elementor "Link" mezőbe dinamikus forrásként beköthető (gomb,
// kép-hivatkozás, Videó widget "Link" mezője - így a natív Videó widget teljes felszerelése
// is használható). A sima "Képzés Adat" tag SZÖVEG-kategóriás, azt a Link mezők nem fogadják el.
// (Dynamic Tag = Elementor PRO funkció; PRO nélkül a "SZEducate Képzés Videó" widget
//  a megoldás.)
//
// A tag neve ('szeducate-course-video-url') tudatosan nem változott, mert a már elmentett
// oldalak ezen az azonosítón hivatkoznak rá - csak a megjelenő cím lett általános.
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-contact-link.php';

class SZEducate_Video_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'szeducate-course-video-url';
	}

	public function get_title() {
		return 'Képzés Hivatkozás (SZEducate)';
	}

	public function get_group() {
		return 'szeducate';
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}

	protected function register_controls() {
		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$options = array( '' => 'Válassz mezőt...' );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( isset( $field['type'], $field['key'] ) && in_array( $field['type'], array( 'url', 'text', 'email' ), true ) ) {
						$options[ $field['key'] ] = isset( $field['label'] ) ? $field['label'] : $field['key'];
					}
				}
			}
		}

		$this->add_control(
			'field_key',
			array(
				'label'       => 'Hivatkozás mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => isset( $options['video'] ) ? 'video' : '',
				'description' => 'Link (url), Egysoros szöveg és E-mail típusú mezők. E-mail / telefonszám esetén az "Előtte" (Speciális fül) mezőbe írt "mailto:" / "tel:" közvetlenül az értékhez kerül; ha üresen hagyod, a tag magától hozzáteszi.',
			)
		);
	}

	public function render() {
		$field_key = $this->get_settings( 'field_key' );
		if ( empty( $field_key ) ) return;

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) return;

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) || empty( $data[ $field_key ] ) || ! is_string( $data[ $field_key ] ) ) return;

		// E-mail / telefonszám: az esc_url() séma nélkül "http://"-t fűzne elé, ami a kézzel
		// beírt "mailto:" / "tel:" előtaggal együtt "tel:http://+36..." hibát adna. Ezért itt
		// a séma nélküli (tisztított) értéket adjuk; a séma vagy az "Előtte" mezőből jön,
		// vagy - ha az üres - mi tesszük hozzá.
		$link = SZEducate_Contact_Link::classify( $data[ $field_key ] );
		if ( $link['kind'] !== 'url' ) {
			$before = $this->get_settings( 'before' );
			echo esc_attr( ( is_string( $before ) && trim( $before ) !== '' ) ? $link['value'] : $link['scheme'] . $link['value'] );
			return;
		}

		echo esc_url( $link['value'] );
	}
}
