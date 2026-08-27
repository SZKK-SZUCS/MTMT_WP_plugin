<?php
/**
 * Szerzőnév-lista formázása widget-kártyához (Fázis 5).
 *
 * A tárolt `authors_text` (Mtmt_Mapper::join_with_and()) mindig a TELJES
 * listát adja — ez a moderációs adminban jó, de sok szerzős cikkeknél
 * (10+) esetlen egy widget-kártyán. Ez az osztály az `authors_raw` JSON-ból
 * (már `listPosition` szerint rendezve, lásd Mtmt_Mapper::map_authors())
 * épít egy rövidített változatot, NEM az `authors_text` stringet vágja —
 * az törékeny lenne, ha egy név maga is vesszőt tartalmazna.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Állapot nélküli formázó.
 */
final class Mtmt_Author_Formatter {

	/**
	 * @param string|null $authors_raw_json Mtmt_Publication_Repository sorának `authors_raw` mezője.
	 * @param int         $max              Hány szerző jelenjen meg névvel, mielőtt levágunk.
	 * @return string Kártyára szánt, escape NÉLKÜLI szöveg (a hívó felelőssége az `esc_html()`).
	 */
	public static function format_for_card( ?string $authors_raw_json, int $max = 5 ): string {
		if ( ! $authors_raw_json ) {
			return '';
		}

		$authors = json_decode( $authors_raw_json, true );
		if ( ! is_array( $authors ) ) {
			return '';
		}

		// A mapper már listPosition szerint rendezve tárolja, de widget-oldalon
		// ne bízzunk a tárolt sorrendben — olcsó itt is rendezni.
		usort(
			$authors,
			static function ( $a, $b ) {
				return ( $a['listPosition'] ?? 0 ) <=> ( $b['listPosition'] ?? 0 );
			}
		);

		$names = array();
		foreach ( $authors as $author ) {
			$given  = trim( (string) ( $author['givenName'] ?? '' ) );
			$family = trim( (string) ( $author['familyName'] ?? '' ) );
			$full   = trim( $given . ' ' . $family );
			if ( '' !== $full ) {
				$names[] = $full;
			}
		}

		$total = count( $names );
		if ( 0 === $total ) {
			return '';
		}

		if ( $total <= $max ) {
			return self::join_with_and( $names );
		}

		$shown     = array_slice( $names, 0, $max );
		$remaining = $total - $max;

		/* translators: %d: a kártyán nem névvel felsorolt szerzők száma */
		return implode( ', ', $shown ) . ', ' . sprintf( _n( 'and %d more', 'and %d more', $remaining, 'mtmt-sync' ), $remaining );
	}

	/**
	 * "A, B, and C" — ugyanaz a konvenció, mint Mtmt_Mapper::join_with_and()
	 * (a megrendelő saját mintadokumentumában szereplő angol "and"-del,
	 * lásd CLAUDE.md §5.4). Szándékosan duplikált kis segédfüggvény, nem
	 * közös trait — a mapper már élesben teszteltre nem akarunk hozzányúlni.
	 *
	 * @param string[] $names
	 * @return string
	 */
	private static function join_with_and( array $names ): string {
		$count = count( $names );

		if ( 1 === $count ) {
			return $names[0];
		}

		$last = array_pop( $names );
		return implode( ', ', $names ) . ', and ' . $last;
	}
}
