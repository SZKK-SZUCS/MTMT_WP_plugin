<?php
/**
 * Plugin Name: JKK MTMT Publications
 * Description: MTMT-alapú publikációs lista jóváhagyással és Elementor megjelenítéssel.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: jkk-mtmt-publications
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

define( 'JKK_MTMT_VERSION', '0.2.0' );
define( 'JKK_MTMT_DB_VERSION', '2' );
define( 'JKK_MTMT_PLUGIN_FILE', __FILE__ );
define( 'JKK_MTMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-activator.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-api-client.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-mapper.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-publication-repository.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-query-profile-repository.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-sync.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-sync-log-repository.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-notifier.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-sync-runner.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-cron.php';
require_once JKK_MTMT_PLUGIN_DIR . 'admin/class-jkk-mtmt-profiles-page.php';
require_once JKK_MTMT_PLUGIN_DIR . 'admin/class-jkk-mtmt-settings-page.php';

register_activation_hook( __FILE__, array( 'Jkk_Mtmt_Activator', 'activate' ) );
register_activation_hook( __FILE__, array( 'Jkk_Mtmt_Cron', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Jkk_Mtmt_Cron', 'deactivate' ) );

add_filter( 'cron_schedules', array( 'Jkk_Mtmt_Cron', 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
add_action( Jkk_Mtmt_Cron::HOOK, array( 'Jkk_Mtmt_Cron', 'run' ) );

/**
 * Fordítások betöltése.
 */
function jkk_mtmt_load_textdomain(): void {
	load_plugin_textdomain( 'jkk-mtmt-publications', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'jkk_mtmt_load_textdomain' );

/**
 * Séma-frissítés ellenőrzése minden betöltéskor — ha a tárolt DB-verzió eltér
 * a kódban lévőtől (pl. plugin-frissítés reaktiválás nélkül), újrafuttatja a
 * dbDelta-migrációt. A dbDelta idempotens: csak a hiányzó táblát/oszlopot
 * hozza létre, meglévő adatot nem érint.
 */
function jkk_mtmt_maybe_upgrade_db(): void {
	if ( get_option( 'jkk_mtmt_db_version' ) !== JKK_MTMT_DB_VERSION ) {
		Jkk_Mtmt_Activator::activate();
	}
}
add_action( 'plugins_loaded', 'jkk_mtmt_maybe_upgrade_db' );

/**
 * Admin menü regisztrálása: query profilok + beállítások (Fázis 3-ban a
 * moderációs lista/edit-form ide kerül majd sibling submenüként).
 */
function jkk_mtmt_register_admin_pages(): void {
	global $wpdb;

	$profile_repo = new Jkk_Mtmt_Query_Profile_Repository( $wpdb );

	( new Jkk_Mtmt_Profiles_Page( $profile_repo ) )->add_menu_page();
	( new Jkk_Mtmt_Settings_Page( new Jkk_Mtmt_Sync_Log_Repository( $wpdb ), $profile_repo ) )->add_menu_page();
}
add_action( 'admin_menu', 'jkk_mtmt_register_admin_pages' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-cli.php';
	WP_CLI::add_command( 'jkk-mtmt sync', 'Jkk_Mtmt_Sync_Command' );
	WP_CLI::add_command( 'jkk-mtmt profile', 'Jkk_Mtmt_Profile_Command' );
}
