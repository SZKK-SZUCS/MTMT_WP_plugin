<?php
/**
 * Query profil repository ("dobozos" scope-konfiguráció, docs/decisions.md #7, #12).
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * A wp_jkk_mtmt_query_profiles tábla minimál CRUD-ja. Sem intézmény-, sem
 * szerző-mtid nincs sehol PHP-kódba hardcode-olva — az kizárólag itt,
 * telepítésenként konfigurálva él (admin oldal és WP-CLI is ezt hívja).
 */
final class Jkk_Mtmt_Query_Profile_Repository {

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
		$this->table = $wpdb->prefix . 'jkk_mtmt_query_profiles';
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * @return array[]
	 */
	public function get_enabled(): array {
		$rows = $this->wpdb->get_results(
			"SELECT * FROM {$this->table} WHERE enabled = 1 ORDER BY id ASC",
			ARRAY_A
		);

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * @return array[]
	 */
	public function get_all(): array {
		$rows = $this->wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY id ASC", ARRAY_A );

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * @param string   $label
	 * @param array    $cond_json              [{field,op,value}, ...] — lásd CLAUDE.md §5.2.
	 * @param int|null $default_group_term_id
	 * @return int Az új profil id-je.
	 */
	public function create( string $label, array $cond_json, ?int $default_group_term_id = null ): int {
		$this->wpdb->insert(
			$this->table,
			array(
				'label'                 => $label,
				'cond_json'             => wp_json_encode( $cond_json ),
				'default_group_term_id' => $default_group_term_id,
				'enabled'               => 1,
			)
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int  $id
	 * @param bool $enabled
	 */
	public function set_enabled( int $id, bool $enabled ): void {
		$this->wpdb->update( $this->table, array( 'enabled' => $enabled ? 1 : 0 ), array( 'id' => $id ) );
	}

	/**
	 * @param int $id
	 */
	public function delete( int $id ): void {
		$this->wpdb->delete( $this->table, array( 'id' => $id ) );
	}

	/**
	 * @param int      $id
	 * @param int|null $last_max_mtid
	 */
	public function update_run_stats( int $id, ?int $last_max_mtid ): void {
		$this->wpdb->update(
			$this->table,
			array(
				'last_run_at'   => current_time( 'mysql' ),
				'last_max_mtid' => $last_max_mtid,
			),
			array( 'id' => $id )
		);
	}

	/**
	 * A "csak DOI-val rendelkező rekordok" profil-opció cond-ja (CLAUDE.md §14/2,
	 * VERIFIKÁLVA: docs/decisions.md #16). Admin oldal és WP-CLI is ezt hívja,
	 * hogy a mező-string egy helyen éljen.
	 *
	 * @return array{field:string,op:string,value:string}
	 */
	public static function doi_only_condition(): array {
		return array(
			'field' => 'identifiers.source.name',
			'op'    => 'eq',
			'value' => 'DOI',
		);
	}

	/**
	 * Egy cond tömb alak-ellenőrzése ([{field,op,value}, ...]) — admin oldal
	 * és WP-CLI is ezt hívja, hogy ne legyen duplikált validáció.
	 *
	 * @param array $conditions
	 * @return bool
	 */
	public static function is_valid_cond_array( array $conditions ): bool {
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || ! isset( $condition['field'], $condition['op'], $condition['value'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array $row
	 * @return array
	 */
	private function hydrate( array $row ): array {
		$decoded           = json_decode( $row['cond_json'] ?? '', true );
		$row['cond_json']  = is_array( $decoded ) ? $decoded : array();
		return $row;
	}
}
