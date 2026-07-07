<?php
// WordPress hívja meg automatikusan, amikor a plugint a Beépülő modulok listájából
// véglegesen törlik (nem csak deaktiválják). Ha nem ebből a folyamatból érkezik a hívás,
// azonnal kilépünk - ez a szokásos, kötelező biztonsági őrzés.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Az adatok törlése MINDIG opt-in: csak akkor futunk tovább, ha az admin a Beállítások
// oldalon kifejezetten bejelölte (és egy JS megerősítő ablakban is jóváhagyta), hogy
// eltávolításkor véglegesen törlődjön minden SZEducate-adat. Alapértelmezetten (a
// beállítás hiányában, vagy ha ki van kapcsolva) semmit sem törlünk - a táblák, a
// beállítások és a helyi mentések változatlanul megmaradnak egy esetleges újratelepítéshez.
$settings = get_option( 'szeducate_settings', array() );
$should_purge = ! empty( $settings['purge_on_uninstall'] );

if ( ! $should_purge ) {
	return;
}

global $wpdb;

// 1. Egyedi táblák törlése
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}szeducate_courses_data" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}szeducate_clients" );

// 2. Minden szeducate_* opció és tranziens törlése
$like_options            = $wpdb->esc_like( 'szeducate_' ) . '%';
$like_transients         = $wpdb->esc_like( '_transient_szeducate_' ) . '%';
$like_transients_timeout = $wpdb->esc_like( '_transient_timeout_szeducate_' ) . '%';

$wpdb->query( $wpdb->prepare(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
	$like_options, $like_transients, $like_transients_timeout
) );

// 3. Ütemezett cron-események törlése
$cron_hooks = array(
	'szeducate_automated_backup_cron',
	'szeducate_daily_expiration_check',
	'szeducate_process_restore_webhooks',
	'szeducate_dispatch_course_webhook',
	'szeducate_dispatch_delete_webhook',
	'szeducate_background_sync',
);
foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// 4. Helyi biztonsági mentések mappájának törlése (a mentések API-tokeneket is
// tartalmaznak, ezért ha az admin az adatok végleges törlését kérte, ez is törlődjön)
$upload_dir = wp_upload_dir();
$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'szeducate-backups/';

if ( is_dir( $backup_dir ) ) {
	$files = glob( $backup_dir . '*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) @unlink( $file );
		}
	}
	@rmdir( $backup_dir );
}
