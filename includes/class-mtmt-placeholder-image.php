<?php
/**
 * Placeholder-kép generálása indexkép hiányában (CLAUDE.md §14/8).
 *
 * A megrendelővel egyeztetve (2026-08, docs/widget-design.md) szerver-oldali
 * (GD) megoldás, mert a cím ténylegesen bele van égetve a képfájlba — ez
 * OG-megosztásnál (Facebook/LinkedIn) is helyes előnézetet ad, amit egy
 * tisztán CSS-alapú overlay nem tudna (a közösségi crawlerek nem futtatnak
 * CSS-t/JS-t a preview-generáláshoz).
 *
 * Ha a szerveren nincs GD vagy nincs elérhető TTF font, a get_url_for_publication()
 * NULL-t ad vissza — a hívó (Mtmt_Card_Renderer) ekkor szépen visszaesik egy
 * tisztán CSS-alapú overlay-re (lásd ott). Ez ugyanaz a "degradálj szépen, ha
 * a függőség hiányzik" minta, mint amit a CLAUDE.md §2 az Elementorra ír elő.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-elt, GD-alapú placeholder-kép generátor.
 */
final class Mtmt_Placeholder_Image {

	/**
	 * Becsomagolt alap-betűtípus (Open Sans Bold, SIL OFL 1.1 — lásd
	 * assets/fonts/OFL.txt), teljes magyar ékezet-készlettel (ő/ű is).
	 */
	private const DEFAULT_FONT = 'assets/fonts/OpenSans-Bold.ttf';

	/**
	 * Becsomagolt alap háttérkép, ha a Beállításokban nincs egyedi megadva.
	 */
	private const DEFAULT_BASE_IMAGE = 'assets/img/placeholder-default.png';

	/**
	 * @param array $publication A publikáció-sor (legalább `mtid`, `title`).
	 * @return string|null A (cache-elt) kép URL-je, vagy NULL, ha a generálás nem lehetséges
	 *                      (nincs GD/font) — ilyenkor a hívónak CSS-overlay-re kell esnie.
	 */
	public static function get_url_for_publication( array $publication ): ?string {
		if ( ! self::is_available() ) {
			return null;
		}

		$base_path = self::resolve_base_image_path();
		$font_path = self::resolve_font_path();
		if ( ! $base_path || ! $font_path ) {
			return null;
		}

		$title = trim( (string) ( $publication['title'] ?? '' ) );
		if ( '' === $title ) {
			$title = __( 'Publikáció', 'mtmt-sync' );
		}
		$mtid = absint( $publication['mtid'] ?? 0 );

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return null;
		}
		$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'mtmt-sync/placeholders';
		$cache_url = trailingslashit( $upload_dir['baseurl'] ) . 'mtmt-sync/placeholders';

		if ( ! wp_mkdir_p( $cache_dir ) ) {
			return null;
		}

		// A cache-kulcs a címből, mtid-ből ÉS az alapkép módosítási idejéből épül —
		// így cím- vagy alapkép-csere esetén automatikusan újragenerálódik, nincs
		// szükség kézi cache-ürítésre. A korábbi (immár orphan) fájlok a lemezen
		// maradnak — ez elfogadott egyszerűsítés ebben a körben, nem törlünk
		// automatikusan (lásd docs/decisions.md).
		$signature = md5( $mtid . '|' . $title . '|' . $base_path . '|' . (string) @filemtime( $base_path ) );
		$filename  = $signature . '.png';
		$dest_path = $cache_dir . '/' . $filename;

		if ( file_exists( $dest_path ) ) {
			return $cache_url . '/' . $filename;
		}

		$ok = self::generate( $base_path, $font_path, $title, $dest_path );
		return $ok ? ( $cache_url . '/' . $filename ) : null;
	}

	/**
	 * @return bool
	 */
	private static function is_available(): bool {
		return extension_loaded( 'gd' ) && function_exists( 'imagettftext' ) && function_exists( 'imagettfbbox' );
	}

	/**
	 * @return string|null Abszolút fájlrendszer-útvonal, vagy NULL, ha semmi nem elérhető.
	 */
	private static function resolve_font_path(): ?string {
		$custom = get_option( 'mtmt_placeholder_font_path' );
		if ( $custom && file_exists( (string) $custom ) ) {
			return (string) $custom;
		}

		$bundled = MTMT_PLUGIN_DIR . self::DEFAULT_FONT;
		return file_exists( $bundled ) ? $bundled : null;
	}

	/**
	 * @return string|null Abszolút fájlrendszer-útvonal a Beállításokban megadott
	 *                      egyedi alapképhez, vagy a becsomagolt alapértelmezetthez.
	 */
	private static function resolve_base_image_path(): ?string {
		$attachment_id = absint( get_option( 'mtmt_placeholder_base_image_id' ) );
		if ( $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			if ( $path && file_exists( $path ) ) {
				return $path;
			}
		}

		$bundled = MTMT_PLUGIN_DIR . self::DEFAULT_BASE_IMAGE;
		return file_exists( $bundled ) ? $bundled : null;
	}

	/**
	 * Az alapkép URL-je — a CSS-overlay degradálásnak kell (Mtmt_Card_Renderer),
	 * ha GD/font hiányában NEM lehet a címet beégetni. Ugyanazt az admin által
	 * beállított egyedi alapképet (vagy a becsomagolt alapértelmezettet) adja
	 * vissza, amit a GD-s generálás is használna — így a két út vizuálisan
	 * konzisztens, csak a cím-elhelyezés módja tér el (beégetve vs. CSS-sel rárakva).
	 *
	 * @return string
	 */
	public static function get_base_image_url(): string {
		$attachment_id = absint( get_option( 'mtmt_placeholder_base_image_id' ) );
		if ( $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'large' );
			if ( $url ) {
				return $url;
			}
		}

		return MTMT_PLUGIN_URL . self::DEFAULT_BASE_IMAGE;
	}

	/**
	 * @param string $base_path
	 * @param string $font_path
	 * @param string $title
	 * @param string $dest_path
	 * @return bool
	 */
	private static function generate( string $base_path, string $font_path, string $title, string $dest_path ): bool {
		$data = @file_get_contents( $base_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $data ) {
			return false;
		}

		$im = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $im ) {
			return false;
		}

		$width  = imagesx( $im );
		$height = imagesy( $im );

		// Sötét, félig áttetsző sáv a kép alsó ~42%-án, hogy a fehér szöveg
		// olvasható legyen bármilyen alapkép fölött.
		imagealphablending( $im, true );
		$band_top   = (int) round( $height * 0.58 );
		$overlay    = imagecolorallocatealpha( $im, 15, 23, 32, 45 );
		imagefilledrectangle( $im, 0, $band_top, $width, $height, $overlay );

		$white     = imagecolorallocate( $im, 255, 255, 255 );
		$font_size = max( 14, (int) round( $width / 26 ) );
		$padding   = (int) round( $width * 0.05 );
		$max_width = $width - ( 2 * $padding );

		$lines = self::wrap_text( $font_path, $font_size, $max_width, $title, 3 );

		$line_height = (int) round( $font_size * 1.35 );
		$text_block  = count( $lines ) * $line_height;
		$y           = $height - (int) round( $height * 0.10 ) - $text_block + $line_height;

		foreach ( $lines as $line ) {
			imagettftext( $im, $font_size, 0, $padding, $y, $white, $font_path, $line );
			$y += $line_height;
		}

		$saved = imagepng( $im, $dest_path );
		imagedestroy( $im );

		return (bool) $saved;
	}

	/**
	 * Szó szerinti tördelés a rendelkezésre álló szélességhez, `$max_lines` fölött
	 * "…"-tal lezárva.
	 *
	 * @param string $font_path
	 * @param int    $font_size
	 * @param int    $max_width
	 * @param string $text
	 * @param int    $max_lines
	 * @return string[]
	 */
	private static function wrap_text( string $font_path, int $font_size, int $max_width, string $text, int $max_lines ): array {
		$words = preg_split( '/\s+/u', trim( $text ) );
		if ( ! $words ) {
			return array( $text );
		}

		$lines   = array();
		$current = '';

		foreach ( $words as $word ) {
			$candidate = '' === $current ? $word : $current . ' ' . $word;
			if ( self::text_width( $font_path, $font_size, $candidate ) <= $max_width || '' === $current ) {
				$current = $candidate;
				continue;
			}

			$lines[] = $current;
			$current = $word;

			if ( count( $lines ) >= $max_lines ) {
				break;
			}
		}

		if ( '' !== $current && count( $lines ) < $max_lines ) {
			$lines[] = $current;
		}

		if ( count( $lines ) > $max_lines ) {
			$lines = array_slice( $lines, 0, $max_lines );
		}

		// Ha maradt fel nem írt szó (a ciklus break-elt vagy levágtuk), jelezzük "…"-tal.
		$consumed = implode( ' ', $lines );
		if ( trim( $text ) !== trim( $current ) && count( $lines ) === $max_lines && ! str_ends_with( $consumed, '…' ) ) {
			$last              = array_pop( $lines );
			$lines[]           = self::truncate_with_ellipsis( $font_path, $font_size, $max_width, $last );
		}

		return $lines;
	}

	/**
	 * @param string $font_path
	 * @param int    $font_size
	 * @param string $text
	 * @return int
	 */
	private static function text_width( string $font_path, int $font_size, string $text ): int {
		$bbox = imagettfbbox( $font_size, 0, $font_path, $text );
		return abs( $bbox[2] - $bbox[0] );
	}

	/**
	 * @param string $font_path
	 * @param int    $font_size
	 * @param int    $max_width
	 * @param string $line
	 * @return string
	 */
	private static function truncate_with_ellipsis( string $font_path, int $font_size, int $max_width, string $line ): string {
		while ( $line && self::text_width( $font_path, $font_size, $line . '…' ) > $max_width ) {
			$line = rtrim( mb_substr( $line, 0, -1 ) );
		}
		return $line . '…';
	}
}
