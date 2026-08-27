<?php
/**
 * Egyéb azonosítós logó-gombok a widget-kártyán (CLAUDE.md §14/4).
 *
 * A DOI és az "Egyéb URL" külön oszlopban van (`doi`, `other_url`) — ide csak
 * a maradék identifier-ek tartoznak (`external_ids`, pl. WoS/Scopus/SZTAKI),
 * lásd Mtmt_Mapper::map_identifiers().
 *
 * FRISSÍTVE (0.9 előkészítés, 2026-08): ha a pluginba be van csomagolva egy
 * egyszínű SVG-ikon az adott forráshoz (`assets/img/icons/{slug}.svg`), azt
 * inline-olja (nem `<img>`-ként — CSS `currentColor`-ral színezhető marad,
 * lásd docs/decisions.md #81). Ikon-fájl hiányában szépen visszaesik a
 * korábbi feliratos "pill" badge-re — egyik forrásnál sem kötelező az SVG.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Állapot nélküli renderelő.
 */
final class Mtmt_External_Id_Icons {

	/**
	 * Ismert forrásnevek rövid, kártyára szánt felirata ÉS az ikon-fájlnév
	 * alapja (`assets/img/icons/{slug}.svg`). Ami nincs a listában, azt a
	 * nyers `source` névvel jelenítjük meg, SVG-ikon nélkül — új MTMT-forrás
	 * megjelenése esetén sem tűnik el semmi, csak nincs "szép" rövidítése/ikonja.
	 *
	 * @var array<string,array{label:string,slug:string}>
	 */
	private const SOURCES = array(
		'WoS'    => array(
			'label' => 'WoS',
			'slug'  => 'wos',
		),
		'Scopus' => array(
			'label' => 'Scopus',
			'slug'  => 'scopus',
		),
		'SZTAKI' => array(
			'label' => 'SZTAKI',
			'slug'  => 'sztaki',
		),
		'PubMed' => array(
			'label' => 'PubMed',
			'slug'  => 'pubmed',
		),
	);

	/**
	 * @param string|null $external_ids_json Mtmt_Publication_Repository sorának `external_ids` mezője.
	 * @return string Kész, escape-elt HTML (üres string, ha nincs egyéb azonosító).
	 */
	public static function render_buttons( ?string $external_ids_json ): string {
		if ( ! $external_ids_json ) {
			return '';
		}

		$ids = json_decode( $external_ids_json, true );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return '';
		}

		$buttons = array();
		foreach ( $ids as $entry ) {
			$source_name = trim( (string) ( $entry['source'] ?? '' ) );
			$url         = $entry['realUrl'] ?? '';

			if ( '' === $source_name || empty( $url ) ) {
				continue;
			}

			$known = self::SOURCES[ $source_name ] ?? null;
			$label = $known['label'] ?? $source_name;
			$icon  = $known ? self::get_icon_svg( $known['slug'] ) : null;

			$buttons[] = sprintf(
				'<a class="mtmt-ext-id-badge%3$s" href="%1$s" target="_blank" rel="noopener noreferrer" title="%2$s">%4$s%2$s</a>',
				esc_url( $url ),
				esc_html( $label ),
				$icon ? ' mtmt-ext-id-badge-icon' : '',
				$icon ? '<span class="mtmt-ext-id-icon">' . $icon . '</span>' : ''
			);
		}

		if ( empty( $buttons ) ) {
			return '';
		}

		return '<div class="mtmt-ext-ids">' . implode( '', $buttons ) . '</div>';
	}

	/**
	 * Becsomagolt, EGYSZÍNŰ SVG-ikon inline-tartalma, ha a fájl létezik —
	 * NULL, ha még nincs elhelyezve. A fájl TARTALMA a pluginhoz csomagolt,
	 * fejlesztő által elhelyezett, MEGBÍZHATÓ erőforrás (nem látogatói input),
	 * ezért közvetlenül kerül a kimenetbe — ugyanaz a bizalmi szint, mint a
	 * becsomagolt placeholder-kép/email-logó fájloknál.
	 *
	 * Az esetleges explicit `fill="..."` attribútumokat eltávolítjuk, hogy a
	 * CSS (`fill: currentColor`) mindig tudja színezni az ikont, függetlenül
	 * attól, hogyan lett exportálva az eredeti SVG.
	 *
	 * @param string $slug
	 * @return string|null
	 */
	private static function get_icon_svg( string $slug ): ?string {
		static $cache = array();

		if ( array_key_exists( $slug, $cache ) ) {
			return $cache[ $slug ];
		}

		$path = MTMT_PLUGIN_DIR . 'assets/img/icons/' . $slug . '.svg';
		if ( ! file_exists( $path ) ) {
			$cache[ $slug ] = null;
			return null;
		}

		$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $svg ) {
			$cache[ $slug ] = null;
			return null;
		}

		// Az XML-prológ/DOCTYPE nem érvényes egy HTML-dokumentumba ágyazva.
		$svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg );
		$svg = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $svg );
		$svg = preg_replace( '/\bfill="[^"]*"/i', '', $svg );
		$svg = trim( $svg );

		$cache[ $slug ] = $svg;
		return $svg;
	}
}
