<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Links_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_links'; }
	public function get_title() { return 'SZEducate Linkek'; }
	public function get_icon() { return 'eicon-button'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {
		
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		$options = array( '' => '-- Válassz mezőt --' );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( isset( $field['type'] ) && $field['type'] === 'links' && isset( $field['key'] ) ) {
						$label = isset( $field['label'] ) ? $field['label'] : $field['key'];
						$options[ $field['key'] ] = $label . ' [' . $field['key'] . ']';
					}
				}
			}
		}

		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Adat Forrás',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'link_field_key',
			[
				'label'       => 'Megjelenítendő Mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => '',
				'description' => 'Csak a Séma Tervezőben "Többszörös Link" (links) típusúra állított mezők jelennek meg itt.'
			]
		);

		$this->add_control(
			'link_icon',
			[
				'label'   => 'Globális Ikon a linkekhez (Opcionális)',
				'type'    => \Elementor\Controls_Manager::ICONS,
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
					'stretch'    => [ 'title' => 'Kifeszít', 'icon' => 'eicon-text-align-justify' ],
				],
				'selectors' => [
					'{{WRAPPER}} .sz-links-wrapper' => 'align-items: {{VALUE}}; justify-content: {{VALUE}};',
					'{{WRAPPER}} .sz-link-item'     => 'justify-content: {{VALUE}}; text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'direction',
			[
				'label'   => 'Irány',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'row'    => 'Egymás mellett',
					'column' => 'Egymás alatt',
				],
				'default' => 'row',
				'selectors' => [
					'{{WRAPPER}} .sz-links-wrapper' => 'flex-direction: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label'      => 'Térköz a gombok között',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 10 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-links-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_button_section',
			[
				'label' => 'Gomb / Link Dizájn',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .sz-link-item',
			]
		);

		$this->add_responsive_control(
			'button_padding',
			[
				'label'      => 'Belső Margó (Padding)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top' => 10, 'right' => 20, 'bottom' => 10, 'left' => 20,
					'unit' => 'px', 'isLinked' => false,
				],
				'selectors'  => [
					'{{WRAPPER}} .sz-link-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'button_border_radius',
			[
				'label'      => 'Lekerekítés (Border Radius)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top' => 4, 'right' => 4, 'bottom' => 4, 'left' => 4,
					'unit' => 'px', 'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .sz-link-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			[ 'label' => 'Normál' ]
		);

		$this->add_control(
			'button_text_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ 
					'{{WRAPPER}} .sz-link-item' => 'color: {{VALUE}};',
					'{{WRAPPER}} .sz-link-item svg' => 'fill: {{VALUE}};',
					'{{WRAPPER}} .sz-link-item i' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_bg_color',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#007cba',
				'selectors' => [ '{{WRAPPER}} .sz-link-item' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .sz-link-item',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .sz-link-item',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			[ 'label' => 'Hover' ]
		);

		$this->add_control(
			'button_hover_text_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ 
					'{{WRAPPER}} .sz-link-item:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .sz-link-item:hover svg' => 'fill: {{VALUE}};',
					'{{WRAPPER}} .sz-link-item:hover i' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_bg_color',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#005a87',
				'selectors' => [ '{{WRAPPER}} .sz-link-item:hover' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'button_hover_border',
				'selector' => '{{WRAPPER}} .sz-link-item:hover',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .sz-link-item:hover',
			]
		);

		$this->add_control(
			'hover_animation',
			[
				'label' => 'Hover Animáció',
				'type'  => \Elementor\Controls_Manager::HOVER_ANIMATION,
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'style_icon_section',
			[
				'label' => 'Ikon Finomhangolás',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
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
					'{{WRAPPER}} .sz-link-icon' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-link-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_spacing',
			[
				'label'      => 'Távolság a szövegtől',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 8 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-link-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_valign',
			[
				'label'      => 'Függőleges pozíció (Finomhangolás)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => -20, 'max' => 20 ] ],
				'default'    => [ 'unit' => 'px', 'size' => 0 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-link-icon' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['link_field_key'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">SZEducate Linkek Widget<br><small>Válassz ki egy mezőt a beállításokban!</small></div>';
			}
			return;
		}

		$post_id = get_the_ID();
		if ( get_post_type( $post_id ) !== 'sz_course' ) return;

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE local_post_id = %d LIMIT 1", $post_id ), ARRAY_A );

		if ( ! $course ) return;

		$data = json_decode( $course['course_data'], true );
		$links = isset( $data[ $settings['link_field_key'] ] ) ? $data[ $settings['link_field_key'] ] : array();

		if ( ! is_array( $links ) || empty( $links ) ) return;

		$icon_html = '';
		$icon_setting = isset($settings['link_icon']) ? $settings['link_icon'] : [];
		if ( ! empty( $icon_setting['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $icon_setting, [ 'aria-hidden' => 'true' ] );
			$icon_out = ob_get_clean();
			
			if ( empty( $icon_out ) && is_string( $icon_setting['value'] ) ) {
				$icon_out = '<i class="' . esc_attr( $icon_setting['value'] ) . '" aria-hidden="true"></i>';
			}
			if ( ! empty( $icon_out ) ) {
				$icon_html = '<span class="sz-link-icon" style="display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">' . $icon_out . '</span>';
			}
		}

		$hover_class = '';
		if ( ! empty( $settings['hover_animation'] ) ) {
			$hover_class = ' elementor-animation-' . esc_attr( $settings['hover_animation'] );
		}

		echo '<div class="sz-links-wrapper" style="display:flex; flex-wrap:wrap;">';
		
		foreach ( $links as $link ) {
			if ( empty( $link['url'] ) || empty( $link['title'] ) ) continue;
			$url = esc_url( $link['url'] );
			$title = esc_html( $link['title'] );
			
			echo "<a href=\"{$url}\" class=\"sz-link-item{$hover_class}\" style=\"display:inline-flex; align-items:center; text-decoration:none; transition:all 0.3s ease; line-height:1.2; box-sizing:border-box;\" target=\"_blank\">";
			if ( ! empty( $icon_html ) ) {
				echo $icon_html;
			}
			echo "<span>{$title}</span>";
			echo "</a>";
		}
		
		echo '</div>';
	}
}