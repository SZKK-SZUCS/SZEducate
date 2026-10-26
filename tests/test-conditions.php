<?php
/**
 * A SZEducate_Conditions (Jogosultsági Mátrix kiértékelő) önálló tesztje - nem kell hozzá WordPress.
 * Futtatás: php tests/test-conditions.php
 */
define( 'SZEDUCATE_CONDITIONS_TEST', true );
require __DIR__ . '/../includes/class-szeducate-conditions.php';

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

$rule  = function ( $field, $op, $val ) { return array( 'field' => $field, 'operator' => $op, 'value' => $val ); };
$group = function ( $logical, array $rules ) { return array( 'logical_operator' => $logical, 'rules' => $rules ); };
$perm  = function ( $conditions ) { return array( 'conditions' => $conditions ); };

// --- from_permissions ---
check( 'üres JSON szöveg = nincs korlátozás', SZEducate_Conditions::from_permissions( '' ), array() );
check( 'sérült JSON -> null (tilt)', SZEducate_Conditions::from_permissions( '{"conditions":' ), null );
check( 'nem tömb JSON ("5") -> null', SZEducate_Conditions::from_permissions( '5' ), null );
check( 'null -> null', SZEducate_Conditions::from_permissions( null ), null );
check( 'nincs conditions kulcs = nincs korlátozás', SZEducate_Conditions::from_permissions( '{"actions":{"create":true}}' ), array() );
check( 'conditions nem tömb -> null', SZEducate_Conditions::from_permissions( '{"conditions":"x"}' ), null );
check( 'régi (lista) formátum becsomagolva', SZEducate_Conditions::from_permissions( array( 'conditions' => array( $rule( 'a', '==', '1' ) ) ) ), $group( 'AND', array( $rule( 'a', '==', '1' ) ) ) );
check( 'üres szabálylista megmarad', SZEducate_Conditions::from_permissions( json_encode( $perm( $group( 'AND', array() ) ) ) ), $group( 'AND', array() ) );

// --- permits: alap ---
$mikro = $group( 'AND', array( $rule( 'kepzesi_forma', '==', 'Mikroképzés' ) ) );
check( 'null feltétel TILT', SZEducate_Conditions::permits( null, array( 'kepzesi_forma' => 'Mikroképzés' ) ), false );
check( 'üres feltétel enged', SZEducate_Conditions::permits( array(), array( 'x' => 1 ) ), true );
check( 'üres szabálylista enged', SZEducate_Conditions::permits( $group( 'AND', array() ), array() ), true );
check( '== egyezik', SZEducate_Conditions::permits( $mikro, array( 'kepzesi_forma' => 'Mikroképzés' ) ), true );
check( '== nem egyezik (MSc)', SZEducate_Conditions::permits( $mikro, array( 'kepzesi_forma' => 'MSc' ) ), false );
check( '== kisbetűs eltérés tilt', SZEducate_Conditions::permits( $mikro, array( 'kepzesi_forma' => 'mikroképzés' ) ), false );
check( '== záró szóköz tilt', SZEducate_Conditions::permits( $mikro, array( 'kepzesi_forma' => 'Mikroképzés ' ) ), false );
check( '== hiányzó mező tilt', SZEducate_Conditions::permits( $mikro, array() ), false );
check( '== course_data nem tömb -> tilt', SZEducate_Conditions::permits( $mikro, null ), false );

// --- permits: a korábbi fail-open esetek most TILTANAK ---
check( 'ismeretlen operátor tilt', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'kepzesi_forma', 'in', 'Mikroképzés' ) ) ), array( 'kepzesi_forma' => 'MSc' ) ), false );
check( 'hiányzó field tilt', SZEducate_Conditions::permits( $group( 'AND', array( array( 'operator' => '==', 'value' => '' ) ) ), array( 'kepzesi_forma' => 'MSc' ) ), false );
check( 'üres field tilt', SZEducate_Conditions::permits( $group( 'AND', array( $rule( '', '==', '' ) ) ), array() ), false );
check( 'rules nem tömb tilt', SZEducate_Conditions::permits( array( 'logical_operator' => 'AND', 'rules' => 'x' ), array() ), false );
check( 'ismeretlen logikai operátor tilt', SZEducate_Conditions::permits( $group( 'XOR', array( $rule( 'a', '==', '1' ) ) ), array( 'a' => '1' ) ), false );
check( 'nem tömb szabály tilt', SZEducate_Conditions::permits( $group( 'AND', array( 'x' ) ), array() ), false );
check( 'contains üres céllal tilt', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'a', 'contains', '' ) ) ), array( 'a' => 'bármi' ) ), false );

// --- permits: operátorok ---
check( '!= egyezik -> tilt', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'kepzesi_forma', '!=', 'MSc' ) ) ), array( 'kepzesi_forma' => 'MSc' ) ), false );
check( '!= más érték -> enged', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'kepzesi_forma', '!=', 'MSc' ) ) ), array( 'kepzesi_forma' => 'BSc' ) ), true );
check( 'contains megtalál', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'telephely', 'contains', 'Győr' ) ) ), array( 'telephely' => array( 'Győr', 'Budapest' ) ) ), true );
check( 'contains nem talál', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'telephely', 'contains', 'Pécs' ) ) ), array( 'telephely' => array( 'Győr', 'Budapest' ) ) ), false );
check( '== tömbre (összefűzött) nem egyezik', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'telephely', '==', 'Győr' ) ) ), array( 'telephely' => array( 'Győr', 'Budapest' ) ) ), false );
check( 'szám érték == szöveg', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'felevek_szama', '==', '4' ) ) ), array( 'felevek_szama' => 4 ) ), true );

// --- permits: logika ---
$vagy = $group( 'OR', array( $rule( 'kepzesi_forma', '==', 'Mikroképzés' ), $rule( 'kepzesi_forma', '==', 'Előkészítő' ) ) );
check( 'VAGY: előkészítő enged', SZEducate_Conditions::permits( $vagy, array( 'kepzesi_forma' => 'Előkészítő' ) ), true );
check( 'VAGY: BSc tilt', SZEducate_Conditions::permits( $vagy, array( 'kepzesi_forma' => 'BSc' ) ), false );
check( 'kisbetűs "or" is működik', SZEducate_Conditions::permits( $group( 'or', $vagy['rules'] ), array( 'kepzesi_forma' => 'Mikroképzés' ) ), true );
$es = $group( 'AND', array( $rule( 'kepzesi_forma', '==', 'Mikroképzés' ), $rule( 'kar', '==', 'KGK' ) ) );
check( 'ÉS: mindkettő igaz', SZEducate_Conditions::permits( $es, array( 'kepzesi_forma' => 'Mikroképzés', 'kar' => 'KGK' ) ), true );
check( 'ÉS: egyik hamis', SZEducate_Conditions::permits( $es, array( 'kepzesi_forma' => 'Mikroképzés', 'kar' => 'AK' ) ), false );
$be = $group( 'AND', array( $rule( 'kar', '==', 'KGK' ), $vagy ) );
check( 'beágyazott csoport: KGK + Előkészítő', SZEducate_Conditions::permits( $be, array( 'kar' => 'KGK', 'kepzesi_forma' => 'Előkészítő' ) ), true );
check( 'beágyazott csoport: KGK + BSc', SZEducate_Conditions::permits( $be, array( 'kar' => 'KGK', 'kepzesi_forma' => 'BSc' ) ), false );
check( 'beágyazott üres csoport enged (ÉS)', SZEducate_Conditions::permits( $group( 'AND', array( $rule( 'kar', '==', 'KGK' ), $group( 'AND', array() ) ) ), array( 'kar' => 'KGK' ) ), true );

// --- teljes lánc: permissions JSON -> döntés ---
$json = json_encode( $perm( $mikro ) );
check( 'JSON lánc: Mikro enged', SZEducate_Conditions::permits( SZEducate_Conditions::from_permissions( $json ), array( 'kepzesi_forma' => 'Mikroképzés' ) ), true );
check( 'JSON lánc: MSc tilt', SZEducate_Conditions::permits( SZEducate_Conditions::from_permissions( $json ), array( 'kepzesi_forma' => 'MSc' ) ), false );
check( 'JSON lánc: sérült JSON tilt', SZEducate_Conditions::permits( SZEducate_Conditions::from_permissions( '{"conditions":{' ), array( 'kepzesi_forma' => 'Mikroképzés' ) ), false );

// --- allows_value / fields_in: a szerkesztő engedélyezett értékei (a JS tesztjével azonos esetek) ---
$csak_osztatlan = $group( 'AND', array( $rule( 'kepzesi_forma', '==', 'Osztatlan' ) ) );
check( 'allows: osztatlan enged', SZEducate_Conditions::allows_value( $csak_osztatlan, 'kepzesi_forma', 'Osztatlan' ), true );
check( 'allows: MSc tilt', SZEducate_Conditions::allows_value( $csak_osztatlan, 'kepzesi_forma', 'MSc' ), false );
check( 'allows: Mikroképzés tilt', SZEducate_Conditions::allows_value( $csak_osztatlan, 'kepzesi_forma', 'Mikroképzés' ), false );
check( 'allows: null feltétel tilt', SZEducate_Conditions::allows_value( null, 'kepzesi_forma', 'Osztatlan' ), false );
check( 'allows: üres feltétel enged', SZEducate_Conditions::allows_value( array(), 'kepzesi_forma', 'MSc' ), true );
check( 'allows: üres szabálylista enged', SZEducate_Conditions::allows_value( $group( 'AND', array() ), 'kepzesi_forma', 'MSc' ), true );
check( 'allows: VAGY előkészítő enged', SZEducate_Conditions::allows_value( $vagy, 'kepzesi_forma', 'Előkészítő' ), true );
check( 'allows: VAGY BSc tilt', SZEducate_Conditions::allows_value( $vagy, 'kepzesi_forma', 'BSc' ), false );
check( 'allows: != MSc tilt', SZEducate_Conditions::allows_value( $group( 'AND', array( $rule( 'kepzesi_forma', '!=', 'MSc' ) ) ), 'kepzesi_forma', 'MSc' ), false );
check( 'allows: != BSc enged', SZEducate_Conditions::allows_value( $group( 'AND', array( $rule( 'kepzesi_forma', '!=', 'MSc' ) ) ), 'kepzesi_forma', 'BSc' ), true );
$kar_es_forma = $group( 'AND', array( $rule( 'kar', '==', 'KGK' ), $rule( 'kepzesi_forma', '==', 'Osztatlan' ) ) );
check( 'allows: más mező szabálya nem blokkol (Osztatlan)', SZEducate_Conditions::allows_value( $kar_es_forma, 'kepzesi_forma', 'Osztatlan' ), true );
check( 'allows: de a saját mező szabálya igen (MSc)', SZEducate_Conditions::allows_value( $kar_es_forma, 'kepzesi_forma', 'MSc' ), false );
check( 'allows: nem szereplő mező szűretlen', SZEducate_Conditions::allows_value( $kar_es_forma, 'telephely', 'Győr' ), true );
check( 'allows: VAGY más mezővel (ismeretlen ág) enged', SZEducate_Conditions::allows_value( $group( 'OR', array( $rule( 'kar', '==', 'KGK' ), $rule( 'kepzesi_forma', '==', 'Osztatlan' ) ) ), 'kepzesi_forma', 'MSc' ), true );
check( 'allows: beágyazott Mikro enged', SZEducate_Conditions::allows_value( $be, 'kepzesi_forma', 'Mikroképzés' ), true );
check( 'allows: beágyazott BSc tilt', SZEducate_Conditions::allows_value( $be, 'kepzesi_forma', 'BSc' ), false );
check( 'allows: ismeretlen operátor tilt', SZEducate_Conditions::allows_value( $group( 'AND', array( $rule( 'kepzesi_forma', 'in', 'MSc' ) ) ), 'kepzesi_forma', 'MSc' ), false );
check( 'allows: üres mezőnév tilt', SZEducate_Conditions::allows_value( $group( 'AND', array( $rule( '', '==', '' ) ) ), 'kepzesi_forma', 'MSc' ), false );
check( 'allows: ismeretlen logikai operátor tilt', SZEducate_Conditions::allows_value( $group( 'XOR', array( $rule( 'kepzesi_forma', '==', 'MSc' ) ) ), 'kepzesi_forma', 'MSc' ), false );
check( 'allows: contains megtalál', SZEducate_Conditions::allows_value( $group( 'AND', array( $rule( 'kepzesi_forma', 'contains', 'kép' ) ) ), 'kepzesi_forma', 'Mikroképzés' ), true );
check( 'allows: contains üres céllal tilt', SZEducate_Conditions::allows_value( $group( 'AND', array( $rule( 'kepzesi_forma', 'contains', '' ) ) ), 'kepzesi_forma', 'MSc' ), false );
check( 'fields_in: beágyazott', SZEducate_Conditions::fields_in( $be ), array( 'kar', 'kepzesi_forma' ) );
check( 'fields_in: üres', SZEducate_Conditions::fields_in( array() ), array() );
check( 'fields_in: null', SZEducate_Conditions::fields_in( null ), array() );

echo $failures === 0 ? "Minden teszt rendben ($count).\n" : "$failures hiba a $count tesztből.\n";
exit( $failures === 0 ? 0 : 1 );
