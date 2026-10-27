<?php
/**
 * A Támogatási táblázat árának formázása (forint / euró).
 *
 * @package SZEducate
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SZEDUCATE_PRICE_FORMAT_TEST' ) ) {
	exit;
}

/**
 * A Támogatási táblázat árának formázása. Az ár pénzneme az "Ár típusa" mezőből jön:
 * a "Félév (EUR)" / "Teljes (EUR)" opció euróban értendő, minden más (pl. "Félév",
 * "Teljes", üres) forintban - így a meglévő adatok és sémák változtatás nélkül Ft-ként
 * jelennek meg tovább. Külön osztály, hogy WordPress/Elementor nélkül tesztelhető legyen.
 */
final class SZEducate_Price_Format {

	/**
	 * Az "Ár típusa" nyers értékét címkére és pénznemre bontja.
	 * "Félév (EUR)" -> label 'Félév', currency 'EUR'; minden más érték változatlan
	 * címkével, 'HUF' pénznemmel.
	 *
	 * @param mixed $price_type A séma "Ár típusa" mezőjének nyers értéke.
	 * @return array{label:string,currency:string}
	 */
	public static function parse_price_type( $price_type ) {
		$raw = trim( (string) $price_type );
		// A zárójeles jelölés: (EUR), (eur), (€), (euró) - kis/nagybetűre és szóközökre érzéketlen.
		if ( preg_match( '/^(.*?)\s*\(\s*(?:eur|euro|euró|€)\s*\)\s*$/iu', $raw, $m ) ) {
			return array(
				'label'    => trim( $m[1] ),
				'currency' => 'EUR',
			);
		}
		return array(
			'label'    => $raw,
			'currency' => 'HUF',
		);
	}

	/**
	 * Az összeg + ár típusa alapján a megjelenítendő szöveg, pl. "50 000 Ft / Félév" vagy
	 * "1 200 € / Teljes". Üres összegre üres szöveg.
	 *
	 * @param mixed  $amount     Az összeg (szám vagy szöveg).
	 * @param string $price_type A séma "Ár típusa" mezőjének nyers értéke.
	 * @return string
	 */
	public static function format( $amount, $price_type = '' ) {
		if ( '' === $amount || null === $amount ) {
			return '';
		}

		$parsed  = self::parse_price_type( $price_type );
		$is_euro = ( 'EUR' === $parsed['currency'] );
		$numeric = is_numeric( $amount ) ? $amount : preg_replace( '/[^0-9.,-]/', '', (string) $amount );

		if ( '' !== $numeric && is_numeric( $numeric ) ) {
			$value = (float) $numeric;
			// Forintnál egész összeg a megszokott; euróban viszont a tört érték (pl. 1250,50)
			// nem veszhet el a kerekítésben.
			$decimals  = ( $is_euro && floor( $value ) !== $value ) ? 2 : 0;
			$formatted = number_format( $value, $decimals, ',', ' ' ) . ( $is_euro ? ' €' : ' Ft' );
		} else {
			$formatted = (string) $amount;
		}

		if ( '' !== $parsed['label'] ) {
			$formatted .= ' / ' . $parsed['label'];
		}
		return $formatted;
	}
}
