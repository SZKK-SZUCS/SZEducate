<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Links_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_links'; }
	public function get_title() { return '🎓 SZEducate Linkek'; }
	public function get_icon() { return 'eicon-editor-list-ul'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		$options = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					// Csak a "links" típusú mezőket listázzuk ki!
					if ( $field['type'] === 'links' ) {
						$options[ $field['key'] ] = $field['label'];
					}
				}
			}
		}

		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Linkek Beállítása',
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'link_field_key',
			[
				'label' => 'Megjelenítendő Mező',
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'description' => 'Csak a Séma Tervezőben Többszörös Link-ként beállított mezők jelennek meg itt.'
			]
		);

		$this->add_control(
			'button_style',
			[
				'label' => 'Megjelenés Stílusa',
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'elementor-button elementor-size-sm' => 'Alapértelmezett Gomb',
					'elementor-button elementor-button-link elementor-size-sm' => 'Másodlagos Gomb',
					'szeducate-simple-link' => 'Sima Szöveges Link',
				],
				'default' => 'elementor-button elementor-size-sm',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['link_field_key'] ) ) return;

		$post_id = get_the_ID();
		if ( get_post_type( $post_id ) !== 'sz_course' ) return;

		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_data FROM $table_name WHERE local_post_id = %d LIMIT 1", $post_id ), ARRAY_A );

		if ( ! $course ) return;

		$data = json_decode( $course['course_data'], true );
		$links = isset( $data[ $settings['link_field_key'] ] ) ? $data[ $settings['link_field_key'] ] : array();

		if ( ! is_array( $links ) || empty( $links ) ) return;

		echo '<div class="szeducate-links-wrapper" style="display:flex; flex-direction:column; gap:10px;">';
		foreach ( $links as $link ) {
			if ( empty( $link['url'] ) || empty( $link['title'] ) ) continue;
			$url = esc_url( $link['url'] );
			$title = esc_html( $link['title'] );
			$class = esc_attr( $settings['button_style'] );
			
			// Ha sima link, kell egy kis stílus
			$inline_style = $class === 'szeducate-simple-link' ? 'style="color:#007cba; text-decoration:underline; font-weight:600;"' : '';

			echo "<a href='{$url}' class='{$class}' {$inline_style} target='_blank'>{$title}</a>";
		}
		echo '</div>';
	}
}