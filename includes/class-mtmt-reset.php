<?php
/**
 * "Adatok törlése" — a Pluginok listaoldalon elérhető gomb, ami kiüríti a
 * plugin saját tábláit (publikációk, profilok, területek, futás-napló). A
 * beállításokat nem érinti. A plugin aktív állapotában is elérhető, nem
 * csak törléskor/deaktiváláskor.
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

		$confirm_text = __( 'Ez véglegesen törli az összes publikációt, jóváhagyást, profilt, szakmai területet és a futás-naplót. A beállítások megmaradnak. Ez nem vonható vissza. Biztosan folytatod?', 'mtmt-sync' );

		$links[] = sprintf(
			'<a href="%1$s" style="color:#b32d2e;" onclick="return confirm(%2$s);">%3$s</a>',
			esc_url( $url ),
			esc_attr( wp_json_encode( $confirm_text ) ),
			esc_html__( 'Adatok törlése', 'mtmt-sync' )
		);

		return $links;
	}

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

		// Az eredményt (esetleges hibával) egy rövid életű tranziensben
		// visszük át a redirecten a következő oldalbetöltéshez.
		set_transient( 'mtmt_reset_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( array( 'mtmt_reset' => '1' ), admin_url( 'plugins.php' ) ) );
		exit;
	}

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
			return;
		}

		if ( empty( $result['failed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Minden adat törölve.', 'mtmt-sync' ) . '</p></div>';
			return;
		}

		$lines = array();
		foreach ( $result['failed'] as $table => $error ) {
			$lines[] = esc_html( $table . ': ' . $error );
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Az adatok nem törlődtek (teljesen vagy részben) — hiba:', 'mtmt-sync' ) . '</strong></p><ul style="list-style:disc;padding-left:1.5em;"><li>' . implode( '</li><li>', $lines ) . '</li></ul></div>';
	}

	/**
	 * A tényleges, visszavonhatatlan művelet: mind az 5 plugin-tábla
	 * kiürítése (`TRUNCATE`, a séma marad, csak az adat tűnik el). Public,
	 * hogy `exit` nélkül, önmagában is tesztelhető legyen.
	 *
	 * @param wpdb $wpdb
	 * @return array{failed:array<string,string>} table => hibaüzenet, ha egy
	 *         TRUNCATE meghiúsult (pl. hiányzó DB-jogosultság miatt).
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
