/* eslint-disable no-console, camelcase */
/**
 * A szerkesztő engedélyezett-érték logikájának (src/allowed-options.js) önálló tesztje.
 * Futtatás: node tests/test-allowed-options.js   (Node 22.7+ felismeri az ES modult)
 */
import {
	isValueAllowed,
	fieldsInConditions,
	filterAllowedOptions,
	onlyAllowedValue,
} from '../src/allowed-options.js';

let failures = 0;
let count = 0;

const check = ( name, got, expected ) => {
	count++;
	if ( JSON.stringify( got ) !== JSON.stringify( expected ) ) {
		failures++;
		console.log(
			`HIBA: ${ name } (kapott: ${ JSON.stringify(
				got
			) }, elvárt: ${ JSON.stringify( expected ) })`
		);
	}
};

const rule = ( field, operator, value ) => ( { field, operator, value } );
const group = ( logical_operator, rules ) => ( { logical_operator, rules } );

const FORMS = [
	'BSc',
	'MSc',
	'Osztatlan',
	'Szakirányú továbbképzés',
	'Mikroképzés',
	'Előkészítő',
].map( ( v ) => ( { label: v, value: v } ) );
const OPTIONS = [ { label: 'Válassz...', value: '' }, ...FORMS ];

const csakOsztatlan = group( 'AND', [
	rule( 'kepzesi_forma', '==', 'Osztatlan' ),
] );

// --- a példa a kérésből: csak az osztatlan képzésekhez fér hozzá ---
check(
	'osztatlan: engedett',
	isValueAllowed( csakOsztatlan, 'kepzesi_forma', 'Osztatlan' ),
	true
);
check(
	'osztatlan: MSc tilt',
	isValueAllowed( csakOsztatlan, 'kepzesi_forma', 'MSc' ),
	false
);
check(
	'osztatlan: Mikro tilt',
	isValueAllowed( csakOsztatlan, 'kepzesi_forma', 'Mikroképzés' ),
	false
);
check(
	'osztatlan: opciólista szűrve',
	filterAllowedOptions( OPTIONS, csakOsztatlan, 'kepzesi_forma', '' ).map(
		( o ) => o.value
	),
	[ '', 'Osztatlan' ]
);
check(
	'osztatlan: egyetlen engedélyezett érték',
	onlyAllowedValue( OPTIONS, csakOsztatlan, 'kepzesi_forma' ),
	'Osztatlan'
);

// --- VAGY ---
const mikroVagyElo = group( 'OR', [
	rule( 'kepzesi_forma', '==', 'Mikroképzés' ),
	rule( 'kepzesi_forma', '==', 'Előkészítő' ),
] );
check(
	'VAGY: két érték',
	filterAllowedOptions( OPTIONS, mikroVagyElo, 'kepzesi_forma', '' ).map(
		( o ) => o.value
	),
	[ '', 'Mikroképzés', 'Előkészítő' ]
);
check(
	'VAGY: nincs egyetlen érték',
	onlyAllowedValue( OPTIONS, mikroVagyElo, 'kepzesi_forma' ),
	null
);

// --- !=: tiltott érték kizárása ---
const nemMsc = group( 'AND', [ rule( 'kepzesi_forma', '!=', 'MSc' ) ] );
check(
	'!=: MSc tilt',
	isValueAllowed( nemMsc, 'kepzesi_forma', 'MSc' ),
	false
);
check(
	'!=: BSc enged',
	isValueAllowed( nemMsc, 'kepzesi_forma', 'BSc' ),
	true
);

// --- más mezőre vonatkozó szabály nem blokkol (ismeretlen) ---
const karEsForma = group( 'AND', [
	rule( 'kar', '==', 'KGK' ),
	rule( 'kepzesi_forma', '==', 'Osztatlan' ),
] );
check(
	'más mező: a vizsgált mező szabálya számít',
	isValueAllowed( karEsForma, 'kepzesi_forma', 'MSc' ),
	false
);
check(
	'más mező: nem tilt, ami még teljesülhet',
	isValueAllowed( karEsForma, 'kepzesi_forma', 'Osztatlan' ),
	true
);
check(
	'más mező szabálya önmagában: szűretlen',
	isValueAllowed( karEsForma, 'telephely', 'Győr' ),
	true
);
check(
	'VAGY más mezővel: ismeretlen ág miatt minden engedett',
	isValueAllowed(
		group( 'OR', [
			rule( 'kar', '==', 'KGK' ),
			rule( 'kepzesi_forma', '==', 'Osztatlan' ),
		] ),
		'kepzesi_forma',
		'MSc'
	),
	true
);

// --- beágyazott csoport ---
const beagyazott = group( 'AND', [ rule( 'kar', '==', 'KGK' ), mikroVagyElo ] );
check(
	'beágyazott: Mikro enged',
	isValueAllowed( beagyazott, 'kepzesi_forma', 'Mikroképzés' ),
	true
);
check(
	'beágyazott: BSc tilt',
	isValueAllowed( beagyazott, 'kepzesi_forma', 'BSc' ),
	false
);

// --- üres / hiányzó feltétel: nincs korlátozás ---
check(
	'nincs feltétel: enged',
	isValueAllowed( null, 'kepzesi_forma', 'MSc' ),
	true
);
check(
	'üres szabálylista: enged',
	isValueAllowed( group( 'AND', [] ), 'kepzesi_forma', 'MSc' ),
	true
);
check(
	'üres szabálylista: opciók szűretlenek',
	filterAllowedOptions( OPTIONS, group( 'AND', [] ), 'kepzesi_forma', '' ),
	OPTIONS
);
check(
	'régi (lista) formátum',
	isValueAllowed(
		[ rule( 'kepzesi_forma', '==', 'Osztatlan' ) ],
		'kepzesi_forma',
		'MSc'
	),
	false
);

// --- fail-closed ---
check(
	'ismeretlen operátor tilt',
	isValueAllowed(
		group( 'AND', [ rule( 'kepzesi_forma', 'in', 'MSc' ) ] ),
		'kepzesi_forma',
		'MSc'
	),
	false
);
check(
	'üres mezőnév tilt',
	isValueAllowed(
		group( 'AND', [ rule( '', '==', '' ) ] ),
		'kepzesi_forma',
		'MSc'
	),
	false
);
check(
	'ismeretlen logikai operátor tilt',
	isValueAllowed(
		group( 'XOR', [ rule( 'kepzesi_forma', '==', 'MSc' ) ] ),
		'kepzesi_forma',
		'MSc'
	),
	false
);
check(
	"kisbetűs 'or' működik",
	isValueAllowed(
		group( 'or', mikroVagyElo.rules ),
		'kepzesi_forma',
		'Előkészítő'
	),
	true
);
check(
	'contains üres céllal tilt',
	isValueAllowed(
		group( 'AND', [ rule( 'kepzesi_forma', 'contains', '' ) ] ),
		'kepzesi_forma',
		'MSc'
	),
	false
);
check(
	'contains megtalál',
	isValueAllowed(
		group( 'AND', [ rule( 'kepzesi_forma', 'contains', 'kép' ) ] ),
		'kepzesi_forma',
		'Mikroképzés'
	),
	true
);

// --- a mentett, de már nem engedélyezett érték nem tűnik el ---
const aktualis = filterAllowedOptions(
	OPTIONS,
	csakOsztatlan,
	'kepzesi_forma',
	'MSc'
);
check(
	'mentett nem engedélyezett érték megmarad',
	aktualis.map( ( o ) => o.value ),
	[ '', 'MSc', 'Osztatlan' ]
);
check(
	'... letiltva és jelölve',
	aktualis.find( ( o ) => o.value === 'MSc' ),
	{
		label: 'MSc (nem engedélyezett)',
		value: 'MSc',
		disabled: true,
	}
);

// --- csak a feltételben szereplő mezőt szűrjük ---
check(
	'nem szereplő mező szűretlen',
	filterAllowedOptions( OPTIONS, csakOsztatlan, 'nyelv', '' ),
	OPTIONS
);
check(
	'mezőnevek a feltételben',
	[ ...fieldsInConditions( beagyazott ) ].sort(),
	[ 'kar', 'kepzesi_forma' ]
);

console.log(
	failures === 0
		? `Minden teszt rendben (${ count }).`
		: `${ failures } hiba a ${ count } tesztből.`
);
process.exit( failures === 0 ? 0 : 1 );
