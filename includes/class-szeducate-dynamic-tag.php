<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'szeducate-course-data';
	}

	public function get_title() {
		return 'Képzés Adat (SZEducate)';
	}

	public function get_group() {
		return 'szeducate';
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		
		$options = array( '' => 'Válassz mezőt...' );

		// A "repeater" és "links" típusú mezők strukturált (tömb-a-tömbben) adatok - ezeknek
		// dedikált Widget kell (Repeater Widget, Linkek Widget), egy sima szöveges Dynamic
		// Tag-ben nincs értelmes, egysoros megjelenítésük, ezért itt nem is választhatók.
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( isset( $field['type'] ) && in_array( $field['type'], array( 'repeater', 'links' ), true ) ) continue;
						$options[ $field['key'] ] = $field['label'];
					}
				}
			}
		}

		$this->add_control(
			'field_key',
			array(
				'label'   => 'Megjelenítendő Mező',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);

		$this->add_control(
			'array_separator',
			array(
				'label'     => 'Elválasztó (több elem esetén)',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => ', ',
				'condition' => array( 'field_key!' => '' ),
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
		if ( ! is_array( $data ) ) return;

		if ( ! isset( $data[ $field_key ] ) || $data[ $field_key ] === '' ) return;

		$value = $data[ $field_key ];

		if ( is_array( $value ) ) {
			// Struktúrált (repeater / links) mezőknél a tömb elemei maguk is tömbök -
			// ezekre a "Repeater" ill. "Linkek" Widget való, itt (sima szöveges Dynamic
			// Tag-ként) csak egyszerű, lapos listákat tudunk értelmesen megjeleníteni.
			// Enélkül az implode() "Array"-t írt volna ki minden elemre.
			$is_flat = true;
			foreach ( $value as $v ) {
				if ( is_array( $v ) ) { $is_flat = false; break; }
			}
			if ( ! $is_flat ) return;

			$separator = $this->get_settings( 'array_separator' );
			echo esc_html( implode( $separator, $value ) );
		} elseif ( is_bool( $value ) ) {
			echo $value ? 'Igen' : 'Nem';
		} else {
			echo wp_kses_post( $value ); 
		}
	}
}