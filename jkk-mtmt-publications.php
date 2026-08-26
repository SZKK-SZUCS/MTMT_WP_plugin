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
define( 'JKK_MTMT_DB_VERSION', '1' );
define( 'JKK_MTMT_PLUGIN_FILE', __FILE__ );
define( 'JKK_MTMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-activator.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-api-client.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-mapper.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-publication-repository.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-query-profile-repository.php';
require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-sync.php';
require_once JKK_MTMT_PLUGIN_DIR . 'admin/class-jkk-mtmt-profiles-page.php';

register_activation_hook( __FILE__, array( 'Jkk_Mtmt_Activator', 'activate' ) );

/**
 * Fordítások betöltése.
 */
function jkk_mtmt_load_textdomain(): void {
	load_plugin_textdomain( 'jkk-mtmt-publications', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'jkk_mtmt_load_textdomain' );

/**
 * Admin menü regisztrálása (query profilok — Fázis 3-ban a moderációs
 * lista/edit-form ide kerül majd sibling submenüként).
 */
function jkk_mtmt_register_admin_pages(): void {
	global $wpdb;
	( new Jkk_Mtmt_Profiles_Page( new Jkk_Mtmt_Query_Profile_Repository( $wpdb ) ) )->add_menu_page();
}
add_action( 'admin_menu', 'jkk_mtmt_register_admin_pages' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once JKK_MTMT_PLUGIN_DIR . 'includes/class-jkk-mtmt-cli.php';
	WP_CLI::add_command( 'jkk-mtmt sync', 'Jkk_Mtmt_Sync_Command' );
	WP_CLI::add_command( 'jkk-mtmt profile', 'Jkk_Mtmt_Profile_Command' );
}
