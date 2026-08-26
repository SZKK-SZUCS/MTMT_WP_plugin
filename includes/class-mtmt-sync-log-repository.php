<?php
/**
 * Futás-napló repository (CLAUDE.md §6, §14/5-6).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minden szinkron-futásról (cron/kézi/CLI) egy sor, profilonként — a
 * Mtmt_Sync::run_profile() eredményét tárolja el, adminban megtekinthetőn.
 */
final class Mtmt_Sync_Log_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @param wpdb $wpdb
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'mtmt_sync_log';
	}

	/**
	 * @param string $trigger_type 'cron'|'manual'|'cli'
	 * @param array  $result       Mtmt_Sync::run_profile() kimenete.
	 * @return int Az új log-sor id-je.
	 */
	public function log_result( string $trigger_type, array $result ): int {
		$errors = $result['errors'] ?? array();

		$this->wpdb->insert(
			$this->table,
			array(
				'profile_id'          => $result['profile_id'] ?? null,
				'trigger_type'        => $trigger_type,
				'started_at'          => current_time( 'mysql' ),
				'duration_s'          => $result['duration_s'] ?? null,
				'inserted'            => (int) ( $result['inserted'] ?? 0 ),
				'updated'             => (int) ( $result['updated'] ?? 0 ),
				'reverted_to_pending' => (int) ( $result['reverted_to_pending'] ?? 0 ),
				'missing'             => (int) ( $result['missing'] ?? 0 ),
				'total_fetched'       => (int) ( $result['total_fetched'] ?? 0 ),
				'has_errors'          => empty( $errors ) ? 0 : 1,
				'errors'              => empty( $errors ) ? null : wp_json_encode( $errors ),
			)
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int $limit
	 * @return array[]
	 */
	public function get_recent( int $limit = 20 ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} ORDER BY started_at DESC, id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
