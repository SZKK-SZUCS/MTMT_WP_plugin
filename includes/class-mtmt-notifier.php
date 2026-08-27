<?php
/**
 * Email-értesítés a heti sync után, ha volt új/frissült tétel (CLAUDE.md §14/5).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Globális, site-szintű címzett-lista (wp_options), nem profilonkénti — lásd
 * docs/decisions.md #24/#5. Csak akkor küld, ha van legalább egy új VAGY
 * frissített (beleértve a pending-be visszaesetteket is) tétel valamelyik
 * profil eredményében.
 *
 * FRISSÍTVE (megrendelői kérés, 2026-08, docs/decisions.md #66-69): HTML-email,
 * opcionális logóval. A logó a PLUGINBA van becsomagolva (`assets/img/email-logo.*`),
 * NEM site-onként, admin médiatárból választható — ez rendszer/kiadói email
 * (a "MTMT Sync" doboz-terméké), minden site-on ugyanaz a fejléc-kép megy ki,
 * konzisztensen a megrendelővel (nem a kliens szervezet saját brandje).
 */
final class Mtmt_Notifier {

	const OPTION_RECIPIENTS = 'mtmt_notification_recipients';

	/**
	 * A becsomagolt email-logó lehetséges fájlnevei, ebben a sorrendben
	 * próbálva (az első létező nyer). PNG/JPG ajánlott — SVG-t a legtöbb
	 * emailkliens (pl. Outlook, Gmail-alkalmazások) NEM renderel megbízhatóan,
	 * ezért szándékosan nincs a listában.
	 *
	 * @var string[]
	 */
	private const LOGO_CANDIDATES = array(
		'assets/img/email-logo.png',
		'assets/img/email-logo.jpg',
		'assets/img/email-logo.jpeg',
	);

	/**
	 * @param array[]           $results        Mtmt_Sync::run_profile() eredmények listája.
	 * @param array<int,string> $profile_labels profile_id => profil neve (a #ID-nál olvashatóbb tárgy/törzs).
	 */
	public function notify_if_needed( array $results, array $profile_labels = array() ): void {
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
				__( '[%s] Új MTMT publikációk a heti szinkronból', 'mtmt-sync' ),
				get_bloginfo( 'name' )
			),
			$this->build_html_body( $results, $profile_labels ),
			array( 'Content-Type: text/html; charset=UTF-8' )
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
	 * A becsomagolt email-logó URL-je, ha a fájl ténylegesen létezik a
	 * pluginban — NULL, ha még nincs elhelyezve (lásd LOGO_CANDIDATES),
	 * ilyenkor az email egyszerűen fejléc-kép nélkül megy ki.
	 *
	 * @return string|null
	 */
	public static function get_logo_url(): ?string {
		foreach ( self::LOGO_CANDIDATES as $relative_path ) {
			if ( file_exists( MTMT_PLUGIN_DIR . $relative_path ) ) {
				return MTMT_PLUGIN_URL . $relative_path;
			}
		}
		return null;
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
	 * Egyszerű, csak-inline-stílusú HTML (a legtöbb emailkliens nem futtat
	 * `<style>` blokkot megbízhatóan) — nincs multipart/alternative szöveges
	 * változat, ez tudatos egyszerűsítés (docs/decisions.md #76).
	 *
	 * @param array[]           $results
	 * @param array<int,string> $profile_labels
	 * @return string
	 */
	private function build_html_body( array $results, array $profile_labels ): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$logo_url  = self::get_logo_url();

		$out  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#33404f;">';
		if ( $logo_url ) {
			$out .= '<div style="text-align:center;padding:20px 0;">';
			$out .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '" style="max-width:220px;height:auto;">';
			$out .= '</div>';
		}

		$out .= '<h2 style="color:#2e5f8a;font-size:18px;margin:0 0 12px;">' . esc_html__( 'Új MTMT publikációk a heti szinkronból', 'mtmt-sync' ) . '</h2>';
		$out .= '<p style="font-size:14px;line-height:1.5;">' . sprintf(
			/* translators: %s: site name */
			esc_html__( 'A(z) %s oldalon lezajlott heti MTMT-szinkron az alábbi eredménnyel futott le:', 'mtmt-sync' ),
			$site_name
		) . '</p>';

		foreach ( $results as $result ) {
			$profile_id = (int) ( $result['profile_id'] ?? 0 );
			$label      = $profile_labels[ $profile_id ] ?? ( '#' . $profile_id );

			if ( ! empty( $result['errors'] ) ) {
				$out .= '<div style="border:1px solid #f5c2c0;background:#fdecec;border-radius:6px;padding:12px 16px;margin-bottom:10px;font-size:14px;">';
				$out .= '<strong>' . esc_html( $label ) . '</strong> — <span style="color:#a3311f;">' . esc_html__( 'HIBA a futás közben', 'mtmt-sync' ) . '</span><br>';
				$out .= '<span style="color:#a3311f;">' . esc_html( implode( '; ', $result['errors'] ) ) . '</span>';
				$out .= '</div>';
				continue;
			}

			$out .= '<div style="border:1px solid #dfe4ea;border-radius:6px;padding:12px 16px;margin-bottom:10px;font-size:14px;">';
			$out .= '<strong>' . esc_html( $label ) . '</strong><br>';
			$out .= sprintf(
				/* translators: 1: uj, 2: frissitett, 3: visszaesett, 4: hianyzo */
				esc_html__( '%1$d új · %2$d frissítve (ebből %3$d visszaesett pending-be) · %4$d hiányzóként jelölve', 'mtmt-sync' ),
				(int) ( $result['inserted'] ?? 0 ),
				(int) ( $result['updated'] ?? 0 ),
				(int) ( $result['reverted_to_pending'] ?? 0 ),
				(int) ( $result['missing'] ?? 0 )
			);
			$out .= '</div>';
		}

		$out .= '<p style="text-align:center;margin:24px 0;">';
		$out .= '<a href="' . esc_url( admin_url( 'admin.php?page=mtmt-profiles' ) ) . '" style="background:#2e5f8a;color:#ffffff;padding:10px 22px;border-radius:6px;text-decoration:none;font-size:14px;display:inline-block;">';
		$out .= esc_html__( 'Jóváhagyás megnyitása', 'mtmt-sync' );
		$out .= '</a></p>';

		$out .= '<p style="color:#667085;font-size:12px;text-align:center;margin-top:24px;">' . sprintf(
			/* translators: %s: site name */
			esc_html__( 'Ezt az emailt a MTMT Sync plugin küldte automatikusan a(z) %s oldalról.', 'mtmt-sync' ),
			$site_name
		) . '</p>';

		$out .= '</div>';

		return $out;
	}
}
