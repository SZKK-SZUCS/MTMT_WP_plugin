<?php
/**
 * Admin oldal: query profilok — "dobozos" scope-konfiguráció UI-ból.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings-szerű admin képernyő a wp_mtmt_query_profiles kezelésére.
 *
 * `manage_options`-hoz kötve, KÜLÖN a mtmt_moderate/mtmt_classify
 * capability-któl (docs/decisions.md #12) — a scope beállítása site-config,
 * nem napi moderációs feladat.
 */
final class Mtmt_Profiles_Page {

	private const CAPABILITY   = 'manage_options';
	private const NONCE_ACTION = 'mtmt_profile_action';
	private const PAGE_SLUG    = 'mtmt-profiles';

	/**
	 * @var Mtmt_Query_Profile_Repository
	 */
	private $profiles;

	/**
	 * @param Mtmt_Query_Profile_Repository $profiles
	 */
	public function __construct( Mtmt_Query_Profile_Repository $profiles ) {
		$this->profiles = $profiles;
	}

	/**
	 * `admin_menu`-ből hívva — a top-level "MTMT" (Mtmt_Publications_Page) alá,
	 * almenüként. `manage_options`-hoz kötve, ezért a `mtmt_moderate`-only
	 * felhasználók a top-level "MTMT" menüt látják, de ezt az almenüt nem.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			Mtmt_Publications_Page::PAGE_SLUG,
			__( 'MTMT — Query profilok', 'mtmt-sync' ),
			__( 'Profilok', 'mtmt-sync' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Az oldal renderelése + POST-kezelés.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez az oldalhoz.', 'mtmt-sync' ) );
		}

		$notice       = $this->handle_request();
		$profiles     = $this->profiles->get_all();
		$nonce_action = self::NONCE_ACTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MTMT Publikációk — Query profilok', 'mtmt-sync' ); ?></h1>
			<p><?php esc_html_e( 'A query profilok határozzák meg, mely MTMT-publikációkat húzza be a szinkron. Intézmény- vagy szerző-azonosító sehol nincs kódba írva — az itt, profilonként adható meg.', 'mtmt-sync' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Név', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Feltétel (cond)', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Engedélyezve', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Utolsó futás', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Művelet', 'mtmt-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $profiles ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'Még nincs egy profil sem.', 'mtmt-sync' ); ?></td>
						</tr>
					<?php endif; ?>
					<?php foreach ( $profiles as $profile ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $profile['id'] ); ?></td>
							<td><?php echo esc_html( (string) $profile['label'] ); ?></td>
							<td><code><?php echo esc_html( (string) wp_json_encode( $profile['cond_json'] ) ); ?></code></td>
							<td>
								<?php
								echo $profile['enabled']
									? esc_html__( 'igen', 'mtmt-sync' )
									: esc_html__( 'nem', 'mtmt-sync' );
								?>
							</td>
							<td><?php echo esc_html( $profile['last_run_at'] ? $profile['last_run_at'] : '—' ); ?></td>
							<td>
								<form method="post" style="display:inline">
									<?php wp_nonce_field( $nonce_action ); ?>
									<input type="hidden" name="mtmt_action" value="sync_now">
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $profile['id'] ); ?>">
									<button type="submit" class="button button-primary">
										<?php esc_html_e( 'Szinkron most', 'mtmt-sync' ); ?>
									</button>
								</form>
								<form method="post" style="display:inline">
									<?php wp_nonce_field( $nonce_action ); ?>
									<input type="hidden" name="mtmt_action" value="toggle">
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $profile['id'] ); ?>">
									<input type="hidden" name="enabled" value="<?php echo $profile['enabled'] ? '0' : '1'; ?>">
									<button type="submit" class="button">
										<?php
										echo $profile['enabled']
											? esc_html__( 'Letiltás', 'mtmt-sync' )
											: esc_html__( 'Engedélyezés', 'mtmt-sync' );
										?>
									</button>
								</form>
								<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Biztos törlöd?', 'mtmt-sync' ) ); ?>');">
									<?php wp_nonce_field( $nonce_action ); ?>
									<input type="hidden" name="mtmt_action" value="delete">
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $profile['id'] ); ?>">
									<button type="submit" class="button button-link-delete">
										<?php esc_html_e( 'Törlés', 'mtmt-sync' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Új profil', 'mtmt-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( $nonce_action ); ?>
				<input type="hidden" name="mtmt_action" value="create">
				<table class="form-table">
					<tr>
						<th><label for="mtmt-label"><?php esc_html_e( 'Profil neve', 'mtmt-sync' ); ?></label></th>
						<td><input type="text" id="mtmt-label" name="label" class="regular-text" required></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Scope típusa', 'mtmt-sync' ); ?></th>
						<td>
							<label><input type="radio" name="scope_type" value="institute" checked> <?php esc_html_e( 'Intézmény MTID', 'mtmt-sync' ); ?></label><br>
							<label><input type="radio" name="scope_type" value="authors"> <?php esc_html_e( 'Szerző MTID-lista (vesszővel elválasztva)', 'mtmt-sync' ); ?></label><br>
							<label><input type="radio" name="scope_type" value="advanced"> <?php esc_html_e( 'Haladó — nyers cond JSON', 'mtmt-sync' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="mtmt-value"><?php esc_html_e( 'Érték', 'mtmt-sync' ); ?></label></th>
						<td>
							<input type="text" id="mtmt-value" name="scope_value" class="regular-text" placeholder="pl. 19662">
							<p class="description">
								<?php esc_html_e( 'Intézmény esetén az MTMT intézmény-MTID (pl. https://m2.mtmt.hu/api/institute/19662 -> 19662). Szerzőknél MTID-lista vesszővel. Haladónál: [{"field":"...","op":"...","value":"..."}]', 'mtmt-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Szűrés', 'mtmt-sync' ); ?></th>
						<td>
							<label><input type="checkbox" name="doi_only" value="1"> <?php esc_html_e( 'Csak DOI azonosítóval rendelkező rekordok', 'mtmt-sync' ); ?></label>
							<p class="description">
								<?php esc_html_e( 'Tapasztalati érték: egy tesztintézményen a rekordok kb. felének volt DOI-ja — ez kb. felére csökkentheti a behúzott tételszámot.', 'mtmt-sync' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Profil létrehozása', 'mtmt-sync' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array{type:string,message:string}|null
	 */
	private function handle_request(): ?array {
		if ( empty( $_POST['mtmt_action'] ) ) {
			return null;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez a művelethez.', 'mtmt-sync' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['mtmt_action'] ) );

		switch ( $action ) {
			case 'create':
				return $this->handle_create();
			case 'toggle':
				return $this->handle_toggle();
			case 'delete':
				return $this->handle_delete();
			case 'sync_now':
				return $this->handle_sync_now();
			default:
				return null;
		}
	}

	/**
	 * @return array{type:string,message:string}
	 */
	private function handle_create(): array {
		$label      = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$scope_type = isset( $_POST['scope_type'] ) ? sanitize_key( wp_unslash( $_POST['scope_type'] ) ) : '';
		$value      = isset( $_POST['scope_value'] ) ? sanitize_text_field( wp_unslash( $_POST['scope_value'] ) ) : '';
		$doi_only   = ! empty( $_POST['doi_only'] );

		if ( '' === $label ) {
			return array(
				'type'    => 'error',
				'message' => __( 'A profil neve kötelező.', 'mtmt-sync' ),
			);
		}

		$conditions = $this->build_conditions( $scope_type, $value );
		if ( is_wp_error( $conditions ) ) {
			return array(
				'type'    => 'error',
				'message' => $conditions->get_error_message(),
			);
		}

		if ( $doi_only ) {
			$conditions[] = Mtmt_Query_Profile_Repository::doi_only_condition();
		}

		$this->profiles->create( $label, $conditions );

		return array(
			'type'    => 'success',
			'message' => __( 'Profil létrehozva.', 'mtmt-sync' ),
		);
	}

	/**
	 * A mód-választó UI kimenetét fordítja cond_json tömbbé.
	 *
	 * @param string $scope_type 'institute'|'authors'|'advanced'
	 * @param string $value
	 * @return array|WP_Error
	 */
	private function build_conditions( string $scope_type, string $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return new WP_Error( 'mtmt_empty_value', __( 'Add meg az értéket a választott típushoz.', 'mtmt-sync' ) );
		}

		switch ( $scope_type ) {
			case 'institute':
				$mtid = absint( $value );
				if ( ! $mtid ) {
					return new WP_Error( 'mtmt_bad_mtid', __( 'Az intézmény MTID-nek pozitív egész számnak kell lennie.', 'mtmt-sync' ) );
				}
				return array(
					array(
						'field' => 'directInstitutes',
						'op'    => 'in',
						'value' => (string) $mtid,
					),
				);

			case 'authors':
				$mtids = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $value ) ) ) );
				if ( empty( $mtids ) ) {
					return new WP_Error( 'mtmt_bad_authors', __( 'Adj meg legalább egy érvényes szerző-MTID-et, vesszővel elválasztva.', 'mtmt-sync' ) );
				}
				return array(
					array(
						'field' => 'authors',
						'op'    => 'in',
						'value' => implode( ',', $mtids ),
					),
				);

			case 'advanced':
				$decoded = json_decode( $value, true );
				if ( ! is_array( $decoded ) || ! Mtmt_Query_Profile_Repository::is_valid_cond_array( $decoded ) ) {
					return new WP_Error(
						'mtmt_bad_json',
						__( 'A haladó cond JSON érvénytelen. Formátum: [{"field":"...","op":"...","value":"..."}]', 'mtmt-sync' )
					);
				}
				return $decoded;

			default:
				return new WP_Error( 'mtmt_bad_scope', __( 'Ismeretlen scope-típus.', 'mtmt-sync' ) );
		}
	}

	/**
	 * @return array{type:string,message:string}
	 */
	private function handle_toggle(): array {
		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$enabled = ! empty( $_POST['enabled'] );

		$this->profiles->set_enabled( $id, $enabled );

		return array(
			'type'    => 'success',
			'message' => __( 'Állapot frissítve.', 'mtmt-sync' ),
		);
	}

	/**
	 * @return array{type:string,message:string}
	 */
	private function handle_delete(): array {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$this->profiles->delete( $id );

		return array(
			'type'    => 'success',
			'message' => __( 'Profil törölve.', 'mtmt-sync' ),
		);
	}

	/**
	 * Kézi "Szinkron most" — bármikor elindítható, nem csak cronból/CLI-ből
	 * (CLAUDE.md §14/6). Szinkron HTTP-requestben fut; nagyobb intézménynél
	 * ez timeoutba futhat — a `set_time_limit(0)` csak a PHP-oldali korlátot
	 * emeli, a webszerver/proxy saját timeoutját nem. Ha ez élesben problémát
	 * okoz, ez a pont válik async/AJAX-progress-szé (lásd docs/roadmap.md).
	 *
	 * @return array{type:string,message:string}
	 */
	private function handle_sync_now(): array {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Érvénytelen profil.', 'mtmt-sync' ),
			);
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$results = Mtmt_Sync_Runner::run( 'manual', $id );
		$result  = $results[0] ?? null;

		if ( ! $result ) {
			return array(
				'type'    => 'error',
				'message' => __( 'A szinkron nem futott le.', 'mtmt-sync' ),
			);
		}

		if ( ! empty( $result['errors'] ) ) {
			return array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %s: hibaüzenetek */
					__( 'Hiba a szinkron közben: %s', 'mtmt-sync' ),
					implode( '; ', $result['errors'] )
				),
			);
		}

		return array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: 1: uj, 2: frissitett, 3: visszaesett, 4: hianyzo */
				__( 'Szinkron lefutott: %1$d új, %2$d frissített (ebből %3$d visszaesett pending-be), %4$d hiányzóként jelölt.', 'mtmt-sync' ),
				$result['inserted'],
				$result['updated'],
				$result['reverted_to_pending'],
				$result['missing']
			),
		);
	}
}
