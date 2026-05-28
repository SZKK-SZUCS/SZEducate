<?php
/**
 * Plugin Name:       SZEducate
 * Description:       Hub-Spoke architektúrájú képzésmenedzsment és szinkronizációs rendszer a Széchenyi István Egyetem számára.
 * Version:           0.0.1
 * Author:            SZE
 * Text Domain:       szeducate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Közvetlen hozzáférés tiltása
}

define( 'SZEDUCATE_VERSION', '0.0.1' );
define( 'SZEDUCATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SZEDUCATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Osztályok betöltése
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-core.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-settings.php';

// Aktivációs hook
register_activation_hook( __FILE__, array( 'SZEducate_Activator', 'activate' ) );

// Core inicializálása
function run_szeducate() {
	$plugin = new SZEducate_Core();
	$plugin->init();
}
run_szeducate();