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
		$result = self::reset_now( $wpdb );

		// A TRUNCATE-eredményt (különösen egy esetleges hibát) rövid életű
		// tranziensben visszük át a redirecten — a query-string nem alkalmas
		// egy hibaüzenet-tömb átadására, és úgyis csak egyszer, a következő
		// oldalbetöltéskor kell elolvasni (lásd maybe_show_notice()). Élesben
		// derült ki, hogy ez a fajta "csendben lenyelt hiba" ugyanaz a
		// hibaosztály volt, mint a szinkron insert/update hibáinál — lásd
		// docs/decisions.md #89.
		set_transient( 'mtmt_reset_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( array( 'mtmt_reset' => '1' ), admin_url( 'plugins.php' ) ) );
		exit;
	}

	/**
	 * `admin_notices`-ből hívva — a reset tényleges kimenetelét mutatja (nem
	 * feltételezi, hogy minden tábla sikeresen kiürült).
	 */
	public static function maybe_show_notice(): void {
		if ( ! isset( $_GET['mtmt_reset'] ) || '1' !== $_GET['mtmt_reset'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$transient_key = 'mtmt_reset_result_' . get_current_user_id();
		$result        = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! is_array( $result ) ) {
			// Nincs tranziens (pl. lejárt, vagy valaki csak rányitotta a
			// query-stringet) — ne állítsunk hamis sikert.
			return;
		}

		if ( empty( $result['failed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Az MTMT Sync összes tábla-adata törölve — a szinkron a legközelebbi futáskor mindent újnak fog látni.', 'mtmt-sync' ) . '</p></div>';
			return;
		}

		$lines = array();
		foreach ( $result['failed'] as $table => $error ) {
			$lines[] = esc_html( $table . ': ' . $error );
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Az MTMT Sync adatai NEM törlődtek (teljesen vagy részben) — adatbázis-hiba:', 'mtmt-sync' ) . '</strong></p><ul style="list-style:disc;padding-left:1.5em;"><li>' . implode( '</li><li>', $lines ) . '</li></ul></div>';
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
	 * KRITIKUS: a `$wpdb->query()` visszatérési értékét ELLENŐRIZNI kell —
	 * `TRUNCATE TABLE` MySQL-ben `DROP`-jogosultságot igényel (nem elég a
	 * DELETE/INSERT/UPDATE), sok korlátozott jogú DB-felhasználónál ez
	 * hiányzik, és a hívás csendben `false`-t adna vissza, ha nem néznénk meg.
	 *
	 * @param wpdb $wpdb
	 * @return array{failed:array<string,string>} table => hibaüzenet, azokra
	 *         a táblákra, amiknél a TRUNCATE ténylegesen meghiúsult.
	 */
	public static function reset_now( wpdb $wpdb ): array {
		$tables = array(
			$wpdb->prefix . 'mtmt_pub_topic_area', // pivot tábla elsőként
			$wpdb->prefix . 'mtmt_publications',
			$wpdb->prefix . 'mtmt_query_profiles',
			$wpdb->prefix . 'mtmt_topic_areas',
			$wpdb->prefix . 'mtmt_sync_log',
		);

		$failed = array();

		foreach ( $tables as $table ) {
			// A táblanév a $wpdb->prefix-ből + fejlesztő által rögzített
			// (nem felhasználói input) utótagból épül — nem paraméterezhető
			// $wpdb->prepare()-rel (az csak ÉRTÉKEKET escape-el, nem
			// azonosítókat), ugyanaz a minta, mint minden repository-osztály
			// FROM/WHERE-jében a $this->table használatakor.
			$query_result = $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( false === $query_result ) {
				$failed[ $table ] = $wpdb->last_error ?: __( 'Ismeretlen adatbázis-hiba.', 'mtmt-sync' );
			}
		}

		if ( class_exists( 'Mtmt_Widget_Cache' ) ) {
			Mtmt_Widget_Cache::bump();
		}

		return array( 'failed' => $failed );
	}
}
