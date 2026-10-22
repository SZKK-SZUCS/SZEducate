<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'SZEDUCATE_CONDITIONS_TEST' ) ) {
	exit;
}

/**
 * A Jogosultsági Mátrix feltételeinek (kliensenkénti "mely képzéseket kaphatja") EGYETLEN
 * kiértékelője. Korábban a Hub API-ban, a Kliensben és a Backup kezelőben külön-külön,
 * egymástól függetlenül élt egy-egy másolata, és mindegyik FAIL-OPEN volt (sérült feltétel,
 * ismeretlen operátor, hiányzó mező-név esetén minden képzést átengedett). Innentől a
 * bizonytalan eset TILT: egy elrontott szabály inkább kevesebbet ad, mint többet.
 *
 * Tudatos kivétel: az ÜRES szabálylista ("rules": []) továbbra is "nincs korlátozás" - ezt
 * a Hub felületén explicit beállítani lehet, és az új kliensek alapértelmezése is ez.
 */
final class SZEducate_Conditions {

	const OPERATORS = array( '==', '!=', 'contains' );

	/**
	 * A kliens permissions értékéből (JSON szöveg vagy már dekódolt tömb) a feltétel-fát adja.
	 *  - array()  : nincs korlátozás (nincs feltétel-kulcs, vagy üres szabálylista)
	 *  - null     : érvénytelen / sérült érték -> a kiértékelő TILT
	 *
	 * @param mixed $permissions
	 * @return array|null
	 */
	public static function from_permissions( $permissions ) {
		if ( is_string( $permissions ) ) {
			if ( trim( $permissions ) === '' ) {
				return array();
			}
			$permissions = json_decode( $permissions, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return null;
			}
		}

		if ( ! is_array( $permissions ) ) {
			return null;
		}

		if ( ! array_key_exists( 'conditions', $permissions ) ) {
			return array();
		}

		$conditions = $permissions['conditions'];

		if ( ! is_array( $conditions ) ) {
			return null;
		}

		// Régi formátum: a feltételek egyszerű szabály-listaként voltak tárolva.
		if ( ! empty( $conditions ) && array_keys( $conditions ) === range( 0, count( $conditions ) - 1 ) ) {
			return array( 'logical_operator' => 'AND', 'rules' => $conditions );
		}

		return $conditions;
	}

	/**
	 * Megfelel-e a képzés adata a feltétel-fának.
	 *
	 * @param array|null $conditions   A from_permissions() eredménye (null = tilt).
	 * @param mixed      $course_data  A képzés course_data tömbje.
	 */
	public static function permits( $conditions, $course_data ) {
		if ( $conditions === null ) {
			return false;
		}
		if ( ! is_array( $conditions ) ) {
			return false;
		}
		if ( empty( $conditions ) || empty( $conditions['rules'] ) ) {
			return true;
		}
		if ( ! is_array( $conditions['rules'] ) ) {
			return false;
		}
		if ( ! is_array( $course_data ) ) {
			$course_data = array();
		}

		$logical = isset( $conditions['logical_operator'] ) ? strtoupper( (string) $conditions['logical_operator'] ) : 'AND';
		if ( $logical !== 'AND' && $logical !== 'OR' ) {
			return false;
		}

		$results = array();

		foreach ( $conditions['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				$results[] = false;
				continue;
			}

			if ( isset( $rule['logical_operator'] ) ) {
				$results[] = self::permits( $rule, $course_data );
				continue;
			}

			$results[] = self::rule_matches( $rule, $course_data );
		}

		return $logical === 'AND' ? ! in_array( false, $results, true ) : in_array( true, $results, true );
	}

	private static function rule_matches( array $rule, array $course_data ) {
		$field    = isset( $rule['field'] ) && is_string( $rule['field'] ) ? $rule['field'] : '';
		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '==';
		$target   = isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? (string) $rule['value'] : '';

		// Mező-név nélküli vagy ismeretlen operátorú szabály nem értelmezhető: tilt.
		if ( $field === '' || ! in_array( $operator, self::OPERATORS, true ) ) {
			return false;
		}

		$actual = isset( $course_data[ $field ] ) ? $course_data[ $field ] : '';
		if ( is_array( $actual ) ) {
			$actual = implode( ';', array_map( 'strval', $actual ) );
		}
		$actual = is_scalar( $actual ) ? (string) $actual : '';

		if ( $operator === '==' ) {
			return $actual === $target;
		}
		if ( $operator === '!=' ) {
			return $actual !== $target;
		}

		// 'contains' (az operátor fent már ellenőrizve): üres célra nincs értelmes egyezés.
		return $target !== '' && strpos( $actual, $target ) !== false;
	}
}
