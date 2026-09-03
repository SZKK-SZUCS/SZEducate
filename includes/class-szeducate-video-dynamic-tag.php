<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// URL-kategóriás Dynamic Tag: egy "url" típusú séma-mező (pl. a képzés videója)
// hivatkozását adja vissza, hogy az Elementor PRO beépített Videó widgetjének
// "Link" mezőjébe dinamikus forrásként beköthető legyen - így a natív widget teljes
// felszerelése (arány, előnézeti kép, lightbox, lejátszás ikon, stílusok) használható.
// A sima "Képzés Adat" tag SZÖVEG-kategóriás, azt a Videó widget nem fogadja el.
// (Dynamic Tag = Elementor PRO funkció; PRO nélkül a "SZEducate Képzés Videó" widget
//  a megoldás.)
class SZEducate_Video_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'szeducate-course-video-url';
	}

	public function get_title() {
		return 'Képzés Videó URL (SZEducate)';
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
					if ( isset( $field['type'], $field['key'] ) && in_array( $field['type'], array( 'url', 'text' ), true ) ) {
						$options[ $field['key'] ] = isset( $field['label'] ) ? $field['label'] : $field['key'];
					}
				}
			}
		}

		$this->add_control(
			'field_key',
			array(
				'label'       => 'Videó / URL mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => isset( $options['video'] ) ? 'video' : '',
				'description' => 'Csak a Séma Tervezőben "Link" (url) típusúra állított mezők.',
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

		echo esc_url( trim( $data[ $field_key ] ) );
	}
}
