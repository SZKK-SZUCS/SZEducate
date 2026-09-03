<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'szeducate-course-data';
	}

	public function get_title() {
		return 'Képzés Adat (SZEducate)';
	}

	public function get_group() {
		return 'szeducate';
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		
		$options = array( '' => 'Válassz mezőt...' );

		// A "repeater" és "links" típusú mezők strukturált (tömb-a-tömbben) adatok - ezeknek
		// dedikált Widget kell (Repeater Widget, Linkek Widget), egy sima szöveges Dynamic
		// Tag-ben nincs értelmes, egysoros megjelenítésük, ezért itt nem is választhatók.
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( isset( $field['type'] ) && in_array( $field['type'], array( 'repeater', 'links' ), true ) ) continue;
						$options[ $field['key'] ] = $field['label'];
					}
				}
			}
		}

		$this->add_control(
			'field_key',
			array(
				'label'   => 'Megjelenítendő Mező',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);

		$this->add_control(
			'array_separator',
			array(
				'label'       => 'Elválasztó (több elem esetén)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => ', ',
				'description' => 'Pl. ", " vagy " • ". Írj \n-t, ha minden elem külön sorba kerüljön. A jelölőnégyzet (több opciós) mezőkre akkor is hat, ha az érték ";"-vel elválasztott szövegként van tárolva.',
				'condition'   => array( 'field_key!' => '' ),
			)
		);
	}

	// A megadott kulcsú séma-mező jelölőnégyzet (több opció) típusú-e - ezeknél a
	// ";"-vel elválasztott szöveges tárolást is listaként kezeljük, hogy az
	// elválasztó-vezérlő rá is hasson.
	private function is_multi_value_field( $key ) {
		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		if ( ! is_array( $schema ) ) return false;
		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( isset( $field['key'] ) && $field['key'] === $key ) {
					return isset( $field['type'] ) && $field['type'] === 'checkbox';
				}
			}
		}
		return false;
	}

	public function render() {
		$field_key = $this->get_settings( 'field_key' );
		if ( empty( $field_key ) ) return;

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) return;

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return;

		if ( ! isset( $data[ $field_key ] ) || $data[ $field_key ] === '' ) return;

		$value = $data[ $field_key ];

		// Több opciós mező ";"-vel elválasztott szövegként tárolva -> listává alakítjuk,
		// hogy ne nyersen (a tárolt ";"-kkel) íródjon ki, hanem az elválasztó-vezérlő
		// szerint. (A séma-szintű tárolás mezőnként ingadozik: hol JSON tömb, hol string.)
		if ( is_string( $value ) && strpos( $value, ';' ) !== false && $this->is_multi_value_field( $field_key ) ) {
			$parts = array_values( array_filter( array_map( 'trim', explode( ';', $value ) ), 'strlen' ) );
			if ( count( $parts ) > 1 ) {
				$value = $parts;
			}
		}

		if ( is_array( $value ) ) {
			// Struktúrált (repeater / links) mezőknél a tömb elemei maguk is tömbök -
			// ezekre a "Repeater" ill. "Linkek" Widget való, itt (sima szöveges Dynamic
			// Tag-ként) csak egyszerű, lapos listákat tudunk értelmesen megjeleníteni.
			$is_flat = true;
			foreach ( $value as $v ) {
				if ( is_array( $v ) ) { $is_flat = false; break; }
			}
			if ( ! $is_flat ) return;

			$parts = array();
			foreach ( $value as $v ) {
				$v = trim( (string) $v );
				if ( $v !== '' ) $parts[] = esc_html( $v );
			}
			if ( empty( $parts ) ) return;

			$raw_sep = $this->get_settings( 'array_separator' );
			if ( $raw_sep === null || $raw_sep === '' ) $raw_sep = ', ';
			// "\n" a szövegmezőben (két karakter) VAGY valódi sortörés -> <br>
			$glue = ( strpos( $raw_sep, '\\n' ) !== false || strpos( $raw_sep, "\n" ) !== false )
				? '<br>'
				: esc_html( $raw_sep );

			echo implode( $glue, $parts );
		} elseif ( is_bool( $value ) ) {
			echo $value ? 'Igen' : 'Nem';
		} else {
			echo wp_kses_post( $value );
		}
	}
}