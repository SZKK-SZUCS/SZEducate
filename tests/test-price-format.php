<?php
/**
 * A SZEducate_Price_Format (Támogatási táblázat ár-formázó) önálló tesztje - nem kell hozzá WordPress.
 * Futtatás: php tests/test-price-format.php
 */
define( 'SZEDUCATE_PRICE_FORMAT_TEST', true );
require __DIR__ . '/../includes/class-szeducate-price-format.php';

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

// --- parse_price_type ---
check( 'Félév = HUF', SZEducate_Price_Format::parse_price_type( 'Félév' ), array( 'label' => 'Félév', 'currency' => 'HUF' ) );
check( 'Teljes = HUF', SZEducate_Price_Format::parse_price_type( 'Teljes' ), array( 'label' => 'Teljes', 'currency' => 'HUF' ) );
check( 'üres = HUF, üres címke', SZEducate_Price_Format::parse_price_type( '' ), array( 'label' => '', 'currency' => 'HUF' ) );
check( 'null = HUF', SZEducate_Price_Format::parse_price_type( null ), array( 'label' => '', 'currency' => 'HUF' ) );
check( 'Félév (EUR)', SZEducate_Price_Format::parse_price_type( 'Félév (EUR)' ), array( 'label' => 'Félév', 'currency' => 'EUR' ) );
check( 'Teljes (EUR)', SZEducate_Price_Format::parse_price_type( 'Teljes (EUR)' ), array( 'label' => 'Teljes', 'currency' => 'EUR' ) );
check( 'kisbetűs (eur)', SZEducate_Price_Format::parse_price_type( 'Félév (eur)' ), array( 'label' => 'Félév', 'currency' => 'EUR' ) );
check( 'szóközök a zárójelben és körül', SZEducate_Price_Format::parse_price_type( '  Teljes  ( EUR )  ' ), array( 'label' => 'Teljes', 'currency' => 'EUR' ) );
check( '(€) jelölés', SZEducate_Price_Format::parse_price_type( 'Félév (€)' ), array( 'label' => 'Félév', 'currency' => 'EUR' ) );
check( '(euró) jelölés', SZEducate_Price_Format::parse_price_type( 'Félév (euró)' ), array( 'label' => 'Félév', 'currency' => 'EUR' ) );
check( 'csak (EUR) -> üres címke', SZEducate_Price_Format::parse_price_type( '(EUR)' ), array( 'label' => '', 'currency' => 'EUR' ) );
check( 'EUR nem zárójelben nem számít', SZEducate_Price_Format::parse_price_type( 'Félév EUR' ), array( 'label' => 'Félév EUR', 'currency' => 'HUF' ) );
check( 'más zárójeles szöveg marad HUF', SZEducate_Price_Format::parse_price_type( 'Félév (kedvezményes)' ), array( 'label' => 'Félév (kedvezményes)', 'currency' => 'HUF' ) );
check( 'EUR a közepén nem számít', SZEducate_Price_Format::parse_price_type( 'Félév (EUR) extra' ), array( 'label' => 'Félév (EUR) extra', 'currency' => 'HUF' ) );

// --- format: forint (a korábbi kimenet változatlan) ---
check( 'Ft: egész + Félév', SZEducate_Price_Format::format( '50000', 'Félév' ), '50 000 Ft / Félév' );
check( 'Ft: int + Teljes', SZEducate_Price_Format::format( 120000, 'Teljes' ), '120 000 Ft / Teljes' );
check( 'Ft: nincs ár típus', SZEducate_Price_Format::format( '50000', '' ), '50 000 Ft' );
check( 'Ft: szöveges összeg szám nélkül változatlan', SZEducate_Price_Format::format( 'egyeztetés szerint', 'Félév' ), 'egyeztetés szerint / Félév' );
check( 'Ft: formázott bevitel (szóköz, Ft)', SZEducate_Price_Format::format( '50 000 Ft', 'Félév' ), '50 000 Ft / Félév' );
check( 'Ft: tört összeg kerekítve (a korábbi viselkedés)', SZEducate_Price_Format::format( '50000.60', 'Félév' ), '50 001 Ft / Félév' );

// --- format: euró ---
check( 'EUR: egész + Félév', SZEducate_Price_Format::format( '1200', 'Félév (EUR)' ), '1 200 € / Félév' );
check( 'EUR: Teljes', SZEducate_Price_Format::format( 4800, 'Teljes (EUR)' ), '4 800 € / Teljes' );
check( 'EUR: tört érték megmarad (2 tizedes)', SZEducate_Price_Format::format( '1250.5', 'Félév (EUR)' ), '1 250,50 € / Félév' );
check( 'EUR: nincs "Ft"', strpos( SZEducate_Price_Format::format( '900', 'Teljes (EUR)' ), 'Ft' ), false );
check( 'EUR: csak (EUR) típus, címke nélkül', SZEducate_Price_Format::format( '900', '(EUR)' ), '900 €' );
check( 'EUR: szöveges összeg változatlan', SZEducate_Price_Format::format( 'egyeztetés', 'Félév (EUR)' ), 'egyeztetés / Félév' );

// --- format: üres összeg ---
check( 'üres összeg = üres', SZEducate_Price_Format::format( '', 'Félév (EUR)' ), '' );
check( 'null összeg = üres', SZEducate_Price_Format::format( null, 'Félév' ), '' );

echo $failures === 0 ? "Minden teszt rendben ($count).\n" : "$failures hiba / $count teszt.\n";
exit( $failures === 0 ? 0 : 1 );
