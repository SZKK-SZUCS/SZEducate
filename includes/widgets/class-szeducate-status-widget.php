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

		// --- Meghirdetési időszakok: "Következő indulás" kiemelése ---
		$this->start_controls_section(
			'periods_section',
			[
				'label' => 'Meghirdetési időszakok',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'periods_enabled',
			[
				'label'        => 'Következő indulás kiemelése',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => 'A mai dátum alapján a soron következő meghirdetést emeli ki, a többit halványan alálistázza. Kikapcsolva egyszerű felsorolás jelenik meg.',
			]
		);

		$this->add_control(
			'next_prefix',
			[
				'label'   => '"Következő" felirat',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Következő indulás:',
			]
		);

		$this->add_control(
			'show_later',
			[
				'label'        => 'A további időszakok listázása',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'periods_enabled' => 'yes' ],
			]
		);

		$this->add_control(
			'later_prefix',
			[
				'label'     => '"Később" felirat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Később:',
				'condition' => [ 'periods_enabled' => 'yes', 'show_later' => 'yes' ],
			]
		);

		$period_repeater = new \Elementor\Repeater();
		$period_repeater->add_control(
			'opt_value',
			[
				'label'       => 'Séma-opció (pontos szöveg)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => 'Ahogy az "Indulás időszaka" mezőben szerepel.',
			]
		);
		$period_repeater->add_control(
			'short_label',
			[
				'label' => 'Rövid címke',
				'type'  => \Elementor\Controls_Manager::TEXT,
			]
		);
		$period_repeater->add_control(
			'deadline_month',
			[
				'label'   => 'Jelentkezési határidő – hónap',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '2',
				'options' => [
					'1' => 'Január', '2' => 'Február', '3' => 'Március', '4' => 'Április',
					'5' => 'Május', '6' => 'Június', '7' => 'Július', '8' => 'Augusztus',
					'9' => 'Szeptember', '10' => 'Október', '11' => 'November', '12' => 'December',
				],
			]
		);
		$period_repeater->add_control(
			'deadline_day',
			[
				'label'   => 'Nap',
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 31,
				'default' => 15,
			]
		);

		$this->add_control(
			'periods',
			[
				'label'       => 'Időszakok és határidők',
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $period_repeater->get_controls(),
				'title_field' => '{{{ short_label || opt_value }}}',
				'description' => 'A "határidő" csak a sorrendhez kell (melyik indulás jön előbb). Nem kell pontosnak lennie, a hónap általában elég.',
				'default'     => [
					[
						'opt_value'      => 'Szeptemberi általános eljárás',
						'short_label'    => 'Szeptemberi felvételi',
						'deadline_month' => '2',
						'deadline_day'   => 15,
					],
					[
						'opt_value'      => 'Szeptemberi pótfelvételi eljárás',
						'short_label'    => 'Pótfelvételi',
						'deadline_month' => '8',
						'deadline_day'   => 7,
					],
					[
						'opt_value'      => 'Februári keresztféléves eljárás',
						'short_label'    => 'Keresztféléves',
						'deadline_month' => '11',
						'deadline_day'   => 15,
					],
				],
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
				'label' => 'Vízszintes igazítás',
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

		$this->add_responsive_control(
			'valignment',
			[
				'label'       => 'Függőleges igazítás',
				'type'        => \Elementor\Controls_Manager::CHOOSE,
				'options'     => [
					'flex-start' => [ 'title' => 'Fent', 'icon' => 'eicon-v-align-top' ],
					'center'     => [ 'title' => 'Középen', 'icon' => 'eicon-v-align-middle' ],
					'flex-end'   => [ 'title' => 'Lent', 'icon' => 'eicon-v-align-bottom' ],
					'stretch'    => [ 'title' => 'Nyújtott', 'icon' => 'eicon-v-align-stretch' ],
				],
				'description' => 'A "Középen" / "Lent" akkor látszik, ha a widget magasabb a tartalmánál - ehhez kapcsold be alább a magasság-kitöltést.',
				'selectors'   => [
					'{{WRAPPER}} .szeducate-status-wrapper' => 'align-items: {{VALUE}}; align-content: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'fill_height',
			[
				'label'        => 'Töltse ki a rendelkezésre álló magasságot',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => '',
				'description'  => 'Ha a widget egy magasabb konténerben ül (pl. hero kép fölött), ezzel a tartalom a "Függőleges igazítás" szerint tolható fel / le.',
				'selectors'    => [
					'{{WRAPPER}}'                              => 'align-self: stretch;',
					'{{WRAPPER}} .elementor-widget-container'  => 'height: 100%;',
					'{{WRAPPER}} .szeducate-status-wrapper'    => 'min-height: 100%;',
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
				// A widget korábban a betűtípust/méretet/vastagságot/sortávot közvetlenül a
				// kimenetbe ("style" attribútum) sütötte, ami a beépített CSS-specificitási
				// szabályok miatt LEHETETLENNÉ tette ennek a szekciónak a tényleges
				// felülírását - az alap kinézet mostantól ITT, a vezérlő alapértékeként él,
				// hogy tényleg szerkeszthető maradjon.
				'fields_options' => [
					'font_family'    => [ 'default' => '' ],
					'font_size'      => [ 'default' => [ 'unit' => 'px', 'size' => 12 ] ],
					'font_weight'    => [ 'default' => '600' ],
					'line_height'    => [ 'default' => [ 'unit' => 'px', 'size' => 1 ] ],
				],
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
				'label' => 'Következő indulás - Háttér',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-group-info .sz-status-main' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_info_text',
			[
				'label' => 'Következő indulás - Szöveg',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-group-info .sz-status-main' => 'color: {{VALUE}}; fill: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'color_later_text',
			[
				'label' => '"Később" felsorolás - Szöveg',
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#6B7280',
				'selectors' => [ '{{WRAPPER}} .sz-period-later' => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	// A bejelölt indulási időszakokat a "Időszakok és határidők" repeater alapján
	// rendezi: melyik jön előbb a mai naptól számítva (év végén körbefordulva). A
	// repeaterben nem szereplő, de bejelölt értékek a lista végére kerülnek, eredeti
	// szövegükkel. Visszaad: [ ['label'=>string, 'ts'=>int], ... ], a legközelebbi elöl.
	private function resolve_periods( $checked, $periods_cfg ) {
		$checked = array_values( array_filter( array_map( 'trim', (array) $checked ) ) );
		if ( empty( $checked ) ) return array();

		$today    = strtotime( current_time( 'Y-m-d' ) );
		$cur_year = (int) date( 'Y', $today );
		$out      = array();
		$seen_lc  = array();

		foreach ( (array) $periods_cfg as $p ) {
			if ( ! is_array( $p ) ) continue;
			$ov = isset( $p['opt_value'] ) ? trim( (string) $p['opt_value'] ) : '';
			if ( $ov === '' ) continue;

			$hit = false;
			foreach ( $checked as $c ) {
				if ( mb_strtolower( $c, 'UTF-8' ) === mb_strtolower( $ov, 'UTF-8' ) ) { $hit = true; break; }
			}
			if ( ! $hit ) continue;

			$m  = max( 1, min( 12, (int) ( isset( $p['deadline_month'] ) ? $p['deadline_month'] : 1 ) ) );
			$d  = max( 1, min( 31, (int) ( isset( $p['deadline_day'] ) ? $p['deadline_day'] : 1 ) ) );
			$ts = strtotime( sprintf( '%04d-%02d-%02d', $cur_year, $m, $d ) );
			if ( $ts === false ) $ts = strtotime( sprintf( '%04d-%02d-01', $cur_year, $m ) );
			if ( $ts !== false && $ts < $today ) $ts = strtotime( '+1 year', $ts );

			$label = ( isset( $p['short_label'] ) && trim( (string) $p['short_label'] ) !== '' )
				? trim( (string) $p['short_label'] ) : $ov;

			$out[]     = array( 'label' => $label, 'ts' => $ts ? $ts : PHP_INT_MAX );
			$seen_lc[] = mb_strtolower( $ov, 'UTF-8' );
		}

		foreach ( $checked as $c ) {
			if ( ! in_array( mb_strtolower( $c, 'UTF-8' ), $seen_lc, true ) ) {
				$out[] = array( 'label' => $c, 'ts' => PHP_INT_MAX );
			}
		}

		usort( $out, function( $a, $b ) { return $a['ts'] <=> $b['ts']; } );
		return $out;
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

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return;

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

		// A betűtípus/méret/vastagság/sortáv szándékosan NEM itt, hanem a "badge_typography"
		// Stílus-vezérlőn keresztül kerül beállításra (lásd register_controls) - ha ide
		// visszakerülne inline "style" attribútumként, a vezérlő megint hatástalanná válna.
		$css_group = "display:inline-flex; align-items:stretch; overflow:hidden;";
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

		$checked_periods = is_array( $start_period )
			? $start_period
			: array_map( 'trim', preg_split( '/;/', (string) $start_period ) );
		$ordered = $this->resolve_periods( $checked_periods, isset( $settings['periods'] ) ? $settings['periods'] : array() );

		if ( ! empty( $ordered ) ) {
			$periods_enabled = ( ! isset( $settings['periods_enabled'] ) ) || $settings['periods_enabled'] === 'yes';

			if ( $periods_enabled ) {
				$next        = array_shift( $ordered );
				$next_prefix = isset( $settings['next_prefix'] ) ? trim( $settings['next_prefix'] ) : 'Következő indulás:';
				$next_text   = $next_prefix !== '' ? ( $next_prefix . ' ' . $next['label'] ) : $next['label'];

				echo "<div class='sz-status-group sz-group-info' style='{$css_group}'>";
					echo "<div class='sz-status-part sz-status-main' style='{$css_part}'>";
					echo $icon_calendar . esc_html( $next_text );
					echo "</div>";
				echo "</div>";

				$show_later = ( ! isset( $settings['show_later'] ) ) || $settings['show_later'] === 'yes';
				if ( $show_later && ! empty( $ordered ) ) {
					$later_prefix = isset( $settings['later_prefix'] ) ? trim( $settings['later_prefix'] ) : 'Később:';
					$later_labels = array();
					foreach ( $ordered as $p ) { $later_labels[] = $p['label']; }
					$later_text = ( $later_prefix !== '' ? $later_prefix . ' ' : '' ) . implode( ' · ', $later_labels );
					echo "<div class='sz-period-later' style='width:100%; padding:2px 2px 0; font-size:12px; line-height:1.4;'>" . esc_html( $later_text ) . "</div>";
				}
			} else {
				$all_labels = array();
				foreach ( $ordered as $p ) { $all_labels[] = $p['label']; }
				echo "<div class='sz-status-group sz-group-info' style='{$css_group}'>";
					echo "<div class='sz-status-part sz-status-main' style='{$css_part}'>";
					echo $icon_info . esc_html( implode( ', ', $all_labels ) );
					echo "</div>";
				echo "</div>";
			}
		}

		echo '</div>';
	}
}