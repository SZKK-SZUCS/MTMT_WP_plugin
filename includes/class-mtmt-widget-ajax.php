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
			'show_topic_area' => ! empty( $_POST['show_topic_area'] ),
			'show_doi_link'   => ! empty( $_POST['show_doi_link'] ),
			'show_sjr_badge'  => ! empty( $_POST['show_sjr_badge'] ),
			'citation_style'  => ( isset( $_POST['citation_style'] ) && 'compact' === $_POST['citation_style'] ) ? 'compact' : 'full',
		);

		$result = $this->widget_data->query( $args );

		$html = '';
		if ( empty( $result['items'] ) ) {
			$html = '<p class="mtmt-widget-empty">' . esc_html__( 'Nincs a szűrésnek megfelelő publikáció.', 'mtmt-sync' ) . '</p>';
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
				'pagination'  => self::render_pagination( $args['paged'], $total_pages ),
			)
		);
	}

	/**
	 * Számozott lapozó (nem "load more"/infinite scroll, docs/widget-design.md).
	 * Public: az Elementor-widgetek is ezt hívják a kezdeti (nem-AJAX) szerver-oldali
	 * render()-nél, hogy ne legyen két hely, ahol a lapozó-HTML épül.
	 *
	 * @param int $current
	 * @param int $total_pages
	 * @return string
	 */
	public static function render_pagination( int $current, int $total_pages ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$out = '<nav class="mtmt-pagination">';
		if ( $current > 1 ) {
			$out .= '<button type="button" class="mtmt-page-btn" data-page="' . esc_attr( (string) ( $current - 1 ) ) . '">&larr; ' . esc_html__( 'Előző', 'mtmt-sync' ) . '</button>';
		}
		$out .= '<span class="mtmt-pagination-status">' . sprintf(
			/* translators: 1: jelenlegi oldal, 2: összes oldal */
			esc_html__( '%1$d. / %2$d oldal', 'mtmt-sync' ),
			$current,
			$total_pages
		) . '</span>';
		if ( $current < $total_pages ) {
			$out .= '<button type="button" class="mtmt-page-btn" data-page="' . esc_attr( (string) ( $current + 1 ) ) . '">' . esc_html__( 'Következő', 'mtmt-sync' ) . ' &rarr;</button>';
		}
		$out .= '</nav>';

		return $out;
	}
}
