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

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
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

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE local_post_id = %d LIMIT 1", $post_id ), ARRAY_A );

		if ( ! $course ) return;

		$data = json_decode( $course['course_data'], true );
		
		if ( ! isset( $data[ $field_key ] ) || $data[ $field_key ] === '' ) return;

		$value = $data[ $field_key ];

		if ( is_array( $value ) ) {
			$separator = $this->get_settings( 'array_separator' );
			echo esc_html( implode( $separator, $value ) );
		} elseif ( is_bool( $value ) ) {
			echo $value ? 'Igen' : 'Nem';
		} else {
			echo wp_kses_post( $value ); 
		}
	}
}