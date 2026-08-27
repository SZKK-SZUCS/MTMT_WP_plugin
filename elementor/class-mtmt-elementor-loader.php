<?php
/**
 * Elementor-widgetek regisztrálása. A widget-osztályok fatal hibát adnának
 * Elementor nélkül, ezért a `require`-ek csak az `elementor/widgets/register`
 * callback belsejében futnak — ez a hook Elementor nélkül sosem tüzel.
 *
 * FONTOS: az `elementor/widgets/register` és `elementor/elements/categories_registered`
 * hookokat FELTÉTEL NÉLKÜL, közvetlenül itt kell felakasztani — NEM egy
 * `elementor/loaded`-re épülő `boot()` mögé, mert az Elementor ezeket MÉG
 * `elementor/loaded` előtt elsüti; egy késleltetett feliratkozás lemaradna róla.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Elementor_Loader {

	public function init(): void {
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
		require_once MTMT_PLUGIN_DIR . 'elementor/trait-mtmt-widget-common-controls.php';
		require_once MTMT_PLUGIN_DIR . 'elementor/class-mtmt-widget-all-publications.php';
		$widgets_manager->register( new Mtmt_Widget_All_Publications() );

		// A "B" widget csak akkor jelenik meg, ha a "kiemelt cikk" funkció be van kapcsolva.
		if ( get_option( 'mtmt_enable_featured' ) ) {
			require_once MTMT_PLUGIN_DIR . 'elementor/class-mtmt-widget-topic-publications.php';
			$widgets_manager->register( new Mtmt_Widget_Topic_Publications() );
		}
	}

	public function enqueue_assets(): void {
		wp_enqueue_style( 'mtmt-widget', MTMT_PLUGIN_URL . 'assets/css/widget.css', array(), MTMT_VERSION );
		wp_enqueue_script( 'mtmt-widget', MTMT_PLUGIN_URL . 'assets/js/widget-frontend.js', array(), MTMT_VERSION, true );
	}
}
