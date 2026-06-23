<?php
require_once __DIR__ . '/vendor/autoload.php';
/**
 * Plugin Name:       SZEducate
 * Plugin URI:        https://github.com/SZKK-SZUCS/SZEducate
 * Description:       Hub-Kliens architektúrájú képzésmenedzsment és szinkronizációs rendszer a Széchenyi István Egyetem számára.
 * Version: 		  0.9.0
 * Author:            Szurofka Márton, MFÜI
 * Author URI:        https://www.uni.sze.hu/
 * Text Domain:       szeducate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SZEDUCATE_VERSION', '0.9.0' );
define( 'SZEDUCATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SZEDUCATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-core.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-settings.php';

register_activation_hook( __FILE__, array( 'SZEducate_Activator', 'activate' ) );

function run_szeducate() {
	$plugin = new SZEducate_Core();
	$plugin->init();
}
run_szeducate();

require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$szeducate_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/SZKK-SZUCS/SZEducate',
	__FILE__,
	'szeducate'
);

$szeducate_update_checker->setBranch('main');