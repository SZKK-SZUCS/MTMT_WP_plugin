<?php
/**
 * Heti WP-Cron ütemezés (CLAUDE.md §6).
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * `jkk_mtmt_weekly_sync` cron-esemény regisztrálása/leiratkoztatása + a
 * tényleges futtatás Jkk_Mtmt_Sync_Runner-en keresztül.
 *
 * Productionben a látogató-vezérelt WP-cron helyett rendszer-cron/külső
 * cron-service hívja a `wp jkk-mtmt sync` CLI-parancsot (DISABLE_WP_CRON) —
 * ezt dokumentáld a README-ben, ez az osztály csak a fallback/dev-működést adja.
 */
final class Jkk_Mtmt_Cron {

	const HOOK = 'jkk_mtmt_weekly_sync';

	/**
	 * `cron_schedules` szűrő — egyedi "weekly" intervallum, ha még nem létezik
	 * (más plugin is regisztrálhatta ugyanezt a kulcsot).
	 *
	 * @param array $schedules
	 * @return array
	 */
	public static function add_schedule( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Hetente', 'jkk-mtmt-publications' ),
			);
		}
		return $schedules;
	}

	/**
	 * Aktiváláskor: ütemezze be a heti eseményt, ha még nincs beütemezve.
	 */
	public static function activate(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::HOOK );
		}
	}

	/**
	 * Deaktiváláskor: vegye le az ütemezést (adatot NEM töröl).
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * A cron-esemény callbackje.
	 */
	public static function run(): void {
		Jkk_Mtmt_Sync_Runner::run( 'cron' );
	}
}
