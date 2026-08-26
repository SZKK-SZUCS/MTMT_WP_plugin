<?php
/**
 * Admin oldal: email-értesítés címzettjei + futás-napló (CLAUDE.md §6, §14/5).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * `manage_options`-hoz kötve, mint a profil-oldal — ez is site-config, nem
 * napi moderáció. Jövőbeli fázisok globális kapcsolói (szakmai terület
 * opt-in, kiemelt cikk opt-in) is ide kerülnek majd.
 */
final class Mtmt_Settings_Page {

	private const CAPABILITY   = 'manage_options';
	private const NONCE_ACTION = 'mtmt_settings_action';
	private const PAGE_SLUG    = 'mtmt-settings';

	/**
	 * @var Mtmt_Sync_Log_Repository
	 */
	private $log_repo;

	/**
	 * @var Mtmt_Query_Profile_Repository
	 */
	private $profile_repo;

	/**
	 * @param Mtmt_Sync_Log_Repository       $log_repo
	 * @param Mtmt_Query_Profile_Repository  $profile_repo
	 */
	public function __construct( Mtmt_Sync_Log_Repository $log_repo, Mtmt_Query_Profile_Repository $profile_repo ) {
		$this->log_repo     = $log_repo;
		$this->profile_repo = $profile_repo;
	}

	/**
	 * `admin_menu`-ből hívva — a top-level "MTMT" (Mtmt_Publications_Page) alá, almenüként.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			Mtmt_Publications_Page::PAGE_SLUG,
			__( 'MTMT Publikációk — Beállítások', 'mtmt-sync' ),
			__( 'Beállítások', 'mtmt-sync' ),
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
		$recipients   = get_option( Mtmt_Notifier::OPTION_RECIPIENTS, '' );
		$logs         = $this->log_repo->get_recent( 20 );
		$profile_labels = array();
		foreach ( $this->profile_repo->get_all() as $profile ) {
			$profile_labels[ (int) $profile['id'] ] = $profile['label'];
		}
		$nonce_action = self::NONCE_ACTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MTMT Publikációk — Beállítások', 'mtmt-sync' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Funkciók', 'mtmt-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( $nonce_action ); ?>
				<input type="hidden" name="mtmt_action" value="save_features">
				<p>
					<label>
						<input type="checkbox" name="enable_featured" value="1" <?php checked( (bool) get_option( 'mtmt_enable_featured' ) ); ?>>
						<?php esc_html_e( '„Kiemelt cikk" funkció engedélyezése', 'mtmt-sync' ); ?>
					</label>
				</p>
				<p class="description">
					<?php esc_html_e( 'Ha ki van kapcsolva: a gazdagító űrlapon nincs "kiemelt cikk" jelölő, és a Fázis 5-ös "B" (terület-aloldal) widget nem lesz elérhető Elementorban.', 'mtmt-sync' ); ?>
				</p>
				<?php submit_button( __( 'Mentés', 'mtmt-sync' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Email-értesítés', 'mtmt-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( $nonce_action ); ?>
				<input type="hidden" name="mtmt_action" value="save_recipients">
				<p>
					<label for="mtmt-recipients"><?php esc_html_e( 'Címzettek (soronként vagy vesszővel elválasztva):', 'mtmt-sync' ); ?></label><br>
					<textarea id="mtmt-recipients" name="recipients" rows="4" class="large-text code"><?php echo esc_textarea( $recipients ); ?></textarea>
				</p>
				<p class="description">
					<?php esc_html_e( 'Ha üres, nem megy ki email. Csak a heti automatikus (cron) futásról küld értesítést, ha volt új vagy frissült tétel — kézi/CLI szinkronnál nem, mert azt az admin úgyis a képernyőn látja.', 'mtmt-sync' ); ?>
				</p>
				<?php submit_button( __( 'Mentés', 'mtmt-sync' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Futás-napló (utolsó 20)', 'mtmt-sync' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Időpont', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Profil', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Forrás', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Új', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Frissített', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Visszaesett', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Hiányzó', 'mtmt-sync' ); ?></th>
						<th><?php esc_html_e( 'Státusz', 'mtmt-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'Még nem futott szinkron.', 'mtmt-sync' ); ?></td>
						</tr>
					<?php endif; ?>
					<?php foreach ( $logs as $log ) : ?>
						<?php $profile_id = isset( $log['profile_id'] ) ? (int) $log['profile_id'] : 0; ?>
						<tr>
							<td><?php echo esc_html( $log['started_at'] ); ?></td>
							<td><?php echo esc_html( $profile_labels[ $profile_id ] ?? ( '#' . $profile_id ) ); ?></td>
							<td><?php echo esc_html( $log['trigger_type'] ); ?></td>
							<td><?php echo esc_html( $log['inserted'] ); ?></td>
							<td><?php echo esc_html( $log['updated'] ); ?></td>
							<td><?php echo esc_html( $log['reverted_to_pending'] ); ?></td>
							<td><?php echo esc_html( $log['missing'] ); ?></td>
							<td>
								<?php if ( ! empty( $log['has_errors'] ) ) : ?>
									<span style="color:#b32d2e;">
										<?php
										$errors = json_decode( (string) $log['errors'], true );
										echo esc_html( is_array( $errors ) ? implode( '; ', $errors ) : __( 'hiba', 'mtmt-sync' ) );
										?>
									</span>
								<?php else : ?>
									<?php esc_html_e( 'OK', 'mtmt-sync' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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

		if ( 'save_recipients' === $action ) {
			$raw = isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '';
			update_option( Mtmt_Notifier::OPTION_RECIPIENTS, $raw );

			return array(
				'type'    => 'success',
				'message' => __( 'Mentve.', 'mtmt-sync' ),
			);
		}

		if ( 'save_features' === $action ) {
			update_option( 'mtmt_enable_featured', ! empty( $_POST['enable_featured'] ) ? 1 : 0 );

			return array(
				'type'    => 'success',
				'message' => __( 'Mentve.', 'mtmt-sync' ),
			);
		}

		return null;
	}
}
