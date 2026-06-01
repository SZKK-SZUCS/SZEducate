<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Client {

	public function init() {
		add_action( 'init', array( $this, 'register_course_cpt' ) );
		add_action( 'init', array( $this, 'register_dynamic_taxonomies' ) );
		
		add_action( 'szeducate_background_sync', array( $this, 'sync_course_to_hub' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_react_app' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_react_root' ) );

		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 100, 2 );
		
		// ÚJ: Golyóálló CSS a natív UI elrejtésére
		add_action( 'admin_head', array( $this, 'clean_admin_ui' ) );
	}

	public function disable_gutenberg( $current_status, $post_type ) {
		if ( $post_type === 'sz_course' ) {
			return false;
		}
		return $current_status;
	}

	public function register_course_cpt() {
		$args = array(
			'labels'             => array(
				'name'          => 'Képzések',
				'singular_name' => 'Képzés',
				'menu_name'     => 'Képzések',
				'add_new'       => 'Új Képzés',
				'all_items'     => 'Minden Képzés',
			),
			'public'             => true,
			'show_ui'            => true,
			'rewrite'            => array( 'slug' => 'kepzes' ),
			'has_archive'        => true,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'show_in_rest'       => true, 
			'supports'           => array( 'title' ), 
		);
		register_post_type( 'sz_course', $args );
	}

	public function register_dynamic_taxonomies() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_taxonomy'] ) && $field['is_taxonomy'] ) {
							$tax_slug = 'sz_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							$rewrite_slug = sanitize_title( $field['label'] ); 

							register_taxonomy( $tax_slug, 'sz_course', array(
								'label'             => $field['label'],
								'hierarchical'      => true,
								'public'            => true,
								'show_ui'           => false, 
								'show_in_rest'      => false,
								'rewrite'           => array( 'slug' => $rewrite_slug ),
							) );
						}
					}
				}
			}
		}
	}

	// ÚJ: CSS beágyazása közvetlenül a fejlécbe
	public function clean_admin_ui() {
		global $typenow;
		if ( $typenow === 'sz_course' ) {
			echo '<style>
				/* Elrejtünk mindent, ami a régi WordPress szerkesztőhöz tartozik */
				#titlediv, 
				#postbox-container-1, 
				#postbox-container-2, 
				.wrap h1, 
				.wrap .page-title-action, 
				.notice, 
				#lost-connection-notice, 
				#local-storage-notice {
					display: none !important;
				}
				/* Formázzuk a mi React felületünket */
				#szeducate-react-root {
					margin-top: 20px;
					max-width: 1000px;
				}
			</style>';
		}
	}

	public function enqueue_react_app( $hook ) {
		global $typenow, $wpdb;
		
		if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $typenow === 'sz_course' ) {
			
			wp_enqueue_media();
			wp_enqueue_editor();
			
			$asset_file = SZEDUCATE_PLUGIN_DIR . 'build/index.asset.php';
			if ( file_exists( $asset_file ) ) {
				$assets = require $asset_file;
				wp_enqueue_script(
					'szeducate-react-app',
					SZEDUCATE_PLUGIN_URL . 'build/index.js',
					$assets['dependencies'],
					$assets['version'],
					true
				);
				
				$post_id = get_the_ID();
				$existing_title = '';
				$existing_data = new stdClass();

				// Ha ez egy meglévő poszt, kiolvassuk az egyedi táblából az adatokat
				if ( $post_id ) {
					$table_name = $wpdb->prefix . 'szeducate_courses_data';
					$course_db = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE local_post_id = %d", $post_id ), ARRAY_A );
					
					if ( $course_db ) {
						$existing_title = $course_db['title'];
						$decoded_data = json_decode( $course_db['course_data'], true );
						if ( is_array( $decoded_data ) ) {
							$existing_data = $decoded_data;
						}
					}
				}

				wp_localize_script( 'szeducate-react-app', 'szEducateData', array(
					'postId'        => $post_id,
					'nonce'         => wp_create_nonce( 'wp_rest' ), 
					'restUrl'       => esc_url_raw( rest_url( 'szeducate/v1/client/course' ) ),
					'schema'        => json_decode( get_option( 'szeducate_local_schema', '[]' ), true ),
					'existingTitle' => $existing_title,
					'existingData'  => $existing_data
				) );
			}
		}
	}

	public function render_react_root( $post ) {
		if ( is_object( $post ) && $post->post_type === 'sz_course' ) {
			echo '<div id="szeducate-react-root"><h2>React betöltése folyamatban...</h2></div>';
		}
	}

	public function sync_course_to_hub( $local_post_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';

		$course = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE local_post_id = %d", $local_post_id ), ARRAY_A );
		if ( ! $course ) return;

		$settings = get_option( 'szeducate_settings', array() );
		$hub_url = rtrim( $settings['hub_url'], '/' );
		$api_token = $settings['api_token'];

		if ( empty( $hub_url ) || empty( $api_token ) ) {
			error_log( 'SZEducate Sync Hiba: Nincs Hub URL vagy Token megadva.' );
			return;
		}

		$endpoint = $hub_url . '/wp-json/szeducate/v1/hub/courses';

		$payload = array(
			'local_post_id' => $local_post_id,
			'title'         => $course['title'],
			'course_data'   => json_decode( $course['course_data'], true )
		);

		$response = wp_remote_post( $endpoint, array(
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 15,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'SZEducate Sync Hálózat Hiba: ' . $response->get_error_message() );
			return; 
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 200 || $code === 201 ) {
			$data = json_decode( $body, true );
			if ( ! empty( $data['hub_id'] ) ) {
				$wpdb->update( 
					$table_name, 
					array( 'hub_id' => intval( $data['hub_id'] ) ), 
					array( 'local_post_id' => $local_post_id ) 
				);
			}
		} else {
			error_log( 'SZEducate Sync Hub Hiba (' . $code . '): ' . $body );
		}
	}
}