<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Elementor {

	public function init() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

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
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-image-dynamic-tag.php';
		$dynamic_tags_manager->register_group( 'szeducate', array( 'title' => 'SZEducate Adatok' ) );
		$dynamic_tags_manager->register( new SZEducate_Dynamic_Tag() );
		$dynamic_tags_manager->register( new SZEducate_Image_Dynamic_Tag() );
	}

	public function register_widgets( $widgets_manager ) {
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-links-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-status-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-listing-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-keywords-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-search-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-repeater-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-pricing-table-widget.php';

		$widgets_manager->register( new SZEducate_Search_Widget() );
		$widgets_manager->register( new SZEducate_Links_Widget() );
		$widgets_manager->register( new SZEducate_Status_Widget() );
		$widgets_manager->register( new SZEducate_Listing_Widget() );
		$widgets_manager->register( new SZEducate_Keywords_Widget() );
		$widgets_manager->register( new SZEducate_Repeater_Widget() );
		$widgets_manager->register( new SZEducate_Pricing_Table_Widget() );
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
				'label' => 'SZEducate Láthatóság',
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			'szeducate_hide_if_empty_keys',
			array(
				'label'       => 'Vizsgált mezők:',
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $options,
				'default'     => array(),
			)
		);

		$element->add_control(
			'szeducate_hide_rule',
			array(
				'label'     => 'Feltétel (Akkor rejti el, ha...)',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'empty'      => 'A mező ÜRES',
					'not_empty'  => 'A mező NEM ÜRES',
					'equals'     => 'EGYENLŐ a megadott értékkel',
					'not_equals' => 'NEM EGYENLŐ a megadott értékkel',
					'contains'   => 'TARTALMAZZA a megadott értéket',
				),
				'default'   => 'empty',
				'condition' => array(
					'szeducate_hide_if_empty_keys!' => '',
				),
			)
		);

		$element->add_control(
			'szeducate_hide_value',
			array(
				'label'     => 'Vizsgált érték (Szöveg)',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array(
					'szeducate_hide_rule' => array( 'equals', 'not_equals', 'contains' ),
					'szeducate_hide_if_empty_keys!' => '',
				),
			)
		);

		$element->add_control(
			'szeducate_hide_logic',
			array(
				'label'     => 'Több mező esetén a logika:',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'all_match' => 'Ha MINDEGYIK kiválasztott megfelel a feltételnek',
					'any_match' => 'Ha BÁRMELYIK kiválasztott megfelel a feltételnek',
				),
				'default'   => 'all_match',
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
		
		if ( empty( $settings['szeducate_hide_if_empty_keys'] ) || ! is_array( $settings['szeducate_hide_if_empty_keys'] ) ) {
			return $should_render;
		}

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $should_render;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) return false;

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return false;

		$keys_to_check = $settings['szeducate_hide_if_empty_keys'];
		$rule = isset( $settings['szeducate_hide_rule'] ) ? $settings['szeducate_hide_rule'] : 'empty';
		$target_val = isset( $settings['szeducate_hide_value'] ) ? $settings['szeducate_hide_value'] : '';
		$logic = isset( $settings['szeducate_hide_logic'] ) ? $settings['szeducate_hide_logic'] : 'all_match';
		
		$match_count = 0;
		$total_keys = count( $keys_to_check );

		foreach ( $keys_to_check as $key ) {
			$actual_val = isset( $data[ $key ] ) ? $data[ $key ] : '';
			$actual_str = is_array( $actual_val ) ? implode( ',', $actual_val ) : (string) $actual_val;
			$is_empty = trim($actual_str) === '';

			$is_match = false;
			switch ( $rule ) {
				case 'empty':      $is_match = $is_empty; break;
				case 'not_empty':  $is_match = !$is_empty; break;
				case 'equals':     $is_match = ($actual_str === $target_val); break;
				case 'not_equals': $is_match = ($actual_str !== $target_val); break;
				case 'contains':   $is_match = (strpos($actual_str, $target_val) !== false); break;
			}
			
			if ( $is_match ) $match_count++;
		}

		if ( $logic === 'all_match' ) {
			if ( $match_count === $total_keys ) return false;
		} else {
			if ( $match_count > 0 ) return false;
		}

		return $should_render;
	}
}