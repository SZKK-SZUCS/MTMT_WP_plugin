<?php
/**
 * Egységes belépési pont: fut a sync + naplózza + (cronnál) értesít.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * A cron, a kézi "Szinkron most" gomb és a WP-CLI `sync` parancs is EZT hívja,
 * nem közvetlenül a Mtmt_Sync-et — így minden futás naplózódik, és a
 * cron-eredetű futásokról (és csak azokról, lásd docs/decisions.md #24)
 * email is megy, ha volt új/frissült tétel.
 */
final class Mtmt_Sync_Runner {

	/**
	 * @param string   $trigger_type 'cron'|'manual'|'cli'
	 * @param int|null $profile_id   Ha megadva, csak ezt a profilt futtatja.
	 * @return array[] Mtmt_Sync::run_profile() eredmények listája.
	 */
	public static function run( string $trigger_type, ?int $profile_id = null ): array {
		global $wpdb;

		$sync = new Mtmt_Sync(
			new Mtmt_Api_Client(),
			new Mtmt_Publication_Repository( $wpdb ),
			new Mtmt_Query_Profile_Repository( $wpdb )
		);

		$results = null !== $profile_id
			? array( $sync->run_profile( $profile_id ) )
			: $sync->run_all();

		$log_repo = new Mtmt_Sync_Log_Repository( $wpdb );
		foreach ( $results as $result ) {
			$log_repo->log_result( $trigger_type, $result );
		}

		if ( 'cron' === $trigger_type ) {
			( new Mtmt_Notifier() )->notify_if_needed( $results );
		}

		return $results;
	}
}
