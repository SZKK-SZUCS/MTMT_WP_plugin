<?php
/**
 * Egy publikáció-kártya HTML-je — közös az "A" és "B" Elementor widget, illetve
 * az AJAX-fragment újratöltés között (docs/widget-design.md).
 *
 * Kizárólag már betöltött publikáció-sorokból (repository `get_list()`/`find()`
 * kimenete + opcionálisan `topic_area_labels`) épít HTML-t — nem hív se DB-t,
 * se az MTMT API-t. Minden MTMT-eredetű szöveget escape-el renderkor (CLAUDE.md §11).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Állapot nélküli renderelő.
 */
final class Mtmt_Card_Renderer {

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
	 * }
	 * @return string Kész HTML.
	 */
	public static function render( array $publication, array $display_options = array() ): string {
		$display_options = array_merge(
			array(
				'show_topic_area' => false,
				'show_doi_link'   => true,
				'show_sjr_badge'  => true,
				'citation_style'  => 'full',
			),
			$display_options
		);

		$title = trim( (string) ( $publication['title'] ?? '' ) );
		$link  = self::link_target( $publication, (bool) $display_options['show_doi_link'] );
		$full  = 'full' === $display_options['citation_style'];

		$out  = '<div class="mtmt-pub-card"' . ( $link ? ' data-href="' . esc_url( $link ) . '" tabindex="0" role="link"' : '' ) . '>';
		$out .= '<div class="mtmt-pub-card-media">' . self::render_media( $publication ) . self::render_type_badge( $publication ) . '</div>';
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

		$out .= '<p class="mtmt-pub-card-meta">';
		if ( $full && ! empty( $publication['source_title'] ) ) {
			$out .= '<em class="mtmt-pub-card-source">' . esc_html( $publication['source_title'] ) . '</em> &middot; ';
		}
		if ( ! empty( $publication['published_year'] ) ) {
			$out .= '<span class="mtmt-pub-card-year">' . esc_html( (string) $publication['published_year'] ) . '</span>';
		}
		if ( $display_options['show_sjr_badge'] && ! empty( $publication['sjr_quartile'] ) ) {
			$out .= ' <span class="mtmt-badge mtmt-badge-sjr ' . esc_attr( self::sjr_badge_class( (string) $publication['sjr_quartile'] ) ) . '">' . esc_html( (string) $publication['sjr_quartile'] ) . '</span>';
		}
		if ( $display_options['show_doi_link'] && ! empty( $publication['doi'] ) ) {
			$out .= ' <span class="mtmt-pub-card-doi">DOI: ' . esc_html( (string) $publication['doi'] ) . '</span>';
		}
		$out .= '</p>';

		if ( $full ) {
			$ext_ids_html = Mtmt_External_Id_Icons::render_buttons( $publication['external_ids'] ?? null );
			if ( '' !== $ext_ids_html ) {
				$out .= $ext_ids_html; // Mtmt_External_Id_Icons már maga escape-eli a tartalmát.
			}
		}

		$out .= '</div></div>';

		return $out;
	}

	/**
	 * DOI, ha van; különben (ha engedélyezve) az MTMT humán gui-linkje;
	 * különben üres string (a kártya ekkor nem kattintható egészben).
	 * VERIFIKÁLVA élesben, lásd CLAUDE.md §14/12 és docs/decisions.md #17.
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

		// Degradálás: nincs GD/font a szerveren -> CSS-overlay ugyanazzal az
		// alapképpel, amit a GD-s út is használna (Mtmt_Placeholder_Image::get_base_image_url()
		// az admin által beállított egyedi képet is figyelembe veszi) — a cím itt
		// CSS-sel van ráhelyezve, nem a képfájlba égetve.
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
