<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Elementor {

	public function init() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );
		add_action( 'elementor/element/section/section_advanced/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );
		add_action( 'elementor/element/column/section_advanced/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );
		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'add_visibility_controls' ), 10, 2 );

		add_filter( 'elementor/frontend/widget/should_render', array( $this, 'should_render_element' ), 10, 2 );
		add_filter( 'elementor/frontend/section/should_render', array( $this, 'should_render_element' ), 10, 2 );
		add_filter( 'elementor/frontend/column/should_render', array( $this, 'should_render_element' ), 10, 2 );
		add_filter( 'elementor/frontend/container/should_render', array( $this, 'should_render_element' ), 10, 2 );

		// Az Elementor "Harmonika" widget (klasszikus 'accordion' ÉS az újabb
		// 'nested-accordion') füleit egyenként SZEducate-láthatósághoz kötő extra
		// vezérlő + a rejtett fülek szerver oldali kiszűrése a kimenetből. Generikus
		// hookot használunk, mert a két widget szakasz-azonosítói eltérnek.
		add_action( 'elementor/element/after_section_end', array( $this, 'maybe_add_accordion_item_visibility' ), 10, 3 );
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_accordion_conditional_tabs' ), 10, 2 );
	}

	public function register_dynamic_tags( $dynamic_tags_manager ) {
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-dynamic-tag.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-image-dynamic-tag.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-video-dynamic-tag.php';
		$dynamic_tags_manager->register_group( 'szeducate', array( 'title' => 'SZEducate Adatok' ) );
		$dynamic_tags_manager->register( new SZEducate_Dynamic_Tag() );
		$dynamic_tags_manager->register( new SZEducate_Image_Dynamic_Tag() );
		$dynamic_tags_manager->register( new SZEducate_Video_Dynamic_Tag() );
	}

	public function register_widgets( $widgets_manager ) {
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-links-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-status-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-listing-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-keywords-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-search-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-repeater-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-pricing-table-widget.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/widgets/class-szeducate-video-widget.php';

		$widgets_manager->register( new SZEducate_Search_Widget() );
		$widgets_manager->register( new SZEducate_Links_Widget() );
		$widgets_manager->register( new SZEducate_Status_Widget() );
		$widgets_manager->register( new SZEducate_Listing_Widget() );
		$widgets_manager->register( new SZEducate_Keywords_Widget() );
		$widgets_manager->register( new SZEducate_Repeater_Widget() );
		$widgets_manager->register( new SZEducate_Pricing_Table_Widget() );
		$widgets_manager->register( new SZEducate_Video_Widget() );
	}

	// Séma-mezők "kulcs => Címke [kulcs]" listája a láthatóság-vezérlők legördülőihez.
	private function get_field_options() {
		$schema  = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$options = array();
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( ! isset( $field['key'] ) ) continue;
					$label = isset( $field['label'] ) ? $field['label'] : $field['key'];
					$options[ $field['key'] ] = $label . ' [' . $field['key'] . ']';
				}
			}
		}
		return $options;
	}

	// Egy mezőérték illeszkedik-e a szabályra. (Közös a szakasz/oszlop/konténer/widget
	// láthatóság és a harmonika-fül feltételek között.)
	private function sz_rule_matches( $actual_val, $rule, $target_val ) {
		// A strukturált mezők (repeater / links) tömb-a-tömbben adatok - a JSON-alak
		// üres tömbnél tényleg üres ("[]"), kitöltöttnél nem.
		$actual_str = is_array( $actual_val ) ? wp_json_encode( $actual_val ) : (string) $actual_val;
		$is_empty   = ( trim( $actual_str ) === '' || $actual_str === '[]' || $actual_str === '{}' );
		$target_val = (string) $target_val;

		switch ( $rule ) {
			case 'empty':      return $is_empty;
			case 'not_empty':  return ! $is_empty;
			case 'equals':     return $actual_str === $target_val;
			case 'not_equals': return $actual_str !== $target_val;
			case 'contains':   return $target_val !== '' && strpos( $actual_str, $target_val ) !== false;
		}
		return false;
	}

	public function add_visibility_controls( $element, $args ) {
		$options = $this->get_field_options();

		$element->start_controls_section(
			'szeducate_visibility_section',
			array(
				'label' => 'SZEducate Láthatóság',
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			'szeducate_hide_if_empty_keys',
			array(
				'label'    => 'Vizsgált mezők:',
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options'  => $options,
				'default'  => array(),
			)
		);

		$element->add_control(
			'szeducate_hide_rule',
			array(
				'label'     => 'Feltétel (Akkor rejti el, ha...)',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'empty'      => 'A mező ÜRES',
					'not_empty'  => 'A mező NEM ÜRES',
					'equals'     => 'EGYENLŐ a megadott értékkel',
					'not_equals' => 'NEM EGYENLŐ a megadott értékkel',
					'contains'   => 'TARTALMAZZA a megadott értéket',
				),
				'default'   => 'empty',
				'condition' => array(
					'szeducate_hide_if_empty_keys!' => '',
				),
			)
		);

		$element->add_control(
			'szeducate_hide_value',
			array(
				'label'     => 'Vizsgált érték (Szöveg)',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array(
					'szeducate_hide_rule'           => array( 'equals', 'not_equals', 'contains' ),
					'szeducate_hide_if_empty_keys!' => '',
				),
			)
		);

		$element->add_control(
			'szeducate_hide_logic',
			array(
				'label'     => 'Több mező esetén a logika:',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'all_match' => 'Ha MINDEGYIK kiválasztott megfelel a feltételnek',
					'any_match' => 'Ha BÁRMELYIK kiválasztott megfelel a feltételnek',
				),
				'default'   => 'all_match',
				'condition' => array(
					'szeducate_hide_if_empty_keys!' => '',
				),
			)
		);

		$element->end_controls_section();
	}

	// A "SZEducate Láthatóság" szekció beállításai alapján el kell-e rejteni az elemet.
	// ($settings = az elem get_settings_for_display()-e, $data = a képzés adatai.)
	private function element_visibility_hidden( $settings, $data ) {
		if ( empty( $settings['szeducate_hide_if_empty_keys'] ) || ! is_array( $settings['szeducate_hide_if_empty_keys'] ) ) {
			return false;
		}
		$keys   = $settings['szeducate_hide_if_empty_keys'];
		$rule   = isset( $settings['szeducate_hide_rule'] ) ? $settings['szeducate_hide_rule'] : 'empty';
		$target = isset( $settings['szeducate_hide_value'] ) ? $settings['szeducate_hide_value'] : '';
		$logic  = isset( $settings['szeducate_hide_logic'] ) ? $settings['szeducate_hide_logic'] : 'all_match';

		$match_count = 0;
		$total_keys  = count( $keys );
		foreach ( $keys as $key ) {
			$actual = isset( $data[ $key ] ) ? $data[ $key ] : '';
			if ( $this->sz_rule_matches( $actual, $rule, $target ) ) {
				$match_count++;
			}
		}

		return ( $logic === 'all_match' )
			? ( $total_keys > 0 && $match_count === $total_keys )
			: ( $match_count > 0 );
	}

	public function should_render_element( $should_render, $element ) {
		if ( ! $should_render ) return $should_render;

		$settings = $element->get_settings_for_display();

		if ( empty( $settings['szeducate_hide_if_empty_keys'] ) || ! is_array( $settings['szeducate_hide_if_empty_keys'] ) ) {
			return $should_render;
		}

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $should_render;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) return false;

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return false;

		return $this->element_visibility_hidden( $settings, $data ) ? false : $should_render;
	}

	// --- Harmonika (accordion) füleinek feltételes elrejtése -------------------------

	// Generikus szakasz-vég hook: az első lezárt szakasz után egyszer beszúrjuk a
	// "Feltételes fülek" szekciót - a klasszikus 'accordion' és az újabb
	// 'nested-accordion' widgethez egyaránt (eltérő szakasz-azonosítók miatt).
	public function maybe_add_accordion_item_visibility( $element, $section_id, $args ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) return;
		$name = $element->get_name();
		if ( $name !== 'accordion' && $name !== 'nested-accordion' ) return;

		static $added = array();
		static $seen  = array();
		$oid = spl_object_id( $element );
		if ( isset( $added[ $oid ] ) ) return;

		$seen[ $oid ] = isset( $seen[ $oid ] ) ? $seen[ $oid ] + 1 : 1;

		// Ideális pozíció: közvetlenül az elemek/fülek szakasza után. Ha azt az
		// azonosítót nem ismerjük fel (Elementor-verziónként eltérhet), legkésőbb a
		// 3. lezárt szakasz után szúrjuk be - így sosem marad ki, és nem a legelejére kerül.
		$items_sections = array( 'section_tabs', 'section_items', 'section_layout', 'layout_section' );
		if ( in_array( $section_id, $items_sections, true ) || $seen[ $oid ] >= 3 ) {
			$added[ $oid ] = true;
			$this->add_accordion_item_visibility_section( $element );
		}
	}

	private function add_accordion_item_visibility_section( $element ) {
		$options = $this->get_field_options();

		$element->start_controls_section(
			'szeducate_accordion_visibility',
			array(
				'label' => 'SZEducate: Feltételes fülek',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$element->add_control(
			'szeducate_accordion_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => 'Csak Képzés (sz_course) oldalon hat. A "Fül sorszáma" a fenti elem-lista pozíciója (1 = első). Ha a feltétel teljesül, a fül fejléce ÉS tartalma is eltűnik. (Klasszikus és "nested" harmonikára egyaránt.)',
				'content_classes' => 'elementor-descriptor',
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'tab_number',
			array( 'label' => 'Fül sorszáma', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'step' => 1, 'default' => 1 )
		);
		$repeater->add_control(
			'field_key',
			array( 'label' => 'Vizsgált mező', 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => $options, 'label_block' => true )
		);
		$repeater->add_control(
			'rule',
			array(
				'label'   => 'Elrejtés, ha a mező...',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'empty',
				'options' => array(
					'empty'      => 'ÜRES',
					'not_empty'  => 'NEM ÜRES',
					'equals'     => 'EGYENLŐ ezzel',
					'not_equals' => 'NEM EGYENLŐ ezzel',
					'contains'   => 'TARTALMAZZA ezt',
				),
			)
		);
		$repeater->add_control(
			'value',
			array(
				'label'     => 'Érték',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array( 'rule' => array( 'equals', 'not_equals', 'contains' ) ),
			)
		);

		$element->add_control(
			'szeducate_conditional_tabs',
			array(
				'label'       => 'Feltételek',
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(),
				'title_field' => 'Fül #{{{ tab_number }}}',
			)
		);

		$element->end_controls_section();
	}

	public function filter_accordion_conditional_tabs( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $content;
		}
		$name = $widget->get_name();
		if ( $name !== 'accordion' && $name !== 'nested-accordion' ) {
			return $content;
		}
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $content;
		}
		if ( strpos( $content, 'accordion' ) === false ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) {
			return $content;
		}

		$settings = $widget->get_settings_for_display();
		$rules    = isset( $settings['szeducate_conditional_tabs'] ) && is_array( $settings['szeducate_conditional_tabs'] )
			? $settings['szeducate_conditional_tabs'] : array();

		$children = ( $name === 'nested-accordion' && method_exists( $widget, 'get_children' ) )
			? $widget->get_children() : array();

		if ( empty( $rules ) && empty( $children ) ) {
			return $content;
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) {
			return $content;
		}

		$hide = array();

		// 1. A widgetre felvett "SZEducate: Feltételes fülek" szabályok (sorszám alapján).
		foreach ( $rules as $r ) {
			$n   = isset( $r['tab_number'] ) ? intval( $r['tab_number'] ) : 0;
			$key = isset( $r['field_key'] ) ? trim( (string) $r['field_key'] ) : '';
			if ( $n < 1 || $key === '' ) continue;
			$actual = isset( $data[ $key ] ) ? $data[ $key ] : '';
			$rule   = isset( $r['rule'] ) ? $r['rule'] : 'empty';
			$value  = isset( $r['value'] ) ? $r['value'] : '';
			if ( $this->sz_rule_matches( $actual, $rule, $value ) ) {
				$hide[ $n ] = true;
			}
		}

		// 2. Nested harmonika: a fül tartalmi konténerére felvett SZEducate Láthatóság is
		//    számít - a konténer tartalma amúgy is elrejtődik (should_render_element),
		//    itt a hozzá tartozó fül-fejlécet is kivesszük, hogy ne maradjon árva cím.
		if ( is_array( $children ) ) {
			$ci = 0;
			foreach ( $children as $child ) {
				$ci++;
				if ( ! is_object( $child ) || ! method_exists( $child, 'get_settings_for_display' ) ) continue;
				if ( $this->element_visibility_hidden( $child->get_settings_for_display(), $data ) ) {
					$hide[ $ci ] = true;
				}
			}
		}

		if ( empty( $hide ) ) {
			return $content;
		}

		return $this->remove_accordion_items( $content, array_keys( $hide ) );
	}

	// A megadott (1-alapú) sorszámú harmonika-fül elemeket kiveszi a HTML-ből
	// (klasszikus: .elementor-accordion-item ; nested: .e-n-accordion-item = <details>).
	// Ha a szerkezet nem az elvárt, vagy a DOM-feldolgozás hibázik, az eredetit adja vissza.
	private function remove_accordion_items( $html, $indexes ) {
		if ( ! class_exists( 'DOMDocument' ) || trim( $html ) === '' ) {
			return $html;
		}

		$prev_errors = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML(
			'<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
			LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		if ( ! $loaded ) {
			return $html;
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return $html;
		}

		$has_class = function( $cls ) {
			return 'contains(concat(" ", normalize-space(@class), " "), " ' . $cls . ' ")';
		};

		$xpath = new DOMXPath( $dom );
		$items = null;
		foreach ( array( 'elementor-accordion', 'e-n-accordion' ) as $wrap ) {
			$item = ( $wrap === 'e-n-accordion' ) ? 'e-n-accordion-item' : 'elementor-accordion-item';
			$q = $xpath->query( '//*[' . $has_class( $wrap ) . ']/*[' . $has_class( $item ) . ']' );
			if ( $q && $q->length > 0 ) { $items = $q; break; }
		}
		if ( ! $items || $items->length === 0 ) {
			// Tartalék: bármely *-accordion-item, dokumentum-sorrendben.
			$items = $xpath->query( '//*[' . $has_class( 'elementor-accordion-item' ) . ' or ' . $has_class( 'e-n-accordion-item' ) . ']' );
		}
		if ( ! $items || $items->length === 0 ) {
			return $html;
		}

		$nodes   = iterator_to_array( $items );
		$removed = false;
		foreach ( $indexes as $i ) {
			$idx = intval( $i ) - 1;
			if ( $idx >= 0 && isset( $nodes[ $idx ] ) && $nodes[ $idx ]->parentNode ) {
				$nodes[ $idx ]->parentNode->removeChild( $nodes[ $idx ] );
				$removed = true;
			}
		}
		if ( ! $removed ) {
			return $html;
		}

		$out = '';
		foreach ( $body->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
		return $out !== '' ? $out : $html;
	}
}
