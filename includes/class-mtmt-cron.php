<?php
/**
 * Heti WP-Cron ütemezés, konfigurálható nap+óra szerint.
 *
 * Szándékosan NEM a WP beépített fix 604800 másodperces "weekly"
 * ismétlődését használja, hanem minden lefutás után saját magát ütemezi
 * újra a mindenkori nap/óra beállításból frissen számolva — így a
 * nyári/téli időszámítás-váltás nem csúsztatja el a beállított órát.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Cron {

	const HOOK = 'mtmt_weekly_sync';

	/** Melyik napon fusson — ISO-8601 (`DateTime::format('N')` minta): 1=hétfő .. 7=vasárnap. */
	private const OPTION_DAY = 'mtmt_cron_day';

	/** Melyik órában (0-23, a site saját időzónájában, Beállítások → Általános). */
	private const OPTION_HOUR = 'mtmt_cron_hour';

	private const DEFAULT_DAY  = 1; // hétfő
	private const DEFAULT_HOUR = 3; // hajnal 3

	public static function activate(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( self::next_run_timestamp(), self::HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * A nap/óra beállítás megváltozásakor hívandó — törli a régi ütemezést,
	 * és az új beállítással azonnal újraütemez.
	 */
	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_single_event( self::next_run_timestamp(), self::HOOK );
	}

	/**
	 * A cron-esemény callbackje. Lefutás után azonnal beütemezi a következőt.
	 */
	public static function run(): void {
		Mtmt_Sync_Runner::run( 'cron' );
		wp_schedule_single_event( self::next_run_timestamp(), self::HOOK );
	}

	/**
	 * @param DateTime|null $now Tesztelhetőség céljából injektálható "most" —
	 *                           `null` esetén a valós aktuális idő (site
	 *                           időzónájában). Éles hívóknak nem kell megadni.
	 * @return int A beállított nap/óra alapján a legközelebbi jövőbeli
	 *             előfordulás Unix-időbélyege, a site saját időzónájában.
	 */
	public static function next_run_timestamp( ?DateTime $now = null ): int {
		$day  = self::get_day();
		$hour = self::get_hour();

		$now    = $now ?? new DateTime( 'now', wp_timezone() );
		$target = clone $now;
		$target->setTime( $hour, 0, 0 );

		$days_ahead = ( $day - (int) $now->format( 'N' ) + 7 ) % 7;
		// Ha ma van a célnap, de a célóra már elmúlt (vagy épp most van, a
		// pinger 5 perces felbontása miatt csúszhat is) -> jövő hét.
		if ( 0 === $days_ahead && $target <= $now ) {
			$days_ahead = 7;
		}
		if ( $days_ahead > 0 ) {
			$target->modify( "+{$days_ahead} days" );
		}

		return $target->getTimestamp();
	}

	/**
	 * @return int 1 (hétfő) .. 7 (vasárnap).
	 */
	public static function get_day(): int {
		$day = (int) get_option( self::OPTION_DAY, self::DEFAULT_DAY );
		return ( $day >= 1 && $day <= 7 ) ? $day : self::DEFAULT_DAY;
	}

	/**
	 * @return int 0-23.
	 */
	public static function get_hour(): int {
		$hour = (int) get_option( self::OPTION_HOUR, self::DEFAULT_HOUR );
		return ( $hour >= 0 && $hour <= 23 ) ? $hour : self::DEFAULT_HOUR;
	}

	/**
	 * Elmenti a nap/óra beállítást, és csak akkor ütemez újra, ha ténylegesen változott.
	 *
	 * @param int $day  1-7.
	 * @param int $hour 0-23.
	 * @return bool true, ha ténylegesen újraütemezésre került (a nap/óra változott).
	 */
	public static function save_schedule( int $day, int $hour ): bool {
		$day  = ( $day >= 1 && $day <= 7 ) ? $day : self::DEFAULT_DAY;
		$hour = ( $hour >= 0 && $hour <= 23 ) ? $hour : self::DEFAULT_HOUR;

		$changed = ( $day !== self::get_day() ) || ( $hour !== self::get_hour() );

		update_option( self::OPTION_DAY, $day );
		update_option( self::OPTION_HOUR, $hour );

		if ( $changed ) {
			self::reschedule();
		}

		return $changed;
	}
}
