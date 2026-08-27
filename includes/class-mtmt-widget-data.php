<?php
/**
 * Közös lekérdező réteg az "A"/"B" Elementor widget és az AJAX-fragment
 * frissítés között (Fázis 5).
 *
 * A widgetek KIZÁRÓLAG ezen keresztül, a saját táblából olvasnak — sosem
 * hívják élesben az MTMT-t (CLAUDE.md §3, §9.1). Az eredményt rövid (5 perces)
 * tranziens cache-eli a lekérdezés-aláírás szerint (CLAUDE.md §9.1
 * "Teljesítmény: object cache / transient a lekérdezett listára") — ilyen
 * rövid TTL mellett egy admin-moderálás legfeljebb 5 percen belül látszik meg
 * a frontenden, explicit cache-invalidálás nélkül is elfogadható egyszerűsítés.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Állapot nélküli (a repository-kat konstruktorban kapja) lekérdező.
 */
final class Mtmt_Widget_Data {

	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** @var Mtmt_Publication_Repository */
	private $repository;

	/** @var Mtmt_Topic_Area_Repository */
	private $topic_area_repo;

	/**
	 * @param Mtmt_Publication_Repository $repository
	 * @param Mtmt_Topic_Area_Repository  $topic_area_repo
	 */
	public function __construct( Mtmt_Publication_Repository $repository, Mtmt_Topic_Area_Repository $topic_area_repo ) {
		$this->repository      = $repository;
		$this->topic_area_repo = $topic_area_repo;
	}

	/**
	 * @param array $args {
	 *     @type int    $year          0 = minden év.
	 *     @type int    $area_id       0 = nincs terület-szűkítés ("B" widget "szakmai terület" módban, vagy "A" terület-szűrője).
	 *     @type int    $profile_id    0 = nincs profil-szűkítés ("B" widget "lekérdezési profil" módban, CLAUDE.md §14/10
	 *                                 "területre/profilra szűkítve" — a két mód kölcsönösen kizárja egymást, de a
	 *                                 repository szinten mindkettő egyszerre is AND-elhető, ha valaki mégis megadná).
	 *     @type bool   $featured_only Csak `is_featured=1` (a "B" widget mindig ezt adja).
	 *     @type string $search        Cím/szerző/forrás LIKE-keresés.
	 *     @type int    $paged
	 *     @type int    $per_page
	 * }
	 * @return array{items:array[],total:int}
	 */
	public function query( array $args ): array {
		$args = array_merge(
			array(
				'year'          => 0,
				'area_id'       => 0,
				'profile_id'    => 0,
				'featured_only' => false,
				'search'        => '',
				'paged'         => 1,
				'per_page'      => 20,
			),
			$args
		);

		$cache_key = 'mtmt_widget_q_' . md5( wp_json_encode( $args ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$repo_args = array(
			'status'        => 'approved', // A widget MINDIG csak jóváhagyott tételt mutat, ez nem kapcsolható ki.
			'year'          => (int) $args['year'],
			'featured_only' => (bool) $args['featured_only'],
			'search'        => (string) $args['search'],
			'paged'         => (int) $args['paged'],
			'per_page'      => (int) $args['per_page'],
			'orderby'       => 'published_year',
			'order'         => 'DESC',
		);

		if ( (int) $args['area_id'] > 0 ) {
			$repo_args['ids'] = $this->topic_area_repo->get_publication_ids_for_area( (int) $args['area_id'] );
		}
		if ( (int) $args['profile_id'] > 0 ) {
			$repo_args['profile_id'] = (int) $args['profile_id'];
		}

		$result = $this->repository->get_list( $repo_args );

		if ( get_option( 'mtmt_enable_topic_areas' ) && ! empty( $result['items'] ) ) {
			$pub_ids = wp_list_pluck( $result['items'], 'id' );
			$labels  = $this->topic_area_repo->get_labels_by_publication( $pub_ids );
			foreach ( $result['items'] as &$item ) {
				$item['topic_area_labels'] = $labels[ (int) $item['id'] ] ?? array();
			}
			unset( $item );
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Elérhető évek az év-fülekhez, UGYANAZZAL a scope-pal (profil/terület/kiemelt),
	 * mint amivel a widget ténylegesen lekérdez — így nem kínálunk fel olyan
	 * év-fület, aminek 0 találata lenne az adott widget-példány szűrésével.
	 *
	 * @param array $scope {
	 *     @type int  $area_id
	 *     @type int  $profile_id
	 *     @type bool $featured_only
	 * }
	 * @return int[]
	 */
	public function get_available_years( array $scope = array() ): array {
		$scope = array_merge(
			array(
				'area_id'       => 0,
				'profile_id'    => 0,
				'featured_only' => false,
			),
			$scope
		);

		$repo_args = array(
			'status'        => 'approved',
			'profile_id'    => (int) $scope['profile_id'],
			'featured_only' => (bool) $scope['featured_only'],
		);
		if ( (int) $scope['area_id'] > 0 ) {
			$repo_args['ids'] = $this->topic_area_repo->get_publication_ids_for_area( (int) $scope['area_id'] );
		}

		$cache_key = 'mtmt_widget_years_' . md5( wp_json_encode( $repo_args ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$years = $this->repository->get_distinct_years_filtered( $repo_args );
		set_transient( $cache_key, $years, self::CACHE_TTL );

		return $years;
	}
}
