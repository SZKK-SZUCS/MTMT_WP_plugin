<?php
/**
 * "Szakmai terület" repository (CLAUDE.md §7, §14/1) — a régi "kutatócsoport"
 * fogalom átnevezve, opt-in funkció.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nem WP-taxonómia (a publikáció nem post-típus) — sima pivot tábla a
 * terület↔publikáció sokoldalú kapcsolathoz, ahogy a CLAUDE.md §7 is javasolja.
 * A terület↔aloldal párosítás egy WP oldal (page) `page_id`-jét tárolja.
 */
final class Mtmt_Topic_Area_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $areas_table;

	/**
	 * @var string
	 */
	private $pivot_table;

	/**
	 * @param wpdb $wpdb
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb        = $wpdb;
		$this->areas_table = $wpdb->prefix . 'mtmt_topic_areas';
		$this->pivot_table = $wpdb->prefix . 'mtmt_pub_topic_area';
	}

	/**
	 * @return array[]
	 */
	public function get_all(): array {
		$rows = $this->wpdb->get_results( "SELECT * FROM {$this->areas_table} ORDER BY label ASC", ARRAY_A );
		return $rows ?: array();
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->areas_table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * @param string   $label
	 * @param int|null $page_id A WP-aloldal (page) ID-je, amihez a terület tartozik.
	 * @return int Az új terület id-je.
	 */
	public function create( string $label, ?int $page_id ): int {
		$this->wpdb->insert(
			$this->areas_table,
			array(
				'label'      => $label,
				'page_id'    => $page_id ?: null,
				'created_at' => current_time( 'mysql' ),
			)
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int $id
	 */
	public function delete( int $id ): void {
		$this->wpdb->delete( $this->pivot_table, array( 'topic_area_id' => $id ) );
		$this->wpdb->delete( $this->areas_table, array( 'id' => $id ) );
		Mtmt_Widget_Cache::bump();
	}

	/**
	 * Egy publikációhoz jelenleg hozzárendelt terület-id-k.
	 *
	 * @param int $pub_id
	 * @return int[]
	 */
	public function get_area_ids_for_publication( int $pub_id ): array {
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare( "SELECT topic_area_id FROM {$this->pivot_table} WHERE pub_id = %d", $pub_id )
		);
		return array_map( 'intval', $rows );
	}

	/**
	 * Teljesen lecseréli egy publikáció terület-hozzárendeléseit (töröl,
	 * majd újra beszúrja a megadott listát).
	 *
	 * @param int   $pub_id
	 * @param int[] $area_ids
	 */
	public function set_areas_for_publication( int $pub_id, array $area_ids ): void {
		$area_ids = array_values( array_unique( array_map( 'absint', $area_ids ) ) );

		$this->wpdb->delete( $this->pivot_table, array( 'pub_id' => $pub_id ) );

		foreach ( $area_ids as $area_id ) {
			$this->wpdb->insert(
				$this->pivot_table,
				array(
					'pub_id'        => $pub_id,
					'topic_area_id' => $area_id,
				)
			);
		}

		Mtmt_Widget_Cache::bump(); // A terület-badge/szűrés a widgeten azonnal a friss hozzárendelést mutassa.
	}

	/**
	 * Egy adott területhez tartozó publikáció-id-k — a Fázis 5-ös "B"
	 * (terület-aloldal) widget scope-jához kell majd.
	 *
	 * @param int $area_id
	 * @return int[]
	 */
	public function get_publication_ids_for_area( int $area_id ): array {
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare( "SELECT pub_id FROM {$this->pivot_table} WHERE topic_area_id = %d", $area_id )
		);
		return array_map( 'intval', $rows );
	}

	/**
	 * Több publikációhoz egyszerre lekéri a hozzájuk rendelt terület-label-eket
	 * (a moderációs lista oszlopához — egy lekérdezés N helyett).
	 *
	 * @param int[] $pub_ids
	 * @return array<int,string[]> pub_id => [label, label, ...]
	 */
	public function get_labels_by_publication( array $pub_ids ): array {
		$pub_ids = array_values( array_unique( array_map( 'absint', $pub_ids ) ) );

		if ( empty( $pub_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $pub_ids ), '%d' ) );
		$sql          = "SELECT pt.pub_id, a.label FROM {$this->pivot_table} pt
			INNER JOIN {$this->areas_table} a ON a.id = pt.topic_area_id
			WHERE pt.pub_id IN ({$placeholders})
			ORDER BY a.label ASC";

		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $pub_ids ), ARRAY_A );

		$result = array();
		foreach ( (array) $rows as $row ) {
			$result[ (int) $row['pub_id'] ][] = $row['label'];
		}
		return $result;
	}
}
