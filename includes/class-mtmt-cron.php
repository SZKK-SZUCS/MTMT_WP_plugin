<?php
/**
 * Heti WP-Cron ütemezés (CLAUDE.md §6), konfigurálható nap+óra szerint
 * (megrendelői kérés: "lehet-e időzíteni a cron futását pl hétfőnként
 * hajnalra", docs/decisions.md #98).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * `mtmt_weekly_sync` cron-esemény regisztrálása/leiratkoztatása + a
 * tényleges futtatás Mtmt_Sync_Runner-en keresztül.
 *
 * Productionben a látogató-vezérelt WP-cron helyett rendszer-cron/külső
 * cron-service hívja a `wp mtmt sync` CLI-parancsot (DISABLE_WP_CRON) —
 * ezt dokumentáld a README-ben, ez az osztály csak a fallback/dev-működést adja.
 *
 * KRITIKUS tervezési döntés: NEM a WP beépített "heti" (fix 604800
 * másodperces) ismétlődő ütemezését használjuk (`wp_schedule_event(...,
 * 'weekly', ...)`), hanem minden lefutás után saját magát ütemezi újra
 * (`wp_schedule_single_event()`), a MOSTANI nap/óra beállításból frissen
 * kiszámított következő időponttal. Indoklás: a fix másodperc-intervallum
 * nyári/téli időszámítás-váltásnál (DST) tartósan (fél évig) 1 órát
 * csúszna a fali-óra időhöz képest — pl. "hétfő 3:00" helyett hónapokig
 * "hétfő 4:00" (vagy 2:00) futna. Az önmagát újraütemező egyszeri esemény
 * minden alkalommal a valós naptári nap/óra alapján számol, ezért ez a
 * csúszás nem fordulhat elő.
 */
final class Mtmt_Cron {

	const HOOK = 'mtmt_weekly_sync';

	/** Melyik napon fusson — ISO-8601 (`DateTime::format('N')` minta): 1=hétfő .. 7=vasárnap. */
	private const OPTION_DAY = 'mtmt_cron_day';

	/** Melyik órában (0-23, a site saját időzónájában, Beállítások → Általános). */
	private const OPTION_HOUR = 'mtmt_cron_hour';

	private const DEFAULT_DAY  = 1; // hétfő
	private const DEFAULT_HOUR = 3; // hajnal 3

	/**
	 * Aktiváláskor: ütemezze be a következő futást, ha még nincs beütemezve.
	 */
	public static function activate(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( self::next_run_timestamp(), self::HOOK );
		}
	}

	/**
	 * Deaktiváláskor: vegye le az ütemezést (adatot NEM töröl).
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * A nap/óra beállítás megváltozásakor hívandó (a Beállítások oldalról) —
	 * törli a jelenlegi (a RÉGI nap/óra alapján számolt) ütemezést, és az
	 * ÚJ beállítással azonnal újraütemezi. Szándékosan KÜLÖN metódus az
	 * `activate()`-től, mert az `activate()` csak akkor nyúl hozzá, ha
	 * MÉG NINCS ütemezve — ha csak a beállítást változtatnánk meg, a régi
	 * (rossz napra/órára szóló) ütemezés némán megmaradna a következő
	 * lefutásig.
	 */
	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_single_event( self::next_run_timestamp(), self::HOOK );
	}

	/**
	 * A cron-esemény callbackje. Lefutás UTÁN azonnal beütemezi a
	 * KÖVETKEZŐ előfordulást (self-rescheduling), a mindenkori nap/óra
	 * beállítás alapján frissen számolva — lásd az osztály-docblockot.
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
	 * Elmenti az új nap/óra beállítást, és — CSAK ha ténylegesen változott
	 * a korábbihoz képest — újraütemezi az eseményt is (`reschedule()`).
	 * Ha nem hívnánk ezt a védelmet, minden egyes Beállítások-mentés
	 * (akár egy teljesen más mezőé is, ha ugyanazon az űrlapon lenne)
	 * feleslegesen "kitolná" a legközelebbi futást.
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
