/**
 * A Jogosultsági Mátrix alapján megmondja, egy select/radio mező mely értékei választhatók.
 *
 * Részleges (háromértékű) kiértékelés: a vizsgált mezőre vonatkozó szabályokat a jelölt
 * értékkel értékeljük ki, a MÁS mezőkre vonatkozó szabály "ismeretlen" (null) - az nem
 * blokkol, mert a szerkesztés közben a többi mező még változhat. Egy érték csak akkor
 * tiltott, ha a feltétel-fa az ismeretlenek mellett is biztosan hamis.
 *
 * A szabályok a PHP SZEducate_Conditions osztállyal egyeznek (fail-closed: ismeretlen
 * operátor, üres mezőnév, ismeretlen logikai operátor -> hamis). Egy közös hívási helyről
 * szinkronban kell tartani: includes/class-szeducate-conditions.php.
 */

const OPERATORS = [ '==', '!=', 'contains' ];

const evaluateRule = ( rule, fieldKey, candidate ) => {
	const field = typeof rule.field === 'string' ? rule.field : '';
	const operator =
		rule.operator !== undefined ? String( rule.operator ) : '==';
	const target =
		rule.value !== undefined &&
		rule.value !== null &&
		typeof rule.value !== 'object'
			? String( rule.value )
			: '';

	// Értelmezhetetlen szabály: tilt (ahogy a szerver is).
	if ( field === '' || ! OPERATORS.includes( operator ) ) return false;

	// Másik mezőre vonatkozó szabály: itt még nem dönthető el.
	if ( field !== fieldKey ) return null;

	if ( operator === '==' ) return candidate === target;
	if ( operator === '!=' ) return candidate !== target;
	return target !== '' && candidate.includes( target );
};

// true / false / null (ismeretlen)
const evaluateNode = ( node, fieldKey, candidate ) => {
	if ( ! node || typeof node !== 'object' ) return false;
	if ( ! Array.isArray( node.rules ) || node.rules.length === 0 ) return true;

	const logical = String( node.logical_operator ?? 'AND' ).toUpperCase();
	if ( logical !== 'AND' && logical !== 'OR' ) return false;

	const results = node.rules.map( ( rule ) => {
		if ( ! rule || typeof rule !== 'object' ) return false;
		if ( rule.logical_operator !== undefined ) {
			return evaluateNode( rule, fieldKey, candidate );
		}
		return evaluateRule( rule, fieldKey, candidate );
	} );

	if ( logical === 'AND' ) {
		if ( results.includes( false ) ) return false;
		return results.includes( null ) ? null : true;
	}

	if ( results.includes( true ) ) return true;
	return results.includes( null ) ? null : false;
};

/**
 * Engedélyezett-e az érték a mezőben.
 * @param {object | Array | null | undefined} conditions A permissions.conditions (a Hubtól).
 * @param {string}                            fieldKey   A vizsgált mező kulcsa.
 * @param {string}                            candidate  A jelölt érték.
 */
export const isValueAllowed = ( conditions, fieldKey, candidate ) => {
	if ( conditions === null || conditions === undefined ) return true;

	// Régi formátum: egyszerű szabály-lista.
	const tree = Array.isArray( conditions )
		? { logical_operator: 'AND', rules: conditions }
		: conditions;

	if ( typeof tree !== 'object' ) return false;

	return evaluateNode( tree, fieldKey, String( candidate ) ) !== false;
};

/**
 * A feltétel-fában szereplő mezőnevek (szabályonként). Csak ezekre a mezőkre érdemes szűrni.
 * @param {*} conditions
 */
export const fieldsInConditions = ( conditions ) => {
	const found = new Set();
	const walk = ( node ) => {
		if ( ! node || typeof node !== 'object' ) return;
		const rules = Array.isArray( node ) ? node : node.rules;
		if ( ! Array.isArray( rules ) ) return;
		rules.forEach( ( rule ) => {
			if (
				rule &&
				typeof rule === 'object' &&
				rule.logical_operator !== undefined
			) {
				walk( rule );
			} else if (
				rule &&
				typeof rule.field === 'string' &&
				rule.field !== ''
			) {
				found.add( rule.field );
			}
		} );
	};
	walk( conditions );
	return found;
};

/**
 * Egy [{label, value}] opciólista szűrése. Az üres ("Válassz...") opció marad; a már
 * kiválasztott, de nem engedélyezett érték is megmarad (letiltva, jelzéssel), hogy ne
 * tűnjön el csendben a mentett adat.
 * @param {*} options
 * @param {*} conditions
 * @param {*} fieldKey
 * @param {*} currentValue
 */
export const filterAllowedOptions = (
	options,
	conditions,
	fieldKey,
	currentValue
) => {
	if ( ! fieldsInConditions( conditions ).has( fieldKey ) ) return options;

	const result = [];
	options.forEach( ( opt ) => {
		if ( opt.value === '' ) {
			result.push( opt );
			return;
		}
		if ( isValueAllowed( conditions, fieldKey, opt.value ) ) {
			result.push( opt );
		} else if (
			currentValue !== undefined &&
			String( currentValue ) === opt.value
		) {
			result.push( {
				...opt,
				label: `${ opt.label } (nem engedélyezett)`,
				disabled: true,
			} );
		}
	} );
	return result;
};

/**
 * Ha a mezőre pontosan egy érték engedélyezett, az adja vissza; különben null.
 * @param {*} options
 * @param {*} conditions
 * @param {*} fieldKey
 */
export const onlyAllowedValue = ( options, conditions, fieldKey ) => {
	if ( ! fieldsInConditions( conditions ).has( fieldKey ) ) return null;
	const allowed = options.filter(
		( o ) =>
			o.value !== '' && isValueAllowed( conditions, fieldKey, o.value )
	);
	return allowed.length === 1 ? allowed[ 0 ].value : null;
};
