<?php
/**
 * Ingest orchestrátor: profil -> fetch + map + upsert + diff.
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Egy profil (vagy az összes enabled profil) teljes szinkronja.
 *
 * Stratégia (CLAUDE.md §5.3, docs/decisions.md): teljes lekérés + mtid-diff.
 * `core;eq;true` és `published;eq;true` mindig kemény-kódolt (idéző-rekord
 * kizárás), `depth=1` mindig kemény-kódolt (authors/SJR enélkül hiányozna).
 */
final class Jkk_Mtmt_Sync {

	private const PAGE_SIZE = 100;

	/**
	 * @var Jkk_Mtmt_Api_Client
	 */
	private $client;

	/**
	 * @var Jkk_Mtmt_Publication_Repository
	 */
	private $publications;

	/**
	 * @var Jkk_Mtmt_Query_Profile_Repository
	 */
	private $profiles;

	/**
	 * @param Jkk_Mtmt_Api_Client                $client
	 * @param Jkk_Mtmt_Publication_Repository     $publications
	 * @param Jkk_Mtmt_Query_Profile_Repository   $profiles
	 */
	public function __construct(
		Jkk_Mtmt_Api_Client $client,
		Jkk_Mtmt_Publication_Repository $publications,
		Jkk_Mtmt_Query_Profile_Repository $profiles
	) {
		$this->client       = $client;
		$this->publications = $publications;
		$this->profiles     = $profiles;
	}

	/**
	 * @param int $profile_id
	 * @return array{profile_id:int,inserted:int,updated:int,missing:int,total_fetched:int,errors:string[],duration_s:float}
	 */
	public function run_profile( int $profile_id ): array {
		$started = microtime( true );
		$result  = array(
			'profile_id'          => $profile_id,
			'inserted'            => 0,
			'updated'             => 0,
			'reverted_to_pending' => 0,
			'missing'             => 0,
			'total_fetched'       => 0,
			'errors'              => array(),
			'duration_s'          => 0.0,
		);

		$profile = $this->profiles->find( $profile_id );
		if ( ! $profile ) {
			$result['errors'][] = sprintf(
				/* translators: %d: profil azonosító */
				__( 'Profil #%d nem található.', 'jkk-mtmt-publications' ),
				$profile_id
			);
			$result['duration_s'] = round( microtime( true ) - $started, 2 );
			return $result;
		}

		$conditions   = $profile['cond_json'];
		$conditions[] = array(
			'field' => 'core',
			'op'    => 'eq',
			'value' => 'true',
		);
		$conditions[] = array(
			'field' => 'published',
			'op'    => 'eq',
			'value' => 'true',
		);

		$seen_mtids = array();
		$max_mtid   = (int) ( $profile['last_max_mtid'] ?? 0 );

		$on_page = function ( array $records ) use ( &$seen_mtids, &$max_mtid, &$result, $profile_id ) {
			foreach ( $records as $raw ) {
				$mapped = Jkk_Mtmt_Mapper::map_publication( $raw );

				if ( ! $mapped['mtid'] ) {
					continue;
				}

				$upsert = $this->publications->upsert( $mapped, $profile_id );

				if ( $upsert['inserted'] ) {
					++$result['inserted'];
				} else {
					++$result['updated'];
				}

				if ( ! empty( $upsert['reverted_to_pending'] ) ) {
					++$result['reverted_to_pending'];
				}

				$seen_mtids[] = $mapped['mtid'];
				$max_mtid     = max( $max_mtid, $mapped['mtid'] );
				++$result['total_fetched'];
			}
		};

		$pagination_result = $this->client->paginate(
			'publication',
			$conditions,
			array(
				'size'      => self::PAGE_SIZE,
				'depth'     => 1,
				'sort'      => array( 'mtid,asc' ),
				'labelLang' => 'hun',
			),
			$on_page
		);

		if ( is_wp_error( $pagination_result ) ) {
			$result['errors'][]   = $pagination_result->get_error_message();
			$result['duration_s'] = round( microtime( true ) - $started, 2 );
			// Hibás/megszakadt lapozásnál NINCS missing-diff — lásd docs/decisions.md #11.
			return $result;
		}

		$active_mtids       = $this->publications->get_active_mtids_for_profile( $profile_id );
		$missing_mtids      = array_diff( $active_mtids, $seen_mtids );
		$result['missing']  = $this->publications->mark_missing( $missing_mtids );

		$this->profiles->update_run_stats( $profile_id, $max_mtid ?: null );

		$result['duration_s'] = round( microtime( true ) - $started, 2 );
		return $result;
	}

	/**
	 * @return array[] Jkk_Mtmt_Sync::run_profile() eredmények listája.
	 */
	public function run_all(): array {
		$results = array();
		foreach ( $this->profiles->get_enabled() as $profile ) {
			$results[] = $this->run_profile( (int) $profile['id'] );
		}
		return $results;
	}
}
