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
				// Link-módban nincs értelme (egyetlen URL-t írunk ki hivatkozásként).
				'condition'   => array( 'field_key!' => '', 'render_as' => 'text' ),
			)
		);

		$this->add_control(
			'render_as',
			array(
				'label'     => 'Megjelenítés',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'text',
				'options'   => array(
					'text' => 'Szövegként (alap)',
					'link' => 'Kattintható hivatkozásként',
				),
				'condition' => array( 'field_key!' => '' ),
			)
		);

		// Opt-in: a meglévő felhasználások nyers értéket kapnak tovább (nem változnak).
		$this->add_control(
			'value_format',
			array(
				'label'       => 'Érték formázása',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''   => 'Nyers érték (alap)',
					'hu' => 'Magyar formázás (dátum, szám)',
				),
				'description' => 'Dátum mező: 2026. augusztus 31. - Szám mező: 50 000. Más típusú mezőt nem érint.',
				'condition'   => array( 'field_key!' => '', 'render_as' => 'text' ),
			)
		);

		$this->add_control(
			'link_prefix',
			array(
				'label'       => 'Bevezető szöveg',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'pl. „Részletek: "',
				'condition'   => array( 'field_key!' => '', 'render_as' => 'link' ),
			)
		);

		$this->add_control(
			'link_text',
			array(
				'label'       => 'Hivatkozás szövege',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Kattints ide',
				'placeholder' => 'üresen: az URL',
				'condition'   => array( 'field_key!' => '', 'render_as' => 'link' ),
			)
		);

		$this->add_control(
			'link_new_tab',
			array(
				'label'        => 'Új lapon nyíljon',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Igen',
				'label_off'    => 'Nem',
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'field_key!' => '', 'render_as' => 'link' ),
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

	// A séma-mező típusa (date / number / text ...), üres string, ha nincs ilyen mező.
	private function get_field_type( $key ) {
		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		if ( ! is_array( $schema ) ) return '';
		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( isset( $field['key'] ) && $field['key'] === $key ) {
					return isset( $field['type'] ) ? (string) $field['type'] : '';
				}
			}
		}
		return '';
	}

	// "Magyar formázás": csak date és number típusú mezőre hat, a többit érintetlenül hagyja.
	// Az ezres tagoló nem törhető szóköz, hogy az "50 000 Ft" ne törjön két sorba.
	private function format_hu_value( $key, $value ) {
		// A szám néha JSON-számként (int/float), néha szövegként tárolt.
		if ( $this->get_settings( 'value_format' ) !== 'hu' || ! ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) ) {
			return $value;
		}
		$type = $this->get_field_type( $key );
		$v    = trim( (string) $value );
		if ( $type === 'date' && preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			// UTC éjfél: a date_i18n a "helyi időként értelmezett" időbélyeget UTC-ként formázza,
			// így a nap nem csúszik át a webhely időzónája miatt.
			return date_i18n( 'Y. F j.', gmmktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] ) );
		}
		if ( $type === 'number' && is_numeric( $v ) ) {
			$n = (float) $v;
			return number_format( $n, floor( $n ) === $n ? 0 : 2, ',', "\xC2\xA0" );
		}
		return $value;
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

		// „Kattintható hivatkozásként" megjelenítés: a mező (string) értékét egyetlen URL-nek
		// vesszük és <a> elemként írjuk ki a megadott felirattal. Így egy sima szöveges
		// kontextusban (Címsor / Szövegszerkesztő) is elhelyezhető egy dinamikus URL-re
		// mutató inline hivatkozás (pl. „Kattints ide"), nem csak nyers URL-ként.
		if ( $this->get_settings( 'render_as' ) === 'link' && is_string( $value ) ) {
			$url = esc_url( trim( $value ) );
			if ( $url === '' ) return; // érvénytelen / nem támogatott protokoll -> a Fallback él

			$link_text = $this->get_settings( 'link_text' );
			if ( $link_text === null || $link_text === '' ) $link_text = $value;

			$prefix = $this->get_settings( 'link_prefix' );
			if ( is_string( $prefix ) && $prefix !== '' ) {
				echo esc_html( $prefix );
			}

			$target = ( $this->get_settings( 'link_new_tab' ) === 'yes' )
				? ' target="_blank" rel="noopener noreferrer"'
				: '';

			echo '<a href="' . $url . '"' . $target . '>' . esc_html( $link_text ) . '</a>';
			return;
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
			echo wp_kses_post( $this->format_hu_value( $field_key, $value ) );
		}
	}
}