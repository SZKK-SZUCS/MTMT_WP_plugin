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
	 * @return array{id:int,inserted:bool,content_changed:bool,reverted_to_pending:bool,error:?string}
	 *         `error` nem null, ha az INSERT/UPDATE meghiúsult — ilyenkor a hívónak
	 *         ezt kell néznie, nem az `inserted` értékét.
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

			$update_result = $this->wpdb->update( $this->table, $data, array( 'id' => (int) $existing['id'] ) );

			return array(
				'id'                  => (int) $existing['id'],
				'inserted'            => false,
				'content_changed'     => $content_changed,
				'reverted_to_pending' => $reverted_to_pending,
				'error'               => false === $update_result ? ( $this->wpdb->last_error ?: 'UPDATE failed.' ) : null,
			);
		}

		$data                     = $source_data;
		$data['mtid']             = $mtid;
		$data['status']           = 'pending';
		$data['query_profile_id'] = $query_profile_id;
		$data['first_seen_at']    = $now;
		$data['last_synced_at']   = $now;

		$insert_result = $this->wpdb->insert( $this->table, $data );

		if ( false === $insert_result ) {
			return array(
				'id'                  => 0,
				'inserted'            => false,
				'content_changed'     => false,
				'reverted_to_pending' => false,
				'error'               => $this->wpdb->last_error ?: 'INSERT failed.',
			);
		}

		return array(
			'id'                  => (int) $this->wpdb->insert_id,
			'inserted'            => true,
			'content_changed'     => true,
			'reverted_to_pending' => false,
			'error'               => null,
		);
	}

	/**
	 * A `raw_json`-t szándékosan kihagyja: abban admin-időbélyegek akkor is
	 * változnak, ha a tényleges tartalom nem.
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

	/**
	 * @var string[]
	 */
	private const VALID_STATUSES = array( 'pending', 'approved', 'rejected' );

	/**
	 * Engedélyezett rendezési oszlopok (get_list `orderby`). A tényleges
	 * ORDER BY kifejezést a lenti `order_by_sql()` építi belőlük — mindegyik
	 * kap egy `id` szerinti tie-breakert, hogy a lapozás determinisztikus
	 * legyen (azonos évű/című tételek ne cserélgessék a helyüket oldalak közt).
	 *
	 * @var string[]
	 */
	private const ALLOWED_ORDERBY = array( 'published_year', 'title', 'sjr_quartile', 'first_seen_at', 'last_synced_at' );

	/**
	 * Moderációs lista, szűrve/lapozva (CLAUDE.md §8.1).
	 *
	 * @param array $args status, year, profile_id, ids (előre kiválogatott
	 *                    id-lista, pl. terület-szűréshez), orderby, order,
	 *                    paged, per_page.
	 * @return array{items:array[],total:int}
	 */
	public function get_list( array $args = array() ): array {
		$args = array_merge(
			array(
				'status'        => '',
				'year'          => 0,
				'profile_id'    => 0,
				'ids'           => null,
				'search'        => '',
				'featured_only' => false,
				'orderby'       => 'published_year',
				'order'         => 'DESC',
				'paged'         => 1,
				'per_page'      => 20,
			),
			$args
		);

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['year'] ) {
			$where[]  = 'published_year = %d';
			$params[] = (int) $args['year'];
		}
		if ( $args['profile_id'] ) {
			$where[]  = 'query_profile_id = %d';
			$params[] = (int) $args['profile_id'];
		}
		if ( ! empty( $args['featured_only'] ) ) {
			$where[] = 'is_featured = 1';
		}
		if ( '' !== trim( (string) $args['search'] ) ) {
			// Cím / szerzők / forrás LIKE-egyezés — widget kereső mezőhöz (Fázis 5).
			$like     = '%' . $this->wpdb->esc_like( trim( (string) $args['search'] ) ) . '%';
			$where[]  = '(title LIKE %s OR authors_text LIKE %s OR source_title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( is_array( $args['ids'] ) ) {
			$ids = array_map( 'absint', $args['ids'] );
			if ( empty( $ids ) ) {
				// Üres szűrt id-lista (pl. egy területhez még nincs hozzárendelve
				// egy publikáció sem) -> szándékosan 0 találat, nem "nincs szűrés".
				return array(
					'items' => array(),
					'total' => 0,
				);
			}
			$where[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params  = array_merge( $params, $ids );
		}

		$orderby   = in_array( $args['orderby'], self::ALLOWED_ORDERBY, true ) ? $args['orderby'] : 'published_year';
		$order     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$order_by  = $this->order_by_sql( $orderby, $order );
		$where_sql = implode( ' AND ', $where );

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
		$total     = $params
			? (int) $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, $params ) )
			: (int) $this->wpdb->get_var( $count_sql );

		$list_sql    = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$items       = $this->wpdb->get_results( $this->wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * ORDER BY kifejezés egy engedélyezett `orderby` + irány párból. Minden
	 * ág `id`-tie-breakerrel zárul a determinisztikus lapozásért. Az SJR
	 * szerinti rendezésnél a besorolás nélküli tételek mindig a lista végére
	 * kerülnek (a D1<Q1<Q2<Q3<Q4 betűrend maga a legjobb→leggyengébb sorrend).
	 *
	 * @param string $orderby A self::ALLOWED_ORDERBY egyike (a hívó már validálta).
	 * @param string $order   'ASC' vagy 'DESC'.
	 * @return string
	 */
	private function order_by_sql( string $orderby, string $order ): string {
		switch ( $orderby ) {
			case 'title':
				return "title {$order}, id {$order}";
			case 'sjr_quartile':
				return "(sjr_quartile IS NULL OR sjr_quartile = '') ASC, sjr_quartile {$order}, published_year DESC, id DESC";
			case 'first_seen_at':
				return "first_seen_at {$order}, id {$order}";
			case 'last_synced_at':
				return "last_synced_at {$order}, id {$order}";
			case 'published_year':
			default:
				return "published_year {$order}, id {$order}";
		}
	}

	/**
	 * Elérhető évek a lista-szűrőhöz, csökkenő sorrendben.
	 *
	 * @return int[]
	 */
	public function get_distinct_years(): array {
		$rows = $this->wpdb->get_col(
			"SELECT DISTINCT published_year FROM {$this->table} WHERE published_year IS NOT NULL ORDER BY published_year DESC"
		);
		return array_map( 'intval', $rows );
	}

	/**
	 * Ugyanaz, mint get_distinct_years(), de a Fázis 5 widget-scope-ra szűkítve
	 * (státusz/profil/kiemelt/id-lista) — Mtmt_Widget_Data-nak kell, hogy ne
	 * kínáljon fel olyan év-fület, aminek a widget adott szűrésével 0 találata
	 * lenne. Szándékosan külön a get_list()-től (ami maga is tesztelt, élesben
	 * validált kód) — kis duplikáció a WHERE-építésben, cserébe nem kell
	 * hozzányúlni a már stabil metódushoz.
	 *
	 * @param array $args {
	 *     @type string $status        Alapértelmezetten 'approved' (a widgetek mindig ezt kérik).
	 *     @type int    $profile_id
	 *     @type bool   $featured_only
	 *     @type string $search        Cím/szerző/forrás LIKE-keresés — a keresés
	 *                                 utáni év-fülekhez (üres évek kihagyása).
	 *     @type int[]|null $ids
	 * }
	 * @return int[]
	 */
	public function get_distinct_years_filtered( array $args = array() ): array {
		$args = array_merge(
			array(
				'status'        => 'approved',
				'profile_id'    => 0,
				'featured_only' => false,
				'search'        => '',
				'ids'           => null,
			),
			$args
		);

		$where  = array( '1=1', 'published_year IS NOT NULL' );
		$params = array();

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['profile_id'] ) {
			$where[]  = 'query_profile_id = %d';
			$params[] = (int) $args['profile_id'];
		}
		if ( ! empty( $args['featured_only'] ) ) {
			$where[] = 'is_featured = 1';
		}
		if ( '' !== trim( (string) $args['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( trim( (string) $args['search'] ) ) . '%';
			$where[]  = '(title LIKE %s OR authors_text LIKE %s OR source_title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( is_array( $args['ids'] ) ) {
			$ids = array_map( 'absint', $args['ids'] );
			if ( empty( $ids ) ) {
				return array();
			}
			$where[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params  = array_merge( $params, $ids );
		}

		$sql  = "SELECT DISTINCT published_year FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY published_year DESC';
		$rows = $params ? $this->wpdb->get_col( $this->wpdb->prepare( $sql, $params ) ) : $this->wpdb->get_col( $sql );

		return array_map( 'intval', $rows );
	}

	/**
	 * @return array{pending:int,approved:int,rejected:int}
	 */
	public function count_by_status(): array {
		$rows   = $this->wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$this->table} GROUP BY status", ARRAY_A );
		$counts = array(
			'pending'  => 0,
			'approved' => 0,
			'rejected' => 0,
		);
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['cnt'];
			}
		}
		return $counts;
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Jóváhagyás/elutasítás/"elutasítás visszavonása" (utóbbi is ezt hívja,
	 * `$status = 'pending'`-del) — CLAUDE.md §8.1 sor-műveletei.
	 *
	 * @param int    $id
	 * @param string $status 'pending'|'approved'|'rejected'
	 * @param int    $user_id
	 * @return bool
	 */
	public function set_status( int $id, string $status, int $user_id ): bool {
		if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table,
			array(
				'status'       => $status,
				'moderated_by' => $user_id,
				'moderated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( false !== $result ) {
			Mtmt_Widget_Cache::bump(); // Jóváhagyás/elutasítás azonnal látszódjon a widgeten (docs/decisions.md #59).
		}

		return false !== $result;
	}

	/**
	 * Tömeges jóváhagyás/elutasítás.
	 *
	 * @param int[]  $ids
	 * @param string $status
	 * @param int    $user_id
	 * @return int Érintett sorok száma.
	 */
	public function bulk_set_status( array $ids, string $status, int $user_id ): int {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) || ! in_array( $status, self::VALID_STATUSES, true ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "UPDATE {$this->table} SET status = %s, moderated_by = %d, moderated_at = %s WHERE id IN ({$placeholders})";
		$params       = array_merge( array( $status, $user_id, current_time( 'mysql' ) ), $ids );

		$affected = (int) $this->wpdb->query( $this->wpdb->prepare( $sql, $params ) );
		if ( $affected > 0 ) {
			Mtmt_Widget_Cache::bump();
		}
		return $affected;
	}

	/**
	 * Tömeges kiemelés / kiemelés visszavonása (CLAUDE.md §14/9, kézi mező).
	 *
	 * @param int[] $ids
	 * @param bool  $featured
	 * @return int Érintett sorok száma.
	 */
	public function bulk_set_featured( array $ids, bool $featured ): int {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "UPDATE {$this->table} SET is_featured = %d WHERE id IN ({$placeholders})";
		$params       = array_merge( array( $featured ? 1 : 0 ), $ids );

		$affected = (int) $this->wpdb->query( $this->wpdb->prepare( $sql, $params ) );
		if ( $affected > 0 ) {
			Mtmt_Widget_Cache::bump();
		}
		return $affected;
	}

	/**
	 * Kézi gazdagítás mentése (CLAUDE.md §8.2) — SOSEM indít MTMT-hívást, és
	 * a sync sosem írja felül ezeket a mezőket (lásd SOURCE_COLUMNS fent).
	 *
	 * @param int   $id
	 * @param array $fields thumbnail_id, funding_override, project_ids,
	 *                      project_verified, is_featured — csak a ténylegesen
	 *                      átadott kulcsok íródnak.
	 * @param int   $user_id A "Ellenőrizve" pipa esetén verified_by/at-hez.
	 * @return bool
	 */
	public function save_enrichment( int $id, array $fields, int $user_id ): bool {
		$data = array();

		if ( array_key_exists( 'thumbnail_id', $fields ) ) {
			$data['thumbnail_id'] = $fields['thumbnail_id'] ? absint( $fields['thumbnail_id'] ) : null;
		}
		if ( array_key_exists( 'funding_override', $fields ) ) {
			$data['funding_override'] = $fields['funding_override'];
		}
		if ( array_key_exists( 'project_ids', $fields ) ) {
			$data['project_ids'] = $fields['project_ids'];
		}
		if ( array_key_exists( 'is_featured', $fields ) ) {
			$data['is_featured'] = ! empty( $fields['is_featured'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'project_verified', $fields ) ) {
			$verified                 = ! empty( $fields['project_verified'] );
			$data['project_verified'] = $verified ? 1 : 0;
			$data['verified_by']      = $verified ? $user_id : null;
			$data['verified_at']      = $verified ? current_time( 'mysql' ) : null;
		}

		if ( empty( $data ) ) {
			return false;
		}

		$result = $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );

		if ( false !== $result ) {
			Mtmt_Widget_Cache::bump(); // pl. thumbnail/is_featured is befolyásolja, mit mutat a widget.
		}

		return false !== $result;
	}
}
