<?php
/**
 * Megosztható keresés-link: a listázó widgetek keresőmezője és a "keresés-gomb"
 * widget közös szabályai egy helyen (URL-paraméter neve, a listázó oldal
 * beállítása, link-összeállítás).
 *
 * A paraméter neve szándékosan előtagozott (`mtmt_q`, nem `q`/`s`), hogy ne
 * ütközzön a téma vagy más pluginok keresésével.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keresés-link szabályok (paraméter-név, listázó oldal, URL-építés).
 */
final class Mtmt_Search_Link {

	/** Az URL-paraméter neve, amiben a keresett szöveg utazik. */
	public const QUERY_ARG = 'mtmt_q';

	/** A listázó oldal (ahol a listázó widget van) WP oldal-azonosítója. */
	public const OPTION_LIST_PAGE = 'mtmt_list_page_id';

	/** Védelem a hosszú URL-ek és a visszaélés ellen. */
	public const MAX_LENGTH = 200;

	/**
	 * A Beállításokban megadott listázó oldal permalinkje; üres string, ha nincs
	 * beállítva, vagy az oldal már nem létezik / nem publikus.
	 */
	public static function get_list_page_url(): string {
		$page_id = absint( get_option( self::OPTION_LIST_PAGE ) );
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? (string) $url : '';
	}

	/**
	 * A keresett szöveg hozzáfűzése egy oldal URL-jéhez. Meglévő query-string és
	 * #horgony megmarad, egy korábbi `mtmt_q` érték felülíródik. Üres keresésnél
	 * a paraméter nem kerül az URL-be (a link simán a listázó oldalra visz).
	 *
	 * @param string $page_url A listázó oldal URL-je.
	 * @param string $query    A keresett szöveg (kódolatlan).
	 */
	public static function build_url( string $page_url, string $query ): string {
		$query = trim( wp_strip_all_tags( $query ) );
		if ( function_exists( 'mb_substr' ) ) {
			$query = mb_substr( $query, 0, self::MAX_LENGTH );
		} else {
			$query = substr( $query, 0, self::MAX_LENGTH );
		}

		$fragment = '';
		$hash_pos = strpos( $page_url, '#' );
		if ( false !== $hash_pos ) {
			$fragment = substr( $page_url, $hash_pos );
			$page_url = substr( $page_url, 0, $hash_pos );
		}

		$page_url = remove_query_arg( self::QUERY_ARG, $page_url );

		if ( '' !== $query ) {
			// Kézzel fűzzük hozzá: az add_query_arg() nem kódolja az értéket.
			$separator = false === strpos( $page_url, '?' ) ? '?' : '&';
			$page_url .= $separator . self::QUERY_ARG . '=' . rawurlencode( $query );
		}

		return $page_url . $fragment;
	}
}
