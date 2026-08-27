<?php
/**
 * Egyéb azonosítós logó-gombok a widget-kártyán (CLAUDE.md §14/4).
 *
 * A DOI és az "Egyéb URL" külön oszlopban van (`doi`, `other_url`) — ide csak
 * a maradék identifier-ek tartoznak (`external_ids`, pl. WoS/Scopus/SZTAKI),
 * lásd Mtmt_Mapper::map_identifiers().
 *
 * Valódi hivatalos logókat (Scopus/WoS/PubMed stb.) nem csomagolunk be
 * forrás/licenc nélkül — egyelőre feliratos "pill" badge-eket adunk, amit
 * bármikor lecserélhetünk tényleges logó-fájlokra (lásd docs/widget-design.md).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Állapot nélküli renderelő.
 */
final class Mtmt_External_Id_Icons {

	/**
	 * Ismert forrásnevek rövid, kártyára szánt felirata. Ami nincs a listában,
	 * azt a nyers `source` névvel jelenítjük meg — új MTMT-forrás megjelenése
	 * esetén sem tűnik el semmi, csak nincs "szép" rövidítése.
	 *
	 * @var array<string,string>
	 */
	private const LABELS = array(
		'WoS'    => 'WoS',
		'Scopus' => 'Scopus',
		'SZTAKI' => 'SZTAKI',
		'PubMed' => 'PubMed',
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

			$label     = self::LABELS[ $source_name ] ?? $source_name;
			$buttons[] = sprintf(
				'<a class="mtmt-ext-id-badge" href="%1$s" target="_blank" rel="noopener noreferrer" title="%2$s">%2$s</a>',
				esc_url( $url ),
				esc_html( $label )
			);
		}

		if ( empty( $buttons ) ) {
			return '';
		}

		return '<div class="mtmt-ext-ids">' . implode( '', $buttons ) . '</div>';
	}
}
