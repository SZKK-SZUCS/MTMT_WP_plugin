<?php
/**
 * MTMT nyers publikáció-objektum -> tábla-sor mapper.
 *
 * Szigorúan a docs/field-map.md szerint. Csak a "MTMT-forrású mezők" blokkot
 * (CLAUDE.md §4.1) adja vissza; a kézi/housekeeping oszlopokhoz nem nyúl,
 * azokat a repository kezeli.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Állapot nélküli mapper: egy nyers MTMT publication-objektumból épít egy
 * asszociatív tömböt a wp_mtmt_publications MTMT-forrású oszlopaihoz.
 */
final class Mtmt_Mapper {

	/**
	 * Legjobb SJR-negyed sorrend (docs/field-map.md „ratings[] -> SJR").
	 *
	 * @var array<string,int>
	 */
	private const SJR_RANK = array(
		'D1' => 5,
		'Q1' => 4,
		'Q2' => 3,
		'Q3' => 2,
		'Q4' => 1,
	);

	/**
	 * @param array $raw Egy content[] elem a `depth=1` publication válaszból.
	 * @return array MTMT-forrású oszlopok (mtid + 4.1 "MTMT-forrású mezők" blokk).
	 */
	public static function map_publication( array $raw ): array {
		$identifiers = self::map_identifiers( $raw['identifiers'] ?? array() );
		$authors     = self::map_authors( $raw['authorships'] ?? array() );

		return array(
			'mtid'           => isset( $raw['mtid'] ) ? absint( $raw['mtid'] ) : 0,
			'title'          => $raw['title'] ?? null,
			'authors_text'   => $authors['text'],
			'authors_raw'    => wp_json_encode( $authors['raw'] ),
			'pub_type'       => $raw['type']['label'] ?? null,
			'pub_category'   => $raw['subType']['name'] ?? null,
			'pub_character'  => $raw['category']['label'] ?? null,
			'language'       => self::map_languages( $raw['languages'] ?? array() ),
			'source_title'   => self::map_source_title( $raw ),
			'volume'         => $raw['volume'] ?? null,
			'issue'          => $raw['issue'] ?? null,
			'page_range'     => self::map_page_range( $raw ),
			'published_year' => isset( $raw['publishedYear'] ) ? (int) $raw['publishedYear'] : null,
			'doi'            => $identifiers['doi'],
			'issn'           => $raw['journal']['pIssn'] ?? ( $raw['journal']['eIssn'] ?? null ),
			'sjr_quartile'   => self::map_sjr( $raw['ratings'] ?? array(), $raw['ratingsForSort'] ?? null ),
			'norway_level'   => null, // Nyitott pont, lásd docs/decisions.md #5.
			'external_ids'   => wp_json_encode( $identifiers['external'] ),
			'other_url'      => $identifiers['other_url'],
			'funding_text'   => null, // A publikus API nem adja, lásd docs/decisions.md #3.
			'mtmt_state'     => $raw['status'] ?? null,
			'raw_json'       => wp_json_encode( $raw ),
		);
	}

	/**
	 * @param array $authorships
	 * @return array{text:string,raw:array[]}
	 */
	private static function map_authors( array $authorships ): array {
		usort(
			$authorships,
			static function ( $a, $b ) {
				return ( $a['listPosition'] ?? 0 ) <=> ( $b['listPosition'] ?? 0 );
			}
		);

		$names = array();
		$raw   = array();

		foreach ( $authorships as $authorship ) {
			$given  = trim( (string) ( $authorship['givenName'] ?? '' ) );
			$family = trim( (string) ( $authorship['familyName'] ?? '' ) );
			$full   = trim( $given . ' ' . $family );

			if ( '' !== $full ) {
				$names[] = $full;
			}

			$raw[] = array(
				'familyName'    => $authorship['familyName'] ?? null,
				'givenName'     => $authorship['givenName'] ?? null,
				'listPosition'  => $authorship['listPosition'] ?? null,
				'corresponding' => ! empty( $authorship['corresponding'] ),
				'authorMtid'    => $authorship['author']['mtid'] ?? null,
			);
		}

		return array(
			'text' => self::join_with_and( $names ),
			'raw'  => $raw,
		);
	}

	/**
	 * "A, B, and C" formátum (docs/field-map.md 5.4 példa szerint, angol "and"-del).
	 *
	 * @param string[] $names
	 * @return string
	 */
	private static function join_with_and( array $names ): string {
		$count = count( $names );

		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $names[0];
		}

		$last = array_pop( $names );
		return implode( ', ', $names ) . ', and ' . $last;
	}

	/**
	 * @param array $languages
	 * @return string|null
	 */
	private static function map_languages( array $languages ): ?string {
		$labels = array();
		foreach ( $languages as $language ) {
			if ( ! empty( $language['label'] ) ) {
				$labels[] = $language['label'];
			}
		}
		return $labels ? implode( ', ', $labels ) : null;
	}

	/**
	 * journal.label (JournalArticle) vagy book.label/book.title (BookChapter) —
	 * a kulcs JELENLÉTE dönt, nem az otype string.
	 *
	 * @param array $raw
	 * @return string|null
	 */
	private static function map_source_title( array $raw ): ?string {
		if ( ! empty( $raw['journal']['label'] ) ) {
			return $raw['journal']['label'];
		}
		if ( ! empty( $raw['book']['label'] ) ) {
			return $raw['book']['label'];
		}
		if ( ! empty( $raw['book']['title'] ) ) {
			return $raw['book']['title'];
		}
		return null;
	}

	/**
	 * firstPage-lastPage, különben internalId ("Paper: ..."), különben null.
	 *
	 * @param array $raw
	 * @return string|null
	 */
	private static function map_page_range( array $raw ): ?string {
		if ( ! empty( $raw['firstPage'] ) && ! empty( $raw['lastPage'] ) ) {
			return $raw['firstPage'] . '–' . $raw['lastPage'];
		}
		if ( ! empty( $raw['internalId'] ) ) {
			return 'Paper: ' . $raw['internalId'];
		}
		return null;
	}

	/**
	 * Legjobb SjrRating.ranking (D1 > Q1 > Q2 > Q3 > Q4), fallback ratingsForSort.
	 *
	 * @param array       $ratings
	 * @param string|null $ratings_for_sort
	 * @return string|null
	 */
	private static function map_sjr( array $ratings, ?string $ratings_for_sort ): ?string {
		$best      = null;
		$best_rank = 0;

		foreach ( $ratings as $rating ) {
			if ( 'SjrRating' !== ( $rating['otype'] ?? '' ) ) {
				continue;
			}

			$ranking = $rating['ranking'] ?? null;
			if ( null === $ranking || ! isset( self::SJR_RANK[ $ranking ] ) ) {
				continue;
			}

			if ( self::SJR_RANK[ $ranking ] > $best_rank ) {
				$best_rank = self::SJR_RANK[ $ranking ];
				$best      = $ranking;
			}
		}

		return $best ?? $ratings_for_sort;
	}

	/**
	 * DOI / Egyéb URL / minden más identifier szétválogatása.
	 *
	 * @param array $identifiers
	 * @return array{doi:?string,other_url:?string,external:array[]}
	 */
	private static function map_identifiers( array $identifiers ): array {
		$doi       = null;
		$other_url = null;
		$external  = array();

		foreach ( $identifiers as $identifier ) {
			$source_name = $identifier['source']['name'] ?? '';
			$value       = $identifier['idValue'] ?? null;

			if ( null === $value ) {
				continue;
			}

			if ( 'DOI' === $source_name ) {
				$doi = $value;
			} elseif ( 'Egyéb URL' === $source_name ) {
				$other_url = $value;
			} else {
				$external[] = array(
					'source'  => $source_name,
					'idValue' => $value,
					'realUrl' => $identifier['realUrl'] ?? null,
				);
			}
		}

		return array(
			'doi'       => $doi,
			'other_url' => $other_url,
			'external'  => $external,
		);
	}
}
