<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Search_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_search'; }
	public function get_title() { return 'SZEducate Okos Kereső'; }
	public function get_icon() { return 'eicon-search'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {

		// Séma-vezérelt szűrők: minden select / radio / checkbox mezőből egy legördülő,
		// ugyanúgy, ahogy a Szaklista widget építi őket – így a kliens ugyanazt a
		// felületet kapja, amit ott már ismer.
		$schema          = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$dynamic_filters = array();
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}
				foreach ( $group['fields'] as $field ) {
					if ( ! in_array( $field['type'], [ 'select', 'radio', 'checkbox' ], true ) || empty( $field['options'] ) ) {
						continue;
					}
					// A séma több képzési-forma csoportban ismételheti ugyanazt a kulcsot
					// (pl. munkarend, nyelv) - az elsőt tartjuk meg, a duplikált vezérlőt
					// az Elementor amúgy is eldobná.
					if ( isset( $dynamic_filters[ $field['key'] ] ) ) {
						continue;
					}
					$choices = array( '' => '-- Mindegy --' );
					foreach ( array_map( 'trim', explode( ';', $field['options'] ) ) as $opt ) {
						if ( $opt !== '' ) {
							$choices[ $opt ] = $opt;
						}
					}
					$dynamic_filters[ $field['key'] ] = array(
						'key'     => $field['key'],
						'label'   => $field['label'],
						'choices' => $choices,
					);
				}
			}
		}

		// ------------------------------------------------------------------
		// Tartalom
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Kereső Beállításai',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'placeholder',
			[
				'label'   => 'Helyőrző szöveg',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Keress képzésekre, kulcsszavakra...',
			]
		);

		$this->add_control(
			'archive_url',
			[
				'label'       => 'Szaklista (Archívum) URL',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'https://sze.hu/kepzeseink/',
				'description' => 'Ide irányítja a látogatót, ha entert üt a keresőben (az Összes találat megtekintéséhez).',
			]
		);

		$this->add_control(
			'show_dots',
			[
				'label'        => 'Pöttyök a találatok előtt',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'distinguish_status',
			[
				'label'        => 'Aktív / inaktív megkülönböztetés',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => 'Kikapcsolva minden találat egyformán jelenik meg (a pötty is egy színű).',
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Tartalom: Szűrés (aloldalakhoz)
		// ------------------------------------------------------------------
		if ( ! empty( $dynamic_filters ) ) {
			$this->start_controls_section(
				'filter_section',
				[
					'label' => 'Szűrés (aloldalakhoz)',
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				]
			);

			$this->add_control(
				'filter_note',
				[
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => 'Ha beállítasz egy vagy több szűrőt, a kereső csak az azoknak megfelelő képzésekben keres (pl. „Képzési Forma = BSc" egy BSc-aloldalon), és a legördülőben nem ajánl fel „mappa" (kategória) találatokat. Üresen hagyva minden képzésben keres.',
					'content_classes' => 'elementor-descriptor',
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

			$this->end_controls_section();
		}

		// ------------------------------------------------------------------
		// Stílus: Beviteli mező
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_input_section',
			[
				'label' => 'Beviteli Mező Stílusa',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'input_bg',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [ '{{WRAPPER}} .sz-search-form' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'input_color',
			[
				'label'     => 'Beírt szöveg színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-search-input' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'           => 'input_typography',
				'label'          => 'Beírt szöveg tipográfia',
				'selector'       => '{{WRAPPER}} .sz-search-input',
				'fields_options' => [ 'font_size' => [ 'default' => [ 'unit' => 'px', 'size' => 16 ] ] ],
			]
		);

		$this->add_control(
			'placeholder_heading',
			[ 'label' => 'Helyőrző szöveg', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ]
		);
		$this->add_control(
			'placeholder_color',
			[
				'label'     => 'Helyőrző színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#9CA3AF',
				'selectors' => [
					'{{WRAPPER}} .sz-search-input::placeholder'      => 'color: {{VALUE}}; opacity: 1;',
					'{{WRAPPER}} .sz-search-input::-webkit-input-placeholder' => 'color: {{VALUE}}; opacity: 1;',
					'{{WRAPPER}} .sz-search-input::-moz-placeholder' => 'color: {{VALUE}}; opacity: 1;',
				],
			]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'placeholder_typography',
				'label'    => 'Helyőrző tipográfia',
				'selector' => '{{WRAPPER}} .sz-search-input::placeholder',
			]
		);

		$this->add_control(
			'icon_heading',
			[ 'label' => 'Nagyító ikon', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ]
		);
		$this->add_control(
			'icon_color',
			[
				'label'     => 'Ikon színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-search-icon' => 'fill: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control(
			'icon_size',
			[
				'label'      => 'Ikon mérete',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 10, 'max' => 40 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 18 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'shape_heading',
			[ 'label' => 'Alak', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'           => 'input_border',
				'selector'       => '{{WRAPPER}} .sz-search-form',
				'fields_options' => [
					'border' => [ 'default' => 'solid' ],
					'width'  => [ 'default' => [ 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px', 'isLinked' => true ] ],
					'color'  => [ 'default' => '#DDDDDD' ],
				],
			]
		);
		$this->add_control(
			'input_border_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'input_padding',
			[
				'label'      => 'Belső margó (a beírt szöveg körül)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Legördülő konténer
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_dropdown_section',
			[
				'label' => 'Legördülő – Konténer',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'dropdown_bg',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-search-results' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'           => 'dropdown_border',
				'selector'       => '{{WRAPPER}} .sz-search-results',
				'fields_options' => [
					'border' => [ 'default' => 'solid' ],
					'width'  => [ 'default' => [ 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px', 'isLinked' => true ] ],
					'color'  => [ 'default' => '#DDDDDD' ],
				],
			]
		);
		$this->add_control(
			'dropdown_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-results' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);
		// Az alap árnyékot alacsony specificitású (nem scope-olt) szabály adja lentebb,
		// hogy ez a vezérlő - ha beállítják - felül tudja írni.
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'dropdown_shadow',
				'selector' => '{{WRAPPER}} .sz-search-results',
			]
		);
		$this->add_control(
			'dropdown_max_height',
			[
				'label'      => 'Maximális magasság (görgetés)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'vh' ],
				'range'      => [ 'px' => [ 'min' => 100, 'max' => 800 ], 'vh' => [ 'min' => 20, 'max' => 90 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 400 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-results' => 'max-height: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_control(
			'dropdown_offset',
			[
				'label'      => 'Távolság a beviteli mezőtől',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 5 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-results' => 'margin-top: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Legördülő – Találati elem
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_item_section',
			[
				'label' => 'Legördülő – Találati elem',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'           => 'item_typography',
				'label'          => 'Tipográfia',
				'selector'       => '{{WRAPPER}} .sz-search-item .sz-search-item-title',
				'fields_options' => [
					'font_weight' => [ 'default' => '600' ],
					'line_height' => [ 'default' => [ 'unit' => 'em', 'size' => 1.2 ] ],
				],
			]
		);
		$this->add_control(
			'dropdown_item_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-search-item .sz-search-item-title' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'dropdown_item_hover',
			[
				'label'     => 'Háttér (hover)',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [ '{{WRAPPER}} .sz-search-item:hover' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'item_hover_color',
			[
				'label'     => 'Szövegszín (hover)',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-search-item:hover .sz-search-item-title' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control(
			'item_padding',
			[
				'label'      => 'Belső margó',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'top' => 12, 'right' => 20, 'bottom' => 12, 'left' => 20, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);
		$this->add_control(
			'item_divider_color',
			[
				'label'     => 'Elválasztó vonal színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#EEEEEE',
				'selectors' => [ '{{WRAPPER}} .sz-search-item' => 'border-bottom-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'item_divider_width',
			[
				'label'      => 'Elválasztó vonal vastagsága',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 5 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 1 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-item' => 'border-bottom-style: solid; border-bottom-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Legördülő – Pötty
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_dot_section',
			[
				'label'     => 'Legördülő – Pötty',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_dots' => 'yes' ],
			]
		);

		$this->add_responsive_control(
			'dot_size',
			[
				'label'      => 'Méret',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 2, 'max' => 20 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 8 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'dot_gap',
			[
				'label'      => 'Térköz a szövegtől',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 12 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-dot' => 'margin-right: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_control(
			'dot_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ '%', 'px' ],
				'range'      => [ '%' => [ 'min' => 0, 'max' => 50 ], 'px' => [ 'min' => 0, 'max' => 10 ] ],
				'default'    => [ 'unit' => '%', 'size' => 50 ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-dot' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_control(
			'dot_color_active',
			[
				'label'     => 'Szín (aktív képzés)',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-search-dot.is-active' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'dot_color_inactive',
			[
				'label'     => 'Szín (inaktív képzés)',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#D9D9D9',
				'condition' => [ 'distinguish_status' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .sz-search-dot.is-inactive' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Legördülő – Kategória találat és lábléc
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_extra_section',
			[
				'label' => 'Legördülő – Kategória és lábléc',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'cat_heading',
			[ 'label' => 'Kategória találat (mappa)', 'type' => \Elementor\Controls_Manager::HEADING ]
		);
		$this->add_control(
			'cat_bg',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F0F6FC',
				'selectors' => [ '{{WRAPPER}} .sz-search-cat' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'cat_color',
			[
				'label'     => 'Szöveg és ikon színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#2271B1',
				'selectors' => [
					'{{WRAPPER}} .sz-search-cat .sz-search-item-title' => 'color: {{VALUE}};',
					'{{WRAPPER}} .sz-search-cat .sz-search-cat-icon'   => 'fill: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'more_heading',
			[ 'label' => '"Összes találat" sor', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ]
		);
		$this->add_control(
			'more_bg',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F9F9F9',
				'selectors' => [ '{{WRAPPER}} .sz-search-more' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'more_link_color',
			[
				'label'     => 'Link színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-search-more-link' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'empty_heading',
			[ 'label' => '"Nincs találat" üzenet', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ]
		);
		$this->add_control(
			'empty_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#888888',
				'selectors' => [ '{{WRAPPER}} .sz-search-empty' => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings    = $this->get_settings_for_display();
		$placeholder = esc_attr( $settings['placeholder'] );
		$archive_url = esc_url( $settings['archive_url'] );
		$widget_id   = $this->get_id();
		$root_id     = 'sz-search-' . $widget_id;

		$show_dots   = ( ! isset( $settings['show_dots'] ) ) || $settings['show_dots'] === 'yes';
		$distinguish = ( ! isset( $settings['distinguish_status'] ) ) || $settings['distinguish_status'] === 'yes';

		// Aktív séma-szűrők begyűjtése (ugyanaz a minta, mint a Szaklista widgetben):
		// minden 'filter_<kulcs>' vezérlő nem üres értéke. A nyers opció-értéket adjuk
		// tovább, a végpont slug-osít és hasonlít.
		$active_filters = array();
		foreach ( $settings as $key => $val ) {
			if ( strpos( $key, 'filter_' ) !== 0 || $key === 'filter_note' ) {
				continue;
			}
			if ( is_string( $val ) && $val !== '' ) {
				$active_filters[ substr( $key, 7 ) ] = $val;
			}
		}
		?>
		<div class="sz-search-wrapper" id="<?php echo esc_attr( $root_id ); ?>">
			<form action="<?php echo $archive_url; ?>" method="GET" class="sz-search-form" id="sz-search-form-<?php echo esc_attr( $widget_id ); ?>">
				<span class="sz-search-icon-wrap">
					<svg class="sz-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
				</span>
				<input type="text" name="sz_search" class="sz-search-input" id="sz-search-input-<?php echo esc_attr( $widget_id ); ?>" placeholder="<?php echo $placeholder; ?>" autocomplete="off">
				<div class="sz-search-spinner" id="sz-spinner-<?php echo esc_attr( $widget_id ); ?>">
					<svg width="20" height="20" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#50ADC9"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".5" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg>
				</div>
			</form>

			<div class="sz-search-results" id="sz-results-<?php echo esc_attr( $widget_id ); ?>"></div>
		</div>

		<style>
			/* Csak SZERKEZET - minden szín / méret / távolság / tipográfia az Elementor
			   Stílus-vezérlőkből jön (a kimenetbe sütött inline stílusok miatt korábban
			   ezek a vezérlők hatástalanok voltak). */

			/* Alap doboz-árnyék: nem scope-olt, alacsony specificitású, hogy a
			   "Legördülő – Konténer > Árnyék" vezérlő ({{WRAPPER}} …, magasabb
			   specificitás) felül tudja írni. */
			.sz-search-results { box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }

			#<?php echo esc_attr( $root_id ); ?> { position: relative; width: 100%; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-form { display: flex; align-items: center; overflow: hidden; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-icon-wrap { display: flex; align-items: center; flex-shrink: 0; padding-left: 20px; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-input { flex: 1 1 auto; min-width: 0; border: none; background: transparent; outline: none; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-spinner { display: none; flex-shrink: 0; padding-right: 20px; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-results { display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; overflow: hidden; overflow-y: auto; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-results ul { list-style: none; margin: 0; padding: 0; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-item { display: flex; align-items: center; text-decoration: none; transition: background-color .2s ease, color .2s ease; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-item:last-child { border-bottom: none; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-dot { display: inline-block; flex-shrink: 0; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-cat-icon { flex-shrink: 0; width: 16px; height: 16px; margin-right: 12px; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-cat-label { font-weight: 400; font-size: 0.8em; opacity: 0.8; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-more { text-align: center; padding: 10px; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-more-link { font-size: 13px; font-weight: 600; text-decoration: underline; }
			#<?php echo esc_attr( $root_id ); ?> .sz-search-empty { padding: 15px 20px; font-style: italic; }
		</style>

		<script>
		(function() {
			var input     = document.getElementById('sz-search-input-<?php echo esc_js( $widget_id ); ?>');
			var resultsBox = document.getElementById('sz-results-<?php echo esc_js( $widget_id ); ?>');
			var spinner   = document.getElementById('sz-spinner-<?php echo esc_js( $widget_id ); ?>');
			if ( ! input || ! resultsBox ) return;

			var SHOW_DOTS    = <?php echo $show_dots ? 'true' : 'false'; ?>;
			var DISTINGUISH  = <?php echo $distinguish ? 'true' : 'false'; ?>;
			var archiveUrl   = <?php echo wp_json_encode( $archive_url ); ?>.replace(/\/$/, "");
			var FILTERS      = <?php echo wp_json_encode( (object) $active_filters ); ?>;
			var debounceTimer;

			function buildSearchUrl(query) {
				var url = '/wp-json/szeducate/v1/client/search?q=' + encodeURIComponent(query);
				Object.keys(FILTERS).forEach(function(k) {
					url += '&f[' + encodeURIComponent(k) + ']=' + encodeURIComponent(FILTERS[k]);
				});
				return url;
			}

			function slugify(text) {
				var a = 'àáäâãåăæąçćčđďèéěėëêęğǵḧìíïîįłḿǹńňñöôœőõöøóòřsşšșťțùúüûųůűűưũýÿýżžź';
				var b = 'aaaaaaaaacccdeeeeeeegghiiiiilmnnnnoooooooooorssssttuuuuuuuuuuuyyyzzz';
				var p = new RegExp(a.split('').join('|'), 'g');
				return text.toString().toLowerCase()
					.replace(/\s+/g, '-')
					.replace(p, function(c){ return b.charAt(a.indexOf(c)); })
					.replace(/&/g, '-and-')
					.replace(/[^\w\-]+/g, '')
					.replace(/\-\-+/g, '-')
					.replace(/^-+/, '')
					.replace(/-+$/, '');
			}

			function esc(s) {
				return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
					return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
				});
			}

			input.addEventListener('input', function() {
				clearTimeout(debounceTimer);
				var query = input.value.trim();

				if (query.length < 2) {
					resultsBox.style.display = 'none';
					if (spinner) spinner.style.display = 'none';
					return;
				}

				if (spinner) spinner.style.display = 'block';

				debounceTimer = setTimeout(function() {
					fetch(buildSearchUrl(query))
					.then(function(r){ return r.json(); })
					.then(function(data) {
						if (spinner) spinner.style.display = 'none';
						resultsBox.innerHTML = '';

						if (data && data.length > 0) {
							var html = '<ul>';

							data.forEach(function(item) {
								if (item.type === 'category') {
									var catUrl = archiveUrl
										? archiveUrl + '/' + item.field_key + '/' + slugify(item.field_val) + '/'
										: '?sz_p=' + item.field_key + '&sz_v=' + encodeURIComponent(item.field_val);
									html += '<li><a href="' + esc(catUrl) + '" class="sz-search-item sz-search-cat">'
										+ '<svg class="sz-search-cat-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>'
										+ '<span class="sz-search-item-title">' + esc(item.title)
										+ ' <span class="sz-search-cat-label">(' + esc(item.label) + ')</span></span>'
										+ '</a></li>';
								} else {
									var statusClass = ( ! DISTINGUISH || item.is_active ) ? 'is-active' : 'is-inactive';
									html += '<li><a href="' + esc(item.url) + '" class="sz-search-item">'
										+ ( SHOW_DOTS ? '<span class="sz-search-dot ' + statusClass + '"></span>' : '' )
										+ '<span class="sz-search-item-title">' + esc(item.title) + '</span>'
										+ '</a></li>';
								}
							});

							<?php if ( ! empty( $archive_url ) ) : ?>
							html += '<li class="sz-search-more"><a href="' + esc(archiveUrl + '?sz_search=' + encodeURIComponent(query)) + '" class="sz-search-more-link">Összes találat megtekintése</a></li>';
							<?php endif; ?>

							html += '</ul>';
							resultsBox.innerHTML = html;
							resultsBox.style.display = 'block';
						} else {
							resultsBox.innerHTML = '<div class="sz-search-empty">Nincs a keresésnek megfelelő képzés.</div>';
							resultsBox.style.display = 'block';
						}
					})
					.catch(function(){
						if (spinner) spinner.style.display = 'none';
					});
				}, 400);
			});

			document.addEventListener('click', function(e) {
				if ( ! input.contains(e.target) && ! resultsBox.contains(e.target) ) {
					resultsBox.style.display = 'none';
				}
			});

			input.addEventListener('focus', function() {
				if (input.value.trim().length >= 2 && resultsBox.innerHTML !== '') {
					resultsBox.style.display = 'block';
				}
			});
		})();
		</script>
		<?php
	}
}
