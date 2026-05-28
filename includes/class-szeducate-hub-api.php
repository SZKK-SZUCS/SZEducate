<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Hub_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {
		// 1. Végpont: Séma lekérdezése (A kliensek innen tudják meg, hogyan épül fel a form)
		register_rest_route( 'szeducate/v1/hub', '/schema', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_schema' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );

		// 2. Végpont: Adatok fogadása és mentése
		register_rest_route( 'szeducate/v1/hub', '/courses', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( $this, 'receive_course_data' ),
			'permission_callback' => array( $this, 'verify_bearer_token' ),
		) );
	}

	// Szigorú token validáció: Timing Attack védelemmel
	public function verify_bearer_token( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'authorization' );
		
		if ( empty( $auth_header ) || ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return new WP_Error( 'missing_token', 'Hiányzó Bearer token.', array( 'status' => 401 ) );
		}

		$incoming_token = $matches[1];
		$incoming_hash = hash( 'sha256', $incoming_token );

		$stored_tokens = get_option( 'szeducate_api_tokens', array() );

		foreach ( $stored_tokens as $token_data ) {
			// Biztonságos string összehasonlítás
			if ( hash_equals( $token_data['hash'], $incoming_hash ) ) {
				return true; // Sikeres azonosítás
			}
		}

		return new WP_Error( 'invalid_token', 'Érvénytelen API token.', array( 'status' => 403 ) );
	}

	public function get_schema( WP_REST_Request $request ) {
		$schema = get_option( 'szeducate_schema', '[]' );
		return new WP_REST_Response( json_decode( $schema ), 200 );
	}

	public function receive_course_data( WP_REST_Request $request ) {
		// Ide fog kerülni a kapott adatok szétválogatása és mentése az egyedi táblába (következő lépés)
		// Egyelőre csak visszaadunk egy sikert, hogy tudjuk tesztelni a kommunikációt.
		
		$payload = $request->get_json_params();
		return new WP_REST_Response( array( 
			'success' => true, 
			'message' => 'Adatok sikeresen fogadva a Hub-on!',
			'received_data' => $payload
		), 200 );
	}
}