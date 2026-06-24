<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Core {

	protected $settings;

	public function init() {
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-schema.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-clients.php'; 
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-hub-api.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client-api.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-import-export.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-elementor.php';

		$this->settings = get_option( 'szeducate_settings', array() );

		if ( is_admin() ) {
			require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-settings.php';
			$settings_page = new SZEducate_Settings();
			$settings_page->init();
		}

		$mode = isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'client';

		if ( 'hub' === $mode ) {
			$this->init_hub();
		} else {
			$this->init_client();
		}
	}

	private function init_hub() {
		$schema = new SZEducate_Schema();
		$schema->init();

		$clients = new SZEducate_Clients(); 
		$clients->init(); 

		$api = new SZEducate_Hub_API();
		$api->init();

		$backup_manager = new SZEducate_Backup_Manager();
		$backup_manager->init();
	}

	private function init_client() {
		$client = new SZEducate_Client();
		$client->init();

		$client_api = new SZEducate_Client_API();
		$client_api->init();

		$import_export = new SZEducate_Import_Export();
		$import_export->init();

		$elementor = new SZEducate_Elementor();
		$elementor->init();
	}
}