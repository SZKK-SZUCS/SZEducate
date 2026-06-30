<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Status_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_status'; }
	public function get_title() { return 'SZEducate Státusz'; }
	public function get_icon() { return 'eicon-info-circle'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {
		
		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Adat Források',
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'status_key',
			[
				'label' => 'Állapot (Aktív/Inaktív) Mező Kulcsa',
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 'meghirdetes_allapota',
			]
		);

		$this->add_control(
			'date_key',
			[
				'label' => 'Passziválás dátuma (ha van) Kulcsa',
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 'passziv_ettol',
			]
		);

		$this->add_control(
			'start_period_key',
			[
				'label' => 'Indulás Időszaka Mező Kulcsa (Opcionális)',
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => 'indulas_idoszaka',
				'description' => 'Pl. Keresztféléves, Szeptemberben induló, stb.'
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_layout_section',
			[
				'label' => 'Elrendezés',
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'alignment',
			[
				'label' => 'Igazítás',
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [ 'title' => 'Balra', 'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => 'Középre', 'icon' => 'eicon-text-align-center' ],
					'flex-end' => [ 'title' => 'Jobbra', 'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [
					'{{WRAPPER}} .szeducate-status-wrapper' => 'justify-content: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'direction',
			[
				'label' => 'Irány (Több adat esetén)',
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'row' => 'Egymás mellett',
					'column' => 'Egymás alatt',
				],
				'default' => 'row',
				'selectors' => [
					'{{WRAPPER}} .szeducate-status-wrapper' => 'flex-direction: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label' => 'Térköz az elemek között',
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range' => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'default' => [ 'unit' => 'px', 'size' => 15 ],
				'selectors' => [
					'{{WRAPPER}} .szeducate-status-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_badge_section',
			[
				'label' => 'Tipográfia és Alak',
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'badge_typography',
				'selector' => '{{WRAPPER}} .sz-status-part',
			]
		);

		$this->add_responsive_control(
			'badge_border_radius',
			[
				'label' => 'Lekerekítés (Border Radius)',
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'default' => [
					'top' => 6, 'right' => 6, 'bottom' => 6, 'left' => 6,
					'unit' => 'px', 'isLinked' => true,
				],
				'selectors' => [
					'{{WRAPPER}} .sz-status-group' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'badge_box_shadow',
				'selector' => '{{WRAPPER}} .sz-status-group',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_colors_section',
			[
				'label' => 'Színek (Állapot alapján)',
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'color_active_bg',
			[
				'label' => 'Aktív - Fő Háttérszín',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-state-active .sz-status-main' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_active_text',
			[
				'label' => 'Aktív - Fő Szövegszín',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-state-active .sz-status-main' => 'color: {{VALUE}}; fill: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_inactive_bg',
			[
				'label' => 'Inaktív - Fő Háttérszín',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#D9D9D9',
				'selectors' => [ '{{WRAPPER}} .sz-state-inactive .sz-status-main' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_inactive_text',
			[
				'label' => 'Inaktív - Fő Szövegszín',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-state-inactive .sz-status-main' => 'color: {{VALUE}}; fill: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_secondary_bg',
			[
				'label' => 'Kiegészítő Adat (Pl. Dátum) - Háttér',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#F3F4F6',
				'selectors' => [ '{{WRAPPER}} .sz-status-sub' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_secondary_text',
			[
				'label' => 'Kiegészítő Adat - Szöveg',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-status-sub' => 'color: {{VALUE}};' ],
			]
		);
		
		$this->add_control(
			'color_info_bg',
			[
				'label' => 'Indulás (Info blokk) - Háttér',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-group-info .sz-status-main' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_info_text',
			[
				'label' => 'Indulás (Info blokk) - Szöveg',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-group-info .sz-status-main' => 'color: {{VALUE}}; fill: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$post_id = get_the_ID();
		
		if ( get_post_type( $post_id ) !== 'sz_course' ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">SZEducate Státusz Widget (Képzés szerkesztésekor jelenik meg)</div>';
			}
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE local_post_id = %d LIMIT 1", $post_id ), ARRAY_A );

		if ( ! $course ) return;

		$data = json_decode( $course['course_data'], true );
		$status = isset( $data[ $settings['status_key'] ] ) ? $data[ $settings['status_key'] ] : '';
		$expiry = isset( $data[ $settings['date_key'] ] ) ? trim((string)$data[ $settings['date_key'] ]) : '';
		$start_period = isset( $data[ $settings['start_period_key'] ] ) ? $data[ $settings['start_period_key'] ] : '';

		$safe_status = mb_strtolower(trim((string)$status), 'UTF-8');
		$is_active = ( $safe_status === 'aktív' || $safe_status === 'aktiv' );
		$is_expired = false;

		if ( $is_active && ! empty( $expiry ) ) {
			$expiry_time = strtotime( $expiry );
			$today_time = strtotime( current_time( 'Y-m-d' ) );
			
			if ( $expiry_time !== false && $today_time >= $expiry_time ) {
				$is_active = false;
				$is_expired = true;
			}
		}

		$icon_calendar = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="margin-right:6px;"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>';
		$icon_info = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="margin-right:6px;"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>';

		$css_group = "display:inline-flex; align-items:stretch; overflow:hidden; font-family:sans-serif; font-size:12px; font-weight:600; line-height:1;";
		$css_part = "display:flex; align-items:center; padding:8px 14px;";

		echo '<div class="szeducate-status-wrapper" style="display:flex; flex-wrap:wrap;">';

		if ( $is_active ) {
			$main_text = 'JELENTKEZÉS NYITVA';
			$state_class = 'sz-state-active';
		} else {
			$main_text = $is_expired ? 'JELENTKEZÉS LEZÁRULT' : 'JELENLEG NEM INDUL';
			$state_class = 'sz-state-inactive';
		}

		echo "<div class='sz-status-group {$state_class}' style='{$css_group}'>";
			echo "<div class='sz-status-part sz-status-main' style='{$css_part}'>";
			echo $icon_calendar . esc_html( $main_text );
			echo "</div>";
			
			if ( $is_active && ! empty( $expiry ) && strtotime( $expiry ) !== false ) {

				$formatted_date = date('Y. m. d.', strtotime($expiry)) . ' határidő';
				echo "<div class='sz-status-part sz-status-sub' style='{$css_part}'>";
				echo esc_html( $formatted_date );
				echo "</div>";
			}
		echo "</div>";

		if ( ! empty( $start_period ) ) {
			$start_text = is_array( $start_period ) ? implode( ', ', $start_period ) : (string)$start_period;
			echo "<div class='sz-status-group sz-group-info' style='{$css_group}'>";
				echo "<div class='sz-status-part sz-status-main' style='{$css_part}'>";
				echo $icon_info . esc_html( $start_text );
				echo "</div>";
			echo "</div>";
		}

		echo '</div>';
	}
}