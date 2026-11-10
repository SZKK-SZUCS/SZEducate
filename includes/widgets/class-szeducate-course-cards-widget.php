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

		$this->add_control(
			'show_active_filter',
			array(
				'label'        => 'Aktív szűrő kiírása a lista fölött',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
				'description'  => 'Csak az EXTRA szűrést írja ki (kereső ?sz_search= / kategórialink). A widgetben beállított alap szűrő nem jelenik meg.',
			)
		);

		$this->add_control(
			'reset_filter_text',
			array(
				'label'       => 'Szűrő-törlő link szövege',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Szűrő törlése',
				'condition'   => array( 'show_active_filter' => 'yes' ),
				'description' => 'Az aktív szűrő jelzése mellett jelenik meg; visszavezet az alap nézetre. Üresen hagyva nincs link.',
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

		// A korábbi 'meta_fields' (egyszerű többes választó) helyett ismétlő: soronként
		// állítható a felirat és a kiírt szöveg. A régi beállítás a rendereléskor még
		// érvényes marad (lásd get_meta_rows()), így a már elmentett oldalak nem változnak.
		$meta_repeater = new \Elementor\Repeater();

		$meta_repeater->add_control(
			'field',
			array(
				'label'       => 'Mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => array( '' => '— válassz —' ) + $field_options,
				'default'     => '',
			)
		);

		$meta_repeater->add_control(
			'show_label',
			array(
				'label'        => 'Felirat mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$meta_repeater->add_control(
			'label_text',
			array(
				'label'       => 'Egyedi felirat',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => 'Üresen a mező címkéje',
				'condition'   => array( 'show_label' => 'yes' ),
			)
		);

		$meta_repeater->add_control(
			'format',
			array(
				'label'       => 'Kiírt szöveg (sablon)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => '{value}',
				'description' => 'Üresen az érték jelenik meg. A {value} az értéket, a {label} a feliratot helyettesíti, pl. „{value} félév”.',
			)
		);

		$this->add_control(
			'meta_rows',
			array(
				'label'       => 'További adatsorok',
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $meta_repeater->get_controls(),
				'title_field' => '{{{ field }}}',
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
		// Tartalom: Látogatói szűrők (panel)
		// ------------------------------------------------------------------
		// Az "Alap szűrők" fent az admin által rögzített, a látogatónak nem látszó szűrés.
		// Ez a szekció egy a kártyák fölött megjelenő, kliensoldali (JS, újratöltés nélküli)
		// szűrőpanelt kapcsol be, amit a látogató állítgat - a Szaklista panelje szerint.
		$this->start_controls_section(
			'user_filters_section',
			array(
				'label' => 'Látogatói szűrők (panel)',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'uf_title',
			array(
				'label'   => 'Panel címe',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Szűrés',
			)
		);

		$this->add_control(
			'uf_reset_text',
			array(
				'label'   => 'Szűrő-törlő link szövege',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Szűrő törlése',
			)
		);

		$this->add_control(
			'uf_no_results_text',
			array(
				'label'   => '„Nincs találat” szöveg',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Nincs a szűrésnek megfelelő képzés.',
			)
		);

		foreach (
			array(
				'munkarend' => array( 'Munkarend szűrő', 'Munkarend', 'Munkarend' ),
				'nyelv'     => array( 'Nyelv szűrő', 'Nyelv', 'Nyelv' ),
				'dualis'    => array( 'Duális képzés szűrő', 'Duális', 'Duális képzésben elérhető' ),
				'emelt'     => array( 'Emelt szintű érettségi szűrő', 'Emelt érettségi', 'Emelt szintű érettségi kell' ),
			) as $fkey => $meta
		) {
			$this->add_control(
				'uf_' . $fkey,
				array(
					'label'        => $meta[0],
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => 'Be',
					'label_off'    => 'Ki',
					'return_value' => 'yes',
					'default'      => '',
					'separator'    => 'before',
				)
			);
			$this->add_control(
				'uf_' . $fkey . '_label',
				array(
					'label'     => $meta[1] . ' – felirat',
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => $meta[2],
					'condition' => array( 'uf_' . $fkey => 'yes' ),
				)
			);
		}

		// Kereső mező + tetszőleges séma-mezőből felvehető extra szűrők (közös a Szaklistával).
		SZEducate_Filter_Extras::register_controls( $this, $schema );

		$this->add_control(
			'uf_bool_yes',
			array(
				'label'     => 'Igen/Nem szűrők – „Igen” címke',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Igen',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'uf_bool_no',
			array(
				'label'   => 'Igen/Nem szűrők – „Nem” címke',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Nem',
			)
		);

		$this->end_controls_section();

		// Nincs megjelenítési feltétel: a panel az extra szűrők (ismétlődő vezérlő) vagy a kereső
		// mező miatt is megjelenhet, és az Elementor 'conditions' feltétele egy ismétlődő
		// vezérlő "van benne elem" állapotára nem megbízható - ezért a szekció mindig látszik.
		$this->start_controls_section(
			'style_user_filters_section',
			array(
				'label' => 'Látogatói szűrőpanel',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'uf_panel_bg',
			array(
				'label'     => 'Panel háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F7F9FB',
				'selectors' => array( '{{WRAPPER}} .sz-cc-filters' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'uf_panel_border_color',
			array(
				'label'     => 'Panel keretszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#E3E8EE',
				'selectors' => array( '{{WRAPPER}} .sz-cc-filters' => 'border-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'uf_panel_text_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => array( '{{WRAPPER}} .sz-cc-filters' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'uf_panel_accent',
			array(
				'label'     => 'Kijelölt elem színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#044790',
				'selectors' => array( '{{WRAPPER}} .sz-cc-filters' => '--sz-cc-accent: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'uf_panel_radius',
			array(
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'default'    => array( 'unit' => 'px', 'size' => 10 ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-filters' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'uf_panel_margin',
			array(
				'label'      => 'Alsó margó',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 24 ),
				'selectors'  => array( '{{WRAPPER}} .sz-cc-filters' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
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
		$meta_rows   = $this->get_meta_rows( $settings );

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

		// ?sz_group=<mező-kulcs> felülírja a widget csoportosítását (mint a Szaklistában). Csak
		// létező séma-mezőt fogadunk el, hogy egy tetszőleges paraméter ne torzítsa a nézetet.
		if ( isset( $_GET['sz_group'] ) ) {
			$req_group = sanitize_text_field( wp_unslash( $_GET['sz_group'] ) );
			if ( $req_group === '' || isset( $labels[ $req_group ] ) ) {
				$group_key = $req_group;
			}
		}

		// Alap szűrők: a widgetben beállított 'bf_<kulcs>' vezérlők, mindig érvényesek.
		$base_filters = array();
		foreach ( $settings as $key => $val ) {
			if ( strpos( $key, 'bf_' ) === 0 && ! empty( $val ) ) {
				$base_filters[ substr( $key, 3 ) ] = sanitize_title( $val );
			}
		}

		// Extra szűrés kategórialinkből (/oldal/<mező>/<érték>/, a plugin rewrite szabálya):
		// a Szaklista viselkedése szerint ez FELÜLÍRJA az alap szűrőket.
		$seo_field   = get_query_var( 'sz_seo_field' );
		$seo_keyword = get_query_var( 'sz_seo_keyword' );
		$seo_active  = ( ! empty( $seo_field ) && ! empty( $seo_keyword ) );

		$active_filters = $seo_active
			? array( sanitize_text_field( $seo_field ) => sanitize_title( $seo_keyword ) )
			: $base_filters;

		// Szabadszavas keresés (?sz_search=) - a Szaklista Kereső widget ide irányít.
		$free_text = isset( $_GET['sz_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['sz_search'] ) ) ) : '';

		$badge_enabled = ( ! isset( $settings['show_active_filter'] ) || $settings['show_active_filter'] === 'yes' );
		$reset_label   = isset( $settings['reset_filter_text'] ) ? trim( (string) $settings['reset_filter_text'] ) : 'Szűrő törlése';
		$badge_html    = $badge_enabled ? $this->active_filter_badge( $free_text, $seo_active, $active_filters, $schema, $reset_label ) : '';

		// Látogatói szűrők konfigurációja (a Szaklista panelje szerint). A "bool" szűrők
		// Igen/Nem jelölőt kapnak, a többi a séma opció-listájából épülő jelölőnégyzet-sort.
		$uf_label = function ( $key, $default ) use ( $settings ) {
			return ( isset( $settings[ $key ] ) && trim( (string) $settings[ $key ] ) !== '' ) ? $settings[ $key ] : $default;
		};
		$uf_specs = array(
			'munkarend' => array(
				'on'    => ( isset( $settings['uf_munkarend'] ) && $settings['uf_munkarend'] === 'yes' ),
				'label' => $uf_label( 'uf_munkarend_label', 'Munkarend' ),
				'opts'  => $this->uf_schema_options( $schema, 'munkarend', array( 'Nappali', 'Levelező', 'Távoktatás' ) ),
				'bool'  => false,
			),
			'nyelv'     => array(
				'on'    => ( isset( $settings['uf_nyelv'] ) && $settings['uf_nyelv'] === 'yes' ),
				'label' => $uf_label( 'uf_nyelv_label', 'Nyelv' ),
				'opts'  => $this->uf_schema_options( $schema, 'nyelv', array( 'Magyar', 'Angol' ) ),
				'bool'  => false,
			),
			'dualis'    => array(
				'on'    => ( isset( $settings['uf_dualis'] ) && $settings['uf_dualis'] === 'yes' ),
				'label' => $uf_label( 'uf_dualis_label', 'Duális képzésben elérhető' ),
				'bool'  => true,
			),
			'emelt'     => array(
				'on'    => ( isset( $settings['uf_emelt'] ) && $settings['uf_emelt'] === 'yes' ),
				'label' => $uf_label( 'uf_emelt_label', 'Emelt szintű érettségi kell' ),
				'bool'  => true,
			),
		);
		$uf_any = false;
		foreach ( $uf_specs as $s ) {
			if ( $s['on'] ) {
				$uf_any = true;
				break;
			}
		}

		// Séma-mezőből felvett extra szűrők és a panel kereső mezője (közös a Szaklistával).
		$uf_extras = SZEducate_Filter_Extras::build_extras( $settings, $schema );
		$uf_search = SZEducate_Filter_Extras::build_search( $settings );
		if ( ! empty( $uf_extras ) || $uf_search['on'] ) {
			$uf_any = true;
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

			$score = 0;
			if ( $free_text !== '' ) {
				$score = $this->search_score( $course['title'], $data, $free_text );
				if ( $score === 0 ) {
					continue;
				}
			}

			$passes = true;
			foreach ( $active_filters as $f_key => $f_slug ) {
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
			foreach ( $meta_rows as $row ) {
				$mk   = $row['key'];
				$vals = $this->field_values( $data, $mk );
				if ( empty( $vals ) ) {
					continue;
				}

				$field_label = isset( $labels[ $mk ] ) ? $labels[ $mk ] : $mk;
				// Elrejtett felirat esetén nincs "Felirat:" előtag; különben az egyedi, üresen a séma-címke.
				$label = $row['show_label'] ? ( $row['label'] !== '' ? $row['label'] : $field_label ) : '';
				$value = implode( ', ', $vals );
				// Sima szövegcsere (nem regex), a kimenet escape-elése a rendereléskor történik.
				$text = $row['format'] !== '' ? strtr( $row['format'], array( '{value}' => $value, '{label}' => $label ) ) : $value;

				$meta[] = array(
					// Ha a sablon maga tartalmazza a {label}-t, a szerző döntött a helyéről: nincs külön előtag.
					'label' => ( $label !== '' && strpos( $row['format'], '{label}' ) === false ) ? $label : '',
					'text'  => $text,
				);
			}

			$card = array(
				'post_id'   => $post_id,
				'title'     => $course['title'],
				'url'       => get_permalink( $post_id ),
				'is_active' => $is_active,
				'chips'     => $chips,
				'meta'      => $meta,
				'score'     => $score,
				'f'         => $uf_any ? $this->uf_course_values( $data ) : null,
				'xf'        => $uf_any ? SZEducate_Filter_Extras::course_values( $course['title'], $data, $uf_extras, $uf_search ) : null,
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
				echo $badge_html;
				$none_text = ( $free_text !== '' || $seo_active ) ? 'Sajnos nem találtunk a keresésnek megfelelő képzést.' : 'Jelenleg nincs megjeleníthető képzés.';
				echo '<p style="color:#888; font-style:italic; padding:20px; text-align:center;">' . esc_html( $none_text ) . '</p>';
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
					if ( $a['score'] !== $b['score'] ) {
						return $b['score'] - $a['score'];
					}
					return $this->hu_collate( $a['title'], $b['title'] );
				}
			);
			$grouped[ $name ] = $cards;
		}

		$this->render_base_css();

		$hover      = ! empty( $settings['card_hover_animation'] ) ? $settings['card_hover_animation'] : 'both';
		$card_class = 'sz-cc-card sz-cc-hover-' . $hover;

		echo '<div class="sz-cc-widget">';
		echo $this->uf_render_panel( $uf_specs, $uf_any, $uf_label( 'uf_bool_yes', 'Igen' ), $uf_label( 'uf_bool_no', 'Nem' ), $uf_label, $this->get_id(), $uf_extras, $uf_search );
		echo $badge_html;
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

		if ( $uf_any ) {
			echo $this->uf_render_script();
		}

		echo '</div>';
	}

	private function render_card( $card, $card_class, $settings ) {
		$show_image = ( ! isset( $settings['show_image'] ) || $settings['show_image'] === 'yes' );
		$show_btn   = ( ! isset( $settings['show_button'] ) || $settings['show_button'] === 'yes' );
		$show_dot   = ( ! isset( $settings['show_dot'] ) || $settings['show_dot'] === 'yes' );
		$btn_text   = isset( $settings['button_text'] ) && trim( $settings['button_text'] ) !== '' ? $settings['button_text'] : 'Tovább';

		$classes = $card_class . ( $card['is_active'] ? ' sz-cc-is-active' : ' sz-cc-is-inactive' );

		$data_attrs = '';
		if ( ! empty( $card['f'] ) ) {
			foreach ( array( 'munkarend', 'nyelv' ) as $fk ) {
				$data_attrs .= ' data-f-' . $fk . '="' . esc_attr( implode( ' ', $card['f'][ $fk ] ) ) . '"';
			}
			foreach ( array( 'dualis', 'emelt' ) as $fk ) {
				$data_attrs .= ' data-f-' . $fk . '="' . esc_attr( $card['f'][ $fk ] ) . '"';
			}
		}

		if ( ! empty( $card['xf'] ) ) {
			$data_attrs .= SZEducate_Filter_Extras::data_attrs( $card['xf'] );
		}

		echo '<article class="' . esc_attr( $classes ) . '"' . $data_attrs . '>';

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
			$label_html = $m['label'] !== '' ? '<strong>' . esc_html( $m['label'] ) . ':</strong> ' : '';
			echo '<div class="sz-cc-meta">' . $label_html . esc_html( $m['text'] ) . '</div>';
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
			// Látogatói szűrőpanel (szerkezet + pirula-jelölők); a színeket a Stílus-vezérlők adják.
			. '.sz-cc-hidden{display:none !important;}'
			. '.sz-cc-filters{border-width:1px;border-style:solid;padding:16px 20px;box-sizing:border-box;}'
			. '.sz-cc-lf-head{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:12px;}'
			. '.sz-cc-lf-title{font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:.06em;}'
			. '.sz-cc-lf-reset{padding:5px 14px;border:1px solid #dfe3ea;border-radius:999px;background:#fff;color:inherit;cursor:pointer;font:inherit;font-size:12px;transition:background .2s,color .2s;}'
			. '.sz-cc-lf-reset:hover{background:#242943;color:#fff;}'
			. '.sz-cc-lf-groups{display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px 28px;}'
			. '.sz-cc-lf-group{border:0;margin:0;padding:0;min-width:0;}'
			. '.sz-cc-lf-group legend{font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.04em;opacity:.75;margin-bottom:5px;padding:0;}'
			. '.sz-cc-lf-group label{display:inline-flex;align-items:center;gap:6px;margin:0 5px 5px 0;padding:3px 11px;border:1px solid #dfe3ea;border-radius:999px;background:#fff;font-size:13px;line-height:1.4;cursor:pointer;white-space:nowrap;transition:background .2s,color .2s,border-color .2s;}'
			. '.sz-cc-lf-group label:hover{border-color:var(--sz-cc-accent,#044790);}'
			. '.sz-cc-lf-group input{width:15px;height:15px;margin:0;flex-shrink:0;accent-color:var(--sz-cc-accent,#044790);}'
			. '@supports selector(:has(a)){'
			. '.sz-cc-lf-group input{position:absolute;opacity:0;width:1px;height:1px;}'
			. '.sz-cc-lf-group label:has(input:checked){background:var(--sz-cc-accent,#044790);border-color:var(--sz-cc-accent,#044790);color:#fff;}'
			. '.sz-cc-lf-group label:has(input:focus-visible){outline:3px solid #50ADC9;outline-offset:2px;}'
			. '}'
			. '.sz-cc-no-results{font-style:italic;padding:16px 0;}'
			. '.sz-cc-lf-search{margin-bottom:12px;}'
			. '.sz-cc-lf-search-label{display:block;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.04em;opacity:.75;margin-bottom:5px;}'
			. '.sz-cc-lf-search-input{width:100%;max-width:420px;box-sizing:border-box;padding:8px 14px;border:1px solid #dfe3ea;border-radius:999px;background:#fff;color:inherit;font:inherit;font-size:14px;}'
			. '.sz-cc-lf-search-input:focus{outline:2px solid var(--sz-cc-accent,#044790);outline-offset:1px;}'
			. '.sz-cc-active-filter{margin:0 0 18px;font-size:14px;}'
			. '.sz-cc-active-filter a{margin-left:8px;white-space:nowrap;}'
			. '</style>';
	}

	// A séma bármely (akár beágyazott) mezőjének opció-listáját megkeresi kulcs alapján;
	// ha nincs / üres, a megadott tartalékot adja vissza. A Munkarend és a Nyelv al-mezőként
	// él a "munkarend_csoportok" repeaterben, ezért kell a mélységi bejárás (lásd Szaklista).
	private function uf_schema_options( $schema, $key, $fallback ) {
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				$found = $this->uf_options_deep( $group['fields'], $key );
				if ( ! empty( $found ) ) {
					return $found;
				}
			}
		}
		return $fallback;
	}

	private function uf_options_deep( $fields, $key ) {
		foreach ( (array) $fields as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			if ( isset( $f['key'] ) && $f['key'] === $key && ! empty( $f['options'] ) ) {
				return array_values(
					array_filter(
						array_map( 'trim', explode( ';', $f['options'] ) ),
						function ( $o ) {
							return $o !== '';
						}
					)
				);
			}
			if ( ! empty( $f['sub_fields'] ) ) {
				$r = $this->uf_options_deep( $f['sub_fields'], $key );
				if ( ! empty( $r ) ) {
					return $r;
				}
			}
		}
		return array();
	}

	// Egy képzés szűrő-értékei: Munkarend / Nyelv a beágyazott "munkarend_csoportok"
	// listából (tartalék az archivált felső szintű mező), a Duális / Emelt a felső szintű
	// boolean mezőkből ("emelt" -> "emelt_kovetelmeny"). Ugyanaz, mint a Szaklistában.
	private function uf_course_values( $data ) {
		$mr = array();
		$ny = array();
		if ( isset( $data['munkarend_csoportok'] ) && is_array( $data['munkarend_csoportok'] ) ) {
			foreach ( $data['munkarend_csoportok'] as $mg ) {
				if ( ! is_array( $mg ) ) {
					continue;
				}
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

		return array(
			'munkarend' => array_values( array_unique( array_filter( array_map( 'sanitize_title', $mr ) ) ) ),
			'nyelv'     => array_values( array_unique( array_filter( array_map( 'sanitize_title', $ny ) ) ) ),
			'dualis'    => ( isset( $data['dualis'] ) && self::is_truthy( $data['dualis'] ) ) ? '1' : '0',
			'emelt'     => ( isset( $data['emelt_kovetelmeny'] ) && self::is_truthy( $data['emelt_kovetelmeny'] ) ) ? '1' : '0',
		);
	}

	private function uf_render_panel( $uf_specs, $uf_any, $uf_yes, $uf_no, $uf_label, $wid, $uf_extras = array(), $uf_search = array() ) {
		if ( ! $uf_any ) {
			return '';
		}

		$title  = $uf_label( 'uf_title', 'Szűrés' );
		$reset  = $uf_label( 'uf_reset_text', 'Szűrő törlése' );
		$no_res = $uf_label( 'uf_no_results_text', 'Nincs a szűrésnek megfelelő képzés.' );

		$out  = '<form class="sz-cc-filters" onsubmit="return false;">';
		$out .= '<div class="sz-cc-lf-head"><span class="sz-cc-lf-title">' . esc_html( $title ) . '</span>';
		$out .= '<button type="button" class="sz-cc-lf-reset">' . esc_html( $reset ) . '</button></div>';
		$out .= SZEducate_Filter_Extras::search_html( $uf_search, 'sz-cc-lf', $wid );
		$out .= '<div class="sz-cc-lf-groups">';
		foreach ( $uf_specs as $fkey => $spec ) {
			if ( empty( $spec['on'] ) ) {
				continue;
			}
			$out .= '<fieldset class="sz-cc-lf-group" data-key="' . esc_attr( $fkey ) . '">';
			$out .= '<legend>' . esc_html( $spec['label'] ) . '</legend>';
			if ( ! empty( $spec['bool'] ) ) {
				// Rádió: Igen / Nem / (egyik sem). A "kivehető" viselkedést a JS adja hozzá. A name
				// widget-példányonként egyedi, hogy egy oldalon több widget ne ütközzön.
				$rname = 'sz-cc-lf-' . $fkey . '-' . $wid;
				$out  .= '<label><input type="radio" name="' . esc_attr( $rname ) . '" value="1"> ' . esc_html( $uf_yes ) . '</label>';
				$out  .= '<label><input type="radio" name="' . esc_attr( $rname ) . '" value="0"> ' . esc_html( $uf_no ) . '</label>';
			} else {
				foreach ( (array) $spec['opts'] as $opt ) {
					$slug = sanitize_title( $opt );
					if ( $slug === '' ) {
						continue;
					}
					$out .= '<label><input type="checkbox" value="' . esc_attr( $slug ) . '"> ' . esc_html( $opt ) . '</label>';
				}
			}
			$out .= '</fieldset>';
		}
		$out .= SZEducate_Filter_Extras::groups_html( $uf_extras, 'sz-cc-lf', $wid, $uf_yes, $uf_no );
		$out .= '</div></form>';
		$out .= '<p class="sz-cc-no-results sz-cc-hidden">' . esc_html( $no_res ) . '</p>';

		return $out;
	}

	// A kliensoldali szűrő-logika; globálisan egyszer írjuk ki, minden .sz-cc-widget
	// konténert magától bekapcsol, és véd a dupla bekötés ellen.
	private function uf_render_script() {
		static $js_done = false;
		if ( $js_done ) {
			return '';
		}
		$js_done = true;

		return <<<'HTML'
<script>
(function(){
  function boot(root){
    if(!root || root.__szCcLf) return;
    var form = root.querySelector('.sz-cc-filters');
    if(!form) return;
    root.__szCcLf = true;
    var items  = [].slice.call(root.querySelectorAll('.sz-cc-card'));
    var blocks = [].slice.call(root.querySelectorAll('.sz-cc-group'));
    var noRes  = root.querySelector('.sz-cc-no-results');
    var sInput = form.querySelector('.sz-cc-lf-search-input');
    // Ugyanaz a hajtogatas, mint a szerveroldali fold(): kisbetu + ekezet-lehuzas.
    function fold(t){
      t = (t || '').toLowerCase();
      try { t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch(e) {}
      return t.replace(/\s+/g, ' ').trim();
    }
    function matchesQ(el){
      if(!sInput) return true;
      var q = fold(sInput.value);
      if(!q) return true;
      var hay = el.getAttribute('data-s') || '';
      return q.split(' ').every(function(w){ return hay.indexOf(w) > -1; });
    }
    function selected(){
      var s = {};
      [].forEach.call(form.querySelectorAll('.sz-cc-lf-group'), function(g){
        var k = g.getAttribute('data-key'), v = [];
        [].forEach.call(g.querySelectorAll('input'), function(cb){ if(cb.checked) v.push(cb.value); });
        if(v.length) s[k] = v;
      });
      return s;
    }
    function matches(el, s){
      for(var k in s){
        var have = (el.getAttribute('data-f-' + k) || '').split(' ').filter(Boolean);
        var ok = s[k].some(function(x){ return have.indexOf(x) > -1; });
        if(!ok) return false;
      }
      return true;
    }
    function apply(){
      var s = selected(), any = false;
      items.forEach(function(el){
        var vis = matches(el, s) && matchesQ(el);
        el.classList.toggle('sz-cc-hidden', !vis);
        if(vis) any = true;
      });
      blocks.forEach(function(b){
        var vis = 0;
        [].forEach.call(b.querySelectorAll('.sz-cc-card'), function(el){
          if(!el.classList.contains('sz-cc-hidden')) vis++;
        });
        b.classList.toggle('sz-cc-hidden', vis === 0);
      });
      if(noRes) noRes.classList.toggle('sz-cc-hidden', any);
    }
    form.addEventListener('change', apply);
    if(sInput) sInput.addEventListener('input', apply);
    // Igen/Nem rádiók: a már bejelöltre újra kattintva kivehető a jelölés (= "egyik se").
    var radioState = {};
    [].forEach.call(form.querySelectorAll('.sz-cc-lf-group'), function(g){
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
    var reset = form.querySelector('.sz-cc-lf-reset');
    if(reset) reset.addEventListener('click', function(){
      [].forEach.call(form.querySelectorAll('input'), function(cb){ cb.checked = false; });
      if(sInput) sInput.value = '';
      for(var k in radioState){ radioState[k] = null; }
      apply();
    });
    apply();
  }
  function initAll(){ [].forEach.call(document.querySelectorAll('.sz-cc-widget'), boot); }
  if(document.readyState !== 'loading') initAll();
  else document.addEventListener('DOMContentLoaded', initAll);
  window.addEventListener('load', initAll);
  if(window.elementorFrontend && window.elementorFrontend.hooks){
    elementorFrontend.hooks.addAction('frontend/element_ready/szeducate_course_cards.default', function($scope){
      var el = ($scope && $scope[0]) ? $scope[0].querySelector('.sz-cc-widget') : null;
      boot(el);
    });
  }
})();
</script>
HTML;
	}

	// A szabadszavas keresés találati pontszáma (0 = nem találat) - a Szaklista logikájának
	// tükre: a cím pontos / elején / szóként / részként egyezése, majd az adatmezők, a
	// kiemelt mezők (képzési forma, kulcsszavak, terület, indulás) nagyobb súllyal.
	private function search_score( $title, $data, $q ) {
		$q_lower     = mb_strtolower( $q, 'UTF-8' );
		$q_quoted    = preg_quote( $q, '/' );
		$priority    = array( 'kepzesi_forma', 'kulcsszavak', 'kepzesiterulet', 'indulas_idoszaka' );
		$score       = 0;
		$title_lower = mb_strtolower( $title, 'UTF-8' );

		if ( $title_lower === $q_lower ) {
			$score += 100;
		} elseif ( mb_strpos( $title_lower, $q_lower ) === 0 ) {
			$score += 80;
		} elseif ( preg_match( '/\b' . $q_quoted . '\b/iu', $title ) ) {
			$score += 60;
		} elseif ( mb_strpos( $title_lower, $q_lower ) !== false ) {
			$score += 40;
		}

		foreach ( $data as $k => $v ) {
			$k = (string) $k;
			if ( is_bool( $v ) || strpos( $k, 'url' ) !== false ) {
				continue;
			}
			$text       = is_scalar( $v ) ? (string) $v : wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
			$text_lower = mb_strtolower( $text, 'UTF-8' );
			if ( mb_strpos( $text_lower, $q_lower ) === false ) {
				continue;
			}
			if ( in_array( $k, $priority, true ) ) {
				$score += ( $text_lower === $q_lower ) ? 50 : 30;
			} else {
				$score += preg_match( '/\b' . $q_quoted . '\b/iu', $text ) ? 10 : 5;
			}
		}

		return $score;
	}

	// "Keresés eredménye" / "Szűrés: mező - érték" jelzés + törlő link - csak EXTRA szűrésnél
	// (kereső vagy kategórialink), az alap szűrőt nem írja ki. Mint a Szaklistában.
	private function active_filter_badge( $free_text, $seo_active, $active_filters, $schema, $reset_label ) {
		$text      = '';
		$reset_url = '';

		if ( $free_text !== '' ) {
			$text      = sprintf( 'Keresés eredménye: "%s"', $free_text );
			$reset_url = remove_query_arg( array( 'sz_search' ) );
		} elseif ( $seo_active ) {
			reset( $active_filters );
			$f_key     = key( $active_filters );
			$f_slug    = current( $active_filters );
			$f_label   = $f_key;
			$f_display = ucfirst( str_replace( '-', ' ', $f_slug ) );
			$info      = SZEducate_Filter_Extras::schema_fields( $schema );
			if ( isset( $info[ $f_key ] ) ) {
				$f_label = $info[ $f_key ]['label'];
				foreach ( $info[ $f_key ]['options'] as $opt ) {
					if ( sanitize_title( $opt ) === $f_slug ) {
						$f_display = $opt;
						break;
					}
				}
			}
			$text      = sprintf( 'Szűrés: %s - %s', $f_label, $f_display );
			$reset_url = get_permalink( get_queried_object_id() );
			if ( empty( $reset_url ) ) {
				$pn        = get_query_var( 'pagename' );
				$reset_url = $pn ? home_url( user_trailingslashit( $pn ) ) : home_url( '/' );
			}
		}

		if ( $text === '' ) {
			return '';
		}
		$html = '<div class="sz-cc-active-filter">' . esc_html( $text );
		if ( $reset_label !== '' && $reset_url ) {
			$html .= ' <a href="' . esc_url( $reset_url ) . '" rel="nofollow">' . esc_html( $reset_label ) . '</a>';
		}
		return $html . '</div>';
	}

	// Egy mező értékeit szöveg-listává egyszerűsíti: tömbből a skalárokat, szövegből a
	// ";"-vel elválasztott részeket veszi; az üres és a logikai értékeket kihagyja.
	// Az adatsorok normalizált listája. Ha az új ismétlő ('meta_rows') üres, de a régi
	// többes választó ('meta_fields') ki volt töltve, az továbbra is érvényes (felirat + érték),
	// hogy a frissítés ne változtassa meg a már elmentett oldalakat.
	private function get_meta_rows( $settings ) {
		$rows = array();

		if ( ! empty( $settings['meta_rows'] ) && is_array( $settings['meta_rows'] ) ) {
			foreach ( $settings['meta_rows'] as $r ) {
				$key = isset( $r['field'] ) ? trim( (string) $r['field'] ) : '';
				if ( $key === '' ) {
					continue;
				}
				$rows[] = array(
					'key'        => $key,
					'show_label' => ! isset( $r['show_label'] ) || $r['show_label'] === 'yes',
					'label'      => isset( $r['label_text'] ) ? trim( (string) $r['label_text'] ) : '',
					'format'     => isset( $r['format'] ) ? trim( (string) $r['format'] ) : '',
				);
			}
			return $rows;
		}

		if ( ! empty( $settings['meta_fields'] ) && is_array( $settings['meta_fields'] ) ) {
			foreach ( $settings['meta_fields'] as $key ) {
				$rows[] = array(
					'key'        => (string) $key,
					'show_label' => true,
					'label'      => '',
					'format'     => '',
				);
			}
		}

		return $rows;
	}

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
