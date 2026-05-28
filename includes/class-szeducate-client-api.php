<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Client_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	public function register_endpoints() {
		register_rest_route( 'szeducate/v1/client', '/course', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_course_data' ),
			'permission_callback' => function() {
				// Csak bejelentkezett WP adminok menthetnek a React formból
				return current_user_can( 'edit_posts' ); 
			}
		) );
	}

	public function save_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();

		// Alapvető validáció
		if ( empty( $params['title'] ) ) {
			return new WP_Error( 'missing_title', 'A képzés címe kötelező!', array( 'status' => 400 ) );
		}

		$post_id = ! empty( $params['local_post_id'] ) ? intval( $params['local_post_id'] ) : 0;
		$title = sanitize_text_field( $params['title'] );
		
		// Sémából származó dinamikus adatok
		$dynamic_data = isset( $params['course_data'] ) ? $params['course_data'] : array();

		// 1. LÉPÉS: TRANZAKCIÓ INDÍTÁSA
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 2. LÉPÉS: WordPress CPT mentése (A permalink miatt)
			$post_args = array(
				'post_title'  => $title,
				'post_type'   => 'sz_course',
				'post_status' => 'publish'
			);

			if ( $post_id > 0 ) {
				$post_args['ID'] = $post_id;
				$saved_post_id = wp_update_post( $post_args, true );
			} else {
				$saved_post_id = wp_insert_post( $post_args, true );
			}

			if ( is_wp_error( $saved_post_id ) ) {
				throw new Exception( 'Nem sikerült létrehozni a WP bejegyzést.' );
			}

			// 3. LÉPÉS: Egyedi Adatbázis tábla mentése (A hibrid mezők szétválogatása)
			// (Itt valójában végig kell iterálni a sémán, hogy mi megy saját oszlopba és mi JSON blobba.
			// Ezt egyelőre egyszerűsítve mentjük, amíg a React SPA-t rákötjük).
			
			$table_name = $wpdb->prefix . 'szeducate_courses_data';
			
			// A JSON blob létrehozása az összes adatból
			$json_blob = wp_json_encode( $dynamic_data, JSON_UNESCAPED_UNICODE );

			$db_data = array(
				'local_post_id' => $saved_post_id,
				'title'         => $title,
				'course_data'   => $json_blob,
			);
			$db_formats = array( '%d', '%s', '%s' );

			// Ha ez egy frissítés
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE local_post_id = %d", $saved_post_id ) );
			
			if ( $existing ) {
				$result = $wpdb->update( $table_name, $db_data, array( 'id' => $existing ), $db_formats, array( '%d' ) );
			} else {
				$result = $wpdb->insert( $table_name, $db_data, $db_formats );
			}

			if ( false === $result ) {
				throw new Exception( 'Adatbázis írási hiba az egyedi táblában.' );
			}

			// 4. LÉPÉS: Taxonómiák beállítása (A Láthatatlan SEO URL-ekhez)
			$schema_json = get_option( 'szeducate_local_schema', '[]' );
			$schema = json_decode( $schema_json, true );
			if ( is_array( $schema ) ) {
				foreach ( $schema as $group ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_taxonomy'] ) && isset( $dynamic_data[ $field['key'] ] ) ) {
							$tax_slug = 'sz_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							$term_val = sanitize_text_field( $dynamic_data[ $field['key'] ] );
							
							// Ráakasztjuk a szót (pl. 'Győr') a posztra, ha létezik az érték
							if ( ! empty( $term_val ) ) {
								wp_set_object_terms( $saved_post_id, $term_val, $tax_slug, false );
							}
						}
					}
				}
			}

			// MINDEN SIKERES: Véglegesítjük a tranzakciót
			$wpdb->query( 'COMMIT' );

			// 5. LÉPÉS: Aszinkron Hub szinkronizáció beütemezése
			// A WP-Cron a háttérben meghívja majd a sync_course_to_hub() függvényt
			wp_schedule_single_event( time(), 'szeducate_background_sync', array( $saved_post_id ) );

			return new WP_REST_Response( array( 
				'success' => true, 
				'message' => 'Sikeres lokális mentés! Szinkronizálás a Hub felé folyamatban...',
				'local_post_id' => $saved_post_id 
			), 200 );

		} catch ( Exception $e ) {
			// HIBA TÖRTÉNT: Visszagörgetünk mindent, mintha mi sem történt volna (Árva poszt védelem)
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db_transaction_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}