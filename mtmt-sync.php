<?php
/**
 * Plugin Name: MTMT Sync
 * Contributor: Szurofka Márton, MFÜI
 * Description: MTMT-alapú publikációs lista jóváhagyással és Elementor megjelenítéssel.
 * Version: 0.8.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: mtmt-sync
 * Domain Path: /languages
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

define( 'MTMT_VERSION', '0.8.0' );
define( 'MTMT_DB_VERSION', '3' );
define( 'MTMT_CAPS_VERSION', '1' );
define( 'MTMT_PLUGIN_FILE', __FILE__ );
define( 'MTMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MTMT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// A `use` importáló deklarációnak a fájl felső szintjén kell lennie — NEM
// tehető feltétel (if) belsejébe, az PHP parse errort adna. Maga az alias
// önmagában ártalmatlan (nem tölt be semmit, nem is kell hozzá, hogy a
// PucFactory osztály ténylegesen létezzen); a tényleges betöltés/hívás
// lent, `is_admin()` mögött történik.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Fázis 6 — GitHub-alapú frissítés a Plugin Update Checker (PUC) v5-tel
 * (CLAUDE.md §10.3). A könyvtár a repóba bevendorolva (`lib/plugin-update-checker/`),
 * nem külső csomagkezelőből töltődik be futásidőben. Csak admin-kontextusban
 * kell — a frontendnek semmi köze a frissítés-ellenőrzéshez, ezért `is_admin()`
 * mögé kötve, hogy ne terheljen minden nyilvános oldalbetöltést.
 *
 * A repo PUBLIKUS (`SZKK-SZUCS/MTMT_WP_plugin`), nem kell hitelesítő token.
 * Ha valaha privátra váltana: `setAuthentication(<token>)`, a tokent SOHA ne
 * commitold — konstansként (wp-config.php) vagy szűrőn keresztül add át.
 */
if ( is_admin() ) {
	require_once MTMT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

	$mtmt_update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/SZKK-SZUCS/MTMT_WP_plugin/',
		__FILE__,
		'mtmt-sync'
	);
	$mtmt_update_checker->setBranch( 'main' );
	// Nincs build-lépés (CLAUDE.md §2) -> a GitHub-generált forrás-zip elég,
	// a PUC kezeli a mappa-wrappert. Ha valaha build kerülne a workflow-ba és
	// csatolt asset-tel adnánk ki release-t, itt kellene: enableReleaseAssets().
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

add_filter( 'cron_schedules', array( 'Mtmt_Cron', 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
add_action( Mtmt_Cron::HOOK, array( 'Mtmt_Cron', 'run' ) );

// "Adatok törlése / alaphelyzet" a Pluginok listaoldalon (CLAUDE.md §11 —
// ez SZÁNDÉKOSAN nem az uninstall.php-hoz kötött, a plugin aktív állapotában
// is elérhető, explicit megerősítéssel, lásd class-mtmt-reset.php).
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'Mtmt_Reset', 'add_plugin_action_link' ) );
add_action( 'admin_init', array( 'Mtmt_Reset', 'maybe_handle_reset' ) );
add_action( 'admin_notices', array( 'Mtmt_Reset', 'maybe_show_notice' ) );

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
 *
 * A verzió-opció ÖNMAGÁBAN nem megbízható jelzés arra, hogy a tábla
 * ténylegesen létezik (docs/decisions.md #89 folytatása, élesben talált
 * hiba: a wp_mtmt_publications tábla fizikailag hiányzott, miközben az
 * opció már a legfrissebb verzióra mutatott — pl. egy DB-visszaállítás/
 * import érintette a wp_options-t, de a saját táblákat nem). Ezért egy
 * olcsó, indexelt SHOW TABLES LIKE lekérdezéssel közvetlenül is
 * ellenőrizzük a fő tábla létét, és a verzió-egyezéstől függetlenül
 * újrafuttatjuk a migrációt, ha a tábla mégis hiányzik.
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
 * Ugyanaz a minta, mint a DB-upgrade-nél: ha a plugin úgy frissül fájlszinten,
 * hogy közben nincs deaktiválás/reaktiválás, egy új capability (pl. ez a
 * Fázis 3-as mtmt_moderate/mtmt_classify) sosem kerülne rá a szerepkörökre.
 */
function mtmt_maybe_upgrade_caps(): void {
	if ( get_option( 'mtmt_caps_version' ) !== MTMT_CAPS_VERSION ) {
		Mtmt_Capabilities::activate();
		update_option( 'mtmt_caps_version', MTMT_CAPS_VERSION );
	}
}
add_action( 'plugins_loaded', 'mtmt_maybe_upgrade_caps' );

/**
 * Admin menü regisztrálása. A top-level "MTMT" a moderációs lista
 * (Mtmt_Publications_Page, `mtmt_moderate` capability) — ezt látják/használják
 * nap mint nap a moderátorok. A Profilok/Beállítások `manage_options`-hoz
 * kötött almenük, site-config jellegűek.
 */
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
 * Minden mutáló admin-műveletet (jóváhagyás/elutasítás, tömeges művelet,
 * gazdagító űrlap mentése) itt kell elintézni — `admin_init` a fejlécek
 * elküldése ELŐTT fut, így `wp_safe_redirect()` még működik. A render()
 * callback (`admin_menu`) már túl késő lenne ehhez.
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

// Elementor-widgetek (Fázis 5) — feltétel nélkül hívható, Elementor nélkül a
// loader belsejében felakasztott `elementor/*` action-ök egyszerűen sosem
// tüzelnek (lásd class-mtmt-elementor-loader.php PHPDoc, CLAUDE.md §2).
( new Mtmt_Elementor_Loader() )->init();

/**
 * Nyilvános AJAX-végpont a widget kereséséhez/lapozásához/szűréséhez —
 * Elementor-tól függetlenül regisztrált (maga a végpont csak a saját táblát
 * olvassa, `status='approved'`-ra szűkítve, lásd Mtmt_Widget_Data).
 */
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
