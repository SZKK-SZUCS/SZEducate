<?php
/**
 * Az Excel-importban érkező dátumok normalizálása ÉÉÉÉ-HH-NN formátumra.
 *
 * @package SZEducate
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SZEDUCATE_DATE_NORMALIZER_TEST' ) ) {
	exit;
}

/**
 * A "date" típusú mezők értéke ÉÉÉÉ-HH-NN (a böngésző dátumválasztója és a szinkron is ezt várja).
 * Az Excel-import a dátumcellákat korábban a cella megjelenített formátumában olvasta be
 * (pl. "8/31/2027"), ezért itt a nyers Excel-sorszámból számolunk. Külön osztály, hogy
 * WordPress és PhpSpreadsheet nélkül tesztelhető legyen.
 */
final class SZEducate_Date_Normalizer {

	/**
	 * Excel-sorszám (1900-as dátumrendszer) -> "ÉÉÉÉ-HH-NN" vagy "ÉÉÉÉ-HH-NN ÓÓ:pp".
	 * Az időrész csak akkor marad meg, ha nem éjfél: a mikroképzések határideje
	 * időpontot is hordozhat, ezt nem szabad csendben eldobni.
	 *
	 * @param float|int $serial Az Excel-cella nyers, számként tárolt értéke.
	 * @return string|null Null, ha a sorszám nem értelmezhető dátumként.
	 */
	public static function from_excel_serial( $serial ) {
		if ( ! is_numeric( $serial ) || $serial < 1 || $serial > 2958465 ) {
			return null;
		}
		$days    = (int) floor( (float) $serial );
		$seconds = (int) round( ( (float) $serial - $days ) * 86400 );
		if ( $seconds >= 86400 ) {
			++$days;
			$seconds = 0;
		}
		// Az Excel 1900-as hibás szökőnapja miatt 60 felett a bázis 1899-12-30.
		$base = new DateTime( '1899-12-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$base->modify( '+' . $days . ' days' );
		$date = $base->format( 'Y-m-d' );
		if ( 0 === $seconds ) {
			return $date;
		}
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		return sprintf( '%s %02d:%02d', $date, $h, $m );
	}

	/**
	 * Szövegként megadott dátum -> "ÉÉÉÉ-HH-NN". Csak az egyértelmű formátumokat alakítja át;
	 * minden mást (pl. "2026. szeptember", "2027 ősz", két egyformán lehetséges
	 * hónap/nap sorrendű "3/4/2027") változatlanul hagy, nem találgat.
	 *
	 * @param mixed $text A cella szövege.
	 * @return string A normalizált dátum, vagy a kapott szöveg trimmelve.
	 */
	public static function from_text( $text ) {
		$s = trim( (string) $text );
		if ( '' === $s ) {
			return $s;
		}

		// 2027-02-20, 2027.02.20, 2027. 2. 20., 2027/02/20 (opcionális idővel: marad az eredeti, ha van).
		if ( preg_match( '/^(\d{4})\s*[-.\/]\s*(\d{1,2})\s*[-.\/]\s*(\d{1,2})\.?$/', $s, $m ) ) {
			return self::build( (int) $m[1], (int) $m[2], (int) $m[3], $s );
		}

		// 20.02.2027 / 20. 02. 2027. (magyar/európai nap.hónap.év, pontokkal egyértelmű).
		if ( preg_match( '/^(\d{1,2})\s*\.\s*(\d{1,2})\s*\.\s*(\d{4})\.?$/', $s, $m ) ) {
			return self::build( (int) $m[3], (int) $m[2], (int) $m[1], $s );
		}

		// 8/31/2027 vagy 31/8/2027: perjelnél csak akkor, ha az egyik tag > 12 (különben nem eldönthető).
		if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m ) ) {
			$a = (int) $m[1];
			$b = (int) $m[2];
			if ( $a > 12 && $b <= 12 ) {
				return self::build( (int) $m[3], $b, $a, $s );
			}
			if ( $b > 12 && $a <= 12 ) {
				return self::build( (int) $m[3], $a, $b, $s );
			}
		}

		return $s;
	}

	/**
	 * Érvényes dátumnál ÉÉÉÉ-HH-NN, különben a kapott eredeti szöveg.
	 *
	 * @param int    $y        Év.
	 * @param int    $mo       Hónap.
	 * @param int    $d        Nap.
	 * @param string $fallback Érvénytelen dátum esetén visszaadott szöveg.
	 * @return string
	 */
	private static function build( $y, $mo, $d, $fallback ) {
		if ( ! checkdate( $mo, $d, $y ) ) {
			return $fallback;
		}
		return sprintf( '%04d-%02d-%02d', $y, $mo, $d );
	}
}
