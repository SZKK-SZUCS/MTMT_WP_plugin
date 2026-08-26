<?php
/**
 * Generikus MTMT REST API kliens.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * wp_remote_get() wrapper: cond-építés, lapozás, retry/backoff, throttle.
 *
 * Nem publikáció-specifikus — bármelyik MTMT objektumtípusra (publication,
 * institute, author, …) használható, hogy Fázis 3-ban a szerző-autocomplete
 * is ugyanezt tudja hívni.
 */
final class Mtmt_Api_Client {

	private const BASE_URL = 'https://m2.mtmt.hu/api/';

	/**
	 * @var string
	 */
	private $user_agent;

	/**
	 * @var int
	 */
	private $timeout;

	/**
	 * @var float
	 */
	private $inter_page_delay;

	/**
	 * @var int
	 */
	private $max_retries;

	/**
	 * @param string $user_agent        Egyedi User-Agent (CLAUDE.md §5.1).
	 * @param int    $timeout           HTTP timeout másodpercben.
	 * @param float  $inter_page_delay  Késleltetés lapozáskor, másodpercben.
	 * @param int    $max_retries       Max próbálkozás 429/5xx/hálózati hibánál.
	 */
	public function __construct( string $user_agent = '', int $timeout = 20, float $inter_page_delay = 0.75, int $max_retries = 3 ) {
		$this->user_agent       = '' !== $user_agent ? $user_agent : $this->default_user_agent();
		$this->timeout          = $timeout;
		$this->inter_page_delay = $inter_page_delay;
		$this->max_retries      = max( 1, $max_retries );
	}

	/**
	 * Egy oldal lekérése.
	 *
	 * @param string                                            $object_type MTMT objektumtípus, pl. 'publication'.
	 * @param array<int,array{field:string,op:string,value:mixed}> $conditions  cond-feltételek, ÉS kapcsolattal.
	 * @param array<string,mixed>                               $params      page, size, depth, sort (string vagy string[]), groupBy, labelLang stb.
	 * @return array{paging:array,content:array[]}|WP_Error
	 */
	public function get_page( string $object_type, array $conditions, array $params = array() ) {
		$url = $this->build_url( $object_type, $conditions, $params );

		$response = $this->request_with_retry( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || ! array_key_exists( 'content', $decoded ) ) {
			return new WP_Error(
				'mtmt_bad_response',
				__( 'Váratlan MTMT API válasz (hiányzó content mező).', 'mtmt-sync' )
			);
		}

		return array(
			'paging'  => is_array( $decoded['paging'] ?? null ) ? $decoded['paging'] : array(),
			'content' => is_array( $decoded['content'] ) ? $decoded['content'] : array(),
		);
	}

	/**
	 * Végigoldalazza a teljes találati halmazt, oldalanként hívja az $on_page callbacket.
	 *
	 * Hibán megáll és WP_Error-t ad vissza (a már feldolgozott oldalak eredménye
	 * a callbacken keresztül már megtörtént, csak a hátralévő lapozás marad el).
	 *
	 * @param string   $object_type
	 * @param array    $conditions
	 * @param array    $params
	 * @param callable $on_page function( array $records, array $paging ): void
	 * @return true|WP_Error
	 */
	public function paginate( string $object_type, array $conditions, array $params, callable $on_page ) {
		$page = 1;
		$size = isset( $params['size'] ) ? (int) $params['size'] : 100;
		unset( $params['page'] );

		do {
			$page_params           = $params;
			$page_params['page']   = $page;
			$page_params['size']   = $size;

			$result = $this->get_page( $object_type, $conditions, $page_params );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$on_page( $result['content'], $result['paging'] );

			$is_last = ! empty( $result['paging']['last'] ) || empty( $result['content'] );
			++$page;

			if ( ! $is_last ) {
				usleep( (int) ( $this->inter_page_delay * 1000000 ) );
			}
		} while ( ! $is_last );

		return true;
	}

	/**
	 * @param string $object_type
	 * @param array  $conditions
	 * @param array  $params
	 * @return string
	 */
	private function build_url( string $object_type, array $conditions, array $params ): string {
		$params['format'] = 'json';

		$pairs = array();

		foreach ( $conditions as $condition ) {
			$value   = is_array( $condition['value'] ) ? implode( ',', $condition['value'] ) : (string) $condition['value'];
			$pairs[] = 'cond=' . rawurlencode( $condition['field'] . ';' . $condition['op'] . ';' . $value );
		}

		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $single ) {
					$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $single );
				}
				continue;
			}
			$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
		}

		return self::BASE_URL . rawurlencode( $object_type ) . '?' . implode( '&', $pairs );
	}

	/**
	 * @param string $url
	 * @return array|WP_Error wp_remote_get() nyers válasza.
	 */
	private function request_with_retry( string $url ) {
		$last_error = new WP_Error( 'mtmt_unknown_error', __( 'Ismeretlen MTMT API hiba.', 'mtmt-sync' ) );

		for ( $attempt = 1; $attempt <= $this->max_retries; $attempt++ ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => $this->timeout,
					'user-agent' => $this->user_agent,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
			} else {
				$code = wp_remote_retrieve_response_code( $response );

				if ( 200 === $code ) {
					return $response;
				}

				// Csak 429/5xx retry-elendő; egyéb 4xx kliens hiba, nincs értelme újrapróbálni.
				if ( 429 !== $code && $code < 500 ) {
					return new WP_Error(
						'mtmt_http_error',
						sprintf(
							/* translators: %d: HTTP státuszkód */
							__( 'MTMT API hiba: HTTP %d', 'mtmt-sync' ),
							$code
						),
						array( 'status' => $code )
					);
				}

				$last_error = new WP_Error(
					'mtmt_http_error',
					sprintf(
						/* translators: %d: HTTP státuszkód */
						__( 'MTMT API hiba: HTTP %d', 'mtmt-sync' ),
						$code
					),
					array( 'status' => $code )
				);
			}

			if ( $attempt < $this->max_retries ) {
				usleep( (int) ( 2 ** $attempt * 500000 ) ); // 1s, 2s, 4s, ...
			}
		}

		return $last_error;
	}

	/**
	 * @return string
	 */
	private function default_user_agent(): string {
		$version = defined( 'MTMT_VERSION' ) ? MTMT_VERSION : '0.0.0';
		return 'MTMT-Sync-WP-Plugin/' . $version . ' (+' . home_url( '/' ) . ')';
	}
}
