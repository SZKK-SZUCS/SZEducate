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

		// --- Látogatói szűrőpanel -------------------------------------------------
		// A "Dinamikus Szűrők" fentebb az admin által rögzített ALAP szűrés (nem
		// látszik a látogatónak). Ez a szekció ezzel szemben egy a lista fölött
		// megjelenő, kliensoldali (JS, újratöltés nélküli) szűrőpanelt kapcsol be,
		// amit a látogató maga állítgat. Szűrőnként külön kapcsolható, hogy melyik
		// widgeten legyen rá szükség.
		$this->start_controls_section(
			'user_filters_section',
			[
				'label' => 'Látogatói szűrők (panel)',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'uf_title',
			[
				'label'   => 'Panel címe',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Szűrés',
			]
		);

		$this->add_control(
			'uf_reset_text',
			[
				'label'   => 'Szűrő-törlő link szövege',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Szűrő törlése',
			]
		);

		$this->add_control(
			'uf_no_results_text',
			[
				'label'   => '„Nincs találat” szöveg',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Nincs a szűrésnek megfelelő képzés.',
			]
		);

		$this->add_control(
			'uf_munkarend',
			[
				'label'        => 'Munkarend szűrő',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			]
		);
		$this->add_control(
			'uf_munkarend_label',
			[
				'label'     => 'Munkarend – felirat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Munkarend',
				'condition' => [ 'uf_munkarend' => 'yes' ],
			]
		);

		$this->add_control(
			'uf_nyelv',
			[
				'label'        => 'Nyelv szűrő',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			]
		);
		$this->add_control(
			'uf_nyelv_label',
			[
				'label'     => 'Nyelv – felirat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Nyelv',
				'condition' => [ 'uf_nyelv' => 'yes' ],
			]
		);

		$this->add_control(
			'uf_dualis',
			[
				'label'        => 'Duális képzés szűrő',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			]
		);
		$this->add_control(
			'uf_dualis_label',
			[
				'label'     => 'Duális – felirat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Duális képzésben elérhető',
				'condition' => [ 'uf_dualis' => 'yes' ],
			]
		);

		$this->add_control(
			'uf_emelt',
			[
				'label'        => 'Emelt szintű érettségi szűrő',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			]
		);
		$this->add_control(
			'uf_emelt_label',
			[
				'label'     => 'Emelt érettségi – felirat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Emelt szintű érettségi kell',
				'condition' => [ 'uf_emelt' => 'yes' ],
			]
		);

		$this->add_control(
			'uf_bool_yes',
			[
				'label'     => 'Igen/Nem szűrők – „Igen” címke',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Igen',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'uf_bool_no',
			[
				'label'   => 'Igen/Nem szűrők – „Nem” címke',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Nem',
			]
		);

		$this->end_controls_section();

		// A szekció csak akkor jelenik meg, ha legalább egy szűrő be van kapcsolva
		// (OR-feltétel: az sima 'condition' tömb AND-et jelentene).
		$this->start_controls_section(
			'style_user_filters_section',
			[
				'label'      => 'Látogatói szűrőpanel',
				'tab'        => \Elementor\Controls_Manager::TAB_STYLE,
				'conditions' => [
					'relation' => 'or',
					'terms'    => [
						[ 'name' => 'uf_munkarend', 'operator' => '===', 'value' => 'yes' ],
						[ 'name' => 'uf_nyelv',     'operator' => '===', 'value' => 'yes' ],
						[ 'name' => 'uf_dualis',    'operator' => '===', 'value' => 'yes' ],
						[ 'name' => 'uf_emelt',     'operator' => '===', 'value' => 'yes' ],
					],
				],
			]
		);

		$this->add_control(
			'uf_panel_bg',
			[
				'label'     => 'Panel háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F7F9FB',
				'selectors' => [ '{{WRAPPER}} .sz-listing-filters' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'uf_panel_border_color',
			[
				'label'     => 'Panel keretszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#E3E8EE',
				'selectors' => [ '{{WRAPPER}} .sz-listing-filters' => 'border-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'uf_panel_text_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-listing-filters' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'uf_panel_accent',
			[
				'label'     => 'Jelölő színe (checkbox / rádió)',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-listing-filters .sz-lf-group input' => 'accent-color: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control(
			'uf_panel_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 10 ],
				'selectors'  => [ '{{WRAPPER}} .sz-listing-filters' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'uf_panel_margin',
			[
				'label'      => 'Alsó margó',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 24 ],
				'selectors'  => [ '{{WRAPPER}} .sz-listing-filters' => 'margin-bottom: {{SIZE}}{{UNIT}};' ],
			]
		);

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

		// --- Látogatói szűrők konfigurációja ------------------------------------
		// A négy ismert szűrő. A "bool" a boolean mezőkhöz Igen/Nem jelölőt ad;
		// a többihez a séma opció-listájából épül a jelölőnégyzet-sor. A course_data
		// kulcs eltérhet a szűrő-kulcstól (pl. "emelt" -> "emelt_kovetelmeny").
		$uf_label = function( $key, $default ) use ( $settings ) {
			return ( isset( $settings[ $key ] ) && trim( (string) $settings[ $key ] ) !== '' ) ? $settings[ $key ] : $default;
		};
		$uf_specs = array(
			'munkarend' => array(
				'on'    => ( isset( $settings['uf_munkarend'] ) && $settings['uf_munkarend'] === 'yes' ),
				'label' => $uf_label( 'uf_munkarend_label', 'Munkarend' ),
				'opts'  => $this->uf_schema_options( $schema, 'munkarend', array( 'Nappali', 'Levelező', 'Távoktatás' ) ),
				'bool'  => false,
			),
			'nyelv' => array(
				'on'    => ( isset( $settings['uf_nyelv'] ) && $settings['uf_nyelv'] === 'yes' ),
				'label' => $uf_label( 'uf_nyelv_label', 'Nyelv' ),
				'opts'  => $this->uf_schema_options( $schema, 'nyelv', array( 'Magyar', 'Angol' ) ),
				'bool'  => false,
			),
			'dualis' => array(
				'on'    => ( isset( $settings['uf_dualis'] ) && $settings['uf_dualis'] === 'yes' ),
				'label' => $uf_label( 'uf_dualis_label', 'Duális képzésben elérhető' ),
				'bool'  => true,
			),
			'emelt' => array(
				'on'    => ( isset( $settings['uf_emelt'] ) && $settings['uf_emelt'] === 'yes' ),
				'label' => $uf_label( 'uf_emelt_label', 'Emelt szintű érettségi kell' ),
				'bool'  => true,
			),
		);
		$uf_any = false;
		foreach ( $uf_specs as $s ) { if ( $s['on'] ) { $uf_any = true; break; } }
		$uf_yes = $uf_label( 'uf_bool_yes', 'Igen' );
		$uf_no  = $uf_label( 'uf_bool_no', 'Nem' );

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
		// A 'kepzesiterulet' az élő séma kulcsa (a korábbi 'kepzesi_terulet' elgépelés
		// sosem talált) - a szabadszavas keresés így ad neki pont-többletet.
		$priority_keys = ['kepzesi_forma', 'kulcsszavak', 'kepzesiterulet', 'indulas_idoszaka'];

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

			// Látogatói szűrőkhöz: Munkarend / Nyelv a beágyazott "munkarend_csoportok"
			// listából (tartalék az archivált felső szintű mező), a Duális / Emelt a
			// felső szintű boolean mezőkből. Csak akkor számoljuk, ha van bekapcsolt szűrő.
			$f_vals = array( 'munkarend' => array(), 'nyelv' => array(), 'dualis' => '0', 'emelt' => '0' );
			if ( $uf_any ) {
				$mr = array();
				$ny = array();
				if ( isset( $data['munkarend_csoportok'] ) && is_array( $data['munkarend_csoportok'] ) ) {
					foreach ( $data['munkarend_csoportok'] as $mg ) {
						if ( ! is_array( $mg ) ) continue;
						if ( isset( $mg['munkarend'] ) && trim( (string) $mg['munkarend'] ) !== '' ) {
							$mr[] = trim( (string) $mg['munkarend'] );
						}
						if ( isset( $mg['variansok'] ) && is_array( $mg['variansok'] ) ) {
							foreach ( $mg['variansok'] as $vr ) {
								if ( is_array( $vr ) && isset( $vr['nyelv'] ) && trim( (string) $vr['nyelv'] ) !== '' ) {
									$ny[] = trim( (string) $vr['nyelv'] );
								}
							}
						}
					}
				}
				if ( empty( $mr ) && isset( $data['munkarend'] ) ) {
					$mr = is_array( $data['munkarend'] ) ? $data['munkarend'] : array_map( 'trim', explode( ';', (string) $data['munkarend'] ) );
				}
				if ( empty( $ny ) && isset( $data['nyelv'] ) ) {
					$ny = is_array( $data['nyelv'] ) ? $data['nyelv'] : array_map( 'trim', explode( ';', (string) $data['nyelv'] ) );
				}
				$f_vals['munkarend'] = array_values( array_unique( array_filter( array_map( 'sanitize_title', $mr ) ) ) );
				$f_vals['nyelv']     = array_values( array_unique( array_filter( array_map( 'sanitize_title', $ny ) ) ) );
				$f_vals['dualis'] = ( isset( $data['dualis'] ) && self::uf_truthy( $data['dualis'] ) ) ? '1' : '0';
				$f_vals['emelt']  = ( isset( $data['emelt_kovetelmeny'] ) && self::uf_truthy( $data['emelt_kovetelmeny'] ) ) ? '1' : '0';
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
					'score'     => $score,
					'f'         => $f_vals,
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

		// A csoportok és a szakok rendezése a magyar ábécét követi (az „á" az „a"
		// után, a „b" előtt), nem a nyers bájtsorrendet - ott az ékezetes betűk a
		// lista végére csúsznának. Lásd hu_collate().
		if ( $settings['group_sort_order'] === 'desc' ) {
			uksort( $grouped_data, function( $a, $b ) { return $this->hu_collate( $b, $a ); } );
		} elseif ( $settings['group_sort_order'] === 'custom' && ! empty( $settings['custom_sort_list'] ) ) {
			$sorted_data = array();
			foreach ( $settings['custom_sort_list'] as $custom_item ) {
				$c_name = trim( $custom_item['group_name'] );
				if ( isset( $grouped_data[ $c_name ] ) ) {
					$sorted_data[ $c_name ] = $grouped_data[ $c_name ];
					unset( $grouped_data[ $c_name ] );
				}
			}
			uksort( $grouped_data, array( $this, 'hu_collate' ) );
			foreach ( $grouped_data as $rem_key => $rem_val ) {
				$sorted_data[ $rem_key ] = $rem_val;
			}
			$grouped_data = $sorted_data;
		} else {
			uksort( $grouped_data, array( $this, 'hu_collate' ) );
		}

		foreach ( $grouped_data as $key => $items ) {
			usort( $grouped_data[$key], function($a, $b) {
				if ( isset($a['score']) && isset($b['score']) && $a['score'] !== $b['score'] ) {
					return $b['score'] - $a['score'];
				}
				return $this->hu_collate( $a['title'], $b['title'] );
			});
		}

		$wid = $this->get_id();
		echo '<div class="sz-listing-widget" id="sz-listing-' . esc_attr( $wid ) . '">';

		echo $this->uf_render_panel( $uf_specs, $uf_any, $uf_yes, $uf_no, $uf_label, $wid );

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

				$li_data = '';
				if ( $uf_any ) {
					$fi = isset( $item['f'] ) ? $item['f'] : array();
					$li_data .= ' data-f-munkarend="' . esc_attr( implode( ' ', isset( $fi['munkarend'] ) ? $fi['munkarend'] : array() ) ) . '"';
					$li_data .= ' data-f-nyelv="' . esc_attr( implode( ' ', isset( $fi['nyelv'] ) ? $fi['nyelv'] : array() ) ) . '"';
					$li_data .= ' data-f-dualis="' . esc_attr( isset( $fi['dualis'] ) ? $fi['dualis'] : '0' ) . '"';
					$li_data .= ' data-f-emelt="' . esc_attr( isset( $fi['emelt'] ) ? $fi['emelt'] : '0' ) . '"';
				}

				echo '<li' . $li_data . ' style="display:flex; align-items:flex-start;">';
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

		echo '</div>'; // .sz-listing-grid / .sz-listing-single

		if ( $uf_any ) {
			echo $this->uf_render_script();
		}

		echo '</div>'; // .sz-listing-widget
	}

	// A séma bármely (akár beágyazott) mezőjének opció-listáját megkeresi kulcs
	// alapján; ha nincs / üres, a megadott tartalékot adja vissza. A Munkarend és a
	// Nyelv al-mezőként él a "munkarend_csoportok" repeaterben, ezért kell a mélységi
	// bejárás.
	private function uf_schema_options( $schema, $key, $fallback ) {
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
				$found = $this->uf_options_deep( $group['fields'], $key );
				if ( ! empty( $found ) ) return $found;
			}
		}
		return $fallback;
	}

	private function uf_options_deep( $fields, $key ) {
		foreach ( (array) $fields as $f ) {
			if ( ! is_array( $f ) ) continue;
			if ( isset( $f['key'] ) && $f['key'] === $key && ! empty( $f['options'] ) ) {
				return array_values( array_filter( array_map( 'trim', explode( ';', $f['options'] ) ), function( $o ) { return $o !== ''; } ) );
			}
			if ( ! empty( $f['sub_fields'] ) ) {
				$r = $this->uf_options_deep( $f['sub_fields'], $key );
				if ( ! empty( $r ) ) return $r;
			}
		}
		return array();
	}

	// Magyar ábécé szerinti összehasonlítás (uksort/usort callback). Ha elérhető az
	// intl kiterjesztés, a Collator (hu_HU) adja a helyes sorrendet - az "á" az "a"
	// után, a "b" előtt, a hosszú/rövid pár a helyén. E nélkül: ékezet-hajtogatás
	// (á->a, ő->o, ...) + kis-nagybetű-független bájt-összevetés, majd döntetlennél
	// az eredeti (ékezetes) alak, hogy a szerver LC_COLLATE-jétől függetlenül az
	// ékezetes betűk ne a lista végére kerüljenek.
	public function hu_collate( $a, $b ) {
		$a = (string) $a;
		$b = (string) $b;

		if ( class_exists( 'Collator' ) ) {
			static $coll = false;
			if ( $coll === false ) {
				$coll = collator_create( 'hu_HU' );
				if ( ! ( $coll instanceof Collator ) ) $coll = null;
			}
			if ( $coll ) {
				$r = $coll->compare( $a, $b );
				if ( $r !== false ) return $r;
			}
		}

		$fa = $this->hu_fold( $a );
		$fb = $this->hu_fold( $b );
		$r  = strcmp( $fa, $fb );
		if ( $r === 0 ) {
			// Azonos hajtogatott alak: az ékezetes változat a második helyre
			// (pl. "alma" < "álma").
			$r = strcmp( mb_strtolower( $a, 'UTF-8' ), mb_strtolower( $b, 'UTF-8' ) );
		}
		return $r;
	}

	private function hu_fold( $str ) {
		$str = mb_strtolower( trim( (string) $str ), 'UTF-8' );
		return strtr( $str, array(
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
			'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
		) );
	}

	// A plugin-konvenció szerinti "hamis" értékek (üres, "0", "false", "nem", stb.).
	// Ugyanazt tükrözi, amit a React szerkesztő toBoolValue()-ja és a PHP oldali
	// boolean-kezelés máshol.
	private static function uf_truthy( $v ) {
		if ( is_bool( $v ) ) return $v;
		if ( is_array( $v ) ) return ! empty( $v );
		$n = mb_strtolower( trim( (string) $v ), 'UTF-8' );
		return ! in_array( $n, array( '', '0', 'false', 'hamis', 'nem', 'no', 'n' ), true );
	}

	private function uf_render_panel( $uf_specs, $uf_any, $uf_yes, $uf_no, $uf_label, $wid ) {
		if ( ! $uf_any ) return '';

		$title = $uf_label( 'uf_title', 'Szűrés' );
		$reset = $uf_label( 'uf_reset_text', 'Szűrő törlése' );
		$no_res = $uf_label( 'uf_no_results_text', 'Nincs a szűrésnek megfelelő képzés.' );

		// A szerkezeti CSS-t widgetenként csak egyszer írjuk ki (több példány esetén is).
		// Színt NEM állít - azok a Stílus-vezérlők alapértékeiből jönnek, hogy a
		// {{WRAPPER}} szelektorú vezérlők felül tudják írni (azonos specificitás,
		// később a sorrendben).
		static $css_done = false;
		$out = '';
		if ( ! $css_done ) {
			$css_done = true;
			$out .= '<style>'
				. '.sz-lf-hidden{display:none !important;}'
				. '.sz-listing-filters{border-width:1px;border-style:solid;padding:16px 18px;box-sizing:border-box;}'
				. '.sz-listing-filters .sz-lf-head{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:12px;}'
				. '.sz-listing-filters .sz-lf-title{font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:.03em;}'
				. '.sz-listing-filters .sz-lf-reset{background:none;border:0;padding:0;cursor:pointer;font-size:12px;text-decoration:underline;color:inherit;opacity:.7;font-family:inherit;}'
				. '.sz-listing-filters .sz-lf-reset:hover{opacity:1;}'
				. '.sz-listing-filters .sz-lf-groups{display:flex;flex-wrap:wrap;gap:6px 32px;}'
				. '.sz-listing-filters .sz-lf-group{border:0;margin:0;padding:0;min-width:150px;}'
				. '.sz-listing-filters .sz-lf-group legend{font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.02em;opacity:.7;margin-bottom:4px;padding:0;}'
				. '.sz-listing-filters .sz-lf-group label{display:flex;align-items:center;gap:8px;font-size:14px;line-height:1.85;cursor:pointer;}'
				. '.sz-listing-filters .sz-lf-group input{width:16px;height:16px;margin:0;flex-shrink:0;}'
				. '</style>';
		}

		$out .= '<form class="sz-listing-filters" onsubmit="return false;">';
		$out .= '<div class="sz-lf-head"><span class="sz-lf-title">' . esc_html( $title ) . '</span>';
		$out .= '<button type="button" class="sz-lf-reset">' . esc_html( $reset ) . '</button></div>';
		$out .= '<div class="sz-lf-groups">';
		foreach ( $uf_specs as $fkey => $spec ) {
			if ( empty( $spec['on'] ) ) continue;
			$out .= '<fieldset class="sz-lf-group" data-key="' . esc_attr( $fkey ) . '">';
			$out .= '<legend>' . esc_html( $spec['label'] ) . '</legend>';
			if ( ! empty( $spec['bool'] ) ) {
				// Rádiógomb: Igen / Nem / (egyik sem). A "kivehető" viselkedést a JS adja
				// hozzá - a bejelöltre újra kattintva törli a jelölést. A name
				// widget-példányonként egyedi, hogy egy oldalon több Szaklista ne
				// ütközzön ugyanazon a csoporton.
				$rname = 'sz-lf-' . $fkey . '-' . $wid;
				$out .= '<label><input type="radio" name="' . esc_attr( $rname ) . '" value="1"> ' . esc_html( $uf_yes ) . '</label>';
				$out .= '<label><input type="radio" name="' . esc_attr( $rname ) . '" value="0"> ' . esc_html( $uf_no ) . '</label>';
			} else {
				foreach ( (array) $spec['opts'] as $opt ) {
					$slug = sanitize_title( $opt );
					if ( $slug === '' ) continue;
					$out .= '<label><input type="checkbox" value="' . esc_attr( $slug ) . '"> ' . esc_html( $opt ) . '</label>';
				}
			}
			$out .= '</fieldset>';
		}
		$out .= '</div></form>';
		$out .= '<p class="sz-listing-no-results sz-lf-hidden" style="font-style:italic; padding:16px 0;">' . esc_html( $no_res ) . '</p>';

		return $out;
	}

	// A kliensoldali szűrő-logika. Globálisan egyszer írjuk ki; minden
	// .sz-listing-widget konténert magától bekapcsol, és véd a dupla bekötés ellen.
	private function uf_render_script() {
		static $js_done = false;
		if ( $js_done ) return '';
		$js_done = true;

		return <<<'HTML'
<script>
(function(){
  function boot(root){
    if(!root || root.__szLf) return;
    var form = root.querySelector('.sz-listing-filters');
    if(!form) return;
    root.__szLf = true;
    var items  = [].slice.call(root.querySelectorAll('.sz-course-list > li'));
    var blocks = [].slice.call(root.querySelectorAll('.sz-group-block'));
    var noRes  = root.querySelector('.sz-listing-no-results');
    function selected(){
      var s = {};
      [].forEach.call(form.querySelectorAll('.sz-lf-group'), function(g){
        var k = g.getAttribute('data-key'), v = [];
        [].forEach.call(g.querySelectorAll('input'), function(cb){ if(cb.checked) v.push(cb.value); });
        if(v.length) s[k] = v;
      });
      return s;
    }
    function matches(li, s){
      for(var k in s){
        var have = (li.getAttribute('data-f-' + k) || '').split(' ').filter(Boolean);
        var ok = s[k].some(function(x){ return have.indexOf(x) > -1; });
        if(!ok) return false;
      }
      return true;
    }
    function apply(){
      var s = selected(), any = false;
      items.forEach(function(li){
        var vis = matches(li, s);
        li.classList.toggle('sz-lf-hidden', !vis);
        if(vis) any = true;
      });
      blocks.forEach(function(b){
        var vis = 0;
        [].forEach.call(b.querySelectorAll('.sz-course-list > li'), function(li){
          if(!li.classList.contains('sz-lf-hidden')) vis++;
        });
        b.classList.toggle('sz-lf-hidden', vis === 0);
      });
      if(noRes) noRes.classList.toggle('sz-lf-hidden', any);
    }
    form.addEventListener('change', apply);
    // Igen/Nem rádiógombok: a már bejelöltre újra kattintva kivehető a jelölés
    // (= "egyik se"). A rádió ezt natívan nem engedi. Csoportonként tartjuk az
    // aktív értéket; ha a click ugyanazt hozza, kivesszük. (A click a beviteli
    // mezőre a címke szövegére kattintva is lefut, ezért ez az út megbízható.)
    var radioState = {};
    [].forEach.call(form.querySelectorAll('.sz-lf-group'), function(g){
      var key = g.getAttribute('data-key');
      var pre = g.querySelector('input[type=radio]:checked');
      radioState[key] = pre ? pre.value : null;
      [].forEach.call(g.querySelectorAll('input[type=radio]'), function(r){
        r.addEventListener('click', function(){
          if(radioState[key] === r.value){
            r.checked = false;
            radioState[key] = null;
            apply();
          } else {
            radioState[key] = r.value;
          }
        });
      });
    });
    var reset = form.querySelector('.sz-lf-reset');
    if(reset) reset.addEventListener('click', function(){
      [].forEach.call(form.querySelectorAll('input'), function(cb){ cb.checked = false; });
      for(var k in radioState){ radioState[k] = null; }
      apply();
    });
    apply();
  }
  function initAll(){ [].forEach.call(document.querySelectorAll('.sz-listing-widget'), boot); }
  if(document.readyState !== 'loading') initAll();
  else document.addEventListener('DOMContentLoaded', initAll);
  window.addEventListener('load', initAll);
  if(window.elementorFrontend && window.elementorFrontend.hooks){
    elementorFrontend.hooks.addAction('frontend/element_ready/szeducate_listing.default', function($scope){
      var el = ($scope && $scope[0]) ? $scope[0].querySelector('.sz-listing-widget') : null;
      boot(el);
    });
  }
})();
</script>
HTML;
	}
}