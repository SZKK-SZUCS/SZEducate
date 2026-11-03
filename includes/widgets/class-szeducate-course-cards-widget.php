<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Kártyás (rácsos) képzéslista - a Szaklista widget mellett külön widget, hogy a meglévő
// példányok kinézete ne változzon. Kiemelt kép, cím, választható séma-mezőkből címkék,
// "Tovább" gomb és egy pulzáló állapot-pötty (meghirdetve / nem meghirdetve).
// Az adatforrás és az aktív/passzív logika ugyanaz, mint a Szaklistában.
class SZEducate_Course_Cards_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_course_cards'; }
	public function get_title() { return 'SZEducate Képzéskártyák'; }
	public function get_icon() { return 'eicon-posts-grid'; }
	public function get_categories() { return array( 'general' ); }

	protected function register_controls() {

		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema      = json_decode( $schema_json, true );

		$field_options   = array();
		$group_options   = array( '' => '-- Nincs csoportosítás --' );
		$dynamic_filters = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				foreach ( $group['fields'] as $field ) {
					if ( empty( $field['key'] ) ) {
						continue;
					}
					$label                         = ! empty( $field['label'] ) ? $field['label'] : $field['key'];
					$field_options[ $field['key'] ] = $label . ' [' . $field['key'] . ']';
					$group_options[ $field['key'] ] = $label;

					$type = isset( $field['type'] ) ? $field['type'] : '';
					if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) ) {
						$choices = array( '' => '-- Mindegy --' );
						foreach ( array_map( 'trim', explode( ';', $field['options'] ) ) as $opt ) {
							if ( $opt !== '' ) {
								$choices[ $opt ] = $opt;
							}
						}
						$dynamic_filters[] = array(
							'key'     => $field['key'],
							'label'   => $label,
							'choices' => $choices,
						);
					}
				}
			}
		}

		// ------------------------------------------------------------------
		// Tartalom: Lekérdezés
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'query_section',
			array(
				'label' => 'Lekérdezés és logika',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'visibility_key',
			array(
				'label'       => 'Láthatóság kapcsoló (séma-mező kulcsa)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'public',
				'description' => 'Csak azok a képzések jelennek meg, ahol ez a mező igaz értékű. Üresen hagyva nincs ilyen szűrés.',
			)
		);

		$this->add_control(
			'status_key',
			array(
				'label'   => 'Állapot kulcs',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'meghirdetes_allapota',
			)
		);

		$this->add_control(
			'date_key',
			array(
				'label'   => 'Passziválás dátuma',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'passziv_ettol',
			)
		);

		$this->add_control(
			'show_inactive',
			array(
				'label'        => 'Nem meghirdetett képzések mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => 'Kikapcsolva csak az aktív (meghirdetett) képzések jelennek meg.',
			)
		);

		$this->add_control(
			'group_by_key',
			array(
				'label'     => 'Csoportosítás (fejléc a kártyák fölött)',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $group_options,
				'default'   => '',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'group_sort_order',
			array(
				'label'     => 'Csoportok sorrendje',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'asc'  => 'A-Z (Növekvő)',
					'desc' => 'Z-A (Csökkenő)',
				),
				'default'   => 'asc',
				'condition' => array( 'group_by_key!' => '' ),
			)
		);

		if ( ! empty( $dynamic_filters ) ) {
			$this->add_control(
				'filter_heading',
				array(
					'label'     => 'Alap szűrők',
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			foreach ( $dynamic_filters as $filter ) {
				$this->add_control(
					'bf_' . $filter['key'],
					array(
						'label'   => 'Szűrés: ' . $filter['label'],
						'type'    => \Elementor\Controls_Manager::SELECT,
						'options' => $filter['choices'],
						'default' => '',
					)
				);
			}
		}

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Tartalom: Kártya
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'card_content_section',
			array(
				'label' => 'Kártya tartalma',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_image',
			array(
				'label'        => 'Kiemelt kép mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => 'Ha a képzésnek nincs kiemelt képe, a Beállítások oldalon megadott alapértelmezett kép jelenik meg.',
			)
		);

		$this->add_control(
			'chip_fields',
			array(
				'label'       => 'Címkék (pl. képzési forma, tudományterület)',
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $field_options,
				'default'     => array( 'kepzesi_forma', 'kepzesiterulet' ),
				'description' => 'A kiválasztott mezők értékei kis címkékként jelennek meg a cím fölött, a megadott sorrendben. Több értékű mezőnél (";"-vel elválasztva) mindegyik külön címke.',
			)
		);

		$this->add_control(
			'meta_fields',
			array(
				'label'       => 'További adatsorok („Felirat: érték”)',
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $field_options,
				'default'     => array(),
				'description' => 'Pl. indulás időszaka. Az üres értékű mezők kimaradnak.',
			)
		);

		$this->add_control(
			'show_button',
			array(
				'label'        => '„Tovább” gomb mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
				'description'  => 'Az egész kártya kattintható marad akkor is, ha a gomb rejtve van.',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => 'Gomb szövege',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Tovább',
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Tartalom: Állapot-pötty
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'status_dot_section',
			array(
				'label' => 'Állapot-pötty',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_dot',
			array(
				'label'        => 'Állapot-pötty mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'dot_position',
			array(
				'label'     => 'Pozíció',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'top-right' => 'Jobb fent',
					'top-left'  => 'Bal fent',
				),
				'default'   => 'top-right',
				'condition' => array( 'show_dot' => 'yes' ),
			)
		);

		$this->add_control(
			'dot_active_label',
			array(
				'label'       => 'Aktív képzés felirata (súgó)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Meghirdetve',
				'condition'   => array( 'show_dot' => 'yes' ),
				'description' => 'Egérrel a pöttyre állva jelenik meg, és a képernyőolvasók is ezt olvassák fel.',
			)
		);

		$this->add_control(
			'dot_inactive_label',
			array(
				'label'     => 'Nem meghirdetett képzés felirata (súgó)',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Jelenleg nem meghirdetett',
				'condition' => array( 'show_dot' => 'yes' ),
			)
		);

		$this->add_control(
			'show_inactive_dot',
			array(
				'label'        => 'Pötty a nem meghirdetett képzésen is',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_dot' => 'yes' ),
				'description'  => 'A nem meghirdetett képzés pöttye nem pulzál, a „Nem meghirdetett” színében jelenik meg.',
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Rács
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_grid_section',
			array(
				'label' => 'Elrendezés (rács)',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => 'Oszlopok száma',
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1 oszlop',
					'2' => '2 oszlop',
					'3' => '3 oszlop',
					'4' => '4 oszlop',
				),
				'selectors'      => array(
					'{{WRAPPER}} .sz-cc-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
			)
		);

		$this->add_responsive_control(
			'grid_gap',
			array(
				'label'      => 'Oszlopok közötti távolság',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 32 ),
				'selectors'  => array(
					'{{WRAPPER}} .sz-cc-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => 'Sorok közötti távolság',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 32 ),
				'selectors'  => array(
					'{{WRAPPER}} .sz-cc-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Csoportfejléc
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_group_section',
			array(
				'label'     => 'Csoportfejléc',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'group_by_key!' => '' ),
			)
		);

		$this->add_control(
			'group_title_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => array( '{{WRAPPER}} .sz-cc-group-title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'group_title_typography',
				'selector' => '{{WRAPPER}} .sz-cc-group-title',
			)
		);

		$this->add_responsive_control(
			'group_title_margin',
			array(
				'label'      => 'Alsó margó',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'default'    => array( 'unit' => 'px', 'size' => 20 ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-group-title' => 'margin: 0 0 {{SIZE}}{{UNIT}} 0;' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Kártya
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_card_section',
			array(
				'label' => 'Kártya',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => array( '{{WRAPPER}} .sz-cc-card' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => 'Belső margó (szöveges rész)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array( 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20, 'unit' => 'px' ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array( 'top' => 4, 'right' => 4, 'bottom' => 4, 'left' => 4, 'unit' => 'px' ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .sz-cc-card',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'           => 'card_box_shadow',
				'selector'       => '{{WRAPPER}} .sz-cc-card',
				'fields_options' => array(
					'box_shadow_type' => array( 'default' => 'yes' ),
					'box_shadow'      => array(
						'default' => array(
							'horizontal' => 0,
							'vertical'   => 6,
							'blur'       => 20,
							'spread'     => 0,
							'color'      => 'rgba(36, 41, 67, 0.10)',
						),
					),
				),
			)
		);

		$this->add_control(
			'card_hover_animation',
			array(
				'label'     => 'Hover animáció',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'none' => 'Nincs',
					'zoom' => 'Kép nagyítása',
					'lift' => 'Kártya megemelése',
					'both' => 'Mindkettő',
				),
				'default'   => 'both',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'image_ratio',
			array(
				'label'     => 'Kép képaránya',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'16/9' => '16:9',
					'3/2'  => '3:2',
					'4/3'  => '4:3',
					'1/1'  => '1:1 (négyzet)',
				),
				'default'   => '3/2',
				'condition' => array( 'show_image' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .sz-cc-image' => 'aspect-ratio: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Cím
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_title_section',
			array(
				'label' => 'Cím',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => array( '{{WRAPPER}} .sz-cc-title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .sz-cc-title',
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Címkék és adatsorok
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_chips_section',
			array(
				'label' => 'Címkék és adatsorok',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'chip_color',
			array(
				'label'     => 'Címke szövegszíne',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#044790',
				'selectors' => array( '{{WRAPPER}} .sz-cc-chip' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'chip_bg_color',
			array(
				'label'     => 'Címke háttérszíne',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#EAF3FA',
				'selectors' => array( '{{WRAPPER}} .sz-cc-chip' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'chip_typography',
				'selector' => '{{WRAPPER}} .sz-cc-chip',
			)
		);

		$this->add_control(
			'meta_color',
			array(
				'label'     => 'Adatsorok szövegszíne',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#5c6170',
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .sz-cc-meta' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'meta_typography',
				'selector' => '{{WRAPPER}} .sz-cc-meta',
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Gomb
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_button_section',
			array(
				'label'     => 'Gomb',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab( 'tab_button_normal', array( 'label' => 'Normál' ) );
		$this->add_control(
			'button_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => array( '{{WRAPPER}} .sz-cc-btn' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_bg_color',
			array(
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => array( '{{WRAPPER}} .sz-cc-btn' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		// A hover a teljes kártyára vonatkozik (a gomb a kártya egészét linkeli).
		$this->start_controls_tab( 'tab_button_hover', array( 'label' => 'Hover' ) );
		$this->add_control(
			'button_hover_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .sz-cc-card:hover .sz-cc-btn' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_hover_bg_color',
			array(
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => array( '{{WRAPPER}} .sz-cc-card:hover .sz-cc-btn' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'button_typography',
				'selector'  => '{{WRAPPER}} .sz-cc-btn',
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => 'Belső margó',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array( 'top' => 9, 'right' => 20, 'bottom' => 9, 'left' => 20, 'unit' => 'px' ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array( 'top' => 6, 'right' => 6, 'bottom' => 6, 'left' => 6, 'unit' => 'px' ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Állapot-pötty
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_dot_section',
			array(
				'label'     => 'Állapot-pötty',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_dot' => 'yes' ),
			)
		);

		$this->add_control(
			'dot_active_color',
			array(
				'label'     => 'Meghirdetett (pulzáló) szín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#22C55E',
				'selectors' => array( '{{WRAPPER}} .sz-cc-dot-active' => '--sz-cc-dot: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'dot_inactive_color',
			array(
				'label'     => 'Nem meghirdetett szín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#9CA3AF',
				'selectors' => array( '{{WRAPPER}} .sz-cc-dot-inactive' => '--sz-cc-dot: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'dot_size',
			array(
				'label'      => 'Méret',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 6, 'max' => 32 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 14 ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'dot_offset',
			array(
				'label'      => 'Távolság a saroktól',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 14 ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-card' => '--sz-cc-dot-offset: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'dot_pulse_speed',
			array(
				'label'      => 'Pulzálás ideje (mp)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0.6, 'max' => 4, 'step' => 0.1 ) ),
				'default'    => array( 'unit' => 's', 'size' => 1.8 ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-dot-active::after' => 'animation-duration: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$vis_key    = isset( $settings['visibility_key'] ) ? trim( $settings['visibility_key'] ) : '';
		$status_key = ! empty( $settings['status_key'] ) ? trim( $settings['status_key'] ) : 'meghirdetes_allapota';
		$date_key   = ! empty( $settings['date_key'] ) ? trim( $settings['date_key'] ) : 'passziv_ettol';
		$group_key  = isset( $settings['group_by_key'] ) ? trim( $settings['group_by_key'] ) : '';

		$show_inactive = ( ! isset( $settings['show_inactive'] ) || $settings['show_inactive'] === 'yes' );

		$chip_fields = ( ! empty( $settings['chip_fields'] ) && is_array( $settings['chip_fields'] ) ) ? $settings['chip_fields'] : array();
		$meta_fields = ( ! empty( $settings['meta_fields'] ) && is_array( $settings['meta_fields'] ) ) ? $settings['meta_fields'] : array();

		// Mező-címkék az adatsorokhoz (a séma "label"-je).
		$labels = array();
		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				foreach ( $group['fields'] as $field ) {
					if ( ! empty( $field['key'] ) ) {
						$labels[ $field['key'] ] = ! empty( $field['label'] ) ? $field['label'] : $field['key'];
					}
				}
			}
		}

		// Alap szűrők: a widgetben beállított 'bf_<kulcs>' vezérlők, mindig érvényesek.
		$base_filters = array();
		foreach ( $settings as $key => $val ) {
			if ( strpos( $key, 'bf_' ) === 0 && ! empty( $val ) ) {
				$base_filters[ substr( $key, 3 ) ] = sanitize_title( $val );
			}
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$courses = SZEducate_Client::get_permitted_courses_data();

		$today_time = strtotime( current_time( 'Y-m-d' ) );
		$grouped    = array();

		foreach ( $courses as $course ) {
			$post_id = $course['local_post_id'];
			if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
				continue;
			}

			$data = json_decode( $course['course_data'], true );
			if ( ! is_array( $data ) ) {
				continue;
			}

			if ( $vis_key !== '' && isset( $data[ $vis_key ] ) && ! self::is_truthy( $data[ $vis_key ] ) ) {
				continue;
			}

			$passes = true;
			foreach ( $base_filters as $f_key => $f_slug ) {
				$slugs = array_map( 'sanitize_title', $this->field_values( $data, $f_key ) );
				if ( ! in_array( $f_slug, $slugs, true ) ) {
					$passes = false;
					break;
				}
			}
			if ( ! $passes ) {
				continue;
			}

			$status    = isset( $data[ $status_key ] ) ? mb_strtolower( trim( (string) $data[ $status_key ] ), 'UTF-8' ) : '';
			$is_active = ( $status === 'aktív' || $status === 'aktiv' );

			$passziv_ettol = isset( $data[ $date_key ] ) ? trim( (string) $data[ $date_key ] ) : '';
			if ( $is_active && $passziv_ettol !== '' ) {
				$p_time = strtotime( $passziv_ettol );
				if ( $p_time !== false && $today_time >= $p_time ) {
					$is_active = false;
				}
			}

			if ( ! $is_active && ! $show_inactive ) {
				continue;
			}

			$chips = array();
			foreach ( $chip_fields as $ck ) {
				foreach ( $this->field_values( $data, $ck ) as $v ) {
					if ( ! in_array( $v, $chips, true ) ) {
						$chips[] = $v;
					}
				}
			}

			$meta = array();
			foreach ( $meta_fields as $mk ) {
				$vals = $this->field_values( $data, $mk );
				if ( ! empty( $vals ) ) {
					$meta[] = array(
						'label' => isset( $labels[ $mk ] ) ? $labels[ $mk ] : $mk,
						'value' => implode( ', ', $vals ),
					);
				}
			}

			$card = array(
				'post_id'   => $post_id,
				'title'     => $course['title'],
				'url'       => get_permalink( $post_id ),
				'is_active' => $is_active,
				'chips'     => $chips,
				'meta'      => $meta,
			);

			if ( $group_key !== '' ) {
				$names = $this->field_values( $data, $group_key );
				if ( empty( $names ) ) {
					$names = array( 'Egyéb' );
				}
				foreach ( $names as $name ) {
					$grouped[ $name ][] = $card;
				}
			} else {
				$grouped[''][] = $card;
			}
		}

		if ( empty( $grouped ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">A beállított szűrőknek megfelelő képzés jelenleg nem található.</p>';
			} else {
				echo '<p style="color:#888; font-style:italic; padding:20px; text-align:center;">Jelenleg nincs megjeleníthető képzés.</p>';
			}
			return;
		}

		if ( $group_key !== '' ) {
			$desc = ( isset( $settings['group_sort_order'] ) && $settings['group_sort_order'] === 'desc' );
			uksort(
				$grouped,
				function ( $a, $b ) use ( $desc ) {
					return $desc ? $this->hu_collate( $b, $a ) : $this->hu_collate( $a, $b );
				}
			);
		}

		foreach ( $grouped as $name => $cards ) {
			usort(
				$cards,
				function ( $a, $b ) {
					return $this->hu_collate( $a['title'], $b['title'] );
				}
			);
			$grouped[ $name ] = $cards;
		}

		$this->render_base_css();

		$hover      = ! empty( $settings['card_hover_animation'] ) ? $settings['card_hover_animation'] : 'both';
		$card_class = 'sz-cc-card sz-cc-hover-' . $hover;

		echo '<div class="sz-cc-widget">';
		foreach ( $grouped as $name => $cards ) {
			echo '<div class="sz-cc-group">';
			if ( $group_key !== '' ) {
				echo '<h3 class="sz-cc-group-title">' . esc_html( $name ) . '</h3>';
			}
			echo '<div class="sz-cc-grid">';
			foreach ( $cards as $card ) {
				$this->render_card( $card, $card_class, $settings );
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	private function render_card( $card, $card_class, $settings ) {
		$show_image = ( ! isset( $settings['show_image'] ) || $settings['show_image'] === 'yes' );
		$show_btn   = ( ! isset( $settings['show_button'] ) || $settings['show_button'] === 'yes' );
		$show_dot   = ( ! isset( $settings['show_dot'] ) || $settings['show_dot'] === 'yes' );
		$btn_text   = isset( $settings['button_text'] ) && trim( $settings['button_text'] ) !== '' ? $settings['button_text'] : 'Tovább';

		$classes = $card_class . ( $card['is_active'] ? ' sz-cc-is-active' : ' sz-cc-is-inactive' );

		echo '<article class="' . esc_attr( $classes ) . '">';

		if ( $show_dot && ( $card['is_active'] || ( isset( $settings['show_inactive_dot'] ) && $settings['show_inactive_dot'] === 'yes' ) ) ) {
			$pos   = ( isset( $settings['dot_position'] ) && $settings['dot_position'] === 'top-left' ) ? 'sz-cc-dot-left' : 'sz-cc-dot-right';
			$state = $card['is_active'] ? 'sz-cc-dot-active' : 'sz-cc-dot-inactive';
			$text  = $card['is_active']
				? ( ! empty( $settings['dot_active_label'] ) ? $settings['dot_active_label'] : 'Meghirdetve' )
				: ( ! empty( $settings['dot_inactive_label'] ) ? $settings['dot_inactive_label'] : 'Jelenleg nem meghirdetett' );
			echo '<span class="sz-cc-dot ' . esc_attr( $state . ' ' . $pos ) . '" role="img" title="' . esc_attr( $text ) . '" aria-label="' . esc_attr( $text ) . '"></span>';
		}

		if ( $show_image ) {
			$plugin_options    = get_option( 'szeducate_settings', array() );
			$fallback_image_id = ! empty( $plugin_options['featured_default_image_id'] ) ? intval( $plugin_options['featured_default_image_id'] ) : 0;

			echo '<div class="sz-cc-image">';
			if ( has_post_thumbnail( $card['post_id'] ) ) {
				echo get_the_post_thumbnail( $card['post_id'], 'medium_large' );
			} elseif ( $fallback_image_id ) {
				echo wp_get_attachment_image( $fallback_image_id, 'medium_large' );
			}
			echo '</div>';
		}

		echo '<div class="sz-cc-body">';

		if ( ! empty( $card['chips'] ) ) {
			echo '<div class="sz-cc-chips">';
			foreach ( $card['chips'] as $chip ) {
				echo '<span class="sz-cc-chip">' . esc_html( $chip ) . '</span>';
			}
			echo '</div>';
		}

		echo '<h3 class="sz-cc-title">' . esc_html( $card['title'] ) . '</h3>';

		foreach ( $card['meta'] as $m ) {
			echo '<div class="sz-cc-meta"><strong>' . esc_html( $m['label'] ) . ':</strong> ' . esc_html( $m['value'] ) . '</div>';
		}

		// A link a kártya egészét lefedi (a .sz-cc-btn::before nyújtja ki). Ha a gomb
		// rejtett, egy képernyőolvasónak szóló (vizuálisan rejtett) link fedi le a kártyát,
		// hogy az akkor is kattintható és billentyűvel elérhető maradjon.
		if ( $show_btn ) {
			echo '<a class="sz-cc-btn" href="' . esc_url( $card['url'] ) . '">' . esc_html( $btn_text )
				. '<span class="sz-cc-sr"> – ' . esc_html( $card['title'] ) . '</span></a>';
		} else {
			echo '<a class="sz-cc-fill" href="' . esc_url( $card['url'] ) . '"><span class="sz-cc-sr">' . esc_html( $card['title'] ) . '</span></a>';
		}

		echo '</div>'; // .sz-cc-body
		echo '</article>';
	}

	// Szerkezeti CSS - színt/térközt nem állít, azok a Stílus-vezérlőkből jönnek. Widget-
	// példányonként nem ismétlődik (statikus zászló), mint a Szaklista / Kiemelt widgeteknél.
	private function render_base_css() {
		static $css_done = false;
		if ( $css_done ) {
			return;
		}
		$css_done = true;

		echo '<style>'
			. '.sz-cc-grid{display:grid;}'
			. '.sz-cc-group{margin-bottom:32px;}.sz-cc-group:last-child{margin-bottom:0;}'
			. '.sz-cc-card{position:relative;display:flex;flex-direction:column;overflow:hidden;box-sizing:border-box;transition:transform .3s ease,box-shadow .3s ease;}'
			. '.sz-cc-hover-lift:hover,.sz-cc-hover-both:hover{transform:translateY(-5px);}'
			. '.sz-cc-image{width:100%;overflow:hidden;background:#eef1f5;}'
			. '.sz-cc-image img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .4s ease;}'
			. '.sz-cc-hover-zoom:hover .sz-cc-image img,.sz-cc-hover-both:hover .sz-cc-image img{transform:scale(1.06);}'
			. '.sz-cc-body{display:flex;flex-direction:column;flex:1;align-items:flex-start;gap:10px;}'
			. '.sz-cc-chips{display:flex;flex-wrap:wrap;gap:6px;}'
			. '.sz-cc-chip{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;line-height:1.5;font-weight:600;}'
			. '.sz-cc-title{margin:0;font-size:20px;line-height:1.3;}'
			. '.sz-cc-meta{font-size:14px;line-height:1.5;}'
			. '.sz-cc-btn{position:static;display:inline-block;margin-top:auto;text-decoration:none;font-weight:600;transition:background-color .2s,color .2s;}'
			. '.sz-cc-btn::before,.sz-cc-fill::before{content:"";position:absolute;inset:0;z-index:1;}'
			. '.sz-cc-fill{position:static;}'
			. '.sz-cc-btn:focus-visible::before,.sz-cc-fill:focus-visible::before{outline:3px solid #50ADC9;outline-offset:-3px;}'
			. '.sz-cc-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}'
			// Állapot-pötty: a --sz-cc-dot szín a Stílus-vezérlőkből jön; a pulzáló gyűrű az ::after.
			. '.sz-cc-dot{position:absolute;z-index:2;top:var(--sz-cc-dot-offset,14px);width:14px;height:14px;border-radius:50%;background:var(--sz-cc-dot,#22C55E);box-shadow:0 0 0 2px #fff;}'
			. '.sz-cc-dot-right{right:var(--sz-cc-dot-offset,14px);}'
			. '.sz-cc-dot-left{left:var(--sz-cc-dot-offset,14px);}'
			. '.sz-cc-dot-active::after{content:"";position:absolute;inset:0;border-radius:50%;background:var(--sz-cc-dot,#22C55E);animation:sz-cc-pulse 1.8s ease-out infinite;}'
			. '@keyframes sz-cc-pulse{0%{transform:scale(1);opacity:.65;}100%{transform:scale(2.6);opacity:0;}}'
			. '@media(prefers-reduced-motion:reduce){.sz-cc-dot-active::after{animation:none;}.sz-cc-card,.sz-cc-image img{transition:none;}.sz-cc-hover-lift:hover,.sz-cc-hover-both:hover{transform:none;}}'
			. '</style>';
	}

	// Egy mező értékeit szöveg-listává egyszerűsíti: tömbből a skalárokat, szövegből a
	// ";"-vel elválasztott részeket veszi; az üres és a logikai értékeket kihagyja.
	private function field_values( $data, $key ) {
		if ( $key === '' || ! isset( $data[ $key ] ) ) {
			return array();
		}
		$raw   = $data[ $key ];
		$parts = is_array( $raw ) ? $raw : explode( ';', (string) $raw );
		$out   = array();
		foreach ( $parts as $p ) {
			if ( ! is_scalar( $p ) || is_bool( $p ) ) {
				continue;
			}
			$p = trim( (string) $p );
			if ( $p !== '' ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	// Magyar ábécé szerinti összehasonlítás - ugyanaz a minta, mint a Szaklista widget
	// hu_collate()-je (class-szeducate-listing-widget.php).
	private function hu_collate( $a, $b ) {
		$a = (string) $a;
		$b = (string) $b;

		if ( class_exists( 'Collator' ) ) {
			static $coll = false;
			if ( $coll === false ) {
				$coll = collator_create( 'hu_HU' );
				if ( ! ( $coll instanceof Collator ) ) {
					$coll = null;
				}
			}
			if ( $coll ) {
				$r = $coll->compare( $a, $b );
				if ( $r !== false ) {
					return $r;
				}
			}
		}

		$fa = $this->hu_fold( $a );
		$fb = $this->hu_fold( $b );
		$r  = strcmp( $fa, $fb );
		if ( $r === 0 ) {
			$r = strcmp( mb_strtolower( $a, 'UTF-8' ), mb_strtolower( $b, 'UTF-8' ) );
		}
		return $r;
	}

	private function hu_fold( $str ) {
		$str = mb_strtolower( trim( (string) $str ), 'UTF-8' );
		return strtr(
			$str,
			array(
				'á' => 'a',
				'é' => 'e',
				'í' => 'i',
				'ó' => 'o',
				'ö' => 'o',
				'ő' => 'o',
				'ú' => 'u',
				'ü' => 'u',
				'ű' => 'u',
			)
		);
	}

	// A plugin boolean-konvenciója (üres / "0" / "false" / "hamis" / "nem" stb. = hamis) -
	// ugyanaz a minta, mint a Szaklista uf_truthy()-ja.
	private static function is_truthy( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_array( $v ) ) {
			return ! empty( $v );
		}
		$n = mb_strtolower( trim( (string) $v ), 'UTF-8' );
		return ! in_array( $n, array( '', '0', 'false', 'hamis', 'nem', 'no', 'n' ), true );
	}
}
