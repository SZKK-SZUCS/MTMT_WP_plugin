<?php
/**
 * Nyilvános AJAX-végpont a widget-frontend (keresés/év-váltás/lapozás/terület-szűrő)
 * kiszolgálásához (Fázis 5).
 *
 * Szerver-oldalon renderelt HTML-fragmentet ad vissza (nem JSON+kliens-template) —
 * ez illeszkedik a "vanilla JS, nincs build-pipeline" megkötéshez (CLAUDE.md §2),
 * és a Mtmt_Card_Renderer-t közvetlenül újrahasználja.
 *
 * A végpont KIZÁRÓLAG `status='approved'` publikus adatot ad ki (Mtmt_Widget_Data
 * mindig approved-ra szűkít) — ezért nem admin-jogosultsághoz kötött végpont
 * (`wp_ajax_nopriv_` is regisztrálva), a nonce itt nem hozzáférés-védelem, hanem
 * a szokásos WP AJAX-minta (véletlen/automata lekérdezések kiszűrése).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * AJAX handler + a hozzá tartozó lapozó-HTML.
 */
final class Mtmt_Widget_Ajax {

	public const ACTION       = 'mtmt_widget_query';
	public const NONCE_ACTION = 'mtmt_widget_query';

	/** @var Mtmt_Widget_Data */
	private $widget_data;

	/**
	 * @param Mtmt_Widget_Data $widget_data
	 */
	public function __construct( Mtmt_Widget_Data $widget_data ) {
		$this->widget_data = $widget_data;
	}

	/**
	 * Hook-regisztráció.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * A tényleges kérés-kiszolgálás.
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$args = array(
			'year'          => isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0,
			'area_id'       => isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0,
			'profile_id'    => isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0,
			'featured_only' => ! empty( $_POST['featured_only'] ),
			'search'        => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'paged'         => isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1,
			// Felülről korlátozva -> egy kliens ne tudjon indokolatlanul nagy lekérdezést kikényszeríteni.
			'per_page'      => isset( $_POST['per_page'] ) ? min( 50, max( 1, absint( $_POST['per_page'] ) ) ) : 20,
		);

		$display_options = array(
			'show_topic_area'   => ! empty( $_POST['show_topic_area'] ),
			'show_doi_link'     => ! empty( $_POST['show_doi_link'] ),
			'show_sjr_badge'    => ! empty( $_POST['show_sjr_badge'] ),
			'citation_style'    => ( isset( $_POST['citation_style'] ) && 'compact' === $_POST['citation_style'] ) ? 'compact' : 'full',
			'ext_id_badge_mode' => ( isset( $_POST['ext_id_badge_mode'] ) && in_array( $_POST['ext_id_badge_mode'], array( 'icon', 'text' ), true ) ) ? $_POST['ext_id_badge_mode'] : 'both',
		);

		// A widget-példány Tartalom fülén szerkeszthető feliratok (Mtmt_Widget_Common_Controls) —
		// a JS a data-empty-text/data-prev-label/data-next-label attribútumokból küldi vissza
		// minden AJAX-kérésnél, hogy a fragment-csere is a testre szabott szöveget mutassa.
		$empty_state_text = isset( $_POST['empty_state_text'] ) ? sanitize_text_field( wp_unslash( $_POST['empty_state_text'] ) ) : '';
		$prev_label       = isset( $_POST['pagination_prev_label'] ) ? sanitize_text_field( wp_unslash( $_POST['pagination_prev_label'] ) ) : '';
		$next_label       = isset( $_POST['pagination_next_label'] ) ? sanitize_text_field( wp_unslash( $_POST['pagination_next_label'] ) ) : '';

		if ( '' === $empty_state_text ) {
			$empty_state_text = __( 'Nincs a szűrésnek megfelelő publikáció.', 'mtmt-sync' );
		}
		if ( '' === $prev_label ) {
			$prev_label = __( 'Előző', 'mtmt-sync' );
		}
		if ( '' === $next_label ) {
			$next_label = __( 'Következő', 'mtmt-sync' );
		}

		$result = $this->widget_data->query( $args );

		$html = '';
		if ( empty( $result['items'] ) ) {
			$html = '<p class="mtmt-widget-empty">' . esc_html( $empty_state_text ) . '</p>';
		} else {
			foreach ( $result['items'] as $item ) {
				$html .= Mtmt_Card_Renderer::render( $item, $display_options );
			}
		}

		$total_pages = $args['per_page'] > 0 ? (int) ceil( $result['total'] / $args['per_page'] ) : 1;
		$total_pages = max( 1, $total_pages );

		wp_send_json_success(
			array(
				'html'        => $html,
				'total'       => (int) $result['total'],
				'total_pages' => $total_pages,
				'paged'       => $args['paged'],
				'pagination'  => self::render_pagination( $args['paged'], $total_pages, $prev_label, $next_label ),
			)
		);
	}

	/**
	 * Számozott lapozó, ellipszissel nagy oldalszámnál (nem "load more"/infinite
	 * scroll, docs/widget-design.md; a számozott forma a referencia-dizájn
	 * alapján, docs/decisions.md #95 — korábban csak Előző/Következő volt).
	 * Public: az Elementor-widgetek is ezt hívják a kezdeti (nem-AJAX) szerver-oldali
	 * render()-nél, hogy ne legyen két hely, ahol a lapozó-HTML épül.
	 *
	 * A `data-page` attribútum minden gombon jelen van (a jelenlegi oldalé
	 * kivételével, ami `<span>`, nem gomb) — az `assets/js/widget-frontend.js`
	 * meglévő, delegált `.mtmt-page-btn` kattintás-kezelője változtatás nélkül
	 * működik minden oldalszám-gombbal, nem csak Előző/Következővel.
	 *
	 * @param int    $current
	 * @param int    $total_pages
	 * @param string $prev_label Widget-szinten szerkeszthető felirat (Mtmt_Widget_Common_Controls) — mostantól aria-label, a gomb maga ikon.
	 * @param string $next_label Widget-szinten szerkeszthető felirat (Mtmt_Widget_Common_Controls) — mostantól aria-label, a gomb maga ikon.
	 * @return string
	 */
	public static function render_pagination( int $current, int $total_pages, string $prev_label = '', string $next_label = '' ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}

		if ( '' === $prev_label ) {
			$prev_label = __( 'Előző', 'mtmt-sync' );
		}
		if ( '' === $next_label ) {
			$next_label = __( 'Következő', 'mtmt-sync' );
		}

		$prev_target = max( 1, $current - 1 );
		$next_target = min( $total_pages, $current + 1 );

		$out  = '<nav class="mtmt-pagination" aria-label="' . esc_attr__( 'Lapozó', 'mtmt-sync' ) . '">';
		$out .= '<button type="button" class="mtmt-page-btn mtmt-page-btn-nav" data-page="' . esc_attr( (string) $prev_target ) . '" aria-label="' . esc_attr( $prev_label ) . '"' . ( 1 === $current ? ' disabled' : '' ) . '>&larr;</button>';

		foreach ( self::pagination_page_numbers( $current, $total_pages ) as $item ) {
			if ( null === $item ) {
				$out .= '<span class="mtmt-page-ellipsis">&hellip;</span>';
				continue;
			}
			if ( $item === $current ) {
				$out .= '<span class="mtmt-page-btn mtmt-page-btn-number is-current" aria-current="page">' . esc_html( (string) $item ) . '</span>';
			} else {
				$out .= '<button type="button" class="mtmt-page-btn mtmt-page-btn-number" data-page="' . esc_attr( (string) $item ) . '">' . esc_html( (string) $item ) . '</button>';
			}
		}

		$out .= '<button type="button" class="mtmt-page-btn mtmt-page-btn-nav" data-page="' . esc_attr( (string) $next_target ) . '" aria-label="' . esc_attr( $next_label ) . '"' . ( $current === $total_pages ? ' disabled' : '' ) . '>&rarr;</button>';
		$out .= '</nav>';

		return $out;
	}

	/**
	 * Mely oldalszámok jelenjenek meg a lapozóban: mindig az első és az utolsó,
	 * plusz a jelenlegi ±1 szomszédja; a köztük lévő kihagyást `null` (ellipszis)
	 * jelöli. Kis oldalszámnál (a "lyuk" sosem nagyobb 1-nél) gyakorlatilag
	 * minden oldal megjelenik, nagy oldalszámnál összecsukódik — ugyanaz a minta,
	 * mint a referencia-képen ("1 2 3 … 8").
	 *
	 * @param int $current
	 * @param int $total
	 * @return array<int,int|null>
	 */
	private static function pagination_page_numbers( int $current, int $total ): array {
		// window=2 -> a referencia-képen látott "1 2 3 … 8" mintát adja (nem
		// csak "1 2 … 8"-at) az 1. oldalon állva, szimmetrikusan az utolsó
		// oldalnál is.
		$window = 2;
		$pages  = array();
		for ( $i = 1; $i <= $total; $i++ ) {
			if ( 1 === $i || $total === $i || ( $i >= $current - $window && $i <= $current + $window ) ) {
				$pages[] = $i;
			}
		}

		$result = array();
		$prev   = null;
		foreach ( $pages as $page ) {
			if ( null !== $prev && $page - $prev > 1 ) {
				$result[] = null;
			}
			$result[] = $page;
			$prev     = $page;
		}
		return $result;
	}
}
