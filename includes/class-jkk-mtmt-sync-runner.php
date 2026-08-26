<?php
/**
 * Egységes belépési pont: fut a sync + naplózza + (cronnál) értesít.
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * A cron, a kézi "Szinkron most" gomb és a WP-CLI `sync` parancs is EZT hívja,
 * nem közvetlenül a Jkk_Mtmt_Sync-et — így minden futás naplózódik, és a
 * cron-eredetű futásokról (és csak azokról, lásd docs/decisions.md #24)
 * email is megy, ha volt új/frissült tétel.
 */
final class Jkk_Mtmt_Sync_Runner {

	/**
	 * @param string   $trigger_type 'cron'|'manual'|'cli'
	 * @param int|null $profile_id   Ha megadva, csak ezt a profilt futtatja.
	 * @return array[] Jkk_Mtmt_Sync::run_profile() eredmények listája.
	 */
	public static function run( string $trigger_type, ?int $profile_id = null ): array {
		global $wpdb;

		$sync = new Jkk_Mtmt_Sync(
			new Jkk_Mtmt_Api_Client(),
			new Jkk_Mtmt_Publication_Repository( $wpdb ),
			new Jkk_Mtmt_Query_Profile_Repository( $wpdb )
		);

		$results = null !== $profile_id
			? array( $sync->run_profile( $profile_id ) )
			: $sync->run_all();

		$log_repo = new Jkk_Mtmt_Sync_Log_Repository( $wpdb );
		foreach ( $results as $result ) {
			$log_repo->log_result( $trigger_type, $result );
		}

		if ( 'cron' === $trigger_type ) {
			( new Jkk_Mtmt_Notifier() )->notify_if_needed( $results );
		}

		return $results;
	}
}
