<?php
/**
 * Admin oldal: mely szerepkörök moderálhatnak/sorolhatnak be.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Capabilities_Page {

	private const CAPABILITY   = 'manage_options';
	private const NONCE_ACTION = 'mtmt_capabilities_action';
	private const PAGE_SLUG    = 'mtmt-capabilities';

	public function add_menu_page(): void {
		add_submenu_page(
			Mtmt_Publications_Page::PAGE_SLUG,
			__( 'MTMT — Jogosultságok', 'mtmt-sync' ),
			__( 'Jogosultságok', 'mtmt-sync' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez az oldalhoz.', 'mtmt-sync' ) );
		}

		$notice         = $this->handle_request();
		$moderate_roles = Mtmt_Capabilities::get_moderate_roles();
		$classify_roles = Mtmt_Capabilities::get_classify_roles();
		$all_roles      = wp_roles()->roles;
		$nonce_action   = self::NONCE_ACTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MTMT Publikációk — Jogosultságok', 'mtmt-sync' ); ?></h1>
			<p><?php esc_html_e( 'Itt állíthatod be, mely szerepkörök tudnak moderálni és besorolni. A mentés azonnal érvénybe lép.', 'mtmt-sync' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( $nonce_action ); ?>
				<input type="hidden" name="mtmt_action" value="save_roles">

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Szerepkör', 'mtmt-sync' ); ?></th>
							<th><?php esc_html_e( 'Moderálás', 'mtmt-sync' ); ?></th>
							<th><?php esc_html_e( 'Besorolás', 'mtmt-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_roles as $role_slug => $role_info ) : ?>
							<tr>
								<td><?php echo esc_html( translate_user_role( $role_info['name'] ) ); ?> <code><?php echo esc_html( $role_slug ); ?></code></td>
								<td>
									<label>
										<input type="checkbox" name="moderate_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $moderate_roles, true ) ); ?>>
										<span class="screen-reader-text"><?php esc_html_e( 'Moderálás', 'mtmt-sync' ); ?></span>
									</label>
								</td>
								<td>
									<label>
										<input type="checkbox" name="classify_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $classify_roles, true ) ); ?>>
										<span class="screen-reader-text"><?php esc_html_e( 'Besorolás', 'mtmt-sync' ); ?></span>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Moderálás', 'mtmt-sync' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Publikációk jóváhagyása/elutasítása, borítókép és egyéb alap adatok szerkesztése.', 'mtmt-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Besorolás', 'mtmt-sync' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Szakmai terület hozzárendelése egy publikációhoz, és a projektazonosító ellenőrzése.', 'mtmt-sync' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Mentés', 'mtmt-sync' ) ); ?>
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

		if ( 'save_roles' === $action ) {
			$valid_roles = array_keys( wp_roles()->roles );

			$moderate_roles = isset( $_POST['moderate_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['moderate_roles'] ) ) : array();
			$classify_roles = isset( $_POST['classify_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['classify_roles'] ) ) : array();

			// Csak ténylegesen létező szerepkör-slugot fogadunk el — a beküldött
			// checkbox-értékek amúgy is a saját <option value>-jainkból jönnek,
			// de egy manipulált POST-tal ne lehessen tetszőleges stringet beírni
			// a role→capability tárolt listába.
			$moderate_roles = array_values( array_intersect( $moderate_roles, $valid_roles ) );
			$classify_roles = array_values( array_intersect( $classify_roles, $valid_roles ) );

			Mtmt_Capabilities::save_role_mapping( $moderate_roles, $classify_roles );

			return array(
				'type'    => 'success',
				'message' => __( 'Mentve. A jogosultságok azonnal érvénybe léptek.', 'mtmt-sync' ),
			);
		}

		return null;
	}
}
