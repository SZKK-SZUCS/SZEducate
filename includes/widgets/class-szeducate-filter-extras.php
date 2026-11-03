<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A két listázó widget (Szaklista, Képzéskártyák) közös "extra látogatói szűrő" logikája:
// - tetszőleges séma-mező (select / radio / checkbox / boolean, akár beágyazott al-mező)
//   felvétele a látogatói szűrőpanelbe egy ismétlődő vezérlőből,
// - bekapcsolható kereső mező a panelben (kliensoldali, ékezet- és kisbetű-független).
// A négy beégetett szűrő (Munkarend, Nyelv, Duális, Emelt) a widgeteken marad; ez azok MELLÉ
// ad további szűrőket. Egy helyen él, hogy a két widget ne duplikálja a (nem triviális)
// beágyazott-érték keresést és a panel-HTML-t.
class SZEducate_Filter_Extras {

	// A séma összes (nem archivált) mezője laposítva, kulcs szerint egyedítve. A beágyazott
	// al-mezők (pl. munkarend_csoportok > variansok > nyelv) is bekerülnek, mert a látogatói
	// szűrők zöme ilyen. Azonos kulcs több csoportban (pl. "dualis" a BSc / MSc csoportban)
	// egyszer szerepel, az opciók uniójával.
	public static function schema_fields( $schema ) {
		$out = array();
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					self::walk_fields( $group['fields'], '', $out );
				}
			}
		}
		return $out;
	}

	private static function walk_fields( $fields, $parent_label, &$out ) {
		foreach ( $fields as $f ) {
			if ( ! is_array( $f ) || empty( $f['key'] ) ) {
				continue;
			}
			// Az archivált mezők régi, már nem szerkesztett adatot tartanak (pl. a felső szintű
			// "munkarend" helyett a beágyazott él) - ezeket nem kínáljuk fel.
			if ( ! empty( $f['is_archived'] ) ) {
				continue;
			}
			$key   = (string) $f['key'];
			$label = ! empty( $f['label'] ) ? (string) $f['label'] : $key;
			$opts  = array();
			if ( ! empty( $f['options'] ) ) {
				foreach ( explode( ';', (string) $f['options'] ) as $o ) {
					$o = trim( $o );
					if ( $o !== '' ) {
						$opts[] = $o;
					}
				}
			}
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ]['options'] = array_values( array_unique( array_merge( $out[ $key ]['options'], $opts ) ) );
			} else {
				$out[ $key ] = array(
					'key'     => $key,
					'label'   => $label,
					'path'    => $parent_label !== '' ? $parent_label . ' › ' . $label : $label,
					'type'    => isset( $f['type'] ) ? (string) $f['type'] : '',
					'options' => $opts,
				);
			}
			if ( ! empty( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ) {
				self::walk_fields( $f['sub_fields'], $label, $out );
			}
		}
	}

	// A látogatói szűrőnek felkínálható mezők: opció-listás típusok + boolean.
	private static function filterable( $info ) {
		$out = array();
		foreach ( $info as $key => $f ) {
			if ( $f['type'] === 'boolean' || ( in_array( $f['type'], array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $f['options'] ) ) ) {
				$out[ $key ] = $f;
			}
		}
		return $out;
	}

	// A kereső mezőben átkutatható mezők: rövid szöveges típusok (a hosszú wysiwyg / url /
	// kép mezőket nem érdemes az oldalba ágyazni).
	private static function searchable( $info ) {
		$out = array();
		foreach ( $info as $key => $f ) {
			if ( in_array( $f['type'], array( 'text', 'textarea', 'select', 'radio', 'checkbox', 'number' ), true ) ) {
				$out[ $key ] = $f;
			}
		}
		return $out;
	}

	// A Tartalom > "Látogatói szűrők" szekció extra vezérlői. A widget hívja a saját
	// user_filters_section-jén belül.
	public static function register_controls( $widget, $schema ) {
		$info = self::schema_fields( $schema );

		$filter_choices = array( '' => '-- Válassz mezőt --' );
		foreach ( self::filterable( $info ) as $key => $f ) {
			$filter_choices[ $key ] = $f['path'] . ' [' . $key . ']';
		}
		$search_choices = array();
		foreach ( self::searchable( $info ) as $key => $f ) {
			$search_choices[ $key ] = $f['path'] . ' [' . $key . ']';
		}

		$widget->add_control(
			'uf_search',
			array(
				'label'        => 'Kereső mező a panelben',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'description'  => 'Gépelés közben szűri a listát (újratöltés nélkül), a képzés neve és az alább kiválasztott mezők között.',
			)
		);
		$widget->add_control(
			'uf_search_label',
			array(
				'label'     => 'Kereső – felirat',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Keresés',
				'condition' => array( 'uf_search' => 'yes' ),
			)
		);
		$widget->add_control(
			'uf_search_placeholder',
			array(
				'label'     => 'Kereső – helyőrző szöveg',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Szak neve vagy kulcsszó…',
				'condition' => array( 'uf_search' => 'yes' ),
			)
		);
		$widget->add_control(
			'uf_search_fields',
			array(
				'label'       => 'Kereső – további mezők (a cím mellett)',
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $search_choices,
				'multiple'    => true,
				'label_block' => true,
				'default'     => array( 'kepzesi_forma', 'kepzesiterulet', 'kulcsszavak' ),
				'condition'   => array( 'uf_search' => 'yes' ),
				'description' => 'A képzés neve mindig benne van. Minden kijelölt mező szövege az oldalba kerül, ezért csak rövid mezőket válassz.',
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'field',
			array(
				'label'       => 'Séma-mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $filter_choices,
				'default'     => '',
				'label_block' => true,
			)
		);
		$repeater->add_control(
			'label',
			array(
				'label'       => 'Felirat (üresen a séma címkéje)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$widget->add_control(
			'uf_extra_heading',
			array(
				'label'       => 'Extra szűrők a sémából',
				'type'        => \Elementor\Controls_Manager::HEADING,
				'separator'   => 'before',
			)
		);
		$widget->add_control(
			'uf_extra',
			array(
				'label'       => 'Extra szűrő mezők',
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ label || field }}}',
				'description' => 'Bármely választó (select / radio / checkbox) vagy igen/nem mező felvehető a fent kapcsolt szűrők mellé. Elem hozzáadása után válaszd ki a mezőt.',
			)
		);
	}

	// Az extra szűrők specifikációi a widget beállításaiból: [ attr-kulcs, séma-kulcs, felirat,
	// bool-e, opciók ]. A hiányzó / ismeretlen / duplikált mezőket kihagyja.
	public static function build_extras( $settings, $schema ) {
		$out  = array();
		$rows = ( ! empty( $settings['uf_extra'] ) && is_array( $settings['uf_extra'] ) ) ? $settings['uf_extra'] : array();
		if ( empty( $rows ) ) {
			return $out;
		}
		$info = self::filterable( self::schema_fields( $schema ) );
		foreach ( $rows as $row ) {
			$key = isset( $row['field'] ) ? (string) $row['field'] : '';
			if ( $key === '' || ! isset( $info[ $key ] ) || isset( $out[ $key ] ) ) {
				continue;
			}
			$f     = $info[ $key ];
			$label = ( isset( $row['label'] ) && trim( (string) $row['label'] ) !== '' ) ? trim( (string) $row['label'] ) : $f['label'];
			$out[ $key ] = array(
				'key'   => $key,
				'attr'  => 'x-' . sanitize_key( $key ),
				'label' => $label,
				'bool'  => ( $f['type'] === 'boolean' ),
				'opts'  => $f['options'],
			);
		}
		return array_values( $out );
	}

	public static function build_search( $settings ) {
		$on     = ( isset( $settings['uf_search'] ) && $settings['uf_search'] === 'yes' );
		$fields = ( ! empty( $settings['uf_search_fields'] ) && is_array( $settings['uf_search_fields'] ) ) ? $settings['uf_search_fields'] : array();
		return array(
			'on'          => $on,
			'label'       => ( isset( $settings['uf_search_label'] ) && trim( (string) $settings['uf_search_label'] ) !== '' ) ? $settings['uf_search_label'] : 'Keresés',
			'placeholder' => ( isset( $settings['uf_search_placeholder'] ) && trim( (string) $settings['uf_search_placeholder'] ) !== '' ) ? $settings['uf_search_placeholder'] : 'Szak neve vagy kulcsszó…',
			'fields'      => array_values( array_filter( array_map( 'strval', $fields ) ) ),
		);
	}

	// Egy képzés extra szűrő-értékei és kereső-szövege.
	// Visszaadja: 'x' => [ attr => érték-sztring ], 's' => összefűzött, hajtogatott keresőszöveg.
	public static function course_values( $title, $data, $extras, $search ) {
		$x = array();
		foreach ( $extras as $e ) {
			$vals = self::deep_values( $data, $e['key'] );
			if ( $e['bool'] ) {
				$yes = false;
				foreach ( $vals as $v ) {
					if ( self::truthy( $v ) ) {
						$yes = true;
						break;
					}
				}
				$x[ $e['attr'] ] = $yes ? '1' : '0';
			} else {
				$slugs = array();
				foreach ( $vals as $v ) {
					$s = sanitize_title( (string) $v );
					if ( $s !== '' ) {
						$slugs[ $s ] = true;
					}
				}
				$x[ $e['attr'] ] = implode( ' ', array_keys( $slugs ) );
			}
		}

		$s = '';
		if ( ! empty( $search['on'] ) ) {
			$parts = array( (string) $title );
			foreach ( $search['fields'] as $fk ) {
				foreach ( self::deep_values( $data, $fk ) as $v ) {
					if ( is_scalar( $v ) && ! is_bool( $v ) ) {
						$parts[] = (string) $v;
					}
				}
			}
			$s = self::fold( implode( ' ', $parts ) );
		}

		return array( 'x' => $x, 's' => $s );
	}

	// HTML-attribútumok a listaelemre / kártyára: data-f-<attr>, data-s.
	public static function data_attrs( $vals ) {
		$out = '';
		if ( ! empty( $vals['x'] ) ) {
			foreach ( $vals['x'] as $attr => $v ) {
				$out .= ' data-f-' . esc_attr( $attr ) . '="' . esc_attr( $v ) . '"';
			}
		}
		if ( isset( $vals['s'] ) && $vals['s'] !== '' ) {
			$out .= ' data-s="' . esc_attr( $vals['s'] ) . '"';
		}
		return $out;
	}

	// A keresőmező HTML-je (a panel tetején). $p a widget CSS-előtagja ("sz-lf" / "sz-cc-lf").
	public static function search_html( $search, $p, $wid ) {
		if ( empty( $search['on'] ) ) {
			return '';
		}
		$id = $p . '-search-' . $wid;
		return '<div class="' . esc_attr( $p ) . '-search"><label for="' . esc_attr( $id ) . '" class="' . esc_attr( $p ) . '-search-label">' . esc_html( $search['label'] ) . '</label>'
			. '<input type="search" id="' . esc_attr( $id ) . '" class="' . esc_attr( $p ) . '-search-input" placeholder="' . esc_attr( $search['placeholder'] ) . '" autocomplete="off"></div>';
	}

	// Az extra szűrők fieldset-jei - a beégetett szűrőkkel azonos szerkezet, hogy a widget
	// meglévő JS-e (data-key + data-f-<key>) változtatás nélkül kezelje.
	public static function groups_html( $extras, $p, $wid, $yes, $no ) {
		$out = '';
		foreach ( $extras as $e ) {
			$out .= '<fieldset class="' . esc_attr( $p ) . '-group" data-key="' . esc_attr( $e['attr'] ) . '">';
			$out .= '<legend>' . esc_html( $e['label'] ) . '</legend>';
			if ( $e['bool'] ) {
				$rname = $p . '-' . $e['attr'] . '-' . $wid;
				$out  .= '<label><input type="radio" name="' . esc_attr( $rname ) . '" value="1"> ' . esc_html( $yes ) . '</label>';
				$out  .= '<label><input type="radio" name="' . esc_attr( $rname ) . '" value="0"> ' . esc_html( $no ) . '</label>';
			} else {
				foreach ( $e['opts'] as $opt ) {
					$slug = sanitize_title( $opt );
					if ( $slug === '' ) {
						continue;
					}
					$out .= '<label><input type="checkbox" value="' . esc_attr( $slug ) . '"> ' . esc_html( $opt ) . '</label>';
				}
			}
			$out .= '</fieldset>';
		}
		return $out;
	}

	// Kis-/nagybetű- és ékezetfüggetlen alak; a kliensoldali JS ugyanezt számolja
	// (toLowerCase + NFD ékezet-lehúzás), ezért a kettőnek egyeznie kell.
	public static function fold( $str ) {
		$str = mb_strtolower( (string) $str, 'UTF-8' );
		$str = remove_accents( $str );
		return trim( preg_replace( '/\s+/u', ' ', $str ) );
	}

	// Egy kulcs értékei a képzés adatában: a felső szinten, ÉS a beágyazott listák
	// (repeater) soraiban is, tetszőleges mélységig (max. 4). A ";"-vel elválasztott szöveget
	// darabolja; a logikai / egyéb skalárt változatlanul adja.
	private static function deep_values( $node, $key, $depth = 0 ) {
		$out = array();
		if ( ! is_array( $node ) || $depth > 4 ) {
			return $out;
		}
		foreach ( $node as $k => $v ) {
			if ( (string) $k === $key ) {
				if ( is_array( $v ) ) {
					foreach ( $v as $p ) {
						if ( is_scalar( $p ) ) {
							$out[] = is_string( $p ) ? trim( $p ) : $p;
						}
					}
				} elseif ( is_string( $v ) ) {
					foreach ( explode( ';', $v ) as $p ) {
						$p = trim( $p );
						if ( $p !== '' ) {
							$out[] = $p;
						}
					}
				} elseif ( is_scalar( $v ) ) {
					$out[] = $v;
				}
			} elseif ( is_array( $v ) ) {
				$out = array_merge( $out, self::deep_values( $v, $key, $depth + 1 ) );
			}
		}
		return $out;
	}

	// A plugin boolean-konvenciója (üres / "0" / "false" / "hamis" / "nem" stb. = hamis).
	private static function truthy( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_array( $v ) ) {
			return ! empty( $v );
		}
		$n = mb_strtolower( trim( (string) $v ), 'UTF-8' );
		return ! in_array( $n, array( '', '0', 'false', 'hamis', 'nem', 'no', 'n' ), true );
	}
}
