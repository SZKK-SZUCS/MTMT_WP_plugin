<?php
/**
 * Email-értesítés a heti sync után, ha volt új/frissült tétel (CLAUDE.md §14/5).
 *
 * @package Jkk_Mtmt_Publications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Globális, site-szintű címzett-lista (wp_options), nem profilonkénti — lásd
 * docs/decisions.md #24/#5. Csak akkor küld, ha van legalább egy új VAGY
 * frissített (beleértve a pending-be visszaesetteket is) tétel valamelyik
 * profil eredményében.
 */
final class Jkk_Mtmt_Notifier {

	const OPTION_RECIPIENTS = 'jkk_mtmt_notification_recipients';

	/**
	 * @param array[] $results Jkk_Mtmt_Sync::run_profile() eredmények listája.
	 */
	public function notify_if_needed( array $results ): void {
		$recipients = self::get_recipients();
		if ( empty( $recipients ) || empty( $results ) ) {
			return;
		}

		if ( ! $this->has_activity( $results ) ) {
			return;
		}

		wp_mail(
			$recipients,
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Új MTMT publikációk a heti szinkronból', 'jkk-mtmt-publications' ),
				get_bloginfo( 'name' )
			),
			$this->build_body( $results )
		);
	}

	/**
	 * @return string[]
	 */
	public static function get_recipients(): array {
		$raw    = (string) get_option( self::OPTION_RECIPIENTS, '' );
		$emails = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );

		return array_values( array_filter( $emails, 'is_email' ) );
	}

	/**
	 * @param array[] $results
	 * @return bool
	 */
	private function has_activity( array $results ): bool {
		foreach ( $results as $result ) {
			if ( ( $result['inserted'] ?? 0 ) > 0 || ( $result['updated'] ?? 0 ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array[] $results
	 * @return string
	 */
	private function build_body( array $results ): string {
		$lines = array();

		foreach ( $results as $result ) {
			if ( ! empty( $result['errors'] ) ) {
				$lines[] = sprintf(
					/* translators: 1: profil id, 2: hibaüzenetek */
					__( 'Profil #%1$d: HIBA a futás közben — %2$s', 'jkk-mtmt-publications' ),
					$result['profile_id'],
					implode( '; ', $result['errors'] )
				);
				continue;
			}

			$lines[] = sprintf(
				/* translators: 1: profil id, 2: uj, 3: frissitett, 4: visszaesett, 5: hianyzo */
				__( 'Profil #%1$d: %2$d új, %3$d frissített (ebből %4$d visszaesett pending-be), %5$d hiányzóként jelölt.', 'jkk-mtmt-publications' ),
				$result['profile_id'],
				$result['inserted'],
				$result['updated'],
				$result['reverted_to_pending'] ?? 0,
				$result['missing']
			);
		}

		$lines[] = '';
		$lines[] = __( 'A jóváhagyáshoz:', 'jkk-mtmt-publications' ) . ' ' . admin_url( 'admin.php?page=jkk-mtmt-profiles' );

		return implode( "\n", $lines );
	}
}
