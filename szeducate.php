<?php
/**
 * Plugin Name:       SZEducate
 * Plugin URI:        https://github.com/SZKK-SZUCS/SZEducate
 * Description:       Hub-Kliens architektúrájú képzésmenedzsment és szinkronizációs rendszer a Széchenyi István Egyetem számára.
 * Version:           0.9.36
 * Author:            Szurofka Márton, MFÜI
 * Author URI:        https://www.uni.sze.hu/
 * Text Domain:       szeducate
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SZEDUCATE_VERSION', '0.9.36' );
define( 'SZEDUCATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SZEDUCATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Egy Képzés course_data JSON-jának maximális mérete - elég nagybőkezű bármilyen valós
// tartalomhoz (gazdag HTML leírások, több mező), de gátat szab a korlátlan méretű
// beküldéseknek.
define( 'SZEDUCATE_MAX_COURSE_DATA_SIZE', 2 * 1024 * 1024 ); // 2 MB

add_action( 'init', function() {
	load_plugin_textdomain( 'szeducate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// 1. COMPOSER AUTOLOADER BETÖLTÉSE (Kritikus az Excel és a Frissítő miatt!)
$composer_autoload = SZEDUCATE_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-sync-log.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-core.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-settings.php';
require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-backup-manager.php';

register_activation_hook( __FILE__, array( 'SZEducate_Activator', 'activate' ) );

// 2. A PLUGIN INICIALIZÁLÁSA
function run_szeducate() {
	$plugin = new SZEducate_Core();
	$plugin->init(); // JAVÍTVA: A Te rendszered init()-et használ, nem run()-t!
}
run_szeducate();

// 3. AUTOMATIKUS FRISSÍTŐ (PUC) INTEGRÁCIÓ
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Megvizsgáljuk, hogy a Composer tényleg letöltötte-e a Frissítőt (Így nem fog összeomlani a WP, ha hiányzik a mappa)
if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$szeducate_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/SZKK-SZUCS/SZEducate',
		__FILE__,
		'szeducate'
	);

	// Figyeli a 'main' branch-et a frissítésekért
	$szeducate_update_checker->setBranch('main');
}