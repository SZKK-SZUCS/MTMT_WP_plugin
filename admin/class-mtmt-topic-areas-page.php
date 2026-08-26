<?php
/**
 * Admin oldal: "Szakmai terület" definíciók kezelése (CLAUDE.md §7, §14/1).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * `manage_options`-hoz kötve, mint a Profilok/Beállítások — a területek
 * DEFINIÁLÁSA (mi létezik, melyik aloldalhoz tartozik) site-config. Az, hogy
 * egy KONKRÉT publikáció melyik területhez tartozzon, a moderációs
 * szerkesztő űrlapon dől el, `mtmt_classify`-hoz kötve (CLAUDE.md §7:
 * "A besorolás kézi, a moderáció része, és külön jogosultsághoz kötött").
 */
final class Mtmt_Topic_Areas_Page {

	private const CAPABILITY   = 'manage_options';
	private const NONCE_ACTION = 'mtmt_topic_area_action';
	private const PAGE_SLUG    = 'mtmt-topic-areas';

	/**
	 * @var Mtmt_Topic_Area_Repository
	 */
	private $areas;

	/**
	 * @param Mtmt_Topic_Area_Repository $areas
	 */
	public function __construct( Mtmt_Topic_Area_Repository $areas ) {
		$this->areas = $areas;
	}

	/**
	 * `admin_menu`-ből hívva — a top-level "MTMT" alá, almenüként.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			Mtmt_Publications_Page::PAGE_SLUG,
			__( 'MTMT — Szakmai területek', 'mtmt-sync' ),
			__( 'Területek', 'mtmt-sync' ),
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
		$areas        = $this->areas->get_all();
		$nonce_action = self::NONCE_ACTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MTMT — Szakmai területek', 'mtmt-sync' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Minden "szakmai terület" egy WP-aloldalhoz van párosítva — a moderációs szerkesztő űrlapon (ha a funkció be van kapcsolva a Beállításokban) minden publikációhoz kiválasztható, melyik terület(ek)hez tartozik. Fázis 5-ben ez alapján fog szűrni a terület-aloldal widget.', 'mtmt-sync' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Terület neve', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Hozzárendelt aloldal', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Művelet', 'mtmt-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $areas ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'Még nincs egy szakmai terület sem.', 'mtmt-sync' ); ?></td>
						</tr>
					<?php endif; ?>
					<?php foreach ( $areas as $area ) : ?>
						<tr>
							<td><?php echo esc_html( $area['label'] ); ?></td>
							<td>
								<?php
								$page_id = (int) ( $area['page_id'] ?? 0 );
								if ( $page_id && get_post( $page_id ) ) {
									echo '<a href="' . esc_url( get_edit_post_link( $page_id ) ) . '">' . esc_html( get_the_title( $page_id ) ) . '</a>';
								} else {
									esc_html_e( '— nincs hozzárendelve —', 'mtmt-sync' );
								}
								?>
							</td>
							<td>
								<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Biztos törlöd? A hozzá rendelt publikációk elveszítik ezt a besorolást.', 'mtmt-sync' ) ); ?>');">
									<?php wp_nonce_field( $nonce_action ); ?>
									<input type="hidden" name="mtmt_action" value="delete">
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $area['id'] ); ?>">
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Törlés', 'mtmt-sync' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Új terület', 'mtmt-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( $nonce_action ); ?>
				<input type="hidden" name="mtmt_action" value="create">
				<table class="form-table">
					<tr>
						<th><label for="mtmt-area-label"><?php esc_html_e( 'Terület neve', 'mtmt-sync' ); ?></label></th>
						<td>
							<input type="text" id="mtmt-area-label" name="label" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Pl. "Autonóm járművek", "Robotika" — ez fog megjelenni a moderációs űrlapon és a widget szűrőjében.', 'mtmt-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="mtmt-area-page"><?php esc_html_e( 'Hozzárendelt aloldal', 'mtmt-sync' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'page_id',
									'id'                => 'mtmt-area-page',
									'show_option_none'  => __( '— nincs kiválasztva —', 'mtmt-sync' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Melyik WP-oldalon jelenjen meg ennek a területnek a widgetje. Utólag is módosítható (törlés + újra létrehozás).', 'mtmt-sync' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Terület létrehozása', 'mtmt-sync' ) ); ?>
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

		if ( 'create' === $action ) {
			$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
			$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;

			if ( '' === $label ) {
				return array(
					'type'    => 'error',
					'message' => __( 'A terület neve kötelező.', 'mtmt-sync' ),
				);
			}

			$this->areas->create( $label, $page_id ?: null );

			return array(
				'type'    => 'success',
				'message' => __( 'Terület létrehozva.', 'mtmt-sync' ),
			);
		}

		if ( 'delete' === $action ) {
			$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
			if ( $id ) {
				$this->areas->delete( $id );
			}

			return array(
				'type'    => 'success',
				'message' => __( 'Terület törölve.', 'mtmt-sync' ),
			);
		}

		return null;
	}
}
