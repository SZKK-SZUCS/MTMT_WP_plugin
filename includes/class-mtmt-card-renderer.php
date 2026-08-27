<?php
/**
 * Egy publikáció-kártya HTML-je — közös az "A" és "B" Elementor widget,
 * illetve az AJAX-fragment újratöltés között. Csak már betöltött adatból
 * épít HTML-t, nem hív se DB-t, se az MTMT API-t.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Card_Renderer {

	/** Dekoratív "nyíl jobbra" ikon a sor végén — csak vizuálisan jelzi a kattinthatóságot. */
	private const ARROW_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';

	/**
	 * @param array $publication      Publikáció-sor (repository `get_list()` eleme),
	 *                                opcionálisan kiegészítve `topic_area_labels` (string[]) kulccsal.
	 * @param array $display_options  {
	 *     @type bool   $show_topic_area A szakmai terület badge megjelenjen-e (csak ha a funkció be van kapcsolva).
	 *     @type bool   $show_doi_link   DOI-szöveg megjelenjen-e, ÉS DOI hiányában a teljes kártya essen-e
	 *                                   vissza az MTMT gui-linkre (CLAUDE.md §14/12).
	 *     @type bool   $show_sjr_badge  SJR-negyed badge megjelenjen-e.
	 *     @type string $citation_style  'compact' vagy 'full' — compact esetben a forrás-sor és az
	 *                                   egyéb-azonosító logó-gombok elmaradnak (CLAUDE.md §9.1 "hivatkozás-stílus").
	 *     @type string $ext_id_badge_mode 'icon' | 'text' | 'both' (alapértelmezett) — az egyéb azonosítós
	 *                                   gombok "Csak ikon"/"Csak szöveg"/"Ikon és szöveg" megjelenítése.
	 * }
	 * @return string Kész HTML.
	 */
	public static function render( array $publication, array $display_options = array() ): string {
		$display_options = array_merge(
			array(
				'show_topic_area'   => false,
				'show_doi_link'     => true,
				'show_sjr_badge'    => true,
				'citation_style'    => 'full',
				'ext_id_badge_mode' => 'both',
			),
			$display_options
		);

		$title = trim( (string) ( $publication['title'] ?? '' ) );
		$link  = self::link_target( $publication, (bool) $display_options['show_doi_link'] );
		$full  = 'full' === $display_options['citation_style'];

		$out  = '<div class="mtmt-pub-card"' . ( $link ? ' data-href="' . esc_url( $link ) . '" tabindex="0" role="link"' : '' ) . '>';
		$out .= '<div class="mtmt-pub-card-media">' . self::render_media( $publication ) . '</div>';
		$out .= '<div class="mtmt-pub-card-body">';
		// Új fülön nyílik (a whole-card JS-kattintás is így viselkedik, hogy a
		// két navigációs útvonal konzisztens legyen, és a látogató ne veszítse
		// el a listát vissza-navigáláskor).
		$out .= '<h3 class="mtmt-pub-card-title">' . ( $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</h3>';

		$authors = Mtmt_Author_Formatter::format_for_card( $publication['authors_raw'] ?? null );
		if ( '' !== $authors ) {
			$out .= '<p class="mtmt-pub-card-authors">' . esc_html( $authors ) . '</p>';
		}

		if ( $display_options['show_topic_area'] && ! empty( $publication['topic_area_labels'] ) ) {
			$out .= '<p class="mtmt-pub-card-topic-areas">';
			foreach ( (array) $publication['topic_area_labels'] as $label ) {
				$out .= '<span class="mtmt-badge mtmt-badge-topic-area">' . esc_html( $label ) . '</span>';
			}
			$out .= '</p>';
		}

		// Forrás + év — kiemelő-színű sor, NEM tényleges link (nincs saját folyóirat-URL-mezőnk).
		$has_year = ! empty( $publication['published_year'] );
		if ( ( $full && ! empty( $publication['source_title'] ) ) || $has_year ) {
			$out .= '<p class="mtmt-pub-card-source-line">';
			if ( $full && ! empty( $publication['source_title'] ) ) {
				$out .= '<span class="mtmt-pub-card-source">' . esc_html( $publication['source_title'] ) . '</span>';
				if ( $has_year ) {
					$out .= ', ';
				}
			}
			if ( $has_year ) {
				$out .= '<span class="mtmt-pub-card-year">' . esc_html( (string) $publication['published_year'] ) . '</span>';
			}
			$out .= '</p>';
		}

		$meta_parts = array();
		if ( $display_options['show_doi_link'] && ! empty( $publication['doi'] ) ) {
			$meta_parts[] = '<span class="mtmt-pub-card-doi">DOI: ' . esc_html( (string) $publication['doi'] ) . '</span>';
		}
		if ( $display_options['show_sjr_badge'] && ! empty( $publication['sjr_quartile'] ) ) {
			$meta_parts[] = '<span class="mtmt-badge mtmt-badge-sjr ' . esc_attr( self::sjr_badge_class( (string) $publication['sjr_quartile'] ) ) . '">' . esc_html( (string) $publication['sjr_quartile'] ) . '</span>';
		}
		if ( ! empty( $meta_parts ) ) {
			$out .= '<p class="mtmt-pub-card-meta">' . implode( ' ', $meta_parts ) . '</p>';
		}

		if ( $full ) {
			$ext_ids_html = Mtmt_External_Id_Icons::render_buttons( $publication['external_ids'] ?? null, (string) $display_options['ext_id_badge_mode'] );
			if ( '' !== $ext_ids_html ) {
				$out .= $ext_ids_html; // Mtmt_External_Id_Icons már maga escape-eli a tartalmát.
			}
		}

		$out .= '</div>'; // .mtmt-pub-card-body vége

		// A típus-badge + nyíl-CTA egy valódi flex-oszlopban van (nem abszolút
		// pozicionálva egy becsült hely fölé), hogy hosszú típus-szövegnél se
		// csússzon rá a címre.
		$side_html = self::render_type_badge( $publication ) . self::render_arrow_cta( $link );
		if ( '' !== $side_html ) {
			$out .= '<div class="mtmt-pub-card-side">' . $side_html . '</div>';
		}

		$out .= '</div>'; // .mtmt-pub-card vége

		return $out;
	}

	/**
	 * DOI, ha van; különben (ha engedélyezve) az MTMT humán gui-linkje;
	 * különben üres string (a kártya ekkor nem kattintható egészben).
	 *
	 * @param array $publication
	 * @param bool  $show_doi_link
	 * @return string
	 */
	private static function link_target( array $publication, bool $show_doi_link ): string {
		$doi = trim( (string) ( $publication['doi'] ?? '' ) );
		if ( '' !== $doi ) {
			return 'https://doi.org/' . $doi;
		}

		$mtid = absint( $publication['mtid'] ?? 0 );
		if ( $show_doi_link && $mtid ) {
			return 'https://m2.mtmt.hu/gui2/?mode=browse&params=publication;' . $mtid;
		}

		return '';
	}

	/**
	 * @param array $publication
	 * @return string
	 */
	private static function render_media( array $publication ): string {
		$thumbnail_id = absint( $publication['thumbnail_id'] ?? 0 );
		if ( $thumbnail_id ) {
			$img = wp_get_attachment_image( $thumbnail_id, 'medium', false, array( 'class' => 'mtmt-pub-card-img' ) );
			if ( $img ) {
				return $img;
			}
		}

		$generated = Mtmt_Placeholder_Image::get_url_for_publication( $publication );
		if ( $generated ) {
			return '<img class="mtmt-pub-card-img" src="' . esc_url( $generated ) . '" alt="">';
		}

		// Nincs GD/font a szerveren -> CSS-overlay ugyanazzal az alapképpel.
		$base_url = Mtmt_Placeholder_Image::get_base_image_url();
		$title    = trim( (string) ( $publication['title'] ?? '' ) );
		return '<div class="mtmt-pub-card-css-placeholder" style="background-image:url(' . esc_url( $base_url ) . ')"><span>' . esc_html( $title ) . '</span></div>';
	}

	/**
	 * @param array $publication
	 * @return string
	 */
	private static function render_type_badge( array $publication ): string {
		$type = trim( (string) ( $publication['pub_type'] ?? '' ) );
		if ( '' === $type ) {
			return '';
		}
		return '<span class="mtmt-badge mtmt-pub-card-type-badge ' . esc_attr( self::type_badge_class( $type ) ) . '">' . esc_html( $type ) . '</span>';
	}

	/**
	 * Csak akkor jelenik meg, ha a sor egésze tényleg kattintható.
	 *
	 * @param string $link
	 * @return string
	 */
	private static function render_arrow_cta( string $link ): string {
		if ( '' === $link ) {
			return '';
		}
		return '<span class="mtmt-pub-card-arrow" aria-hidden="true">' . self::ARROW_SVG . '</span>';
	}

	/**
	 * Determinisztikus (de tetszőleges kiadványtípus-szöveghez igazodó) szín-index —
	 * nem kell előre felsorolni minden lehetséges MTMT `pub_type` értéket.
	 *
	 * @param string $type
	 * @return string
	 */
	private static function type_badge_class( string $type ): string {
		return 'mtmt-badge-color-' . ( crc32( $type ) % 5 );
	}

	/**
	 * @param string $quartile
	 * @return string
	 */
	private static function sjr_badge_class( string $quartile ): string {
		$slug = sanitize_html_class( strtolower( $quartile ) );
		return 'mtmt-badge-sjr-' . ( $slug ?: 'other' );
	}
}
