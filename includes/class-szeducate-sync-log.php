<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Központi napló a Kliens-szinkronizációkhoz és a Visszaállítás folyamatához ---
class SZEducate_Sync_Log {

	const OPTION_KEY   = 'szeducate_sync_log';
	const MAX_ENTRIES  = 300;
	const PROGRESS_TTL = 900; // 15 perc

	// --- Tartós napló (Kliensek fül alatt megjeleníthető, hosszabb távú hibakereséshez) ---
	public static function add( $type, $message, $success = true ) {
		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) $log = array();

		$log[] = array(
			'time'    => current_time( 'mysql' ),
			'type'    => $type,
			'success' => (bool) $success,
			'message' => $message,
		);

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $log, false );
	}

	public static function get_recent( $limit = 50 ) {
		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) return array();
		return array_slice( array_reverse( $log ), 0, $limit );
	}

	// --- Élő, soron következő folyamatjelző egy konkrét futó visszaállításhoz ---
	public static function progress_reset( $key ) {
		set_transient( 'szeducate_progress_' . $key, array( 'lines' => array(), 'done' => false, 'error' => false ), self::PROGRESS_TTL );
	}

	public static function progress_log( $key, $message ) {
		$transient_key = 'szeducate_progress_' . $key;
		$state = get_transient( $transient_key );
		if ( ! is_array( $state ) ) $state = array( 'lines' => array(), 'done' => false, 'error' => false );

		$state['lines'][] = array( 'time' => current_time( 'H:i:s' ), 'message' => $message );
		set_transient( $transient_key, $state, self::PROGRESS_TTL );
	}

	public static function progress_finish( $key, $had_error = false ) {
		$transient_key = 'szeducate_progress_' . $key;
		$state = get_transient( $transient_key );
		if ( ! is_array( $state ) ) $state = array( 'lines' => array(), 'done' => false, 'error' => false );

		$state['done']  = true;
		$state['error'] = $had_error;
		set_transient( $transient_key, $state, self::PROGRESS_TTL );
	}

	public static function progress_get( $key ) {
		$state = get_transient( 'szeducate_progress_' . $key );
		if ( ! is_array( $state ) ) return array( 'lines' => array(), 'done' => false, 'error' => false );
		return $state;
	}

	// --- Vészleállítás jelzőzászló egy futó visszaállításhoz ---
	public static function request_abort( $key ) {
		set_transient( 'szeducate_restore_abort_' . $key, true, self::PROGRESS_TTL );
	}

	public static function abort_requested( $key ) {
		return (bool) get_transient( 'szeducate_restore_abort_' . $key );
	}

	public static function clear_abort( $key ) {
		delete_transient( 'szeducate_restore_abort_' . $key );
	}
}
