<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Featured_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_featured'; }
	public function get_title() { return 'SZEducate Kiemelt Képzések'; }
	public function get_icon() { return 'eicon-posts-grid'; }
	public function get_categories() { return array( 'general' ); }

	// Az Elementor core-ba beépített Swiper.js-t hasznosítjuk újra (ugyanazt a
	// 'swiper' handle-t regisztrálja, amit a natív Kép Karusszel widget is használ) -
	// nincs saját JS/CSS függőség bevezetve. FIGYELEM: ezt a handle-nevet nem tudtam
	// élesben ellenőrizni (ehhez telepített Elementor kell) - ha a Csúsztató mód nem
	// animál, a böngésző konzolján "Swiper is not defined" hibát fog mutatni, jelezve,
	// hogy az adott Elementor verzióban más a handle neve.
	public function get_script_depends() { return array( 'swiper' ); }
	public function get_style_depends() { return array( 'swiper' ); }

	protected function register_controls() {

		// Séma-mezők listája a "Leírás forrás-mezője" vezérlőhöz - ugyanaz a minta,
		// mint SZEducate_Elementor::get_field_options() és a Szaklista widget
		// csoportosítás-mezője.
		$schema     = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$all_fields = array( '' => '-- Nincs --' );
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				foreach ( $group['fields'] as $field ) {
					if ( empty( $field['key'] ) ) {
						continue;
					}
					$label                       = ! empty( $field['label'] ) ? $field['label'] : $field['key'];
					$all_fields[ $field['key'] ] = $label . ' [' . $field['key'] . ']';
				}
			}
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-featured.php';
		$terms        = get_terms(
			array(
				'taxonomy'   => SZEducate_Featured::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$term_options = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_options[ $term->term_id ] = $term->name . ' (' . intval( $term->count ) . ')';
			}
		}

		// ------------------------------------------------------------------
		// Tartalom: Forrás
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'source_section',
			array(
				'label' => 'Forrás',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		if ( empty( $term_options ) ) {
			$this->add_control(
				'no_categories_notice',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => 'Még nincs egyetlen Kiemelt kategória sem. Hozz létre egyet a Képzések admin listájában a "Kiemelt kategóriába helyezés" tömeges művelettel.',
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				)
			);
		}

		$this->add_control(
			'kiemelt_kategoriak',
			array(
				'label'       => 'Kiemelt kategóriák',
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $term_options,
				'default'     => array(),
				'description' => 'A kiválasztott kategóriá(k)ba sorolt, publikált képzések jelennek meg. A besorolás a Képzések admin listájában, tömeges művelettel állítható.',
			)
		);

		$this->add_control(
			'sorrend',
			array(
				'label'       => 'Sorrend',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'custom'     => 'Egyéni sorrend (Kiemelt sorrend oldalon beállítva)',
					'title_asc'  => 'Cím szerint A-Z',
					'title_desc' => 'Cím szerint Z-A',
					'date_desc'  => 'Legfrissebb elöl',
					'date_asc'   => 'Legrégebbi elöl',
				),
				'default'     => 'custom',
				'description' => 'Egyéni sorrend esetén, ha több kategóriát is választottál, a widget kategóriánként egymás után fűzi a mentett sorrendet.',
			)
		);

		$this->add_control(
			'max_count',
			array(
				'label'       => 'Max. megjelenített darabszám',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => '0 = mind.',
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

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Tartalom: Megjelenítés (elrendezés + kártya-stílus váltók)
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'display_section',
			array(
				'label' => 'Megjelenítés',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'layout_mode',
			array(
				'label'   => 'Elrendezés',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'grid'     => 'Rács',
					'carousel' => 'Csúsztató (karusszel)',
				),
				'default' => 'grid',
			)
		);

		$this->add_control(
			'card_layout',
			array(
				'label'       => 'Kártya stílusa',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'overlay' => 'Kép + cím (a cím a képre írva)',
					'body'    => 'Kép + leírás + gomb (külön szöveges blokk)',
				),
				'default'     => 'overlay',
				'description' => '"Kép + cím" módban nincs sem leírás, sem gomb - a cím a képre írva jelenik meg, mint egy feliratozott fotó.',
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Tartalom: Kártya (csak "Kép + leírás + gomb" módban releváns mezők)
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
				'label'        => 'Kép mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_description',
			array(
				'label'        => 'Leírás mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'card_layout' => 'body' ),
			)
		);

		$this->add_control(
			'description_field',
			array(
				'label'       => 'Leírás forrás-mezője',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $all_fields,
				'default'     => '',
				'condition'   => array(
					'card_layout'      => 'body',
					'show_description' => 'yes',
				),
				'description' => 'A képzéstípusok eltérő mezőkben tárolják a leírást (pl. BSc-nél "A képzésről", Mikroképzésnél "Leírás (összefoglalás)") - válaszd ki, melyik mezőt használja ez a widget.',
			)
		);

		$this->add_control(
			'description_length',
			array(
				'label'     => 'Leírás max. hossza (karakter)',
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 20,
				'default'   => 150,
				'condition' => array(
					'card_layout'      => 'body',
					'show_description' => 'yes',
				),
			)
		);

		$this->add_control(
			'cta_text_source',
			array(
				'label'     => 'Gomb szövege',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'field'  => 'A képzés "CTA szöveg" mezőjéből',
					'custom' => 'Egyedi szöveg (mindegyik kártyán ugyanaz)',
				),
				'default'   => 'field',
				'condition' => array( 'card_layout' => 'body' ),
			)
		);

		$this->add_control(
			'cta_custom_text',
			array(
				'label'     => 'Egyedi gombszöveg',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Részletek',
				'condition' => array(
					'card_layout'     => 'body',
					'cta_text_source' => 'custom',
				),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Rács / Csúsztató elrendezés
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_grid_section',
			array(
				'label' => 'Rács / Csúsztató elrendezés',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => 'Oszlopok / látható kártyák száma',
				'type'           => \Elementor\Controls_Manager::SELECT,
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'selectors'      => array(
					'{{WRAPPER}} .sz-featured-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
				),
			)
		);

		$this->add_responsive_control(
			'grid_gap',
			array(
				'label'      => 'Térköz',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 24,
				),
				'selectors'  => array(
					'{{WRAPPER}} .sz-featured-grid' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'carousel_heading',
			array(
				'label'     => 'Csúsztató beállításai',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_loop',
			array(
				'label'        => 'Végtelen ismétlés (loop)',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_autoplay',
			array(
				'label'        => 'Automatikus lapozás',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_autoplay_delay',
			array(
				'label'     => 'Lapozás közti idő (ms)',
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 1000,
				'step'      => 500,
				'default'   => 4000,
				'condition' => array(
					'layout_mode'       => 'carousel',
					'carousel_autoplay' => 'yes',
				),
			)
		);

		$this->add_control(
			'carousel_effect',
			array(
				'label'     => 'Átmenet animáció',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'slide' => 'Csúsztatás',
					'fade'  => 'Áttűnés (fade)',
				),
				'default'   => 'slide',
				'condition' => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_speed',
			array(
				'label'     => 'Átmenet sebessége (ms)',
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 100,
				'step'      => 100,
				'default'   => 500,
				'condition' => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_arrows',
			array(
				'label'        => 'Nyilak mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_dots',
			array(
				'label'        => 'Lapozó pöttyök mutatása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'layout_mode' => 'carousel' ),
			)
		);

		$this->add_control(
			'carousel_nav_color',
			array(
				'label'     => 'Nyilak / pöttyök színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'condition' => array( 'layout_mode' => 'carousel' ),
				'selectors' => array( '{{WRAPPER}} .sz-featured-swiper' => '--swiper-theme-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Kártya
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_card_section',
			array(
				'label' => 'Kártya stílus',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => array( '{{WRAPPER}} .sz-featured-card' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => 'Belső margó (szöveges rész)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array(
					'top'    => 20,
					'right'  => 20,
					'bottom' => 20,
					'left'   => 20,
					'unit'   => 'px',
				),
				'condition'  => array( 'card_layout' => 'body' ),
				'selectors'  => array( '{{WRAPPER}} .sz-featured-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array(
					'top'    => 12,
					'right'  => 12,
					'bottom' => 12,
					'left'   => 12,
					'unit'   => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .sz-featured-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .sz-featured-card',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_box_shadow',
				'selector' => '{{WRAPPER}} .sz-featured-card',
			)
		);

		$this->add_control(
			'card_hover_animation',
			array(
				'label'     => 'Hover animáció',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'none'   => 'Nincs',
					'zoom'   => 'Kép nagyítása',
					'lift'   => 'Kártya megemelése',
					'darken' => 'Kép sötétítése',
				),
				'default'   => 'zoom',
				'separator' => 'before',
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Kép
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_image_section',
			array(
				'label'     => 'Kép',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->add_control(
			'image_ratio',
			array(
				'label'     => 'Képarány',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'16/9' => '16:9',
					'4/3'  => '4:3',
					'1/1'  => '1:1 (négyzet)',
					'3/4'  => '3:4 (álló)',
				),
				'default'   => '4/3',
				'selectors' => array( '{{WRAPPER}} .sz-featured-card-image' => 'aspect-ratio: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Felirat overlay ("Kép + cím" módban)
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_overlay_section',
			array(
				'label'     => 'Felirat overlay (kép + cím módban)',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'card_layout' => 'overlay' ),
			)
		);

		$this->add_control(
			'overlay_title_position',
			array(
				'label'   => 'Cím pozíciója',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'top-left'     => 'Bal fent',
					'top-right'    => 'Jobb fent',
					'bottom-left'  => 'Bal lent',
					'bottom-right' => 'Jobb lent',
					'center'       => 'Középen',
				),
				'default' => 'top-left',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_shade',
				'label'          => 'Sötétítő réteg (átfedés)',
				'types'          => array( 'classic', 'gradient' ),
				'selector'       => '{{WRAPPER}} .sz-featured-card-shade',
				'fields_options' => array(
					'background' => array( 'default' => 'gradient' ),
					'color'      => array( 'default' => 'rgba(20,22,38,0.15)' ),
					'gradient'   => array(
						'default' => array(
							'type'  => 'linear',
							'angle' => array(
								'unit' => 'deg',
								'size' => 180,
							),
						),
					),
					'color_b'    => array( 'default' => 'rgba(20,22,38,0.75)' ),
				),
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
				'selectors' => array( '{{WRAPPER}} .sz-featured-card-title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .sz-featured-card-title',
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label'      => 'Alsó margó (csak "Kép + leírás" módban)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 10,
				),
				'condition'  => array( 'card_layout' => 'body' ),
				'selectors'  => array( '{{WRAPPER}} .sz-featured-card-body .sz-featured-card-title' => 'margin: 0 0 {{SIZE}}{{UNIT}} 0;' ),
			)
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Leírás
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_description_section',
			array(
				'label'     => 'Leírás',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'card_layout'      => 'body',
					'show_description' => 'yes',
				),
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#5c6170',
				'selectors' => array( '{{WRAPPER}} .sz-featured-card-desc' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'description_typography',
				'selector' => '{{WRAPPER}} .sz-featured-card-desc',
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
				'condition' => array( 'card_layout' => 'body' ),
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
				'selectors' => array( '{{WRAPPER}} .sz-featured-card-cta' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_bg_color',
			array(
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => array( '{{WRAPPER}} .sz-featured-card-cta' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_button_hover', array( 'label' => 'Hover' ) );
		$this->add_control(
			'button_hover_color',
			array(
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .sz-featured-card:hover .sz-featured-card-cta' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_hover_bg_color',
			array(
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => array( '{{WRAPPER}} .sz-featured-card:hover .sz-featured-card-cta' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'button_typography',
				'selector'  => '{{WRAPPER}} .sz-featured-card-cta',
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => 'Belső margó',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'    => 8,
					'right'  => 18,
					'bottom' => 8,
					'left'   => 18,
					'unit'   => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .sz-featured-card-cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'    => 6,
					'right'  => 6,
					'bottom' => 6,
					'left'   => 6,
					'unit'   => 'px',
				),
				'selectors'  => array( '{{WRAPPER}} .sz-featured-card-cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$term_ids = ! empty( $settings['kiemelt_kategoriak'] ) && is_array( $settings['kiemelt_kategoriak'] )
			? array_map( 'intval', $settings['kiemelt_kategoriak'] )
			: array();

		if ( empty( $term_ids ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">Válassz ki legalább egy Kiemelt kategóriát a widget beállításaiban.</p>';
			}
			return;
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-featured.php';

		$sort           = ! empty( $settings['sorrend'] ) ? $settings['sorrend'] : 'custom';
		$visibility_key = ! empty( $settings['visibility_key'] ) ? trim( $settings['visibility_key'] ) : '';

		if ( $sort === 'custom' ) {
			$post_ids = array();
			$seen     = array();
			foreach ( $term_ids as $term_id ) {
				foreach ( SZEducate_Featured::get_ordered_course_posts( $term_id ) as $pid ) {
					if ( ! isset( $seen[ $pid ] ) ) {
						$post_ids[]   = $pid;
						$seen[ $pid ] = true;
					}
				}
			}
		} else {
			$orderby = ( $sort === 'date_desc' || $sort === 'date_asc' ) ? 'date' : 'title';
			$order   = ( $sort === 'title_desc' || $sort === 'date_desc' ) ? 'DESC' : 'ASC';

			$post_ids = get_posts(
				array(
					'post_type'      => 'sz_course',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => $orderby,
					'order'          => $order,
					'tax_query'      => array(
						array(
							'taxonomy' => SZEducate_Featured::TAXONOMY,
							'field'    => 'term_id',
							'terms'    => $term_ids,
						),
					),
				)
			);

			// A magyar ábécé-rendezéshez (hu_collate) WP_Query-n kívül, PHP-ban rendezünk -
			// az 'orderby' => 'title' natív MySQL-rendezése nem ismeri az ékezeteket.
			if ( $sort === 'title_asc' || $sort === 'title_desc' ) {
				usort(
					$post_ids,
					function ( $a, $b ) use ( $order ) {
						$r = $this->hu_collate( get_the_title( $a ), get_the_title( $b ) );
						return $order === 'DESC' ? -$r : $r;
					}
				);
			}
		}

		$cards = array();
		foreach ( $post_ids as $post_id ) {
			if ( get_post_status( $post_id ) !== 'publish' ) {
				continue;
			}

			$data = SZEducate_Client::get_course_data_for_post( $post_id );

			if ( $visibility_key !== '' && is_array( $data ) && array_key_exists( $visibility_key, $data ) ) {
				if ( ! self::uf_truthy( $data[ $visibility_key ] ) ) {
					continue;
				}
			}

			$cards[] = array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post_id ),
				'url'     => get_permalink( $post_id ),
				'data'    => is_array( $data ) ? $data : array(),
			);
		}

		if ( ! empty( $settings['max_count'] ) && intval( $settings['max_count'] ) > 0 ) {
			$cards = array_slice( $cards, 0, intval( $settings['max_count'] ) );
		}

		if ( empty( $cards ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">A kiválasztott kategóriákban jelenleg nincs megjeleníthető képzés.</p>';
			}
			return;
		}

		$this->render_base_css();

		$layout_mode = ! empty( $settings['layout_mode'] ) ? $settings['layout_mode'] : 'grid';
		$card_layout = ! empty( $settings['card_layout'] ) ? $settings['card_layout'] : 'overlay';

		$plugin_options    = get_option( 'szeducate_settings', array() );
		$fallback_image_id = ! empty( $plugin_options['featured_default_image_id'] ) ? intval( $plugin_options['featured_default_image_id'] ) : 0;

		$hover_anim = ! empty( $settings['card_hover_animation'] ) ? $settings['card_hover_animation'] : 'none';
		$title_pos  = ! empty( $settings['overlay_title_position'] ) ? $settings['overlay_title_position'] : 'top-left';

		$card_classes = array( 'sz-featured-card', 'sz-featured-card-layout-' . $card_layout, 'sz-anim-' . $hover_anim );
		if ( $card_layout === 'overlay' ) {
			$card_classes[] = 'sz-title-pos-' . $title_pos;
		}
		$card_class = implode( ' ', $card_classes );

		if ( $layout_mode === 'carousel' ) {
			$swiper_id = 'sz-featured-swiper-' . $this->get_id();

			echo '<div class="sz-featured-swiper swiper" id="' . esc_attr( $swiper_id ) . '"><div class="swiper-wrapper">';
			foreach ( $cards as $card ) {
				echo '<div class="swiper-slide">';
				$this->render_card( $card, $card_class, $card_layout, $settings, $fallback_image_id );
				echo '</div>';
			}
			echo '</div>'; // .swiper-wrapper

			if ( $settings['carousel_arrows'] === 'yes' ) {
				echo '<div class="swiper-button-prev"></div><div class="swiper-button-next"></div>';
			}
			if ( $settings['carousel_dots'] === 'yes' ) {
				echo '<div class="swiper-pagination"></div>';
			}
			echo '</div>'; // .sz-featured-swiper

			$this->render_swiper_init_script( $swiper_id, $settings );
		} else {
			echo '<div class="sz-featured-grid">';
			foreach ( $cards as $card ) {
				$this->render_card( $card, $card_class, $card_layout, $settings, $fallback_image_id );
			}
			echo '</div>';
		}
	}

	private function render_card( $card, $card_class, $card_layout, $settings, $fallback_image_id ) {
		$show_image = $settings['show_image'] === 'yes';

		echo '<a class="' . esc_attr( $card_class ) . '" href="' . esc_url( $card['url'] ) . '">';

		if ( $show_image ) {
			echo '<div class="sz-featured-card-image">';

			if ( has_post_thumbnail( $card['post_id'] ) ) {
				echo get_the_post_thumbnail( $card['post_id'], 'medium_large' );
			} elseif ( $fallback_image_id ) {
				echo wp_get_attachment_image( $fallback_image_id, 'medium_large' );
			}

			if ( $card_layout === 'overlay' ) {
				echo '<div class="sz-featured-card-shade"></div>';
				echo '<h3 class="sz-featured-card-title">' . esc_html( $card['title'] ) . '</h3>';
			}

			echo '</div>'; // .sz-featured-card-image
		}

		if ( $card_layout === 'body' ) {
			echo '<div class="sz-featured-card-body">';
			echo '<h3 class="sz-featured-card-title">' . esc_html( $card['title'] ) . '</h3>';

			$show_description = $settings['show_description'] === 'yes';
			$desc_field       = ! empty( $settings['description_field'] ) ? $settings['description_field'] : '';
			$desc_length      = ! empty( $settings['description_length'] ) ? intval( $settings['description_length'] ) : 150;

			if ( $show_description && $desc_field !== '' && ! empty( $card['data'][ $desc_field ] ) ) {
				$desc_raw  = $card['data'][ $desc_field ];
				$desc_text = is_array( $desc_raw ) ? '' : wp_strip_all_tags( (string) $desc_raw );
				if ( $desc_text !== '' ) {
					if ( mb_strlen( $desc_text, 'UTF-8' ) > $desc_length ) {
						$desc_text = mb_substr( $desc_text, 0, $desc_length, 'UTF-8' ) . '…';
					}
					echo '<p class="sz-featured-card-desc">' . esc_html( $desc_text ) . '</p>';
				}
			}

			$cta_source = ! empty( $settings['cta_text_source'] ) ? $settings['cta_text_source'] : 'field';
			$cta_text   = ! empty( $settings['cta_custom_text'] ) ? $settings['cta_custom_text'] : 'Részletek';
			if ( $cta_source === 'field' && ! empty( $card['data']['cta_szoveg'] ) && ! is_array( $card['data']['cta_szoveg'] ) ) {
				$cta_text = $card['data']['cta_szoveg'];
			}
			if ( $cta_text !== '' ) {
				echo '<span class="sz-featured-card-cta">' . esc_html( $cta_text ) . '</span>';
			}

			echo '</div>'; // .sz-featured-card-body
		} elseif ( ! $show_image ) {
			// "Kép + cím" mód, de a kép ki van kapcsolva - a cím enélkül sehol nem jelenne meg.
			echo '<div class="sz-featured-card-body"><h3 class="sz-featured-card-title">' . esc_html( $card['title'] ) . '</h3></div>';
		}

		echo '</a>';
	}

	// A Swiper inicializáló JS-t inline írjuk ki, ugyanúgy, ahogy az Okos Kereső widget
	// is közvetlenül a render() kimenetébe ágyaz <script>-et (class-szeducate-search-widget.php) -
	// ez a projekt bevett mintája, nincs külön @wordpress/scripts frontend bundle.
	// FONTOS: a térköz (spaceBetween) csak az asztali "Térköz" értéket használja minden
	// töréspontnál - nincs törésponton kénti spaceBetween, ez egyszerűsítés.
	private function render_swiper_init_script( $swiper_id, $settings ) {
		$slides_desktop = ! empty( $settings['columns'] ) ? intval( $settings['columns'] ) : 3;
		$slides_tablet  = ! empty( $settings['columns_tablet'] ) ? intval( $settings['columns_tablet'] ) : 2;
		$slides_mobile  = ! empty( $settings['columns_mobile'] ) ? intval( $settings['columns_mobile'] ) : 1;
		$gap            = isset( $settings['grid_gap']['size'] ) ? floatval( $settings['grid_gap']['size'] ) : 24;

		$options = array(
			'slidesPerView' => $slides_mobile,
			'spaceBetween'  => $gap,
			'loop'          => $settings['carousel_loop'] === 'yes',
			'speed'         => ! empty( $settings['carousel_speed'] ) ? intval( $settings['carousel_speed'] ) : 500,
			'effect'        => ! empty( $settings['carousel_effect'] ) ? $settings['carousel_effect'] : 'slide',
			'breakpoints'   => array(
				768  => array( 'slidesPerView' => $slides_tablet ),
				1025 => array( 'slidesPerView' => $slides_desktop ),
			),
		);

		if ( $settings['carousel_autoplay'] === 'yes' ) {
			$options['autoplay'] = array(
				'delay'                => ! empty( $settings['carousel_autoplay_delay'] ) ? intval( $settings['carousel_autoplay_delay'] ) : 4000,
				'disableOnInteraction' => false,
			);
		}
		if ( $settings['carousel_arrows'] === 'yes' ) {
			$options['navigation'] = array(
				'nextEl' => '#' . $swiper_id . ' .swiper-button-next',
				'prevEl' => '#' . $swiper_id . ' .swiper-button-prev',
			);
		}
		if ( $settings['carousel_dots'] === 'yes' ) {
			$options['pagination'] = array(
				'el'        => '#' . $swiper_id . ' .swiper-pagination',
				'clickable' => true,
			);
		}

		echo '<script type="text/javascript">'
			. '(function(){'
			. 'var el = document.getElementById(' . wp_json_encode( $swiper_id ) . ');'
			. 'if (!el || el.getAttribute("data-sz-swiper-init") === "1") return;'
			. 'if (typeof Swiper === "undefined") return;'
			. 'el.setAttribute("data-sz-swiper-init", "1");'
			. 'new Swiper(el, ' . wp_json_encode( $options ) . ');'
			. '})();'
			. '</script>';
	}

	// A rács/kártya/overlay szerkezeti CSS-e (nem szín/térköz - azok a Stílus-vezérlőkből
	// jönnek), widgetenként csak egyszer kiírva, mint a Szaklista widget uf_render_panel()-je.
	private function render_base_css() {
		static $css_done = false;
		if ( $css_done ) {
			return;
		}
		$css_done = true;

		echo '<style>'
			. '.sz-featured-grid{display:grid;}'
			. '.sz-featured-swiper{width:100%;padding-bottom:6px;}'
			. '.sz-featured-swiper .swiper-slide{height:auto;display:flex;}'
			. '.sz-featured-card{display:flex;flex-direction:column;width:100%;text-decoration:none;transition:transform .3s ease,box-shadow .3s ease;}'
			. '.sz-anim-lift:hover{transform:translateY(-6px);}'
			. '.sz-featured-card-image{position:relative;width:100%;overflow:hidden;}'
			. '.sz-featured-card-image img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s ease,filter .3s ease;}'
			. '.sz-anim-zoom:hover .sz-featured-card-image img{transform:scale(1.08);}'
			. '.sz-anim-darken:hover .sz-featured-card-image img{filter:brightness(0.75);}'
			. '.sz-featured-card-body{display:flex;flex-direction:column;flex:1;}'
			. '.sz-featured-card-title{margin:0;}'
			. '.sz-featured-card-desc{flex:1;}'
			. '.sz-featured-card-cta{display:inline-block;align-self:flex-start;}'
			// Overlay ("Kép + cím") mód - a cím a képre pozicionálva.
			. '.sz-featured-card-shade{position:absolute;inset:0;pointer-events:none;}'
			. '.sz-featured-card-layout-overlay .sz-featured-card-title{position:absolute;margin:0;padding:18px;max-width:100%;box-sizing:border-box;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.4);}'
			. '.sz-title-pos-top-left .sz-featured-card-title{top:0;left:0;text-align:left;}'
			. '.sz-title-pos-top-right .sz-featured-card-title{top:0;right:0;text-align:right;}'
			. '.sz-title-pos-bottom-left .sz-featured-card-title{bottom:0;left:0;text-align:left;}'
			. '.sz-title-pos-bottom-right .sz-featured-card-title{bottom:0;right:0;text-align:right;}'
			. '.sz-title-pos-center .sz-featured-card-title{top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;}'
			. '</style>';
	}

	// Magyar ábécé szerinti összehasonlítás (á az a után, ö/ő egy csoportban stb.) -
	// ugyanaz a minta, mint a Szaklista widget hu_collate()-je (class-szeducate-listing-widget.php).
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
	// ugyanaz a minta, mint SZEducate_Elementor::sz_is_truthy() és a Szaklista widget uf_truthy()-ja.
	private static function uf_truthy( $v ) {
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
