<?php
/**
 * Elementor-widgetek regisztrálása, függőség-ellenőrzéssel (CLAUDE.md §2:
 * "Elementor widget; a widgetnél ellenőrizd a függőséget, és degradálj
 * szépen, ha nincs").
 *
 * A widget-osztályok `\Elementor\Widget_Base`-t terjesztenek ki — ezeket a
 * fájlokat NEM szabad betölteni, ha az Elementor nincs aktiválva (fatal hibát
 * adna). Ezért mindent az `elementor/loaded` action mögé kötünk: ha az
 * Elementor nincs jelen, ez az action sosem fut le, tehát a `require`-ek sem.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Csak akkor csinál bármit, ha az Elementor ténylegesen be van töltve.
 */
final class Mtmt_Elementor_Loader {

	/**
	 * Regisztrálja az `elementor/loaded` horgot — magát a widget-regisztrációt
	 * csak EZUTÁN, ha az Elementor tényleg fut.
	 */
	public function init(): void {
		add_action( 'elementor/loaded', array( $this, 'boot' ) );
	}

	/**
	 * Csak Elementor jelenlétében fut.
	 */
	public function boot(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Saját "MTMT" kategória az Elementor widget-panelen, hogy a két widget
	 * ne vesszen el az általános listában.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'mtmt',
			array(
				'title' => __( 'MTMT', 'mtmt-sync' ),
				'icon'  => 'fa fa-graduation-cap',
			)
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register_widgets( $widgets_manager ): void {
		require_once MTMT_PLUGIN_DIR . 'elementor/class-mtmt-widget-all-publications.php';
		$widgets_manager->register( new Mtmt_Widget_All_Publications() );

		// A "B" widget csak akkor jelenik meg a widget-listában, ha a "kiemelt
		// cikk" funkció be van kapcsolva — ez a regisztráció feltétele, FÜGGETLENÜL
		// a "szakmai terület" toggle-től (CLAUDE.md §14/11, docs/widget-design.md).
		if ( get_option( 'mtmt_enable_featured' ) ) {
			require_once MTMT_PLUGIN_DIR . 'elementor/class-mtmt-widget-topic-publications.php';
			$widgets_manager->register( new Mtmt_Widget_Topic_Publications() );
		}
	}

	/**
	 * Közös widget CSS/JS — csak akkor kerül be, ha ténylegesen van a lapon
	 * MTMT-widget (Elementor `after_enqueue_styles` már az elem-renderelés
	 * után fut, de a wp_enqueue itt még mindig a `<head>`-be kerül; az
	 * egyszerűség kedvéért mindig enqueue-oljuk Elementor-szerkesztés/-frontend
	 * kontextusban, dupla enqueue nem gond, a WP dedupe-olja).
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style( 'mtmt-widget', MTMT_PLUGIN_URL . 'assets/css/widget.css', array(), MTMT_VERSION );
		wp_enqueue_script( 'mtmt-widget', MTMT_PLUGIN_URL . 'assets/js/widget-frontend.js', array(), MTMT_VERSION, true );
	}
}
