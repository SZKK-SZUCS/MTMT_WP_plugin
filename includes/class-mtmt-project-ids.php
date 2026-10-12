<?php
/**
 * Projektazonosító(k) normalizálása — egy helyen, hogy az admin-mentés, a
 * DB-migráció, a szűrés és a megjelenítés ugyanazt a formát lássa.
 *
 * Tárolási forma a `project_ids` TEXT oszlopban: ", "-szel elválasztott,
 * trimmelt, (kis-/nagybetűre érzéketlenül) duplikátum-mentes lista, pl.
 * "TKP2021-NVA-23, K 123456". Erre a formára épül a szűrés
 * (`FIND_IN_SET(%s, REPLACE(project_ids, ', ', ','))`), ezért mentéskor
 * MINDIG a normalize()-on kell átmennie.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Project_Ids {

	/** Tárolási elválasztó (a szűrő-SQL ezt cseréli sima vesszőre). */
	public const SEPARATOR = ', ';

	/**
	 * Nyers szövegből azonosító-lista. Elválasztó: vessző, pontosvessző vagy
	 * sortörés (a szóköz NEM, mert pl. a "K 123456" egyetlen azonosító).
	 * Szándékosan NINCS hossz-csonkolás: a migráció a már rögzített (akár
	 * hosszabb, szabad szöveges) értékeket sem vághatja meg.
	 *
	 * @param string|null $raw
	 * @return string[]
	 */
	public static function parse( ?string $raw ): array {
		$raw = (string) $raw;
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$parts  = preg_split( '/[,;\r\n]+/u', $raw );
		$result = array();
		$seen   = array();

		foreach ( (array) $parts as $part ) {
			$id = trim( (string) preg_replace( '/\s+/u', ' ', (string) $part ) );
			if ( '' === $id ) {
				continue;
			}
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $id ) : strtolower( $id );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$result[]     = $id;
		}

		return $result;
	}

	/**
	 * Rövidített felirat lenyílókhoz — csak a MEGJELENÍTÉSHEZ (az érték
	 * mindig a teljes azonosító marad). A régi, szabad szövegként rögzített
	 * hosszú értékek így nem törik szét a szűrősort.
	 *
	 * @param string $id
	 * @return string
	 */
	public static function display_label( string $id ): string {
		return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $id, 0, 60, '…' ) : $id;
	}

	/**
	 * Tárolási forma (üres lista -> üres string).
	 *
	 * @param string|null $raw
	 * @return string
	 */
	public static function normalize( ?string $raw ): string {
		return implode( self::SEPARATOR, self::parse( $raw ) );
	}

	/**
	 * Több sor `project_ids` értékéből egyedi, természetes sorrendű lista
	 * (admin-szűrő, autocomplete, widget-szűrő).
	 *
	 * @param string[] $raw_values
	 * @return string[]
	 */
	public static function unique_from_rows( array $raw_values ): array {
		$all  = array();
		$seen = array();
		foreach ( $raw_values as $raw ) {
			foreach ( self::parse( (string) $raw ) as $id ) {
				$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $id ) : strtolower( $id );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$all[]        = $id;
			}
		}
		natcasesort( $all );
		return array_values( $all );
	}
}
