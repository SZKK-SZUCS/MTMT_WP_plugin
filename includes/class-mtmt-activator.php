<?php
/**
 * Aktiváláskori tábla-migráció.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Activator {

	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$publications_table = $wpdb->prefix . 'mtmt_publications';
		$profiles_table     = $wpdb->prefix . 'mtmt_query_profiles';

		$sql_publications = "CREATE TABLE {$publications_table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mtid BIGINT UNSIGNED NOT NULL,
  title TEXT NULL,
  authors_text TEXT NULL,
  authors_raw LONGTEXT NULL,
  pub_type VARCHAR(120) NULL,
  pub_category VARCHAR(120) NULL,
  pub_character VARCHAR(60) NULL,
  language VARCHAR(40) NULL,
  source_title TEXT NULL,
  volume VARCHAR(40) NULL,
  issue VARCHAR(40) NULL,
  page_range VARCHAR(60) NULL,
  published_year SMALLINT NULL,
  doi VARCHAR(255) NULL,
  issn VARCHAR(20) NULL,
  sjr_quartile VARCHAR(8) NULL,
  norway_level VARCHAR(8) NULL,
  external_ids LONGTEXT NULL,
  other_url TEXT NULL,
  funding_text TEXT NULL,
  mtmt_state VARCHAR(60) NULL,
  raw_json LONGTEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  thumbnail_id BIGINT UNSIGNED NULL,
  funding_override TEXT NULL,
  project_ids TEXT NULL,
  project_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  query_profile_id BIGINT UNSIGNED NULL,
  first_seen_at DATETIME NULL,
  last_synced_at DATETIME NULL,
  missing_since DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_mtid (mtid),
  KEY idx_status_year (status, published_year),
  KEY idx_year (published_year),
  KEY idx_profile (query_profile_id)
) {$charset_collate};";

		$sql_profiles = "CREATE TABLE {$profiles_table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(255) NULL,
  cond_json LONGTEXT NULL,
  default_group_term_id BIGINT UNSIGNED NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_run_at DATETIME NULL,
  last_max_mtid BIGINT UNSIGNED NULL,
  PRIMARY KEY  (id)
) {$charset_collate};";

		$sync_log_table = $wpdb->prefix . 'mtmt_sync_log';

		$sql_sync_log = "CREATE TABLE {$sync_log_table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  profile_id BIGINT UNSIGNED NULL,
  trigger_type VARCHAR(20) NOT NULL,
  started_at DATETIME NOT NULL,
  duration_s DECIMAL(8,2) NULL,
  inserted INT UNSIGNED NOT NULL DEFAULT 0,
  updated INT UNSIGNED NOT NULL DEFAULT 0,
  reverted_to_pending INT UNSIGNED NOT NULL DEFAULT 0,
  missing INT UNSIGNED NOT NULL DEFAULT 0,
  total_fetched INT UNSIGNED NOT NULL DEFAULT 0,
  has_errors TINYINT(1) NOT NULL DEFAULT 0,
  errors LONGTEXT NULL,
  PRIMARY KEY  (id),
  KEY idx_started (started_at),
  KEY idx_profile (profile_id)
) {$charset_collate};";

		$topic_areas_table = $wpdb->prefix . 'mtmt_topic_areas';

		$sql_topic_areas = "CREATE TABLE {$topic_areas_table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(255) NOT NULL,
  page_id BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  PRIMARY KEY  (id)
) {$charset_collate};";

		$pub_topic_area_table = $wpdb->prefix . 'mtmt_pub_topic_area';

		$sql_pub_topic_area = "CREATE TABLE {$pub_topic_area_table} (
  pub_id BIGINT UNSIGNED NOT NULL,
  topic_area_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY  (pub_id, topic_area_id),
  KEY idx_topic_area (topic_area_id)
) {$charset_collate};";

		dbDelta( $sql_publications );
		dbDelta( $sql_profiles );
		dbDelta( $sql_sync_log );
		dbDelta( $sql_topic_areas );
		dbDelta( $sql_pub_topic_area );

		// A dbDelta() nem jelez megbízhatóan hibát, ezért explicit ellenőrizzük,
		// hogy mind az 5 tábla ténylegesen létrejött-e, mielőtt a verzió-opciót
		// "kész"-re állítanánk — ha bármelyik hiányzik, az opció nem frissül,
		// hogy a legközelebbi oldalbetöltéskor újra megpróbáljuk.
		$required_tables = array(
			$publications_table,
			$profiles_table,
			$sync_log_table,
			$topic_areas_table,
			$pub_topic_area_table,
		);

		foreach ( $required_tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table !== $exists ) {
				return;
			}
		}

		self::normalize_project_ids( $publications_table );

		update_option( 'mtmt_db_version', MTMT_DB_VERSION );
	}

	/** Az eredeti (migráció előtti) project_ids értékek mentése — ld. normalize_project_ids(). */
	public const PROJECT_IDS_BACKUP_OPTION = 'mtmt_project_ids_backup_v4';

	/**
	 * DB v4: a `project_ids` oszlop egységes tárolási formára hozása
	 * (Mtmt_Project_Ids::normalize() — ", "-szel elválasztva, trimmelve,
	 * duplikátum nélkül), hogy a projektazonosítóra szűrés a korábban
	 * szabad szövegként beírt értékekre is működjön.
	 *
	 * Adatvesztés elleni garanciák (docs/decisions.md #102):
	 * - nincs csonkolás, csak elválasztó/szóköz-egységesítés és
	 *   (kis-/nagybetűre érzéketlen) ismétlődés-kiszűrés;
	 * - ha a normalizálás üres eredményt adna (pl. csak írásjel volt a
	 *   mezőben), a sor érintetlen marad;
	 * - minden ténylegesen módosított sor EREDETI értéke a
	 *   PROJECT_IDS_BACKUP_OPTION opcióba kerül (id => eredeti), nem
	 *   autoload-olva; egy korábbi mentést sosem ír felül, csak bővít;
	 * - idempotens: a már normalizált sorokat nem írja újra, így többszöri
	 *   (pl. újraaktiválás miatti) futás sem okoz változást.
	 *
	 * @param string $publications_table
	 */
	private static function normalize_project_ids( string $publications_table ): void {
		global $wpdb;

		if ( ! class_exists( 'Mtmt_Project_Ids' ) ) {
			return;
		}

		$rows = $wpdb->get_results( "SELECT id, project_ids FROM {$publications_table} WHERE project_ids IS NOT NULL AND project_ids <> ''", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- saját táblanév, nincs felhasználói input.

		$changes = array();
		foreach ( (array) $rows as $row ) {
			$original   = (string) $row['project_ids'];
			$normalized = Mtmt_Project_Ids::normalize( $original );
			if ( '' === $normalized || $normalized === $original ) {
				continue;
			}
			$changes[ (int) $row['id'] ] = array( $original, $normalized );
		}

		if ( empty( $changes ) ) {
			return;
		}

		// Előbb a mentés, csak utána az átírás — ha a mentés nem sikerül, nem írunk át semmit.
		$backup = get_option( self::PROJECT_IDS_BACKUP_OPTION, array() );
		if ( ! is_array( $backup ) ) {
			$backup = array();
		}
		foreach ( $changes as $id => $pair ) {
			if ( ! array_key_exists( $id, $backup ) ) {
				$backup[ $id ] = $pair[0];
			}
		}
		update_option( self::PROJECT_IDS_BACKUP_OPTION, $backup, false );
		$stored = get_option( self::PROJECT_IDS_BACKUP_OPTION, array() );
		if ( ! is_array( $stored ) || count( array_intersect_key( $stored, $changes ) ) !== count( $changes ) ) {
			return;
		}

		foreach ( $changes as $id => $pair ) {
			$wpdb->update(
				$publications_table,
				array( 'project_ids' => $pair[1] ),
				array(
					'id'          => $id,
					'project_ids' => $pair[0], // Csak ha közben senki nem írta át.
				)
			);
		}
	}
}
