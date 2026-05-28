<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Core {

	protected $settings;

	public function init() {
		// Osztályok betöltése
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-hub.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-schema.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-hub-api.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client-api.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-import-export.php';
		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-elementor.php';

		$this->settings = get_option( 'szeducate_settings', array() );

		if ( is_admin() ) {
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
		// Token és API kezelés inicializálása
		$hub = new SZEducate_Hub();
		$hub->init();

		// Séma kezelő inicializálása
		$schema = new SZEducate_Schema();
		$schema->init();

		// Hub REST API inicializálása
		$api = new SZEducate_Hub_API();
		$api->init();
	}

	private function init_client() {
		// Kliens inicializálása
		$client = new SZEducate_Client();
		$client->init();

		// Kliens REST API (Mentés tranzakcióval)
		$client_api = new SZEducate_Client_API();
		$client_api->init();

		// CSV Importáló inicializálása
		$import_export = new SZEducate_Import_Export();
		$import_export->init();

		// Elementor integráció inicializálása
		$elementor = new SZEducate_Elementor();
		$elementor->init();
	}
}