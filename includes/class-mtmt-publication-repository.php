<?php
/**
 * Publikáció-tábla upsert/diff repository.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Upsert `mtid` kulcson (CLAUDE.md §4.3): új mtid -> insert pending-be;
 * létező mtid -> csak az MTMT-forrású oszlopok frissülnek, a kézi/housekeeping
 * mezőkhöz (status, thumbnail_id, funding_override, project_*, query_profile_id
 * update-kor) nem nyúl.
 */
final class Mtmt_Publication_Repository {

	/**
	 * MTMT-forrású oszlopok — ezek íródnak update-kor. Bővebbet lásd
	 * Mtmt_Mapper::map_publication() kimenetét, ez pontosan azzal egyezik
	 * (mtid nélkül, azt külön kezeljük insertkor).
	 *
	 * @var string[]
	 */
	private const SOURCE_COLUMNS = array(
		'title',
		'authors_text',
		'authors_raw',
		'pub_type',
		'pub_category',
		'pub_character',
		'language',
		'source_title',
		'volume',
		'issue',
		'page_range',
		'published_year',
		'doi',
		'issn',
		'sjr_quartile',
		'norway_level',
		'external_ids',
		'other_url',
		'funding_text',
		'mtmt_state',
		'raw_json',
	);

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
		$this->table = $wpdb->prefix . 'mtmt_publications';
	}

	/**
	 * Ha egy már `approved`/`rejected` rekord MTMT-oldali tartalma ténylegesen
	 * változott, ide esik vissza — nincs csendes auto-apply (CLAUDE.md §4.3, §14/7).
	 *
	 * @var string[]
	 */
	private const REVERT_ON_CHANGE_STATUSES = array( 'approved', 'rejected' );

	/**
	 * @param array    $mapped_row       Mtmt_Mapper::map_publication() kimenete.
	 * @param int|null $query_profile_id A futó szinkron profilja (csak insertkor íródik).
	 * @return array{id:int,inserted:bool,content_changed:bool,reverted_to_pending:bool}
	 */
	public function upsert( array $mapped_row, ?int $query_profile_id ): array {
		$mtid = absint( $mapped_row['mtid'] ?? 0 );
		$now  = current_time( 'mysql' );

		$select_columns = array_merge( array( 'id', 'status' ), self::SOURCE_COLUMNS );
		$existing       = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT ' . implode( ', ', $select_columns ) . " FROM {$this->table} WHERE mtid = %d",
				$mtid
			),
			ARRAY_A
		);

		$source_data = array();
		foreach ( self::SOURCE_COLUMNS as $column ) {
			$source_data[ $column ] = $mapped_row[ $column ] ?? null;
		}

		if ( $existing ) {
			$content_changed = $this->content_changed( $source_data, $existing );

			$data                   = $source_data;
			$data['last_synced_at'] = $now;
			$data['missing_since']  = null; // Ha korábban hiányzott, most visszakerült.

			$reverted_to_pending = false;
			if ( $content_changed && in_array( $existing['status'], self::REVERT_ON_CHANGE_STATUSES, true ) ) {
				$data['status']       = 'pending';
				$reverted_to_pending = true;
			}

			$this->wpdb->update( $this->table, $data, array( 'id' => (int) $existing['id'] ) );

			return array(
				'id'                  => (int) $existing['id'],
				'inserted'            => false,
				'content_changed'     => $content_changed,
				'reverted_to_pending' => $reverted_to_pending,
			);
		}

		$data                     = $source_data;
		$data['mtid']             = $mtid;
		$data['status']           = 'pending';
		$data['query_profile_id'] = $query_profile_id;
		$data['first_seen_at']    = $now;
		$data['last_synced_at']   = $now;

		$this->wpdb->insert( $this->table, $data );

		return array(
			'id'                  => (int) $this->wpdb->insert_id,
			'inserted'            => true,
			'content_changed'     => true,
			'reverted_to_pending' => false,
		);
	}

	/**
	 * A `raw_json`-t szándékosan kihagyja: abban admin-időbélyegek
	 * (`lastRefresh`, `lastModified`) akkor is változnak, ha a tényleges
	 * tartalom nem — lásd docs/decisions.md #19.
	 *
	 * @param array $new_values   Az új, mapper-kimenetből épített SOURCE_COLUMNS értékek.
	 * @param array $existing_row A jelenleg tárolt sor (ugyanazokkal az oszlopokkal, + id/status).
	 * @return bool
	 */
	private function content_changed( array $new_values, array $existing_row ): bool {
		foreach ( self::SOURCE_COLUMNS as $column ) {
			if ( 'raw_json' === $column ) {
				continue;
			}
			if ( (string) ( $new_values[ $column ] ?? '' ) !== (string) ( $existing_row[ $column ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Egy profilhoz jelenleg (nem hiányzóként jelölt) tartozó mtid-k — a diffhez.
	 *
	 * @param int $query_profile_id
	 * @return int[]
	 */
	public function get_active_mtids_for_profile( int $query_profile_id ): array {
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT mtid FROM {$this->table} WHERE query_profile_id = %d AND missing_since IS NULL",
				$query_profile_id
			)
		);

		return array_map( 'intval', $rows );
	}

	/**
	 * A futásban nem látott mtid-eket "missing_since"-re állítja (nem töröl).
	 *
	 * @param int[] $mtids_not_seen
	 * @return int Érintett sorok száma.
	 */
	public function mark_missing( array $mtids_not_seen ): int {
		$mtids_not_seen = array_values( array_unique( array_map( 'absint', $mtids_not_seen ) ) );

		if ( empty( $mtids_not_seen ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $mtids_not_seen ), '%d' ) );
		$sql          = "UPDATE {$this->table} SET missing_since = %s WHERE missing_since IS NULL AND mtid IN ({$placeholders})";
		$params       = array_merge( array( current_time( 'mysql' ) ), $mtids_not_seen );

		return (int) $this->wpdb->query( $this->wpdb->prepare( $sql, $params ) );
	}
}
