<?php
/**
 * Plugin Name: MTMT Sync
 * Contributor: Szurofka Márton, MFÜI
 * Description: MTMT-alapú publikációs lista jóváhagyással és Elementor megjelenítéssel.
 * Version: 0.12.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: mtmt-sync
 * Domain Path: /languages
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

define( 'MTMT_VERSION', '0.12.0' );
define( 'MTMT_DB_VERSION', '3' );
define( 'MTMT_CAPS_VERSION', '1' );
define( 'MTMT_PLUGIN_FILE', __FILE__ );
define( 'MTMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MTMT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// A `use` a fájl tetején kell legyen, nem tehető if-be (parse error lenne).
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Automatikus frissítés GitHubról (nyilvános repó, nem kell token). Csak
// adminban fut, a frontendnek nincs köze hozzá.
if ( is_admin() ) {
	require_once MTMT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

	$mtmt_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/SZKK-SZUCS/MTMT_WP_plugin/',
		__FILE__,
		'mtmt-sync'
	);
	$mtmt_update_checker->setBranch( 'main' );
}

require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-activator.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-capabilities.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-api-client.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-mapper.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-widget-cache.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-publication-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-query-profile-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-topic-area-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-sync.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-sync-log-repository.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-notifier.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-sync-runner.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-cron.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-author-formatter.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-external-id-icons.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-placeholder-image.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-card-renderer.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-widget-data.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-widget-ajax.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-list-table.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-publications-page.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-profiles-page.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-settings-page.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-topic-areas-page.php';
require_once MTMT_PLUGIN_DIR . 'admin/class-mtmt-capabilities-page.php';
require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-reset.php';
require_once MTMT_PLUGIN_DIR . 'elementor/class-mtmt-elementor-loader.php';

register_activation_hook( __FILE__, array( 'Mtmt_Activator', 'activate' ) );
register_activation_hook( __FILE__, array( 'Mtmt_Capabilities', 'activate' ) );
register_activation_hook( __FILE__, array( 'Mtmt_Cron', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Mtmt_Cron', 'deactivate' ) );

add_action( Mtmt_Cron::HOOK, array( 'Mtmt_Cron', 'run' ) );

// "Adatok törlése" a Pluginok listaoldalon — a plugin aktív állapotában is
// elérhető, nem csak törléskor.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'Mtmt_Reset', 'add_plugin_action_link' ) );
add_action( 'admin_init', array( 'Mtmt_Reset', 'maybe_handle_reset' ) );
add_action( 'admin_notices', array( 'Mtmt_Reset', 'maybe_show_notice' ) );

function mtmt_load_textdomain(): void {
	load_plugin_textdomain( 'mtmt-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'mtmt_load_textdomain' );

/**
 * Séma-frissítés ellenőrzése minden betöltéskor. A verzió-opció önmagában
 * nem elég (előfordulhat, hogy egyezik, miközben a tábla fizikailag
 * hiányzik), ezért a tábla tényleges létét is megnézzük.
 */
function mtmt_maybe_upgrade_db(): void {
	global $wpdb;

	$version_matches = get_option( 'mtmt_db_version' ) === MTMT_DB_VERSION;

	$publications_table = $wpdb->prefix . 'mtmt_publications';
	$table_exists        = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $publications_table ) ) === $publications_table;

	if ( ! $version_matches || ! $table_exists ) {
		Mtmt_Activator::activate();
	}
}
add_action( 'plugins_loaded', 'mtmt_maybe_upgrade_db' );

/**
 * Cron-ütemezés ellenőrzése minden betöltéskor — ha egy site úgy jön létre,
 * hogy a plugin már eleve "aktívként" van jelen (pl. egy meglévő konténer-
 * image-ből klónozva), az aktiválási hook sosem fut le, és a heti esemény
 * beütemezetlen marad. Itt pótoljuk, ha hiányzik.
 */
function mtmt_maybe_reschedule_cron(): void {
	if ( ! wp_next_scheduled( Mtmt_Cron::HOOK ) ) {
		Mtmt_Cron::activate();
	}
}
add_action( 'plugins_loaded', 'mtmt_maybe_reschedule_cron' );

function mtmt_maybe_upgrade_caps(): void {
	if ( get_option( 'mtmt_caps_version' ) !== MTMT_CAPS_VERSION ) {
		Mtmt_Capabilities::activate();
		update_option( 'mtmt_caps_version', MTMT_CAPS_VERSION );
	}
}
add_action( 'plugins_loaded', 'mtmt_maybe_upgrade_caps' );

function mtmt_register_admin_pages(): void {
	global $wpdb;

	$publication_repo = new Mtmt_Publication_Repository( $wpdb );
	$profile_repo      = new Mtmt_Query_Profile_Repository( $wpdb );
	$topic_area_repo   = new Mtmt_Topic_Area_Repository( $wpdb );

	( new Mtmt_Publications_Page( $publication_repo, $profile_repo, $topic_area_repo ) )->add_menu_page();
	( new Mtmt_Profiles_Page( $profile_repo ) )->add_menu_page();
	( new Mtmt_Topic_Areas_Page( $topic_area_repo ) )->add_menu_page();
	( new Mtmt_Settings_Page( new Mtmt_Sync_Log_Repository( $wpdb ), $profile_repo ) )->add_menu_page();
	( new Mtmt_Capabilities_Page() )->add_menu_page();
}
add_action( 'admin_menu', 'mtmt_register_admin_pages' );

/**
 * Mutáló admin-műveletek (jóváhagyás, mentés) — `admin_init` a redirect miatt kell.
 */
function mtmt_handle_admin_actions(): void {
	global $wpdb;

	( new Mtmt_Publications_Page(
		new Mtmt_Publication_Repository( $wpdb ),
		new Mtmt_Query_Profile_Repository( $wpdb ),
		new Mtmt_Topic_Area_Repository( $wpdb )
	) )->maybe_handle_request();
}
add_action( 'admin_init', 'mtmt_handle_admin_actions' );

( new Mtmt_Elementor_Loader() )->init();

function mtmt_register_widget_ajax(): void {
	global $wpdb;
	( new Mtmt_Widget_Ajax( new Mtmt_Widget_Data( new Mtmt_Publication_Repository( $wpdb ), new Mtmt_Topic_Area_Repository( $wpdb ) ) ) )->register();
}
add_action( 'init', 'mtmt_register_widget_ajax' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MTMT_PLUGIN_DIR . 'includes/class-mtmt-cli.php';
	WP_CLI::add_command( 'mtmt sync', 'Mtmt_Sync_Command' );
	WP_CLI::add_command( 'mtmt profile', 'Mtmt_Profile_Command' );
}
