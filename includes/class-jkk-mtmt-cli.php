<?php
/**
 * WP-CLI parancsok: `wp jkk-mtmt sync`, `wp jkk-mtmt profile list|create`.
 *
 * Csak akkor töltődik be, ha WP-CLI fut (lásd bootstrap fájl).
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * `wp jkk-mtmt sync [--profile=<id>]`
 */
final class Jkk_Mtmt_Sync_Command {

	/**
	 * Lefuttatja a szinkront egy adott profilra, vagy — ha nincs --profile —
	 * az összes enabled profilra.
	 *
	 * ## OPTIONS
	 *
	 * [--profile=<id>]
	 * : Csak ezt a profilt futtatja.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jkk-mtmt sync
	 *     wp jkk-mtmt sync --profile=1
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$profile_id = ! empty( $assoc_args['profile'] ) ? (int) $assoc_args['profile'] : null;
		$results    = Jkk_Mtmt_Sync_Runner::run( 'cli', $profile_id );

		if ( empty( $results ) ) {
			WP_CLI::warning( 'Nincs enabled query profil. Hozz létre egyet: wp jkk-mtmt profile create --label=... --cond=...' );
			return;
		}

		foreach ( $results as $result ) {
			$this->print_result( $result );
		}
	}

	/**
	 * @param array $result
	 */
	private function print_result( array $result ): void {
		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::warning(
				sprintf(
					'Profil #%d hiba: %s',
					$result['profile_id'],
					implode( '; ', $result['errors'] )
				)
			);
			return;
		}

		WP_CLI::success(
			sprintf(
				'Profil #%d: %d új, %d frissített (ebből %d visszaesett pending-be tartalomváltozás miatt), %d hiányzóként jelölt, összesen %d rekord (%.2fs).',
				$result['profile_id'],
				$result['inserted'],
				$result['updated'],
				$result['reverted_to_pending'],
				$result['missing'],
				$result['total_fetched'],
				$result['duration_s']
			)
		);
	}
}

/**
 * `wp jkk-mtmt profile list|create`
 */
final class Jkk_Mtmt_Profile_Command {

	/**
	 * Query profilok listázása.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jkk-mtmt profile list
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function list( array $args, array $assoc_args ): void {
		global $wpdb;

		$repo  = new Jkk_Mtmt_Query_Profile_Repository( $wpdb );
		$items = $repo->get_all();

		if ( empty( $items ) ) {
			WP_CLI::log( 'Nincs egy query profil sem.' );
			return;
		}

		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = array(
				'id'            => $item['id'],
				'label'         => $item['label'],
				'cond'          => wp_json_encode( $item['cond_json'] ),
				'enabled'       => $item['enabled'] ? 'yes' : 'no',
				'last_run_at'   => $item['last_run_at'] ?: '-',
				'last_max_mtid' => $item['last_max_mtid'] ?: '-',
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'label', 'cond', 'enabled', 'last_run_at', 'last_max_mtid' ) );
	}

	/**
	 * Új query profil létrehozása.
	 *
	 * ## OPTIONS
	 *
	 * --label=<label>
	 * : A profil neve.
	 *
	 * --cond=<json>
	 * : A cond_json tömb, pl. '[{"field":"directInstitutes","op":"in","value":"19662"}]'
	 *
	 * [--doi-only]
	 * : Csak DOI azonosítóval rendelkező rekordokat húzzon be (a JKK profilon ez
	 * a rekordok kb. 48%-a — ld. docs/decisions.md #16).
	 *
	 * ## EXAMPLES
	 *
	 *     wp jkk-mtmt profile create --label="JKK" --cond='[{"field":"directInstitutes","op":"in","value":"19662"}]'
	 *     wp jkk-mtmt profile create --label="JKK (csak DOI)" --cond='[{"field":"directInstitutes","op":"in","value":"19662"}]' --doi-only
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function create( array $args, array $assoc_args ): void {
		$label = isset( $assoc_args['label'] ) ? (string) $assoc_args['label'] : '';
		$cond  = isset( $assoc_args['cond'] ) ? (string) $assoc_args['cond'] : '';

		if ( '' === $label || '' === $cond ) {
			WP_CLI::error( '--label és --cond kötelező.' );
			return;
		}

		$conditions = json_decode( $cond, true );
		if ( ! is_array( $conditions ) || ! Jkk_Mtmt_Query_Profile_Repository::is_valid_cond_array( $conditions ) ) {
			WP_CLI::error( 'A --cond érvénytelen. Formátum: [{"field":"...","op":"...","value":"..."}]' );
			return;
		}

		if ( ! empty( $assoc_args['doi-only'] ) ) {
			$conditions[] = Jkk_Mtmt_Query_Profile_Repository::doi_only_condition();
		}

		global $wpdb;
		$repo = new Jkk_Mtmt_Query_Profile_Repository( $wpdb );
		$id   = $repo->create( $label, $conditions );

		WP_CLI::success( sprintf( 'Profil létrehozva: #%d (%s)', $id, $label ) );
	}
}
