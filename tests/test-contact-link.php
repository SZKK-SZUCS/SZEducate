<?php
/**
 * A SZEducate_Contact_Link::classify (e-mail / telefonszám / URL felismerés) önálló tesztje - nem kell hozzá WordPress.
 * Futtatás: php tests/test-contact-link.php
 */
define( 'SZEDUCATE_CONTACT_LINK_TEST', true );
require __DIR__ . '/../includes/class-szeducate-contact-link.php';

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

function c( $v ) {
	return SZEducate_Contact_Link::classify( $v );
}

// --- e-mail ---
check( 'e-mail', c( 'kapcsolat@sze.hu' ), array( 'kind' => 'email', 'value' => 'kapcsolat@sze.hu', 'scheme' => 'mailto:' ) );
check( 'e-mail körüli szóköz', c( "  kapcsolat@sze.hu \n" ), array( 'kind' => 'email', 'value' => 'kapcsolat@sze.hu', 'scheme' => 'mailto:' ) );
check( 'e-mail + jel', c( 'nev+tag@sze.hu' )['kind'], 'email' );

// --- telefonszám ---
check( 'nemzetközi, szóközökkel', c( '+36 30 123 4567' ), array( 'kind' => 'tel', 'value' => '+36301234567', 'scheme' => 'tel:' ) );
check( 'kötőjeles', c( '+36-30-123-4567' )['value'], '+36301234567' );
check( 'helyi, zárójeles körzet', c( '(06) 96 503 400' ), array( 'kind' => 'tel', 'value' => '0696503400', 'scheme' => 'tel:' ) );
check( 'helyi: a + nem kerül elé', c( '06 30 123 4567' )['value'], '06301234567' );
check( 'perjeles tagolás', c( '96/503-400' )['value'], '96503400' );
check( 'túl rövid szám nem telefon', c( '12345' )['kind'], 'url' );

// --- URL / egyéb: érintetlenül marad ---
check( 'https URL', c( 'https://example.com/a' ), array( 'kind' => 'url', 'value' => 'https://example.com/a', 'scheme' => '' ) );
check( 'már mailto:', c( 'mailto:a@b.hu' )['kind'], 'url' );
check( 'már tel:', c( 'tel:+3630' )['kind'], 'url' );
check( 'séma nélküli domain marad url', c( 'www.sze.hu' )['kind'], 'url' );
check( 'üres', c( '' ), array( 'kind' => 'url', 'value' => '', 'scheme' => '' ) );
check( 'null', c( null ), array( 'kind' => 'url', 'value' => '', 'scheme' => '' ) );
check( 'tömb', c( array( 'a' ) )['kind'], 'url' );
check( 'betűs szöveg nem telefon', c( '+36 hívjon' )['kind'], 'url' );

echo $failures ? "\n$failures HIBA / $count ellenőrzés\n" : "OK - $count ellenőrzés\n";
exit( $failures ? 1 : 0 );
