<?php
/**
 * Admin oldal: moderációs lista + szerkesztő/gazdagító űrlap (CLAUDE.md §8).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ez a top-level "MTMT" menü — `mtmt_moderate` capabilityhez kötve, mert ezt
 * használják nap mint nap a moderátorok (nem `manage_options`, mint a Profilok/
 * Beállítások almenük). A projektazonosító-ellenőrzés (checkbox) `mtmt_classify`-hoz
 * kötött külön (CLAUDE.md §8.3) — a form megjelenik moderate-only usernek is,
 * de az ellenőrzött-pipát nem tudja állítani.
 */
final class Mtmt_Publications_Page {

	const PAGE_SLUG = 'mtmt';

	private const NONCE_ACTION_ENRICH = 'mtmt_enrich_action';

	/**
	 * @var Mtmt_Publication_Repository
	 */
	private $repository;

	/**
	 * @var Mtmt_Query_Profile_Repository
	 */
	private $profile_repo;

	/**
	 * @param Mtmt_Publication_Repository       $repository
	 * @param Mtmt_Query_Profile_Repository     $profile_repo
	 */
	public function __construct( Mtmt_Publication_Repository $repository, Mtmt_Query_Profile_Repository $profile_repo ) {
		$this->repository   = $repository;
		$this->profile_repo = $profile_repo;
	}

	/**
	 * `admin_menu`-ből hívva.
	 */
	public function add_menu_page(): void {
		$pending    = $this->repository->count_by_status()['pending'] ?? 0;
		$menu_title = __( 'MTMT', 'mtmt-sync' );

		if ( $pending > 0 ) {
			$menu_title .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
				(int) $pending
			);
		}

		add_menu_page(
			__( 'MTMT Publikációk', 'mtmt-sync' ),
			$menu_title,
			Mtmt_Capabilities::MODERATE,
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-media-document',
			30
		);
	}

	/**
	 * `admin_init`-ből hívva — MINDEN mutáló műveletet itt kell elintézni,
	 * MIELŐTT a page callback (render) lefutna, mert addigra a fejlécek már
	 * elmentek és wp_safe_redirect() már nem működne.
	 */
	public function maybe_handle_request(): void {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( Mtmt_Capabilities::MODERATE ) ) {
			return;
		}

		if ( isset( $_REQUEST['mtmt_enrich_submit'] ) ) {
			$this->handle_save_enrichment();
			return;
		}

		$id_param = $_REQUEST['id'] ?? null;

		if ( is_array( $id_param ) ) {
			$this->handle_bulk_action();
			return;
		}

		$action = isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action']
			? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
			: '';

		if ( in_array( $action, array( 'approve', 'reject', 'undo_reject' ), true ) && $id_param ) {
			$this->handle_single_row_action( $action );
		}
	}

	/**
	 * Az oldal renderelése (csak megjelenítés — a mutáció addigra megtörtént).
	 */
	public function render(): void {
		if ( ! current_user_can( Mtmt_Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez az oldalhoz.', 'mtmt-sync' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'edit' === $action ) {
			$this->render_edit_view();
			return;
		}

		$this->render_list_view();
	}

	/**
	 * @param string $action 'approve'|'reject'|'undo_reject'
	 */
	private function handle_single_row_action( string $action ): void {
		$id = absint( $_GET['id'] );
		if ( ! $id ) {
			return;
		}

		check_admin_referer( 'mtmt_row_action_' . $action . '_' . $id );

		$status_map = array(
			'approve'     => 'approved',
			'reject'      => 'rejected',
			'undo_reject' => 'pending',
		);

		$this->repository->set_status( $id, $status_map[ $action ], get_current_user_id() );

		wp_safe_redirect(
			add_query_arg(
				'mtmt_notice',
				'status_updated',
				remove_query_arg( array( 'action', 'id', '_wpnonce' ) )
			)
		);
		exit;
	}

	/**
	 * A WP_List_Table saját tömeges-művelet mezőit (action/action2 + id[]) dolgozza fel.
	 */
	private function handle_bulk_action(): void {
		$action = '';
		if ( ! empty( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( ! empty( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}

		if ( ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-publications' );

		$ids = isset( $_REQUEST['id'] ) && is_array( $_REQUEST['id'] )
			? array_map( 'absint', wp_unslash( $_REQUEST['id'] ) )
			: array();

		if ( empty( $ids ) ) {
			return;
		}

		$status = 'approve' === $action ? 'approved' : 'rejected';
		$this->repository->bulk_set_status( $ids, $status, get_current_user_id() );

		wp_safe_redirect(
			add_query_arg(
				'mtmt_notice',
				'bulk_updated',
				remove_query_arg( array( 'action', 'action2', 'id', '_wpnonce', '_wp_http_referer' ) )
			)
		);
		exit;
	}

	/**
	 * A gazdagító űrlap mentése.
	 */
	private function handle_save_enrichment(): void {
		check_admin_referer( self::NONCE_ACTION_ENRICH );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			return;
		}

		$fields = array(
			'thumbnail_id'      => isset( $_POST['thumbnail_id'] ) ? absint( $_POST['thumbnail_id'] ) : 0,
			'funding_override'  => isset( $_POST['funding_override'] ) ? sanitize_textarea_field( wp_unslash( $_POST['funding_override'] ) ) : '',
			'project_ids'       => isset( $_POST['project_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['project_ids'] ) ) : '',
		);

		// A projektazonosító ELLENŐRZÉSE külön capability (CLAUDE.md §8.3) —
		// moderate-only felhasználó a szöveget mentheti, a pipát nem.
		if ( current_user_can( Mtmt_Capabilities::CLASSIFY ) ) {
			$fields['project_verified'] = ! empty( $_POST['project_verified'] );
		}

		if ( get_option( 'mtmt_enable_featured' ) ) {
			$fields['is_featured'] = ! empty( $_POST['is_featured'] );
		}

		$this->repository->save_enrichment( $id, $fields, get_current_user_id() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'action'      => 'edit',
					'id'          => $id,
					'mtmt_notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * A lista nézet.
	 */
	private function render_list_view(): void {
		$years    = $this->repository->get_distinct_years();
		$profiles = array_map(
			static function ( $profile ) {
				return array(
					'id'    => $profile['id'],
					'label' => $profile['label'],
				);
			},
			$this->profile_repo->get_all()
		);

		$list_table = new Mtmt_List_Table( $this->repository, self::PAGE_SLUG, $years, $profiles );
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MTMT Publikációk', 'mtmt-sync' ); ?></h1>
			<?php $this->render_notice(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * A szerkesztő/gazdagító nézet.
	 */
	private function render_edit_view(): void {
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$item = $id ? $this->repository->find( $id ) : null;

		if ( ! $item ) {
			echo '<div class="wrap"><p>' . esc_html__( 'A publikáció nem található.', 'mtmt-sync' ) . '</p></div>';
			return;
		}

		wp_enqueue_media();

		$can_classify      = current_user_can( Mtmt_Capabilities::CLASSIFY );
		$featured_enabled  = (bool) get_option( 'mtmt_enable_featured' );
		$back_url          = remove_query_arg( array( 'action', 'id' ) );
		$status_labels     = array(
			'pending'  => __( 'Függőben', 'mtmt-sync' ),
			'approved' => __( 'Jóváhagyva', 'mtmt-sync' ),
			'rejected' => __( 'Elutasítva', 'mtmt-sync' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Publikáció gazdagítása', 'mtmt-sync' ); ?></h1>
			<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Vissza a listához', 'mtmt-sync' ); ?></a></p>
			<?php $this->render_notice(); ?>

			<h2><?php echo esc_html( $item['title'] ? $item['title'] : __( '(cím nélkül)', 'mtmt-sync' ) ); ?></h2>
			<p class="description"><?php echo esc_html( (string) $item['authors_text'] ); ?></p>

			<p>
				<?php
				printf(
					/* translators: %s: státusz */
					esc_html__( 'Jelenlegi státusz: %s', 'mtmt-sync' ),
					'<strong>' . esc_html( $status_labels[ $item['status'] ] ?? $item['status'] ) . '</strong>'
				);
				?>
				&nbsp;
				<?php if ( 'approved' !== $item['status'] ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'approve', 'id' => $id ), admin_url( 'admin.php' ) ), 'mtmt_row_action_approve_' . $id ) ); ?>"><?php esc_html_e( 'Jóváhagyás', 'mtmt-sync' ); ?></a>
				<?php endif; ?>
				<?php if ( 'rejected' !== $item['status'] ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'reject', 'id' => $id ), admin_url( 'admin.php' ) ), 'mtmt_row_action_reject_' . $id ) ); ?>"><?php esc_html_e( 'Elutasítás', 'mtmt-sync' ); ?></a>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'undo_reject', 'id' => $id ), admin_url( 'admin.php' ) ), 'mtmt_row_action_undo_reject_' . $id ) ); ?>"><?php esc_html_e( 'Elutasítás visszavonása', 'mtmt-sync' ); ?></a>
				<?php endif; ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION_ENRICH ); ?>
				<input type="hidden" name="mtmt_enrich_submit" value="1">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Indexkép', 'mtmt-sync' ); ?></th>
						<td>
							<div id="mtmt-thumbnail-preview">
								<?php if ( ! empty( $item['thumbnail_id'] ) ) : ?>
									<?php echo wp_get_attachment_image( (int) $item['thumbnail_id'], 'medium' ); ?>
								<?php endif; ?>
							</div>
							<input type="hidden" name="thumbnail_id" id="mtmt-thumbnail-id" value="<?php echo esc_attr( (string) ( $item['thumbnail_id'] ?? '' ) ); ?>">
							<p>
								<button type="button" class="button" id="mtmt-select-thumbnail"><?php esc_html_e( 'Kép kiválasztása', 'mtmt-sync' ); ?></button>
								<button type="button" class="button" id="mtmt-remove-thumbnail"><?php esc_html_e( 'Eltávolítás', 'mtmt-sync' ); ?></button>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="mtmt-funding"><?php esc_html_e( 'Támogatás felülbírálása', 'mtmt-sync' ); ?></label></th>
						<td>
							<textarea id="mtmt-funding" name="funding_override" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $item['funding_override'] ?? '' ) ); ?></textarea>
							<?php if ( ! empty( $item['funding_text'] ) ) : ?>
								<p class="description"><?php esc_html_e( 'MTMT-ből importált (nem szerkeszthető, csak felülírható):', 'mtmt-sync' ); ?> <?php echo esc_html( $item['funding_text'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="mtmt-project"><?php esc_html_e( 'Projektazonosító(k)', 'mtmt-sync' ); ?></label></th>
						<td>
							<input type="text" id="mtmt-project" name="project_ids" class="regular-text" value="<?php echo esc_attr( (string) ( $item['project_ids'] ?? '' ) ); ?>">
							<?php if ( $can_classify ) : ?>
								<label><input type="checkbox" name="project_verified" value="1" <?php checked( ! empty( $item['project_verified'] ) ); ?>> <?php esc_html_e( 'Ellenőrizve', 'mtmt-sync' ); ?></label>
							<?php elseif ( ! empty( $item['project_verified'] ) ) : ?>
								<span class="description">✓ <?php esc_html_e( 'Ellenőrizve', 'mtmt-sync' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $featured_enabled ) : ?>
						<tr>
							<th><?php esc_html_e( 'Kiemelt cikk', 'mtmt-sync' ); ?></th>
							<td><label><input type="checkbox" name="is_featured" value="1" <?php checked( ! empty( $item['is_featured'] ) ); ?>> <?php esc_html_e( 'Megjelölés kiemelt cikkként', 'mtmt-sync' ); ?></label></td>
						</tr>
					<?php endif; ?>
				</table>

				<?php submit_button( __( 'Mentés', 'mtmt-sync' ) ); ?>
			</form>
		</div>
		<script>
		( function() {
			var frame;
			var selectBtn = document.getElementById( 'mtmt-select-thumbnail' );
			var removeBtn = document.getElementById( 'mtmt-remove-thumbnail' );

			selectBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( frame ) {
					frame.open();
					return;
				}
				frame = wp.media( {
					title: <?php echo wp_json_encode( __( 'Indexkép kiválasztása', 'mtmt-sync' ) ); ?>,
					multiple: false,
					library: { type: 'image' }
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
					document.getElementById( 'mtmt-thumbnail-id' ).value = attachment.id;
					document.getElementById( 'mtmt-thumbnail-preview' ).innerHTML = '<img src="' + url + '" style="max-width:300px;height:auto;">';
				} );
				frame.open();
			} );

			removeBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				document.getElementById( 'mtmt-thumbnail-id' ).value = '';
				document.getElementById( 'mtmt-thumbnail-preview' ).innerHTML = '';
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Flash-üzenet a `mtmt_notice` query-argból.
	 */
	private function render_notice(): void {
		if ( empty( $_GET['mtmt_notice'] ) ) {
			return;
		}

		$messages = array(
			'status_updated' => __( 'Állapot frissítve.', 'mtmt-sync' ),
			'bulk_updated'   => __( 'Tömeges művelet végrehajtva.', 'mtmt-sync' ),
			'saved'          => __( 'Mentve.', 'mtmt-sync' ),
		);

		$key = sanitize_key( wp_unslash( $_GET['mtmt_notice'] ) );
		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
	}
}
