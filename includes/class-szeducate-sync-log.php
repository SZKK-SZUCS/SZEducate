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

	// --- Több kliens egyidejű (párhuzamos) értesítése egyetlen, a leglassabb válasszal
	// arányos várakozással, ahelyett hogy klienenként sorban, összeadódó várakozással
	// futnának le a kérések. $requests: assoc tömb kulcsonként ['url','method','body',
	// 'headers','timeout']. Visszaadja ugyanazokkal a kulcsokkal: ['code','error'].
	public static function parallel_requests( array $requests ) {
		$results = array();
		if ( empty( $requests ) ) return $results;

		if ( ! function_exists( 'curl_multi_init' ) ) {
			foreach ( $requests as $key => $req ) {
				$response = wp_remote_request( $req['url'], array(
					'method'   => $req['method'] ?? 'POST',
					'body'     => $req['body'] ?? null,
					'headers'  => $req['headers'] ?? array(),
					'timeout'  => $req['timeout'] ?? 8,
					'blocking' => true,
				) );
				$results[ $key ] = is_wp_error( $response )
					? array( 'code' => null, 'error' => $response->get_error_message() )
					: array( 'code' => wp_remote_retrieve_response_code( $response ), 'error' => null );
			}
			return $results;
		}

		$multi = curl_multi_init();
		$handles = array();

		foreach ( $requests as $key => $req ) {
			$headers_flat = array();
			foreach ( ( $req['headers'] ?? array() ) as $h_key => $h_val ) {
				$headers_flat[] = "$h_key: $h_val";
			}

			$ch = curl_init( $req['url'] );
			curl_setopt_array( $ch, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST  => $req['method'] ?? 'POST',
				CURLOPT_POSTFIELDS     => $req['body'] ?? null,
				CURLOPT_HTTPHEADER     => $headers_flat,
				CURLOPT_TIMEOUT        => $req['timeout'] ?? 8,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_FOLLOWLOCATION => true,
			) );
			curl_multi_add_handle( $multi, $ch );
			$handles[ $key ] = $ch;
		}

		$running = null;
		do {
			$status = curl_multi_exec( $multi, $running );
			if ( $running > 0 ) curl_multi_select( $multi, 1 );
		} while ( $running > 0 && $status === CURLM_OK );

		foreach ( $handles as $key => $ch ) {
			$errno = curl_errno( $ch );
			$results[ $key ] = $errno
				? array( 'code' => null, 'error' => curl_error( $ch ) )
				: array( 'code' => curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 'error' => null );

			curl_multi_remove_handle( $multi, $ch );
			curl_close( $ch );
		}
		curl_multi_close( $multi );

		return $results;
	}
}
