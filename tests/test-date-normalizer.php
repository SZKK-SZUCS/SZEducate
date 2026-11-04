<?php
/**
 * A SZEducate_Date_Normalizer (Excel-import dátum-normalizáló) önálló tesztje - nem kell hozzá WordPress.
 * Futtatás: php tests/test-date-normalizer.php
 */
define( 'SZEDUCATE_DATE_NORMALIZER_TEST', true );
require __DIR__ . '/../includes/class-szeducate-date-normalizer.php';

$failures = 0;
$count    = 0;

function check( $name, $got, $expected ) {
	global $failures, $count;
	$count++;
	if ( $got !== $expected ) {
		$failures++;
		echo "HIBA: $name (kapott: " . var_export( $got, true ) . ", elvárt: " . var_export( $expected, true ) . ")\n";
	}
}

// --- from_excel_serial: a valódi Excel-dátumcellák nyers értéke ---
check( '2027-08-31 sorszám', SZEducate_Date_Normalizer::from_excel_serial( 46630 ), '2027-08-31' );
check( '2027-02-20 sorszám', SZEducate_Date_Normalizer::from_excel_serial( 46438 ), '2027-02-20' );
check( '2000-01-01 sorszám', SZEducate_Date_Normalizer::from_excel_serial( 36526 ), '2000-01-01' );
check( 'tört (float) sorszám, éjfél', SZEducate_Date_Normalizer::from_excel_serial( 46630.0 ), '2027-08-31' );
check( 'időrész megmarad (12:00)', SZEducate_Date_Normalizer::from_excel_serial( 46630.5 ), '2027-08-31 12:00' );
check( 'időrész megmarad (23:59)', SZEducate_Date_Normalizer::from_excel_serial( 46630 + ( 23 * 3600 + 59 * 60 ) / 86400 ), '2027-08-31 23:59' );
check( 'sorszám stringként', SZEducate_Date_Normalizer::from_excel_serial( '46630' ), '2027-08-31' );
check( 'nem szám -> null', SZEducate_Date_Normalizer::from_excel_serial( 'abc' ), null );
check( '0 -> null', SZEducate_Date_Normalizer::from_excel_serial( 0 ), null );
check( 'negatív -> null', SZEducate_Date_Normalizer::from_excel_serial( -5 ), null );

// --- from_text: szövegként beírt dátumok ---
check( 'már ISO', SZEducate_Date_Normalizer::from_text( '2027-02-20' ), '2027-02-20' );
check( 'pontokkal', SZEducate_Date_Normalizer::from_text( '2027.02.20' ), '2027-02-20' );
check( 'pontokkal, zárópont', SZEducate_Date_Normalizer::from_text( '2027.02.20.' ), '2027-02-20' );
check( 'pontokkal, szóközökkel, egy számjegyű', SZEducate_Date_Normalizer::from_text( '2027. 2. 5.' ), '2027-02-05' );
check( 'ÉÉÉÉ/HH/NN', SZEducate_Date_Normalizer::from_text( '2027/02/20' ), '2027-02-20' );
check( 'nap.hónap.év', SZEducate_Date_Normalizer::from_text( '20.02.2027' ), '2027-02-20' );
check( 'nap. hónap. év.', SZEducate_Date_Normalizer::from_text( '31. 8. 2027.' ), '2027-08-31' );
check( 'amerikai, egyértelmű (nap > 12)', SZEducate_Date_Normalizer::from_text( '8/31/2027' ), '2027-08-31' );
check( 'európai perjeles, egyértelmű (első > 12)', SZEducate_Date_Normalizer::from_text( '31/8/2027' ), '2027-08-31' );
check( 'perjeles, nem eldönthető -> érintetlen', SZEducate_Date_Normalizer::from_text( '3/4/2027' ), '3/4/2027' );
check( 'érvénytelen dátum -> érintetlen', SZEducate_Date_Normalizer::from_text( '2027-02-30' ), '2027-02-30' );
check( 'szabad szöveg -> érintetlen', SZEducate_Date_Normalizer::from_text( '2026. szeptember' ), '2026. szeptember' );
check( 'szabad szöveg 2 -> érintetlen', SZEducate_Date_Normalizer::from_text( '2027 ősz' ), '2027 ősz' );
check( 'idővel -> érintetlen', SZEducate_Date_Normalizer::from_text( '2027.02.20 - 12:00' ), '2027.02.20 - 12:00' );
check( 'üres', SZEducate_Date_Normalizer::from_text( '' ), '' );
check( 'null', SZEducate_Date_Normalizer::from_text( null ), '' );
check( 'trim', SZEducate_Date_Normalizer::from_text( '  2027-02-20 ' ), '2027-02-20' );

echo $failures === 0 ? "OK: $count teszt lefutott.\n" : "$failures / $count teszt HIBÁS.\n";
exit( $failures === 0 ? 0 : 1 );
