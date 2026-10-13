<?php
/**
 * Admin oldal: moderációs lista + szerkesztő/gazdagító űrlap (CLAUDE.md §8).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Publications_Page {

	const PAGE_SLUG = 'mtmt';

	private const NONCE_ACTION_ENRICH = 'mtmt_enrich_action';

	/** @var Mtmt_Publication_Repository */
	private $repository;

	/** @var Mtmt_Query_Profile_Repository */
	private $profile_repo;

	/** @var Mtmt_Topic_Area_Repository */
	private $topic_area_repo;

	public function __construct( Mtmt_Publication_Repository $repository, Mtmt_Query_Profile_Repository $profile_repo, Mtmt_Topic_Area_Repository $topic_area_repo ) {
		$this->repository      = $repository;
		$this->profile_repo    = $profile_repo;
		$this->topic_area_repo = $topic_area_repo;
	}

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
	 * `admin_init`-ből hívva — a redirect miatt a page callback előtt kell lefutnia.
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

		// A sor-művelet linkje már hordozza a lista nézet-állapotát (szűrők,
		// rendezés, oldalszám) — csak a művelet-specifikus kulcsokat vesszük ki,
		// így a jóváhagyás/elutasítás után is ugyanarra az oldalra térünk vissza.
		wp_safe_redirect(
			add_query_arg(
				'mtmt_notice',
				'status_updated',
				remove_query_arg( array( 'action', 'id', '_wpnonce', 'mtmt_notice' ) )
			)
		);
		exit;
	}

	private function handle_bulk_action(): void {
		$action = '';
		if ( ! empty( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( ! empty( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}

		$allowed_actions = array( 'approve', 'reject' );
		if ( get_option( 'mtmt_enable_featured' ) ) {
			$allowed_actions[] = 'feature';
			$allowed_actions[] = 'unfeature';
		}

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return;
		}

		check_admin_referer( 'bulk-publications' );

		$ids = isset( $_REQUEST['id'] ) && is_array( $_REQUEST['id'] )
			? array_map( 'absint', wp_unslash( $_REQUEST['id'] ) )
			: array();

		if ( empty( $ids ) ) {
			return;
		}

		if ( in_array( $action, array( 'feature', 'unfeature' ), true ) ) {
			$this->repository->bulk_set_featured( $ids, 'feature' === $action );
		} else {
			$status = 'approve' === $action ? 'approved' : 'rejected';
			$this->repository->bulk_set_status( $ids, $status, get_current_user_id() );
		}

		// A tömeges művelet GET-form-ja nem küldi vissza az oldalszámot; a
		// `_wp_http_referer` viszont a lista teljes URL-jét tartalmazza (szűrők
		// + rendezés + oldalszám), ezért oda térünk vissza.
		$sendback = wp_get_referer();
		if ( ! $sendback ) {
			$sendback = remove_query_arg( array( 'action', 'action2', 'id', '_wpnonce', '_wp_http_referer', 'filter_action' ) );
		}
		$sendback = remove_query_arg( array( 'action', 'action2', 'id', '_wpnonce', '_wp_http_referer', 'filter_action', 'mtmt_notice' ), $sendback );

		wp_safe_redirect( add_query_arg( 'mtmt_notice', 'bulk_updated', $sendback ) );
		exit;
	}

	private function handle_save_enrichment(): void {
		check_admin_referer( self::NONCE_ACTION_ENRICH );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			return;
		}

		$fields = array(
			'thumbnail_id'      => isset( $_POST['thumbnail_id'] ) ? absint( $_POST['thumbnail_id'] ) : 0,
			'funding_override'  => isset( $_POST['funding_override'] ) ? sanitize_textarea_field( wp_unslash( $_POST['funding_override'] ) ) : '',
			// A chip-mező egy rejtett inputba ír vesszővel elválasztott listát; a
			// repository Mtmt_Project_Ids::normalize()-zal egységesíti tárolás előtt.
			'project_ids'       => isset( $_POST['project_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['project_ids'] ) ) : '',
		);

		// Az "Ellenőrizve" pipa csak kötelező ellenőrzésnél létezik, és csak
		// classify-jogosultsággal állítható. Kikapcsolt ellenőrzésnél a korábban
		// rögzített érték érintetlen marad (nem küldjük a mezőt).
		if ( Mtmt_Project_Ids::verification_required() && current_user_can( Mtmt_Capabilities::CLASSIFY ) ) {
			$fields['project_verified'] = ! empty( $_POST['project_verified'] );
		}

		if ( get_option( 'mtmt_enable_featured' ) ) {
			$fields['is_featured'] = ! empty( $_POST['is_featured'] );
		}

		$this->repository->save_enrichment( $id, $fields, get_current_user_id() );

		// A terület-besorolás is classify-jogosultsághoz kötött.
		if ( get_option( 'mtmt_enable_topic_areas' ) && current_user_can( Mtmt_Capabilities::CLASSIFY ) ) {
			$area_ids = isset( $_POST['topic_areas'] ) && is_array( $_POST['topic_areas'] )
				? array_map( 'absint', wp_unslash( $_POST['topic_areas'] ) )
				: array();
			$this->topic_area_repo->set_areas_for_publication( $id, $area_ids );
		}

		wp_safe_redirect(
			add_query_arg(
				array_merge(
					Mtmt_List_Table::current_view_args(),
					array(
						'page'        => self::PAGE_SLUG,
						'action'      => 'edit',
						'id'          => $id,
						'mtmt_notice' => 'saved',
					)
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

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

		$list_table = new Mtmt_List_Table( $this->repository, $this->topic_area_repo, self::PAGE_SLUG, $years, $profiles );
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'MTMT Publikációk', 'mtmt-sync' ); ?></h1>
			<?php if ( '' !== Mtmt_List_Table::current_search() ) : ?>
				<span class="subtitle">
					<?php
					printf(
						/* translators: %s: keresett szöveg */
						esc_html__( 'Keresési találatok erre: %s', 'mtmt-sync' ),
						'<strong>' . esc_html( Mtmt_List_Table::current_search() ) . '</strong>'
					);
					?>
				</span>
			<?php endif; ?>
			<hr class="wp-header-end">
			<?php $this->render_notice(); ?>
			<p class="description"><?php esc_html_e( 'A kereső a címben, a szerzők között, a forrásban, a projektazonosítóban és a DOI-ban keres; számra az MTMT-azonosítóra is.', 'mtmt-sync' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php $list_table->search_box( __( 'Keresés', 'mtmt-sync' ), 'mtmt-publication-search' ); ?>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function render_edit_view(): void {
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$item = $id ? $this->repository->find( $id ) : null;

		if ( ! $item ) {
			echo '<div class="wrap"><p>' . esc_html__( 'A publikáció nem található.', 'mtmt-sync' ) . '</p></div>';
			return;
		}

		wp_enqueue_media();

		$can_classify      = current_user_can( Mtmt_Capabilities::CLASSIFY );
		$verify_required   = Mtmt_Project_Ids::verification_required();
		$featured_enabled  = (bool) get_option( 'mtmt_enable_featured' );
		$topic_areas_on    = (bool) get_option( 'mtmt_enable_topic_areas' );
		$all_areas         = $topic_areas_on ? $this->topic_area_repo->get_all() : array();
		$assigned_area_ids = $topic_areas_on ? $this->topic_area_repo->get_area_ids_for_publication( $id ) : array();
		$back_url          = remove_query_arg( array( 'action', 'id', 'mtmt_notice' ) );
		$known_project_ids = $this->repository->get_distinct_project_ids();
		$item_project_ids  = Mtmt_Project_Ids::parse( (string) ( $item['project_ids'] ?? '' ) );
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
					<a class="button button-primary" href="<?php echo esc_url( $this->moderation_action_url( 'approve', $id ) ); ?>"><?php esc_html_e( 'Jóváhagyás', 'mtmt-sync' ); ?></a>
				<?php endif; ?>
				<?php if ( 'rejected' !== $item['status'] ) : ?>
					<a class="button" href="<?php echo esc_url( $this->moderation_action_url( 'reject', $id ) ); ?>"><?php esc_html_e( 'Elutasítás', 'mtmt-sync' ); ?></a>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( $this->moderation_action_url( 'undo_reject', $id ) ); ?>"><?php esc_html_e( 'Elutasítás visszavonása', 'mtmt-sync' ); ?></a>
				<?php endif; ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION_ENRICH ); ?>
				<input type="hidden" name="mtmt_enrich_submit" value="1">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php foreach ( Mtmt_List_Table::current_view_args() as $view_key => $view_val ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $view_key ); ?>" value="<?php echo esc_attr( (string) $view_val ); ?>">
				<?php endforeach; ?>

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
							<p class="description"><?php esc_html_e( 'A publikáció mellett megjelenő kép a weboldalon. Ha nem választasz, a rendszer egy alapértelmezett képet készít a cím alapján.', 'mtmt-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="mtmt-funding"><?php esc_html_e( 'Támogatás', 'mtmt-sync' ); ?></label></th>
						<td>
							<textarea id="mtmt-funding" name="funding_override" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $item['funding_override'] ?? '' ) ); ?></textarea>
							<?php if ( ! empty( $item['funding_text'] ) ) : ?>
								<p class="description"><?php esc_html_e( 'MTMT-ből importált (nem szerkeszthető, csak felülírható):', 'mtmt-sync' ); ?> <?php echo esc_html( $item['funding_text'] ); ?></p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Milyen támogatás/pályázat állt a publikáció mögött (pl. a cikk köszönetnyilvánítása). Ha kitöltöd, "Támogatás:" sorként megjelenik a weboldalon a publikáció kártyáján (a widgetben kikapcsolható). Az MTMT ezt az adatot nem adja át, ezért kell kézzel megadni.', 'mtmt-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="mtmt-project"><?php esc_html_e( 'Projektazonosító(k)', 'mtmt-sync' ); ?></label></th>
						<td>
							<div class="mtmt-project-picker" id="mtmt-project-picker">
								<input type="hidden" name="project_ids" id="mtmt-project-value" value="<?php echo esc_attr( implode( Mtmt_Project_Ids::SEPARATOR, $item_project_ids ) ); ?>">
								<div class="mtmt-project-chips" id="mtmt-project-chips"></div>
								<input type="text" id="mtmt-project" class="regular-text" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="mtmt-project-suggestions" aria-autocomplete="list" placeholder="<?php echo esc_attr__( 'Kezdd el gépelni az azonosítót…', 'mtmt-sync' ); ?>">
								<ul class="mtmt-project-suggestions" id="mtmt-project-suggestions" role="listbox" hidden></ul>
							</div>
							<?php if ( $verify_required && $can_classify ) : ?>
								<label class="mtmt-project-verified"><input type="checkbox" name="project_verified" value="1" <?php checked( ! empty( $item['project_verified'] ) ); ?>> <?php esc_html_e( 'Ellenőrizve', 'mtmt-sync' ); ?></label>
							<?php elseif ( $verify_required ) : ?>
								<span class="description"><?php echo ! empty( $item['project_verified'] ) ? '✓ ' . esc_html__( 'Ellenőrizve', 'mtmt-sync' ) : esc_html__( 'Még nincs ellenőrizve — a weboldalon csak ellenőrzés után jelenik meg.', 'mtmt-sync' ); ?></span>
							<?php endif; ?>
							<?php if ( $verify_required && ! empty( $item['project_verified'] ) && ! empty( $item['verified_by'] ) ) : ?>
								<?php $verifier = get_userdata( (int) $item['verified_by'] ); ?>
								<p class="description">
									<?php
									printf(
										/* translators: 1: felhasználó neve, 2: dátum */
										esc_html__( 'Ellenőrizte: %1$s, %2$s', 'mtmt-sync' ),
										esc_html( $verifier ? $verifier->display_name : '#' . (int) $item['verified_by'] ),
										esc_html( (string) $item['verified_at'] )
									);
									?>
								</p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'A publikációhoz tartozó pályázati/projekt-azonosító(k), pl. "K 123456". Gépeléskor felajánlja a korábban már használt azonosítókat; ha még nincs ilyen, "Új azonosító hozzáadása" jelenik meg. Több azonosító is megadható. Az azonosítók megjelennek a weboldalon, és szűrni is lehet rájuk.', 'mtmt-sync' ); ?></p>
							<?php if ( $verify_required ) : ?>
								<p class="description"><?php esc_html_e( 'Az "Ellenőrizve" pipát a besorolási jogú kolléga teszi be, miután megnézte, hogy a publikáció valóban a megadott projekt(ek) eredménye (pl. szerepel benne a köszönetnyilvánítás). Rögzíti, ki és mikor ellenőrizte. A weboldalon csak az ellenőrzött azonosítók jelennek meg.', 'mtmt-sync' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $topic_areas_on ) : ?>
						<tr>
							<th><?php esc_html_e( 'Szakmai terület', 'mtmt-sync' ); ?></th>
							<td>
								<?php if ( ! $can_classify ) : ?>
									<?php
									$assigned_labels = array_map(
										static function ( $area ) {
											return $area['label'];
										},
										array_filter(
											$all_areas,
											static function ( $area ) use ( $assigned_area_ids ) {
												return in_array( (int) $area['id'], $assigned_area_ids, true );
											}
										)
									);
									?>
									<p class="description"><?php echo $assigned_labels ? esc_html( implode( ', ', $assigned_labels ) ) : esc_html__( 'Nincs terület kijelölve.', 'mtmt-sync' ); ?></p>
								<?php elseif ( empty( $all_areas ) ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: link a Területek oldalra */
											esc_html__( 'Még nincs egy szakmai terület sem beállítva. %s', 'mtmt-sync' ),
											'<a href="' . esc_url( admin_url( 'admin.php?page=mtmt-topic-areas' ) ) . '">' . esc_html__( 'Területek kezelése', 'mtmt-sync' ) . '</a>'
										);
										?>
									</p>
								<?php else : ?>
									<?php foreach ( $all_areas as $area ) : ?>
										<label style="display:block;">
											<input type="checkbox" name="topic_areas[]" value="<?php echo esc_attr( (string) $area['id'] ); ?>" <?php checked( in_array( (int) $area['id'], $assigned_area_ids, true ) ); ?>>
											<?php echo esc_html( $area['label'] ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Melyik szakmai terület aloldalán jelenjen meg. Több is választható.', 'mtmt-sync' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( $featured_enabled ) : ?>
						<tr>
							<th><?php esc_html_e( 'Kiemelt cikk', 'mtmt-sync' ); ?></th>
							<td>
								<label><input type="checkbox" name="is_featured" value="1" <?php checked( ! empty( $item['is_featured'] ) ); ?>> <?php esc_html_e( 'Megjelölés kiemelt cikkként', 'mtmt-sync' ); ?></label>
								<p class="description"><?php esc_html_e( 'A kiemelt cikkek jelennek meg a szakmai terület aloldalak widgetjén.', 'mtmt-sync' ); ?></p>
							</td>
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
		<style>
			.mtmt-project-picker { position: relative; max-width: 32em; }
			.mtmt-project-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
			.mtmt-project-chips:empty { display: none; }
			.mtmt-project-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 4px 2px 8px; border: 1px solid #c3c4c7; border-radius: 3px; background: #f6f7f7; font-size: 13px; line-height: 1.6; }
			.mtmt-project-chip button { border: 0; background: none; cursor: pointer; padding: 0 2px; color: #646970; font-size: 16px; line-height: 1; }
			.mtmt-project-chip button:hover { color: #d63638; }
			.mtmt-project-picker input[type="text"] { width: 100%; }
			.mtmt-project-suggestions { position: absolute; z-index: 1000; left: 0; right: 0; margin: 2px 0 0; padding: 0; list-style: none; max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid #8c8f94; border-radius: 4px; box-shadow: 0 3px 8px rgba(0,0,0,.12); }
			.mtmt-project-suggestions li { margin: 0; padding: 6px 10px; cursor: pointer; }
			.mtmt-project-suggestions li.is-active, .mtmt-project-suggestions li:hover { background: #2271b1; color: #fff; }
			.mtmt-project-suggestions li.is-new { border-top: 1px solid #dcdcde; font-weight: 600; }
			.mtmt-project-suggestions li.is-empty { color: #646970; cursor: default; background: none; }
			.mtmt-project-verified { display: inline-block; margin-top: 6px; }
		</style>
		<script>
		( function() {
			// Projektazonosító chip-mező autocomplete-tel (docs/decisions.md #101):
			// fókuszra / gépelésre felajánlja a korábban rögzített azonosítókat; ha a
			// begépelt szöveg pontosan még nem létezik, "Új azonosító hozzáadása".
			var known = <?php echo wp_json_encode( array_values( $known_project_ids ) ); ?>;
			var i18n = {
				addNew: <?php echo wp_json_encode( __( 'Új azonosító hozzáadása: %s', 'mtmt-sync' ) ); ?>,
				remove: <?php echo wp_json_encode( __( 'Eltávolítás', 'mtmt-sync' ) ); ?>,
				none: <?php echo wp_json_encode( __( 'Még nincs rögzített projektazonosító — gépeld be az elsőt.', 'mtmt-sync' ) ); ?>
			};

			var hidden = document.getElementById( 'mtmt-project-value' );
			var input = document.getElementById( 'mtmt-project' );
			var chipsEl = document.getElementById( 'mtmt-project-chips' );
			var list = document.getElementById( 'mtmt-project-suggestions' );
			if ( ! hidden || ! input || ! chipsEl || ! list ) {
				return;
			}

			var selected = hidden.value ? hidden.value.split( /\s*[,;]\s*/ ).filter( Boolean ) : [];
			var options = [];
			var activeIndex = -1;

			function norm( v ) {
				return String( v ).replace( /\s+/g, ' ' ).trim();
			}
			function lower( v ) {
				return norm( v ).toLocaleLowerCase();
			}
			function isSelected( v ) {
				var l = lower( v );
				return selected.some( function ( s ) { return lower( s ) === l; } );
			}

			function sync() {
				hidden.value = selected.join( ', ' );
				chipsEl.innerHTML = '';
				selected.forEach( function ( id, idx ) {
					var chip = document.createElement( 'span' );
					chip.className = 'mtmt-project-chip';
					chip.appendChild( document.createTextNode( id ) );
					var btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.setAttribute( 'aria-label', i18n.remove + ': ' + id );
					btn.textContent = '×';
					btn.addEventListener( 'click', function () {
						selected.splice( idx, 1 );
						sync();
						input.focus();
					} );
					chip.appendChild( btn );
					chipsEl.appendChild( chip );
				} );
			}

			function add( value ) {
				var v = norm( value ).replace( /[,;]/g, '' );
				if ( v && ! isSelected( v ) ) {
					// Ha a korábban rögzítettek között van (más kis-/nagybetűvel), azt az alakot használjuk.
					var existing = known.filter( function ( k ) { return lower( k ) === lower( v ); } )[ 0 ];
					selected.push( existing || v );
					if ( ! existing ) {
						known.push( v );
					}
					sync();
				}
				input.value = '';
				render();
			}

			function close() {
				list.hidden = true;
				input.setAttribute( 'aria-expanded', 'false' );
				activeIndex = -1;
			}

			function render() {
				var q = lower( input.value );
				options = known.filter( function ( k ) {
					return ! isSelected( k ) && ( '' === q || lower( k ).indexOf( q ) !== -1 );
				} ).map( function ( k ) {
					return { value: k, label: k, isNew: false };
				} );

				// "Új azonosító hozzáadása" csak akkor, ha a begépelt szövegre már
				// egyetlen korábbi azonosító sem illeszkedik — így nem keletkeznek
				// elgépelt "majdnem-duplikátumok". (Egy meglévő azonosító elejével
				// egyező új azonosító vesszővel zárva továbbra is felvehető.)
				var exact = known.some( function ( k ) { return lower( k ) === q; } );
				if ( '' !== q && ! options.length && ! exact && ! isSelected( input.value ) ) {
					options.push( { value: norm( input.value ), label: i18n.addNew.replace( '%s', '„' + norm( input.value ) + '”' ), isNew: true } );
				}

				list.innerHTML = '';
				if ( ! options.length ) {
					if ( '' === q && ! known.length ) {
						var empty = document.createElement( 'li' );
						empty.className = 'is-empty';
						empty.textContent = i18n.none;
						list.appendChild( empty );
						list.hidden = false;
						input.setAttribute( 'aria-expanded', 'true' );
						return;
					}
					close();
					return;
				}

				if ( activeIndex >= options.length ) {
					activeIndex = options.length - 1;
				}
				options.forEach( function ( opt, idx ) {
					var li = document.createElement( 'li' );
					li.setAttribute( 'role', 'option' );
					li.textContent = opt.label;
					if ( opt.isNew ) {
						li.className = 'is-new';
					}
					if ( idx === activeIndex ) {
						li.className += ' is-active';
						li.setAttribute( 'aria-selected', 'true' );
					}
					// mousedown: a blur előtt fusson le.
					li.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault();
						add( opt.value );
					} );
					list.appendChild( li );
				} );
				list.hidden = false;
				input.setAttribute( 'aria-expanded', 'true' );
			}

			input.addEventListener( 'focus', function () {
				activeIndex = -1;
				render();
			} );
			input.addEventListener( 'input', function () {
				// Gépelés közben az első javaslat ki van jelölve -> Enter azt veszi fel.
				activeIndex = norm( input.value ) ? 0 : -1;
				if ( /[,;]/.test( input.value ) ) {
					input.value.split( /[,;]/ ).forEach( function ( part, i, arr ) {
						if ( i < arr.length - 1 ) {
							add( part );
						} else {
							input.value = part;
						}
					} );
				}
				render();
			} );
			input.addEventListener( 'blur', function () {
				close();
			} );
			input.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
					e.preventDefault();
					if ( list.hidden ) {
						render();
					}
					if ( ! options.length ) {
						return;
					}
					activeIndex = 'ArrowDown' === e.key
						? ( activeIndex + 1 ) % options.length
						: ( activeIndex <= 0 ? options.length - 1 : activeIndex - 1 );
					render();
				} else if ( 'Enter' === e.key ) {
					// Enter ne küldje el az űrlapot, hanem vegye fel az azonosítót.
					if ( activeIndex >= 0 && options[ activeIndex ] ) {
						e.preventDefault();
						add( options[ activeIndex ].value );
					} else if ( norm( input.value ) ) {
						e.preventDefault();
						add( input.value );
					}
				} else if ( 'Escape' === e.key ) {
					close();
				} else if ( 'Backspace' === e.key && '' === input.value && selected.length ) {
					selected.pop();
					sync();
					render();
				}
			} );

			// Mentéskor a félbehagyott (be nem "Enter"-ezett) szöveg se vesszen el.
			input.form.addEventListener( 'submit', function () {
				if ( norm( input.value ) ) {
					add( input.value );
				}
				hidden.value = selected.join( ', ' );
			} );

			sync();
		} )();
		</script>
		<?php
	}

	/**
	 * Jóváhagyás/elutasítás/visszavonás link a szerkesztő nézetből — a lista
	 * nézet-állapotát (szűrők, rendezés, oldalszám) is beleszőve, hogy a
	 * művelet után a helyes oldalra térjünk vissza.
	 *
	 * @param string $action 'approve'|'reject'|'undo_reject'
	 * @param int    $id
	 * @return string
	 */
	private function moderation_action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array_merge(
					Mtmt_List_Table::current_view_args(),
					array(
						'page'   => self::PAGE_SLUG,
						'action' => $action,
						'id'     => $id,
					)
				),
				admin_url( 'admin.php' )
			),
			'mtmt_row_action_' . $action . '_' . $id
		);
	}

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
