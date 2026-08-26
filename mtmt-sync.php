<?php
/**
 * Plugin Name: MTMT Sync
 * Description: MTMT-alapú publikációs lista jóváhagyással és Elementor megjelenítéssel.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: mtmt-sync
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

define( 'MTMT_VERSION', '0.2.0' );
define( 'MTMT_DB_VERSION', '2' );
define( 'MTMT_PLUGIN_FILE', __FILE__ );
define( 'MTMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-activator.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-api-client.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-mapper.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-publication-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-query-profile-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-sync.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-sync-log-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-notifier.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-sync-runner.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-cron.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-profiles-page.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-settings-page.php';

register_activation_hook( __FILE__, array( 'Mtmt_Activator', 'activate' ) );
register_activation_hook( __FILE__, array( 'Mtmt_Cron', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Mtmt_Cron', 'deactivate' ) );

add_filter( 'cron_schedules', array( 'Mtmt_Cron', 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
add_action( Mtmt_Cron::HOOK, array( 'Mtmt_Cron', 'run' ) );

/**
 * Fordítások betöltése.
 */
function mtmt_load_textdomain(): void {
	load_plugin_textdomain( 'mtmt-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'mtmt_load_textdomain' );

/**
 * Séma-frissítés ellenőrzése minden betöltéskor — ha a tárolt DB-verzió eltér
 * a kódban lévőtől (pl. plugin-frissítés reaktiválás nélkül), újrafuttatja a
 * dbDelta-migrációt. A dbDelta idempotens: csak a hiányzó táblát/oszlopot
 * hozza létre, meglévő adatot nem érint.
 */
function mtmt_maybe_upgrade_db(): void {
	if ( get_option( 'mtmt_db_version' ) !== MTMT_DB_VERSION ) {
		Mtmt_Activator::activate();
	}
}
add_action( 'plugins_loaded', 'mtmt_maybe_upgrade_db' );

/**
 * Admin menü regisztrálása: query profilok + beállítások (Fázis 3-ban a
 * moderációs lista/edit-form ide kerül majd sibling submenüként).
 */
function mtmt_register_admin_pages(): void {
	global $wpdb;

	$profile_repo = new Mtmt_Query_Profile_Repository( $wpdb );

	( new Mtmt_Profiles_Page( $profile_repo ) )->add_menu_page();
	( new Mtmt_Settings_Page( new Mtmt_Sync_Log_Repository( $wpdb ), $profile_repo ) )->add_menu_page();
}
add_action( 'admin_menu', 'mtmt_register_admin_pages' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-cli.php';
	WP_CLI::add_command( 'mtmt sync', 'Mtmt_Sync_Command' );
	WP_CLI::add_command( 'mtmt profile', 'Mtmt_Profile_Command' );
}
