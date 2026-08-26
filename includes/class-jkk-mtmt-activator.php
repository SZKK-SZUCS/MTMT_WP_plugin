<?php
/**
 * Aktiváláskori tábla-migráció.
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * A plugin két saját tábláját hozza létre/frissíti dbDelta-val.
 */
final class Jkk_Mtmt_Activator {

	/**
	 * Aktiváláskor lefutó migráció.
	 */
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$publications_table = $wpdb->prefix . 'jkk_mtmt_publications';
		$profiles_table     = $wpdb->prefix . 'jkk_mtmt_query_profiles';

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

		dbDelta( $sql_publications );
		dbDelta( $sql_profiles );

		update_option( 'jkk_mtmt_db_version', JKK_MTMT_DB_VERSION );
	}
}
