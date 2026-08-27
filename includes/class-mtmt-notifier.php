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
 * FRISSÍTVE (megrendelői kérés, 2026-08, docs/decisions.md #74-78): HTML-email,
 * világos, designolt háttérrel + a becsomagolt kiadói logóval (jelenleg
 * `assets/img/mfui-logo.png`, lásd LOGO_CANDIDATES). A logó a PLUGINBA van
 * becsomagolva, NEM site-onként, admin médiatárból választható — ez
 * rendszer/kiadói email (a "MTMT Sync" doboz-terméké), minden site-on
 * ugyanaz a fejléc-kép megy ki, konzisztensen a megrendelővel (nem a
 * kliens szervezet saját brandje).
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
		'assets/img/mfui-logo.png',
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
	 * Teljes, minimális HTML-dokumentum (nem csak egy `<div>`-fragment) —
	 * explicit, VILÁGOS `<body>`-háttér kell, mert a becsomagolt logó sötét
	 * (navy) feliratú, átlátszó/fehér alapra tervezve (docs/decisions.md #78) —
	 * egy esetleges sötét emailkliens-háttéren (pl. dark mode) a felirat
	 * olvashatatlanná válna, ha nem adunk neki saját, világos "kártyát".
	 * Csak inline stílusok (a legtöbb emailkliens nem futtat `<style>`
	 * blokkot megbízhatóan) — nincs multipart/alternative szöveges változat,
	 * ez tudatos egyszerűsítés.
	 *
	 * @param array[]           $results
	 * @param array<int,string> $profile_labels
	 * @return string
	 */
	private function build_html_body( array $results, array $profile_labels ): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$logo_url  = self::get_logo_url();

		// A becsomagolt logó (assets/img/mfui-logo.png) sötét navy szín-
		// világával összehangolt paletta — a hangsúly-szín a logó teal
		// csík árnyalatához igazítva.
		$navy   = '#16233f';
		$muted  = '#5b6b7c';
		$accent = '#16aebd';
		$border = '#e1e6ea';
		$page   = '#eef2f6';

		$out  = '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . esc_html__( 'MTMT szinkron-értesítő', 'mtmt-sync' ) . '</title></head>';
		$out .= '<body style="margin:0;padding:0;background-color:' . esc_attr( $page ) . ';">';
		$out .= '<div style="background-color:' . esc_attr( $page ) . ';padding:32px 16px;">';
		$out .= '<div style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid ' . esc_attr( $border ) . ';font-family:Arial,Helvetica,sans-serif;">';

		if ( $logo_url ) {
			$out .= '<div style="background-color:#ffffff;padding:28px 32px 22px;text-align:center;">';
			$out .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '" style="max-width:280px;height:auto;display:inline-block;">';
			$out .= '</div>';
		}
		$out .= '<div style="height:4px;background-color:' . esc_attr( $accent ) . ';line-height:4px;font-size:0;">&nbsp;</div>';

		$out .= '<div style="padding:28px 32px;">';
		$out .= '<h1 style="color:' . esc_attr( $navy ) . ';font-size:19px;margin:0 0 14px;">' . esc_html__( 'Új MTMT publikációk a heti szinkronból', 'mtmt-sync' ) . '</h1>';
		$out .= '<p style="color:' . esc_attr( $muted ) . ';font-size:14px;line-height:1.6;margin:0 0 20px;">' . sprintf(
			/* translators: %s: site name */
			esc_html__( 'A(z) %s oldalon lezajlott heti MTMT-szinkron az alábbi eredménnyel futott le:', 'mtmt-sync' ),
			$site_name
		) . '</p>';

		foreach ( $results as $result ) {
			$profile_id = (int) ( $result['profile_id'] ?? 0 );
			$label      = $profile_labels[ $profile_id ] ?? ( '#' . $profile_id );

			if ( ! empty( $result['errors'] ) ) {
				$out .= '<div style="background-color:#fdf1f1;border-left:4px solid #d64545;border-radius:6px;padding:14px 18px;margin-bottom:12px;">';
				$out .= '<strong style="color:' . esc_attr( $navy ) . ';font-size:14px;">' . esc_html( $label ) . '</strong> — <span style="color:#c23b3b;font-weight:bold;font-size:13px;">' . esc_html__( 'HIBA a futás közben', 'mtmt-sync' ) . '</span><br>';
				$out .= '<span style="color:#c23b3b;font-size:13px;">' . esc_html( implode( '; ', $result['errors'] ) ) . '</span>';
				$out .= '</div>';
				continue;
			}

			$out .= '<div style="background-color:#f6fafb;border-left:4px solid ' . esc_attr( $accent ) . ';border-radius:6px;padding:14px 18px;margin-bottom:12px;">';
			$out .= '<strong style="color:' . esc_attr( $navy ) . ';font-size:14px;">' . esc_html( $label ) . '</strong><br>';
			$out .= '<span style="color:' . esc_attr( $muted ) . ';font-size:13px;">' . sprintf(
				/* translators: 1: uj, 2: frissitett, 3: visszaesett, 4: hianyzo */
				esc_html__( '%1$d új · %2$d frissítve (ebből %3$d visszaesett pending-be) · %4$d hiányzóként jelölve', 'mtmt-sync' ),
				(int) ( $result['inserted'] ?? 0 ),
				(int) ( $result['updated'] ?? 0 ),
				(int) ( $result['reverted_to_pending'] ?? 0 ),
				(int) ( $result['missing'] ?? 0 )
			) . '</span>';
			$out .= '</div>';
		}

		$out .= '<div style="text-align:center;margin:28px 0 6px;">';
		$out .= '<a href="' . esc_url( admin_url( 'admin.php?page=mtmt-profiles' ) ) . '" style="background-color:' . esc_attr( $accent ) . ';color:#ffffff;padding:12px 26px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:bold;display:inline-block;">';
		$out .= esc_html__( 'Jóváhagyás megnyitása', 'mtmt-sync' );
		$out .= '</a></div>';
		$out .= '</div>'; // padding:28px 32px

		$out .= '<div style="background-color:' . esc_attr( $page ) . ';padding:16px 32px;text-align:center;border-top:1px solid ' . esc_attr( $border ) . ';">';
		$out .= '<p style="color:#8a97a6;font-size:12px;margin:0;">' . sprintf(
			/* translators: %s: site name */
			esc_html__( 'Ezt az emailt a MTMT Sync plugin küldte automatikusan a(z) %s oldalról.', 'mtmt-sync' ),
			$site_name
		) . '</p>';
		$out .= '</div>';

		$out .= '</div></div></body></html>';

		return $out;
	}
}
