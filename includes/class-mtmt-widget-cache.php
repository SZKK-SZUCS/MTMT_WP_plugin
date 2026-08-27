<?php
/**
 * Widget-lekérdezés cache-verzió — a `Mtmt_Widget_Data` tranziens-cache
 * kulcsainak része (Fázis 5 utólagos javítás, élő visszajelzés alapján).
 *
 * Az eredeti terv (docs/decisions.md #53) a sima 5 perces TTL-t "elfogadható
 * egyszerűsítésnek" nevezte — élesben viszont zavaróan érezhető volt, hogy egy
 * jóváhagyás után a frontend akár 5 percig a régi listát mutatta. Ahelyett,
 * hogy lerövidítenénk a TTL-t (ami csak enyhítené, nem oldaná meg, és többet
 * terhelné a DB-t), egy verziószámlálót vezetünk be: minden olyan admin-írás,
 * ami widget-láthatóságot érinthet (jóváhagyás/elutasítás, tömeges művelet,
 * gazdagítás, terület-hozzárendelés, egy teljes sync-futás), NÖVELI ezt a
 * számlálót — a `Mtmt_Widget_Data` pedig a cache-kulcsba beleszámítja, így egy
 * ilyen írás után az ELSŐ következő frontend-lekérdezés automatikusan
 * "cache-miss" lesz, a többi (változatlan) lekérdezés viszont továbbra is a
 * gyors tranziens-útvonalat használja.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Egyetlen, könnyű `wp_options` bejegyzés — nem autoload (nem kell minden
 * oldalbetöltéskor), csak akkor olvassuk, amikor ténylegesen widget-lekérdezés
 * történik.
 */
final class Mtmt_Widget_Cache {

	private const OPTION = 'mtmt_widget_cache_version';

	/**
	 * @return int
	 */
	public static function version(): int {
		return (int) get_option( self::OPTION, 1 );
	}

	/**
	 * Meghívandó minden olyan írás után, ami megváltoztathatja, mit lát a
	 * widget (státusz, kiemelés, gazdagítás, terület-hozzárendelés, sync-futás).
	 */
	public static function bump(): void {
		update_option( self::OPTION, self::version() + 1, false );
	}
}
