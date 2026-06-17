<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Listing_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_listing'; }
	public function get_title() { return 'SZEducate Szaklista'; }
	public function get_icon() { return 'eicon-bullet-list'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {
		
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		
		$group_options = array( '' => '-- Nincs csoportosítás --' );
		$dynamic_filters = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					$group_options[ $field['key'] ] = $field['label'];

					if ( in_array( $field['type'], ['select', 'radio', 'checkbox'] ) && ! empty( $field['options'] ) ) {
						$opts = array_map( 'trim', explode( ';', $field['options'] ) );
						$choices = array( '' => '-- Mindegy --' );
						foreach ( $opts as $opt ) {
							if ( $opt !== '' ) {
								$choices[$opt] = $opt;
							}
						}
						$dynamic_filters[] = array(
							'key'     => $field['key'],
							'label'   => $field['label'],
							'choices' => $choices
						);
					}
				}
			}
		}

		// --- TARTALOM FÜL: Lekérdezés ---
		$this->start_controls_section(
			'query_section',
			[
				'label' => 'Lekérdezés és Logika',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'visibility_key',
			[
				'label'   => 'Láthatóság Kapcsoló (Boolean kulcs)',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'public',
				'description' => 'Ez a mező dönti el, hogy a szak egyáltalán megjelenjen-e a listában. Ha a szaknál ez hamis, el lesz rejtve.',
			]
		);

		$this->add_control(
			'group_by_key',
			[
				'label'   => 'Csoportosítás alapja (Kártyák)',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $group_options,
				'default' => '',
			]
		);

		$this->add_control(
			'status_heading',
			[
				'label'     => 'Státusz és Dizájn Adatok',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'status_key',
			[
				'label'   => 'Állapot kulcs (Aktív/Passzív stílushoz)',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'meghirdetes_allapota',
			]
		);

		$this->add_control(
			'date_key',
			[
				'label'   => 'Passziválás dátuma kulcs',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'passziv_ettol',
			]
		);

		if ( ! empty( $dynamic_filters ) ) {
			$this->add_control(
				'filter_heading',
				[
					'label'     => 'Dinamikus Szűrők',
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				]
			);

			foreach ( $dynamic_filters as $filter ) {
				$this->add_control(
					'filter_' . $filter['key'],
					[
						'label'   => 'Szűrés: ' . $filter['label'],
						'type'    => \Elementor\Controls_Manager::SELECT,
						'options' => $filter['choices'],
						'default' => '',
					]
				);
			}
		}

		$this->end_controls_section();

		// --- STÍLUS FÜL: Elrendezés (Rács) ---
		$this->start_controls_section(
			'style_layout_section',
			[
				'label' => 'Elrendezés (Rács)',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'columns',
			[
				'label'          => 'Oszlopok száma',
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => [
					'1' => '1 Oszlop',
					'2' => '2 Oszlop',
					'3' => '3 Oszlop',
					'4' => '4 Oszlop',
				],
				'selectors'      => [
					'{{WRAPPER}} .sz-listing-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
				],
			]
		);

		$this->add_responsive_control(
			'grid_gap',
			[
				'label'      => 'Kártyák (oszlopok) közötti távolság',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 30 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-listing-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'row_gap',
			[
				'label'      => 'Kártyák (sorok) közötti távolság',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 30 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-listing-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-group-block'  => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// --- STÍLUS FÜL: Kártya (Csoport) Stílus ---
		$this->start_controls_section(
			'style_card_section',
			[
				'label' => 'Kártya (Csoport) Stílus',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'card_bg_color',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-group-block' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'      => 'Belső margó (Padding)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'default'    => [
					'top' => 25, 'right' => 25, 'bottom' => 25, 'left' => 25,
					'unit' => 'px', 'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .sz-group-block' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'card_border_radius',
			[
				'label'      => 'Lekerekítés (Border Radius)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'default'    => [
					'top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12,
					'unit' => 'px', 'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .sz-group-block' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .sz-group-block',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_box_shadow',
				'selector' => '{{WRAPPER}} .sz-group-block',
				'fields_options' => [
					'box_shadow_type' => [ 'default' => 'yes' ],
					'box_shadow' => [
						'default' => [
							'horizontal' => 0,
							'vertical'   => 5,
							'blur'       => 15,
							'spread'     => 0,
							'color'      => 'rgba(36, 41, 67, 0.08)',
						]
					]
				]
			]
		);

		$this->end_controls_section();

		// --- STÍLUS FÜL: Címsorok ---
		$this->start_controls_section(
			'style_title_section',
			[
				'label'     => 'Kategória Címek',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'group_by_key!' => '',
				],
			]
		);

		$this->add_control(
			'group_title_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-group-title' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'group_title_border_color',
			[
				'label'     => 'Alsó elválasztó vonal színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-group-title' => 'border-bottom-color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'group_title_typography',
				'selector' => '{{WRAPPER}} .sz-group-title',
			]
		);

		$this->add_responsive_control(
			'group_title_margin',
			[
				'label'      => 'Alsó margó',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 20 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-group-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// --- STÍLUS FÜL: Lista Elemek (Szakok) és Ikonok ---
		$this->start_controls_section(
			'style_items_section',
			[
				'label' => 'Lista Elemek (Szakok)',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'active_icon',
			[
				'label' => 'Aktív Szak Ikon',
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-chevron-right',
					'library' => 'fa-solid',
				],
			]
		);

		$this->add_control(
			'passive_icon',
			[
				'label' => 'Passzív Szak Ikon',
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-circle',
					'library' => 'fa-solid',
				],
			]
		);

		$this->add_responsive_control(
			'icon_size',
			[
				'label'      => 'Ikon Mérete',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 5, 'max' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 14 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-item-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-item-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-item-icon svg' => 'width: 100%; height: 100%;',
				],
			]
		);

		$this->add_responsive_control(
			'icon_spacing',
			[
				'label'      => 'Ikon Távolsága a szövegtől',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 10 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-item-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_valign',
			[
				'label'      => 'Ikon Függőleges Pozíciója (Finomhangolás)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => -20, 'max' => 20 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 2 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-item-icon' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
				'separator'  => 'after',
			]
		);

		$this->start_controls_tabs( 'tabs_item_style' );

		// AKTÍV NÉZET
		$this->start_controls_tab(
			'tab_item_normal',
			[ 'label' => 'Aktív' ]
		);

		$this->add_control(
			'item_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-course-active' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'item_icon_color',
			[
				'label'     => 'Ikon Színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ 
					'{{WRAPPER}} .sz-course-active .sz-item-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-active .sz-item-icon i' => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-active .sz-item-icon svg' => 'fill: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-active .sz-item-icon svg path' => 'fill: {{VALUE}} !important;',
				],
			]
		);

		$this->end_controls_tab();

		// HOVER NÉZET
		$this->start_controls_tab(
			'tab_item_hover',
			[ 'label' => 'Hover' ]
		);

		$this->add_control(
			'item_hover_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-course-active:hover' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'item_icon_hover_color',
			[
				'label'     => 'Ikon Színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [ 
					'{{WRAPPER}} .sz-course-active:hover .sz-item-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-active:hover .sz-item-icon i' => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-active:hover .sz-item-icon svg' => 'fill: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-active:hover .sz-item-icon svg path' => 'fill: {{VALUE}} !important;',
				],
			]
		);

		$this->end_controls_tab();

		// PASSZÍV NÉZET
		$this->start_controls_tab(
			'tab_item_passive',
			[ 'label' => 'Passzív' ]
		);

		$this->add_control(
			'item_passive_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#8C8F94',
				'selectors' => [ '{{WRAPPER}} .sz-course-passive' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'item_icon_passive_color',
			[
				'label'     => 'Ikon Színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#D9D9D9',
				'selectors' => [ 
					'{{WRAPPER}} .sz-course-passive .sz-item-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-passive .sz-item-icon i' => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-passive .sz-item-icon svg' => 'fill: {{VALUE}} !important;',
					'{{WRAPPER}} .sz-course-passive .sz-item-icon svg path' => 'fill: {{VALUE}} !important;',
				],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'item_typography',
				'label'     => 'Tipográfia',
				'selector'  => '{{WRAPPER}} .sz-course-link',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'item_spacing',
			[
				'label'      => 'Szakok közötti térköz (sorok)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 12 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-course-list li' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-course-list li:last-child' => 'margin-bottom: 0;',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		
		$vis_key    = isset($settings['visibility_key']) ? trim($settings['visibility_key']) : 'public';
		$status_key = isset($settings['status_key']) ? trim($settings['status_key']) : 'meghirdetes_allapota';
		$date_key   = isset($settings['date_key']) ? trim($settings['date_key']) : 'passziv_ettol';
		$group_key  = isset($settings['group_by_key']) ? trim($settings['group_by_key']) : '';

		// Ikon HTML előállítása golyóálló wrapperrel
		$active_icon_html = '';
		$active_icon = isset($settings['active_icon']) ? $settings['active_icon'] : [];
		if ( ! empty( $active_icon['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $active_icon, [ 'aria-hidden' => 'true' ] );
			$icon_out = ob_get_clean();
			
			if ( empty( $icon_out ) && is_string( $active_icon['value'] ) ) {
				$icon_out = '<i class="' . esc_attr( $active_icon['value'] ) . '" aria-hidden="true"></i>';
			}
			if ( ! empty( $icon_out ) ) {
				$active_icon_html = '<span class="sz-item-icon" style="display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">' . $icon_out . '</span>';
			}
		}

		$passive_icon_html = '';
		$passive_icon = isset($settings['passive_icon']) ? $settings['passive_icon'] : [];
		if ( ! empty( $passive_icon['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $passive_icon, [ 'aria-hidden' => 'true' ] );
			$icon_out = ob_get_clean();
			
			if ( empty( $icon_out ) && is_string( $passive_icon['value'] ) ) {
				$icon_out = '<i class="' . esc_attr( $passive_icon['value'] ) . '" aria-hidden="true"></i>';
			}
			if ( ! empty( $icon_out ) ) {
				$passive_icon_html = '<span class="sz-item-icon" style="display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">' . $icon_out . '</span>';
			}
		}

		$active_filters = array();
		foreach ( $settings as $key => $val ) {
			if ( strpos( $key, 'filter_' ) === 0 && $key !== 'filter_heading' && ! empty( $val ) ) {
				$actual_key = str_replace( 'filter_', '', $key );
				$active_filters[$actual_key] = $val;
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$courses = $wpdb->get_results( "SELECT local_post_id, title, course_data FROM $table_name", ARRAY_A );

		$grouped_data = array();
		$today_time = strtotime( current_time( 'Y-m-d' ) );

		foreach ( $courses as $course ) {
			$post_id = $course['local_post_id'];
			if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) continue;

			$data = json_decode( $course['course_data'], true );
			if ( ! is_array( $data ) ) continue;

			if ( ! empty( $vis_key ) && isset( $data[$vis_key] ) ) {
				$v = $data[$vis_key];
				if ( $v === 0 || $v === '0' || $v === false || $v === 'false' || $v === '' ) {
					continue; 
				}
			}

			$passes_filters = true;
			foreach ( $active_filters as $f_key => $f_val ) {
				$actual_val = isset( $data[$f_key] ) ? $data[$f_key] : '';
				if ( is_array( $actual_val ) ) {
					if ( ! in_array( $f_val, $actual_val ) ) { $passes_filters = false; break; }
				} else {
					if ( trim((string)$actual_val) !== trim($f_val) ) { $passes_filters = false; break; }
				}
			}
			if ( ! $passes_filters ) continue;

			$status = isset( $data[$status_key] ) ? mb_strtolower(trim((string)$data[$status_key]), 'UTF-8') : '';
			$is_active = ( $status === 'aktív' || $status === 'aktiv' );

			$passziv_ettol = isset( $data[$date_key] ) ? trim((string)$data[$date_key]) : '';
			if ( $is_active && ! empty( $passziv_ettol ) ) {
				$p_time = strtotime( $passziv_ettol );
				if ( $p_time !== false && $today_time >= $p_time ) {
					$is_active = false;
				}
			}

			$groups_for_course = array();
			if ( ! empty( $group_key ) ) {
				$gv_raw = isset( $data[ $group_key ] ) ? $data[ $group_key ] : '';
				if ( is_array( $gv_raw ) && ! empty( $gv_raw ) ) {
					$groups_for_course = $gv_raw; 
				} elseif ( ! is_array( $gv_raw ) && trim((string)$gv_raw) !== '' ) {
					$groups_for_course = array( trim((string)$gv_raw) );
				} else {
					$groups_for_course = array( 'Egyéb' );
				}
			} else {
				$groups_for_course = array( 'Összes Képzés' );
			}

			foreach ( $groups_for_course as $g_name ) {
				$safe_g_name = trim( $g_name );
				if ( ! isset( $grouped_data[ $safe_g_name ] ) ) {
					$grouped_data[ $safe_g_name ] = array();
				}
				$grouped_data[ $safe_g_name ][] = array(
					'title'     => $course['title'],
					'url'       => get_permalink( $post_id ),
					'is_active' => $is_active
				);
			}
		}

		if ( empty( $grouped_data ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">A beállított szűrőknek megfelelő, látható képzés jelenleg nem található.</p>';
			}
			return;
		}

		ksort( $grouped_data );
		foreach ( $grouped_data as $key => $items ) {
			usort( $grouped_data[$key], function($a, $b) {
				return strcmp( $a['title'], $b['title'] );
			});
		}

		$grid_class = ! empty( $group_key ) ? 'sz-listing-grid' : 'sz-listing-single';
		$grid_style = ! empty( $group_key ) ? 'display:grid; width:100%;' : 'width:100%;';

		echo "<div class=\"{$grid_class}\" style=\"{$grid_style}\">";
		
		foreach ( $grouped_data as $group_name => $items ) {
			echo '<div class="sz-group-block" style="display:flex; flex-direction:column; overflow:hidden;">';
			
			if ( ! empty( $group_key ) ) {
				echo '<h3 class="sz-group-title" style="margin-top:0; border-bottom:2px solid; padding-bottom:10px; width:100%; font-weight:700; letter-spacing:0.5px; text-transform:uppercase;">' . esc_html( $group_name ) . '</h3>';
			}
			
			echo '<ul class="sz-course-list" style="list-style:none; padding:0; margin:0; width:100%;">';
			foreach ( $items as $item ) {
				$state_class = $item['is_active'] ? 'sz-course-active' : 'sz-course-passive';
				$current_icon = $item['is_active'] ? $active_icon_html : $passive_icon_html;
				
				echo '<li style="display:flex; align-items:flex-start;">';
				echo '<a href="' . esc_url( $item['url'] ) . '" class="sz-course-link ' . $state_class . '" style="display:inline-flex; align-items:flex-start; text-decoration:none; transition:all 0.3s ease; line-height:1.4;">';
				
				if ( ! empty( $current_icon ) ) {
					echo $current_icon;
				}
				
				echo '<span>' . esc_html( $item['title'] ) . '</span>';
				echo '</a>';
				echo '</li>';
			}
			echo '</ul>';
			
			echo '</div>';
		}
		
		echo '</div>';
	}
}