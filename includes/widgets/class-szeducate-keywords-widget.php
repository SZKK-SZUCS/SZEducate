<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Keywords_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_keywords'; }
	public function get_title() { return 'SZEducate Kulcsszavak'; }
	public function get_icon() { return 'eicon-tags'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {
		
		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Adat Forrás és Logika',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'keyword_field_key',
			[
				'label'       => 'Kulcsszó Mező Kulcsa',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'kulcsszavak',
				'description' => 'A Séma Tervezőben megadott mező pontos kulcsa.'
			]
		);

		$this->add_control(
			'prepend_hashtag',
			[
				'label'        => 'Hashtag (#) karakter megjelenítése',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);

		$this->add_control(
			'archive_url',
			[
				'label'       => 'Céloldal URL (Szaklista oldal)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'https://sze.hu/kepzesek/',
				'description' => 'Az az oldal, ahova a látogató érkezik a címkére kattintva. Ha üres, a címkék nem lesznek kattinthatók.',
				'separator'   => 'before',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_layout_section',
			[
				'label' => 'Elrendezés',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'alignment',
			[
				'label'   => 'Igazítás',
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [ 'title' => 'Balra', 'icon' => 'eicon-text-align-left' ],
					'center'     => [ 'title' => 'Középre', 'icon' => 'eicon-text-align-center' ],
					'flex-end'   => [ 'title' => 'Jobbra', 'icon' => 'eicon-text-align-right' ],
				],
				'default' => 'flex-start',
				'selectors' => [
					'{{WRAPPER}} .sz-keywords-wrapper' => 'justify-content: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label'      => 'Térköz a címkék között',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 8 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-keywords-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_pill_section',
			[
				'label' => 'Címke (Pill) Dizájn',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'pill_typography',
				'selector' => '{{WRAPPER}} .sz-keyword-pill',
			]
		);

		$this->add_responsive_control(
			'pill_padding',
			[
				'label'      => 'Belső Margó (Padding)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top' => 6, 'right' => 14, 'bottom' => 6, 'left' => 14,
					'unit' => 'px', 'isLinked' => false,
				],
				'selectors'  => [
					'{{WRAPPER}} .sz-keyword-pill' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'pill_border_radius',
			[
				'label'      => 'Lekerekítés (Border Radius)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20,
					'unit' => 'px', 'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .sz-keyword-pill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'tabs_pill_style' );

		$this->start_controls_tab(
			'tab_pill_normal',
			[ 'label' => 'Normál' ]
		);

		$this->add_control(
			'pill_text_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-keyword-pill' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'pill_bg_color',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [ '{{WRAPPER}} .sz-keyword-pill' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'pill_border',
				'selector' => '{{WRAPPER}} .sz-keyword-pill',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'pill_box_shadow',
				'selector' => '{{WRAPPER}} .sz-keyword-pill',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_pill_hover',
			[ 'label' => 'Hover' ]
		);

		$this->add_control(
			'pill_hover_text_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-keyword-pill:hover' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'pill_hover_bg_color',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-keyword-pill:hover' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'pill_hover_border',
				'selector' => '{{WRAPPER}} .sz-keyword-pill:hover',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'pill_hover_box_shadow',
				'selector' => '{{WRAPPER}} .sz-keyword-pill:hover',
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$field_key = ! empty( $settings['keyword_field_key'] ) ? trim( $settings['keyword_field_key'] ) : 'kulcsszavak';

		$post_id = get_the_ID();
		if ( get_post_type( $post_id ) !== 'sz_course' ) return;

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return;

		$keywords_raw = isset( $data[ $field_key ] ) ? $data[ $field_key ] : '';

		if ( empty( $keywords_raw ) ) return;

		$pills = [];
		if ( is_array( $keywords_raw ) ) {
			$pills = $keywords_raw;
		} else {
			$pills = array_map( 'trim', explode( ';', $keywords_raw ) );
		}

		$pills = array_filter( $pills );
		if ( empty( $pills ) ) return;

		$hashtag = ( $settings['prepend_hashtag'] === 'yes' ) ? '#' : '';
		$base_url = ! empty( $settings['archive_url'] ) ? trim( $settings['archive_url'], '/' ) : '';
		$base_slug = '';
		if ( ! empty( $base_url ) ) {
			$url_parts = explode('/', parse_url($base_url, PHP_URL_PATH));
			$base_slug = end($url_parts);
		}

		echo '<div class="sz-keywords-wrapper" style="display:flex; flex-wrap:wrap; width:100%;">';
		foreach ( $pills as $pill ) {
			$cleaned_pill = trim( $pill );
			if ( $cleaned_pill === '' ) continue;

			if ( ! empty( $base_slug ) ) {
				$clean_keyword = sanitize_title( $cleaned_pill );
				$link_url = home_url( '/' . $base_slug . '/' . $field_key . '/' . $clean_keyword . '/' );
				
				echo '<a href="' . esc_url( $link_url ) . '" class="sz-keyword-pill" style="display:inline-flex; align-items:center; text-decoration:none; transition:all 0.3s ease; box-sizing:border-box; cursor:pointer;">' . $hashtag . esc_html( $cleaned_pill ) . '</a>';
			} else {
				echo '<span class="sz-keyword-pill" style="display:inline-flex; align-items:center; transition:all 0.3s ease; box-sizing:border-box; cursor:default;">' . $hashtag . esc_html( $cleaned_pill ) . '</span>';
			}
		}
		echo '</div>';
	}
}