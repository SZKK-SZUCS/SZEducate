<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Egy "repeater" (ismétlődő sorokból álló, pl. Tantárgyak/Díjtételek/Ösztöndíjak)
// típusú séma-mező megjelenítésére. A Dynamic Tag-gel szemben (ami csak sima
// szöveges mezőkhöz való, és tömb-a-tömbben adatokat nem tud értelmesen kiírni)
// ez a Widget ismeri a repeater al-mezőit (sub_fields), és azokból épít
// táblázatot vagy kártyás elrendezést, teljes Stílus-vezérlőkkel.
class SZEducate_Repeater_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_repeater'; }
	public function get_title() { return 'SZEducate Ismétlődő Lista (Repeater)'; }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'general' ]; }

	private function get_repeater_fields() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		$fields = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( isset( $field['type'] ) && $field['type'] === 'repeater' && isset( $field['key'] ) ) {
						$fields[ $field['key'] ] = $field;
					}
				}
			}
		}

		return $fields;
	}

	protected function register_controls() {

		$repeater_fields = $this->get_repeater_fields();
		$options = array( '' => '-- Válassz Repeater mezőt --' );
		foreach ( $repeater_fields as $key => $field ) {
			$label = isset( $field['label'] ) ? $field['label'] : $key;
			$options[ $key ] = $label . ' [' . $key . ']';
		}

		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Adat Forrás',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'repeater_field_key',
			[
				'label'       => 'Megjelenítendő Mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => '',
				'description' => 'Csak a Séma Tervezőben "Ismétlődő Lista" (repeater) típusúra állított mezők jelennek meg itt.',
			]
		);

		$this->add_control(
			'layout',
			[
				'label'   => 'Elrendezés',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'table' => 'Táblázat',
					'cards' => 'Kártyák',
				],
				'default' => 'table',
			]
		);

		$this->add_control(
			'show_header',
			[
				'label'        => 'Fejléc sor megjelenítése',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'layout' => 'table' ],
			]
		);

		$this->add_control(
			'striped_rows',
			[
				'label'        => 'Csíkozott sorok',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'layout' => 'table' ],
			]
		);

		$this->add_control(
			'empty_text',
			[
				'label'     => 'Szöveg, ha nincs adat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => '',
				'separator' => 'before',
			]
		);

		$this->end_controls_section();

		// --- Táblázat elrendezés stílusa ---
		$this->start_controls_section(
			'style_table_section',
			[
				'label'     => 'Táblázat Stílusa',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [ 'layout' => 'table' ],
			]
		);

		$this->add_control(
			'header_bg_color',
			[
				'label'     => 'Fejléc Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-th' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'header_text_color',
			[
				'label'     => 'Fejléc Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-th' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'header_typography',
				'label'    => 'Fejléc Tipográfia',
				'selector' => '{{WRAPPER}} .sz-repeater-th',
				'fields_options' => [
					'font_weight'    => [ 'default' => '700' ],
					'text_transform' => [ 'default' => 'uppercase' ],
					'font_size'      => [ 'default' => [ 'unit' => 'px', 'size' => 12 ] ],
				],
			]
		);

		$this->add_control(
			'row_bg_color',
			[
				'label'     => 'Sor Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-tr' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'row_stripe_bg_color',
			[
				'label'     => 'Páratlan Sor Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F8F9FA',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-tr-odd' => 'background-color: {{VALUE}};' ],
				'condition' => [ 'striped_rows' => 'yes' ],
			]
		);

		$this->add_control(
			'cell_text_color',
			[
				'label'     => 'Cella Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-td' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'cell_typography',
				'label'    => 'Cella Tipográfia',
				'selector' => '{{WRAPPER}} .sz-repeater-td',
			]
		);

		$this->add_responsive_control(
			'cell_padding',
			[
				'label'      => 'Cella Belső Margó (Padding)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [ 'top' => 12, 'right' => 16, 'bottom' => 12, 'left' => 16, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .sz-repeater-th, {{WRAPPER}} .sz-repeater-td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'cell_border',
				'label'    => 'Cella Elválasztó',
				'selector' => '{{WRAPPER}} .sz-repeater-th, {{WRAPPER}} .sz-repeater-td',
				'fields_options' => [
					'border' => [ 'default' => 'solid' ],
					'width'  => [ 'default' => [ 'top' => '0', 'right' => '0', 'bottom' => '1', 'left' => '0', 'unit' => 'px', 'isLinked' => false ] ],
					'color'  => [ 'default' => '#E5E7EB' ],
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'table_border',
				'label'    => 'Táblázat Kerete',
				'selector' => '{{WRAPPER}} .sz-repeater-table-el',
			]
		);

		$this->add_responsive_control(
			'table_border_radius',
			[
				'label'      => 'Táblázat Lekerekítése',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px' ],
				'selectors'  => [
					'{{WRAPPER}} .sz-repeater-table-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				],
			]
		);

		$this->end_controls_section();

		// --- Kártyás elrendezés stílusa ---
		$this->start_controls_section(
			'style_cards_section',
			[
				'label'     => 'Kártyák Stílusa',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [ 'layout' => 'cards' ],
			]
		);

		$this->add_responsive_control(
			'cards_columns',
			[
				'label'          => 'Oszlopok száma',
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '2',
				'tablet_default' => '1',
				'mobile_default' => '1',
				'options'        => [
					'1' => '1 Oszlop',
					'2' => '2 Oszlop',
					'3' => '3 Oszlop',
					'4' => '4 Oszlop',
				],
				'selectors'      => [
					'{{WRAPPER}} .sz-repeater-cards' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
				],
			]
		);

		$this->add_responsive_control(
			'cards_gap',
			[
				'label'      => 'Kártyák közötti térköz',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 16 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-repeater-cards' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'card_bg_color',
			[
				'label'     => 'Kártya Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-card' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'      => 'Kártya Belső Margó (Padding)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [ 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-repeater-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'card_border_radius',
			[
				'label'      => 'Kártya Lekerekítése',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'default'    => [ 'top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-repeater-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .sz-repeater-card',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_box_shadow',
				'selector' => '{{WRAPPER}} .sz-repeater-card',
			]
		);

		$this->add_control(
			'card_row_heading',
			[
				'label'     => 'Kártyán belüli sorok',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'card_label_color',
			[
				'label'     => 'Címke Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#8C8F94',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-card-label' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'card_label_typography',
				'label'    => 'Címke Tipográfia',
				'selector' => '{{WRAPPER}} .sz-repeater-card-label',
				'fields_options' => [
					'font_size'      => [ 'default' => [ 'unit' => 'px', 'size' => 12 ] ],
					'text_transform' => [ 'default' => 'uppercase' ],
				],
			]
		);

		$this->add_control(
			'card_value_color',
			[
				'label'     => 'Érték Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-repeater-card-value' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'card_value_typography',
				'label'    => 'Érték Tipográfia',
				'selector' => '{{WRAPPER}} .sz-repeater-card-value',
			]
		);

		$this->add_responsive_control(
			'card_row_spacing',
			[
				'label'      => 'Sorok közötti térköz',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'default'    => [ 'unit' => 'px', 'size' => 8 ],
				'selectors'  => [
					'{{WRAPPER}} .sz-repeater-card-row' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .sz-repeater-card-row:last-child' => 'margin-bottom: 0;',
				],
			]
		);

		$this->end_controls_section();
	}

	// Egy repeater-sor egy cellájának kiírása az al-mező TÍPUSA szerint (logikus
	// szöveg helyett pl. Igen/Nem a boolean-nak, kattintható link az url-nek).
	private function render_cell_value( $value, $sub_field ) {
		$type = isset( $sub_field['type'] ) ? $sub_field['type'] : 'text';

		if ( $type === 'boolean' ) {
			return $value ? 'Igen' : 'Nem';
		}

		if ( $type === 'url' && ! empty( $value ) ) {
			return '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
		}

		if ( is_array( $value ) ) {
			return esc_html( implode( ', ', $value ) );
		}

		return esc_html( (string) $value );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$field_key = ! empty( $settings['repeater_field_key'] ) ? trim( $settings['repeater_field_key'] ) : '';

		if ( empty( $field_key ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">SZEducate Repeater Widget<br><small>Válassz ki egy Ismétlődő Lista mezőt a beállításokban!</small></div>';
			}
			return;
		}

		$repeater_fields = $this->get_repeater_fields();
		if ( ! isset( $repeater_fields[ $field_key ] ) ) return;
		$sub_fields = ! empty( $repeater_fields[ $field_key ]['sub_fields'] ) ? $repeater_fields[ $field_key ]['sub_fields'] : array();
		if ( empty( $sub_fields ) ) return;

		$post_id = get_the_ID();
		if ( get_post_type( $post_id ) !== 'sz_course' ) return;

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return;

		$rows = isset( $data[ $field_key ] ) && is_array( $data[ $field_key ] ) ? $data[ $field_key ] : array();

		if ( empty( $rows ) ) {
			if ( ! empty( $settings['empty_text'] ) ) {
				echo '<div class="sz-repeater-empty">' . esc_html( $settings['empty_text'] ) . '</div>';
			} elseif ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">Ehhez a Képzéshez még nincs adat felvéve ebben a listában.</div>';
			}
			return;
		}

		if ( $settings['layout'] === 'cards' ) {
			echo '<div class="sz-repeater-wrapper sz-repeater-cards" style="display:grid; width:100%;">';
			foreach ( $rows as $row ) {
				echo '<div class="sz-repeater-card">';
				foreach ( $sub_fields as $sf ) {
					$val = isset( $row[ $sf['key'] ] ) ? $row[ $sf['key'] ] : '';
					if ( $val === '' || $val === null ) continue;
					echo '<div class="sz-repeater-card-row">';
					echo '<span class="sz-repeater-card-label" style="display:block;">' . esc_html( $sf['label'] ) . '</span>';
					echo '<span class="sz-repeater-card-value" style="display:block;">' . $this->render_cell_value( $val, $sf ) . '</span>';
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
			return;
		}

		echo '<div class="sz-repeater-wrapper sz-repeater-table-wrap" style="width:100%; overflow-x:auto;">';
		echo '<table class="sz-repeater-table-el" style="width:100%; border-collapse:collapse;">';

		if ( $settings['show_header'] === 'yes' ) {
			echo '<thead><tr>';
			foreach ( $sub_fields as $sf ) {
				echo '<th class="sz-repeater-th" style="text-align:left;">' . esc_html( $sf['label'] ) . '</th>';
			}
			echo '</tr></thead>';
		}

		echo '<tbody>';
		foreach ( $rows as $index => $row ) {
			$stripe_class = ( $settings['striped_rows'] === 'yes' && $index % 2 === 1 ) ? ' sz-repeater-tr-odd' : '';
			echo '<tr class="sz-repeater-tr' . $stripe_class . '">';
			foreach ( $sub_fields as $sf ) {
				$val = isset( $row[ $sf['key'] ] ) ? $row[ $sf['key'] ] : '';
				echo '<td class="sz-repeater-td">' . $this->render_cell_value( $val, $sf ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody>';

		echo '</table>';
		echo '</div>';
	}
}
