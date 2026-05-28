<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Elementor {

	public function init() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );

		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );
		add_action( 'elementor/element/section/section_advanced/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );
		add_action( 'elementor/element/column/section_advanced/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );
		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );

		add_filter( 'elementor/frontend/widget/should_render', array( $this, 'should_render_element' ), 10, 2 );
		add_filter( 'elementor/frontend/section/should_render', array( $this, 'should_render_element' ), 10, 2 );
		add_filter( 'elementor/frontend/column/should_render', array( $this, 'should_render_element' ), 10, 2 );
		add_filter( 'elementor/frontend/container/should_render', array( $this, 'should_render_element' ), 10, 2 );
	}

	public function register_dynamic_tags( $dynamic_tags_manager ) {
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-dynamic-tag.php';
		$dynamic_tags_manager->register_group( 'szeducate', array( 'title' => 'SZEducate Adatok' ) );
		$dynamic_tags_manager->register( new SZEducate_Dynamic_Tag() );
	}

	public function add_visibility_controls( $element, $args ) {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		
		$options = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						$options[ $field['key'] ] = $field['label'] . ' [' . $field['key'] . ']';
					}
				}
			}
		}

		$element->start_controls_section(
			'szeducate_visibility_section',
			array(
				'label' => '👁️ SZEducate Láthatóság',
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		// Átalakítva többszörös választóvá (SELECT2)
		$element->add_control(
			'szeducate_hide_if_empty_keys',
			array(
				'label'       => 'Vizsgált mezők:',
				'description' => 'Válassz ki egy vagy több mezőt.',
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $options,
				'default'     => array(),
			)
		);

		// ÚJ: Logikai operátor
		$element->add_control(
			'szeducate_hide_logic',
			array(
				'label'     => 'Elrejtés feltétele:',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'all_empty' => 'Ha MINDEGYIK kiválasztott üres',
					'any_empty' => 'Ha BÁRMELYIK kiválasztott üres',
				),
				'default'   => 'all_empty',
				'condition' => array(
					'szeducate_hide_if_empty_keys!' => '',
				),
			)
		);

		$element->end_controls_section();
	}

	public function should_render_element( $should_render, $element ) {
		if ( ! $should_render ) return $should_render;

		$settings = $element->get_settings_for_display();
		
		// Ha nincsenek beállítva vizsgálandó kulcsok
		if ( empty( $settings['szeducate_hide_if_empty_keys'] ) || ! is_array( $settings['szeducate_hide_if_empty_keys'] ) ) {
			return $should_render;
		}

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $should_render;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) {
			return false; 
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE local_post_id = %d LIMIT 1", $post_id ), ARRAY_A );

		if ( ! $course ) return false;

		$data = json_decode( $course['course_data'], true );
		$keys_to_check = $settings['szeducate_hide_if_empty_keys'];
		$logic = isset( $settings['szeducate_hide_logic'] ) ? $settings['szeducate_hide_logic'] : 'all_empty';
		
		$empty_count = 0;
		$total_keys = count( $keys_to_check );

		// Végigmegyünk az összes kiválasztott kulcson
		foreach ( $keys_to_check as $key ) {
			$is_empty = true;
			
			if ( isset( $data[ $key ] ) ) {
				$val = $data[ $key ];
				if ( is_array( $val ) && ! empty( $val ) ) {
					$is_empty = false;
				} elseif ( trim( (string) $val ) !== '' ) {
					$is_empty = false;
				}
			}
			
			if ( $is_empty ) {
				$empty_count++;
			}
		}

		// Kiértékeljük a logikát
		if ( $logic === 'all_empty' ) {
			// Csak akkor rejtjük el (return false), ha az összes vizsgált mező üres
			if ( $empty_count === $total_keys ) {
				return false;
			}
		} else { // 'any_empty'
			// Ha legalább 1 üres a listából, már rejtjük is
			if ( $empty_count > 0 ) {
				return false;
			}
		}

		return $should_render;
	}
}