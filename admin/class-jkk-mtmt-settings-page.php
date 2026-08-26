<?php
/**
 * Admin oldal: email-értesítés címzettjei + futás-napló (CLAUDE.md §6, §14/5).
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * `manage_options`-hoz kötve, mint a profil-oldal — ez is site-config, nem
 * napi moderáció. Jövőbeli fázisok globális kapcsolói (szakmai terület
 * opt-in, kiemelt cikk opt-in) is ide kerülnek majd.
 */
final class Jkk_Mtmt_Settings_Page {

	private const CAPABILITY   = 'manage_options';
	private const NONCE_ACTION = 'jkk_mtmt_settings_action';
	private const PAGE_SLUG    = 'jkk-mtmt-settings';

	/**
	 * @var Jkk_Mtmt_Sync_Log_Repository
	 */
	private $log_repo;

	/**
	 * @var Jkk_Mtmt_Query_Profile_Repository
	 */
	private $profile_repo;

	/**
	 * @param Jkk_Mtmt_Sync_Log_Repository       $log_repo
	 * @param Jkk_Mtmt_Query_Profile_Repository  $profile_repo
	 */
	public function __construct( Jkk_Mtmt_Sync_Log_Repository $log_repo, Jkk_Mtmt_Query_Profile_Repository $profile_repo ) {
		$this->log_repo     = $log_repo;
		$this->profile_repo = $profile_repo;
	}

	/**
	 * `admin_menu`-ből hívva — a Profilok oldal alá, almenüként.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'jkk-mtmt-profiles',
			__( 'JKK MTMT — Beállítások', 'jkk-mtmt-publications' ),
			__( 'Beállítások', 'jkk-mtmt-publications' ),
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
			wp_die( esc_html__( 'Nincs jogosultságod ehhez az oldalhoz.', 'jkk-mtmt-publications' ) );
		}

		$notice       = $this->handle_request();
		$recipients   = get_option( Jkk_Mtmt_Notifier::OPTION_RECIPIENTS, '' );
		$logs         = $this->log_repo->get_recent( 20 );
		$profile_labels = array();
		foreach ( $this->profile_repo->get_all() as $profile ) {
			$profile_labels[ (int) $profile['id'] ] = $profile['label'];
		}
		$nonce_action = self::NONCE_ACTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'JKK MTMT — Beállítások', 'jkk-mtmt-publications' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Email-értesítés', 'jkk-mtmt-publications' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( $nonce_action ); ?>
				<input type="hidden" name="jkk_mtmt_action" value="save_recipients">
				<p>
					<label for="jkk-mtmt-recipients"><?php esc_html_e( 'Címzettek (soronként vagy vesszővel elválasztva):', 'jkk-mtmt-publications' ); ?></label><br>
					<textarea id="jkk-mtmt-recipients" name="recipients" rows="4" class="large-text code"><?php echo esc_textarea( $recipients ); ?></textarea>
				</p>
				<p class="description">
					<?php esc_html_e( 'Ha üres, nem megy ki email. Csak a heti automatikus (cron) futásról küld értesítést, ha volt új vagy frissült tétel — kézi/CLI szinkronnál nem, mert azt az admin úgyis a képernyőn látja.', 'jkk-mtmt-publications' ); ?>
				</p>
				<?php submit_button( __( 'Mentés', 'jkk-mtmt-publications' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Futás-napló (utolsó 20)', 'jkk-mtmt-publications' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Időpont', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Profil', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Forrás', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Új', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Frissített', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Visszaesett', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Hiányzó', 'jkk-mtmt-publications' ); ?></th>
						<th><?php esc_html_e( 'Státusz', 'jkk-mtmt-publications' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'Még nem futott szinkron.', 'jkk-mtmt-publications' ); ?></td>
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
										echo esc_html( is_array( $errors ) ? implode( '; ', $errors ) : __( 'hiba', 'jkk-mtmt-publications' ) );
										?>
									</span>
								<?php else : ?>
									<?php esc_html_e( 'OK', 'jkk-mtmt-publications' ); ?>
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
		if ( empty( $_POST['jkk_mtmt_action'] ) ) {
			return null;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez a művelethez.', 'jkk-mtmt-publications' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['jkk_mtmt_action'] ) );

		if ( 'save_recipients' === $action ) {
			$raw = isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '';
			update_option( Jkk_Mtmt_Notifier::OPTION_RECIPIENTS, $raw );

			return array(
				'type'    => 'success',
				'message' => __( 'Mentve.', 'jkk-mtmt-publications' ),
			);
		}

		return null;
	}
}
