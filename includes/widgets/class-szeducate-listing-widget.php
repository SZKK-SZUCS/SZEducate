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
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
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
				'label'   => 'Láthatóság Kapcsoló',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'public',
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
			'group_sort_order',
			[
				'label'   => 'Csoportok sorrendje',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'asc'    => 'A-Z (Növekvő)',
					'desc'   => 'Z-A (Csökkenő)',
					'custom' => 'Egyedi sorrend (Drag & Drop)',
				],
				'default' => 'asc',
				'condition' => [
					'group_by_key!' => '',
				],
			]
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'group_name',
			[
				'label'       => 'Csoport pontos neve',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'pl. Agrár',
				'label_block' => true,
			]
		);

		$this->add_control(
			'custom_sort_list',
			[
				'label'       => 'Egyedi sorrend beállítása',
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ group_name }}}',
				'condition'   => [
					'group_sort_order' => 'custom',
					'group_by_key!'    => '',
				],
			]
		);

		$this->add_control(
			'show_active_filter',
			[
				'label'        => 'Aktív szűrő kiírása a lista fölött',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
				'description'  => 'Csak az EXTRA szűrést írja ki (kereső / kategórialink). A widgetben beállított alap szűrő (pl. „Képzési Forma = MSc") nem jelenik meg badge-ként.',
			]
		);

		$this->add_control(
			'reset_filter_text',
			[
				'label'       => 'Szűrő-törlő link szövege',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Szűrő törlése',
				'condition'   => [ 'show_active_filter' => 'yes' ],
				'description' => 'Az aktív szűrő badge mellett jelenik meg; visszavezet az alap (widgetben beállított) nézetre. Üresen hagyva nincs link.',
			]
		);

		$this->add_control(
			'status_heading',
			[
				'label'     => 'Státusz Adatok',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'status_key',
			[
				'label'   => 'Állapot kulcs',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'meghirdetes_allapota',
			]
		);

		$this->add_control(
			'date_key',
			[
				'label'   => 'Passziválás dátuma',
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

		$this->start_controls_section(
			'style_active_filter_section',
			[
				'label'     => 'Aktív Szűrő Szövege',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_active_filter' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'active_filter_typography',
				'selector' => '{{WRAPPER}} .sz-active-filter-badge',
				'fields_options' => [
					'font_weight' => [ 'default' => '600' ],
				],
			]
		);

		$this->add_control(
			'active_filter_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-active-filter-badge' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'active_filter_margin',
			[
				'label'      => 'Alsó margó',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 20 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-active-filter-badge' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'reset_filter_heading',
			[
				'label'     => 'Szűrő-törlő link',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'reset_filter_color',
			[
				'label'     => 'Link színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#8C8F94',
				'selectors' => [ '{{WRAPPER}} .sz-reset-filter' => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();

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
				'default'    => [ 'unit' => 'px', 'size' => 30 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-listing-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-group-block'  => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

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
				'default'    => [ 'top' => 25, 'right' => 25, 'bottom' => 25, 'left' => 25, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-group-block' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'card_border_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'default'    => [ 'top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-group-block' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
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
							'horizontal' => 0, 'vertical' => 5, 'blur' => 15, 'spread' => 0, 'color' => 'rgba(36, 41, 67, 0.08)',
						]
					]
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_title_section',
			[
				'label'     => 'Kategória Címek',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [ 'group_by_key!' => '' ],
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
				'label'     => 'Alsó elválasztó',
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
				// A vastagság/kis-nagybetű/betűköz korábban a kimenetbe sütött "style"
				// attribútumból jött, ami miatt ez a vezérlő nem tudta felülírni azokat -
				// az alapértéket most a vezérlő adja, hogy tényleg szerkeszthető maradjon.
				'fields_options' => [
					'font_weight'    => [ 'default' => '700' ],
					'text_transform' => [ 'default' => 'uppercase' ],
					'letter_spacing' => [ 'default' => [ 'unit' => 'px', 'size' => 0.5 ] ],
				],
			]
		);

		$this->add_responsive_control(
			'group_title_margin',
			[
				'label'      => 'Alsó margó',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 20 ],
				'selectors'  => [ '{{WRAPPER}} .sz-group-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();

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
				'default' => [ 'value' => 'fas fa-chevron-right', 'library' => 'fa-solid' ],
			]
		);

		$this->add_control(
			'inactive_icon',
			[
				'label' => 'Inaktív Szak Ikon',
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [ 'value' => 'fas fa-circle', 'library' => 'fa-solid' ],
			]
		);

		$this->add_responsive_control(
			'icon_size',
			[
				'label'      => 'Ikon Mérete',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
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
				'label'      => 'Ikon Távolsága',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 10 ],
				'selectors'  => [ '{{WRAPPER}} .sz-item-icon' => 'margin-right: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'icon_valign',
			[
				'label'      => 'Ikon Függőleges Pozíció',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 0 ],
				'selectors'  => [ '{{WRAPPER}} .sz-item-icon' => 'margin-top: {{SIZE}}{{UNIT}};' ],
				'separator'  => 'after',
			]
		);

		$this->start_controls_tabs( 'tabs_item_style' );

		$this->start_controls_tab( 'tab_item_normal', [ 'label' => 'Aktív' ] );
		$this->add_control( 'item_color', [ 'label' => 'Szövegszín', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#242943', 'selectors' => [ '{{WRAPPER}} .sz-course-active' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'item_icon_color', [ 'label' => 'Ikon Színe', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#50ADC9', 'selectors' => [ '{{WRAPPER}} .sz-course-active .sz-item-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-active .sz-item-icon i' => 'color: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-active .sz-item-icon svg' => 'fill: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-active .sz-item-icon svg path' => 'fill: {{VALUE}} !important;' ] ] );
		$this->end_controls_tab();

		// A hover az ÖSSZES listaelemre vonatkozik (aktív és inaktív egyaránt) - a
		// .sz-course-link a közös osztály mindkét állapoton. Korábban csak a
		// .sz-course-active:hover volt megcélozva, ezért az inaktív szakok nem kaptak
		// hover-kiemelést.
		$this->start_controls_tab( 'tab_item_hover', [ 'label' => 'Hover' ] );
		$this->add_control( 'item_hover_color', [ 'label' => 'Szövegszín', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#50ADC9', 'description' => 'Aktív és inaktív listaelemre egyaránt.', 'selectors' => [ '{{WRAPPER}} .sz-course-link:hover' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'item_icon_hover_color', [ 'label' => 'Ikon Színe', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-course-link:hover .sz-item-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-link:hover .sz-item-icon i' => 'color: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-link:hover .sz-item-icon svg' => 'fill: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-link:hover .sz-item-icon svg path' => 'fill: {{VALUE}} !important;' ] ] );
		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_item_inactive', [ 'label' => 'Inaktív' ] );
		$this->add_control( 'item_inactive_color', [ 'label' => 'Szövegszín', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#8C8F94', 'selectors' => [ '{{WRAPPER}} .sz-course-inactive' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'item_icon_inactive_color', [ 'label' => 'Ikon Színe', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#D9D9D9', 'selectors' => [ '{{WRAPPER}} .sz-course-inactive .sz-item-icon' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-inactive .sz-item-icon i' => 'color: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-inactive .sz-item-icon svg' => 'fill: {{VALUE}} !important;', '{{WRAPPER}} .sz-course-inactive .sz-item-icon svg path' => 'fill: {{VALUE}} !important;' ] ] );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'item_typography',
				'label'     => 'Tipográfia',
				'selector'  => '{{WRAPPER}} .sz-course-link',
				'separator' => 'before',
				// A sortáv és az aláhúzás korábban a kimenetbe sütött "style" attribútumból
				// jött, ami miatt ez a vezérlő nem tudta felülírni azokat.
				'fields_options' => [
					'line_height'     => [ 'default' => [ 'unit' => 'px', 'size' => 1.2 ] ],
					'text_decoration' => [ 'default' => 'none' ],
				],
			]
		);

		$this->add_responsive_control(
			'item_spacing',
			[
				'label'      => 'Szakok közötti térköz (sorok)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
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
		
		$group_key  = isset($_GET['sz_group']) ? sanitize_text_field($_GET['sz_group']) : (isset($settings['group_by_key']) ? trim($settings['group_by_key']) : '');
		
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );

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

		$inactive_icon_html = '';
		$inactive_icon = isset($settings['inactive_icon']) ? $settings['inactive_icon'] : [];
		if ( ! empty( $inactive_icon['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $inactive_icon, [ 'aria-hidden' => 'true' ] );
			$icon_out = ob_get_clean();
			if ( empty( $icon_out ) && is_string( $inactive_icon['value'] ) ) {
				$icon_out = '<i class="' . esc_attr( $inactive_icon['value'] ) . '" aria-hidden="true"></i>';
			}
			if ( ! empty( $icon_out ) ) {
				$inactive_icon_html = '<span class="sz-item-icon" style="display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">' . $icon_out . '</span>';
			}
		}

		// Alap szűrők: a widgetben beállított 'filter_<kulcs>' vezérlők. Ezek MINDIG
		// érvényesek a lekérdezésre, de sosem jelennek meg eltávolítható badge-ként
		// (ez a "nem törölhető alap szűrés" - pl. egy MSc-aloldalon a Képzési Forma).
		$base_filters = array();
		foreach ( $settings as $key => $val ) {
			if ( strpos( $key, 'filter_' ) === 0 && $key !== 'filter_heading' && ! empty( $val ) ) {
				$actual_key = str_replace( 'filter_', '', $key );
				$base_filters[$actual_key] = sanitize_title( $val );
			}
		}

		// Extra szűrés kategórialinkből (pretty URL) - a régi viselkedés szerint ez
		// FELÜLÍRJA az alapot a lekérdezésben.
		$seo_field   = get_query_var('sz_seo_field');
		$seo_keyword = get_query_var('sz_seo_keyword');
		$seo_active  = ( ! empty($seo_field) && ! empty($seo_keyword) );

		$active_filters = $seo_active
			? array( sanitize_text_field($seo_field) => sanitize_title($seo_keyword) )
			: $base_filters;

		$free_text_search = isset($_GET['sz_search']) ? trim(sanitize_text_field($_GET['sz_search'])) : '';
		$free_text_search_lower = mb_strtolower($free_text_search, 'UTF-8');

		// Badge + "Szűrő törlése" link CSAK extra szűrésnél (szabadszavas keresés vagy
		// kategórialink). Az alap widget-szűrő önmagában nem ír ki semmit.
		$badge_enabled = ( isset($settings['show_active_filter']) && $settings['show_active_filter'] === 'yes' );
		$reset_label   = isset($settings['reset_filter_text']) ? trim($settings['reset_filter_text']) : '';

		$display_filter_text = '';
		$reset_url           = '';

		if ( $badge_enabled && $free_text_search !== '' ) {
			$display_filter_text = sprintf( 'Keresés eredménye: "%s"', $free_text_search );
			// A keresés eltávolítása után az alap (widget-config) nézet marad.
			$reset_url = remove_query_arg( array( 'sz_search' ) );
		} elseif ( $badge_enabled && $seo_active ) {
			reset($active_filters);
			$f_key = key($active_filters);
			$f_val_slug = current($active_filters);
			$f_label = $f_key;
			$f_val_display = $f_val_slug;

			if ( is_array( $schema ) ) {
				foreach ( $schema as $group ) {
					if ( empty( $group['fields'] ) ) continue;
					foreach ( $group['fields'] as $field ) {
						if ( $field['key'] === $f_key ) {
							$f_label = $field['label'];
							if ( ! empty( $field['options'] ) ) {
								$opts = array_map( 'trim', explode( ';', $field['options'] ) );
								foreach ( $opts as $opt ) {
									if ( sanitize_title($opt) === $f_val_slug ) {
										$f_val_display = $opt;
										break;
									}
								}
							} else {
								$f_val_display = ucfirst( str_replace('-', ' ', $f_val_slug) );
							}
							break 2;
						}
					}
				}
			}
			$display_filter_text = sprintf( 'Szűrés: %s - %s', $f_label, $f_val_display );

			// A kategórialink a path-ban ül; a törlés a tiszta oldal-permalinkre visz.
			$reset_url = get_permalink( get_queried_object_id() );
			if ( empty( $reset_url ) ) {
				$pn = get_query_var( 'pagename' );
				$reset_url = $pn ? home_url( user_trailingslashit( $pn ) ) : home_url( '/' );
			}
		}

		$filter_badge_html = '';
		if ( $display_filter_text !== '' ) {
			$filter_badge_html = '<div class="sz-active-filter-badge">' . esc_html( $display_filter_text );
			if ( $reset_label !== '' && ! empty( $reset_url ) ) {
				$filter_badge_html .= ' <a href="' . esc_url( $reset_url ) . '" class="sz-reset-filter" rel="nofollow" style="margin-left:8px; white-space:nowrap;">' . esc_html( $reset_label ) . '</a>';
			}
			$filter_badge_html .= '</div>';
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$courses = SZEducate_Client::get_cached_courses_data();

		$grouped_data = array();
		$today_time = strtotime( current_time( 'Y-m-d' ) );
		$priority_keys = ['kepzesi_forma', 'kulcsszavak', 'kepzesi_terulet', 'indulas_idoszaka'];

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

			$score = 0;
			if ( ! empty( $free_text_search ) ) {
				$t_lower = mb_strtolower( $course['title'], 'UTF-8' );
				
				if ( $t_lower === $free_text_search_lower ) $score += 100;
				elseif ( mb_strpos( $t_lower, $free_text_search_lower ) === 0 ) $score += 80;
				elseif ( preg_match( '/\b' . preg_quote( $free_text_search, '/' ) . '\b/iu', $course['title'] ) ) $score += 60;
				elseif ( mb_strpos( $t_lower, $free_text_search_lower ) !== false ) $score += 40;

				foreach ( $data as $k => $v ) {
					$safe_key = (string) $k;
					if ( is_bool($v) || strpos($safe_key, 'url') !== false ) continue;
					
					$s_text = is_scalar($v) ? (string)$v : wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
					$s_text_lower = mb_strtolower( $s_text, 'UTF-8' );
					
					if ( mb_strpos( $s_text_lower, $free_text_search_lower ) !== false ) {
						if ( in_array( $safe_key, $priority_keys ) ) {
							if ( $s_text_lower === $free_text_search_lower ) $score += 50;
							else $score += 30;
						} else {
							if ( preg_match( '/\b' . preg_quote( $free_text_search, '/' ) . '\b/iu', $s_text ) ) $score += 10;
							else $score += 5;
						}
					}
				}
				if ( $score === 0 ) continue;
			}

			$passes_filters = true;
			foreach ( $active_filters as $f_key => $f_val_slug ) {
				$actual_val = isset( $data[$f_key] ) ? $data[$f_key] : '';
				
				if ( is_array( $actual_val ) ) {
					$actual_slugs = array_map('sanitize_title', $actual_val);
					if ( ! in_array( $f_val_slug, $actual_slugs ) ) { $passes_filters = false; break; }
				} else {
					$parts = array_map('sanitize_title', explode(';', (string)$actual_val));
					if ( ! in_array( $f_val_slug, $parts ) && sanitize_title((string)$actual_val) !== $f_val_slug ) {
						$passes_filters = false; 
						break; 
					}
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
					$groups_for_course = array_map('trim', explode(';', (string)$gv_raw));
				} else {
					$groups_for_course = array( 'Egyéb' );
				}
			} else {
				$groups_for_course = array( 'Összes Képzés' );
			}

			foreach ( $groups_for_course as $g_name ) {
				$safe_g_name = trim( $g_name );
				if ( $safe_g_name === '' ) continue;
				if ( ! isset( $grouped_data[ $safe_g_name ] ) ) {
					$grouped_data[ $safe_g_name ] = array();
				}
				$grouped_data[ $safe_g_name ][] = array(
					'title'     => $course['title'],
					'url'       => get_permalink( $post_id ),
					'is_active' => $is_active,
					'score'     => $score
				);
			}
		}

		if ( empty( $grouped_data ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">A beállított szűrőknek megfelelő képzés jelenleg nem található.</p>';
			} else {
				echo $filter_badge_html;
				echo '<p style="color:#888; font-style:italic; padding:20px; text-align:center;">Sajnos nem találtunk a keresésnek megfelelő képzést.</p>';
			}
			return;
		}

		if ( $settings['group_sort_order'] === 'desc' ) {
			krsort( $grouped_data );
		} elseif ( $settings['group_sort_order'] === 'custom' && ! empty( $settings['custom_sort_list'] ) ) {
			$sorted_data = array();
			foreach ( $settings['custom_sort_list'] as $custom_item ) {
				$c_name = trim( $custom_item['group_name'] );
				if ( isset( $grouped_data[ $c_name ] ) ) {
					$sorted_data[ $c_name ] = $grouped_data[ $c_name ];
					unset( $grouped_data[ $c_name ] );
				}
			}
			ksort( $grouped_data );
			foreach ( $grouped_data as $rem_key => $rem_val ) {
				$sorted_data[ $rem_key ] = $rem_val;
			}
			$grouped_data = $sorted_data;
		} else {
			ksort( $grouped_data ); 
		}

		foreach ( $grouped_data as $key => $items ) {
			usort( $grouped_data[$key], function($a, $b) {
				if ( isset($a['score']) && isset($b['score']) && $a['score'] !== $b['score'] ) {
					return $b['score'] - $a['score'];
				}
				return strcmp( $a['title'], $b['title'] );
			});
		}

		echo $filter_badge_html;

		$grid_class = ! empty( $group_key ) ? 'sz-listing-grid' : 'sz-listing-single';
		$grid_style = ! empty( $group_key ) ? 'display:grid; width:100%;' : 'width:100%;';

		echo "<div class=\"{$grid_class}\" style=\"{$grid_style}\">";
		
		foreach ( $grouped_data as $group_name => $items ) {
			echo '<div class="sz-group-block" style="display:flex; flex-direction:column; overflow:hidden;">';
			
			if ( ! empty( $group_key ) ) {
				echo '<h3 class="sz-group-title" style="margin-top:0; border-bottom-style:solid; border-bottom-width:2px; padding-bottom:10px; width:100%;">' . esc_html( $group_name ) . '</h3>';
			}
			
			echo '<ul class="sz-course-list" style="list-style:none; padding:0; margin:0; width:100%;">';
			foreach ( $items as $item ) {
				$state_class = $item['is_active'] ? 'sz-course-active' : 'sz-course-inactive';
				$current_icon = $item['is_active'] ? $active_icon_html : $inactive_icon_html;
				
				echo '<li style="display:flex; align-items:flex-start;">';
				echo '<a href="' . esc_url( $item['url'] ) . '" class="sz-course-link ' . $state_class . '" style="display:inline-flex; align-items:center; transition:all 0.3s ease; width:100%; box-sizing:border-box;">';
				
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