<?php
/**
 * "Adatok törlése / alaphelyzet" — a Pluginok listaoldalon elérhető, a
 * plugin saját tábláit ürítő reset-funkció (megrendelői kérés, 2026-08,
 * docs/decisions.md #88).
 *
 * FONTOS elhatárolás a `uninstall.php`-tól (CLAUDE.md §11: "ne dobj adatot
 * deaktiváláskor, csak explicit uninstall.php-ban, megerősítéssel"): ez a
 * művelet ATTÓL FÜGGETLEN, a plugin AKTÍV állapotában is elérhető, kifejezetten
 * arra, hogy a felhasználó bármikor, a plugin törlése/deaktiválása nélkül is
 * "tiszta lappal" tudjon újrakezdeni (pl. teszt-adatok kitakarítása). Csak a
 * TÁBLÁKAT üríti (publikációk, profilok, területek, futás-napló) — a
 * beállításokat (címzettek, funkció-kapcsolók, jogosultság-leképzés,
 * placeholder-kép) SZÁNDÉKOSAN nem érinti, mert a megrendelői kérés kifejezetten
 * "a táblákat resetelje" volt, nem a site-konfigurációt.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Reset {

	private const ACTION       = 'mtmt_reset_all_data';
	private const NONCE_ACTION = 'mtmt_reset_all_data';

	/**
	 * `plugin_action_links_{basename}`-hez kötve — egy piros "Adatok törlése"
	 * linket told be a plugin sorába a Pluginok oldalon, a szokásos
	 * Aktiválás/Deaktiválás/Szerkesztés linkek mellé.
	 *
	 * @param string[] $links
	 * @return string[]
	 */
	public static function add_plugin_action_link( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$url = wp_nonce_url(
			add_query_arg( array( 'action' => self::ACTION ), admin_url( 'plugins.php' ) ),
			self::NONCE_ACTION
		);

		$confirm_text = __( 'Ez VÉGLEGESEN törli az összes MTMT-adatot: minden szinkronizált publikációt, jóváhagyási státuszt, query-profilt, szakmai területet és a futás-naplót. A beállítások (címzettek, funkció-kapcsolók, jogosultság-leképzés, placeholder-kép) megmaradnak. Ez NEM vonható vissza. Biztosan folytatod?', 'mtmt-sync' );

		$links[] = sprintf(
			'<a href="%1$s" style="color:#b32d2e;" onclick="return confirm(%2$s);">%3$s</a>',
			esc_url( $url ),
			esc_attr( wp_json_encode( $confirm_text ) ),
			esc_html__( 'Adatok törlése / alaphelyzet', 'mtmt-sync' )
		);

		return $links;
	}

	/**
	 * `admin_init`-ből hívva (fejlécek elküldése előtt fut, hogy a
	 * `wp_safe_redirect()` még működjön — ugyanaz az időzítési minta, mint
	 * a `Mtmt_Publications_Page::maybe_handle_request()`-nél).
	 */
	public static function maybe_handle_reset(): void {
		if ( ! isset( $_GET['action'] ) || self::ACTION !== $_GET['action'] ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez a művelethez.', 'mtmt-sync' ) );
		}

		global $wpdb;
		self::reset_now( $wpdb );

		wp_safe_redirect( add_query_arg( array( 'mtmt_reset' => '1' ), admin_url( 'plugins.php' ) ) );
		exit;
	}

	/**
	 * `admin_notices`-ből hívva — sikeres reset után egyszeri visszajelzés.
	 */
	public static function maybe_show_notice(): void {
		if ( ! isset( $_GET['mtmt_reset'] ) || '1' !== $_GET['mtmt_reset'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Az MTMT Sync összes tábla-adata törölve — a szinkron a legközelebbi futáskor mindent újnak fog látni.', 'mtmt-sync' ) . '</p></div>';
	}

	/**
	 * A tényleges, visszavonhatatlan művelet: mind az 5 plugin-tábla
	 * kiürítése. `TRUNCATE` — nem `DROP`+`dbDelta`-újralétrehozás —, hogy a
	 * séma biztosan változatlan maradjon, csak az adat tűnjön el.
	 *
	 * Public (nem private) — szándékosan elválasztva a `maybe_handle_reset()`
	 * HTTP-folyamatvezérlésétől (redirect+exit), hogy önmagában, unit-
	 * teszttel is hívható legyen (`exit`-et hívó kódot nem lehet biztonságosan
	 * tesztelni ugyanabban a PHP-processzben).
	 *
	 * @param wpdb $wpdb
	 */
	public static function reset_now( wpdb $wpdb ): void {
		$tables = array(
			$wpdb->prefix . 'mtmt_pub_topic_area', // pivot tábla elsőként
			$wpdb->prefix . 'mtmt_publications',
			$wpdb->prefix . 'mtmt_query_profiles',
			$wpdb->prefix . 'mtmt_topic_areas',
			$wpdb->prefix . 'mtmt_sync_log',
		);

		foreach ( $tables as $table ) {
			// A táblanév a $wpdb->prefix-ből + fejlesztő által rögzített
			// (nem felhasználói input) utótagból épül — nem paraméterezhető
			// $wpdb->prepare()-rel (az csak ÉRTÉKEKET escape-el, nem
			// azonosítókat), ugyanaz a minta, mint minden repository-osztály
			// FROM/WHERE-jében a $this->table használatakor.
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( class_exists( 'Mtmt_Widget_Cache' ) ) {
			Mtmt_Widget_Cache::bump();
		}
	}
}
