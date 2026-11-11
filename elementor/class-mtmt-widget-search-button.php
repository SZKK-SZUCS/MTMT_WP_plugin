<?php
/**
 * "Keresés-gomb" widget: egy gomb, ami a listázó oldalra mutat, a keresett
 * szöveggel előtöltve (`?mtmt_q=…`). A listázó widget keresőmezője az URL-ből
 * olvassa vissza az értéket (assets/js/widget-frontend.js).
 *
 * A stílus-fül szándékosan a natív Elementor gomb teljes eszköztárát adja
 * (normál/hover állapot: szín, háttér + színátmenet, keret, árnyék,
 * szöveg-árnyék/körvonal, hover-animáció, átmenet, transzformáció).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Widget_Search_Button extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'mtmt_search_button';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'MTMT keresés-gomb', 'mtmt-sync' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-button';
	}

	/**
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'mtmt' );
	}

	/**
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'mtmt', 'gomb', 'button', 'keresés', 'search', 'link', 'publikáció' );
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_button_style_controls();
		$this->register_icon_style_controls();
	}

	private function register_content_controls(): void {
		$this->start_controls_section(
			'mtmt_btn_link_section',
			array(
				'label' => __( 'Keresés-link', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'search_value',
			array(
				'label'       => __( 'Keresési érték', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => __( 'pl. autonóm jármű', 'mtmt-sync' ),
				'description' => __( 'Ez a szöveg kerül a listázó oldal keresőmezőjébe. Üresen hagyva a gomb simán a listázó oldalra visz.', 'mtmt-sync' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'list_page',
			array(
				'label'         => __( 'Listázó oldal', 'mtmt-sync' ),
				'type'          => \Elementor\Controls_Manager::URL,
				'label_block'   => true,
				'show_external' => true,
				'placeholder'   => __( 'Oldal keresése vagy URL', 'mtmt-sync' ),
				// Az alapérték a Beállításokban megadott listázó oldal. Az Elementor
				// a nem módosított controlnál ezt adja vissza, így a Beállítások
				// későbbi átírása a még nem felülírt gombokra is érvényes.
				'default'       => array(
					'url'         => Mtmt_Search_Link::get_list_page_url(),
					'is_external' => '',
					'nofollow'    => '',
				),
				'description'   => __( 'Alapértelmezetten a plugin Beállításaiban megadott listázó oldal. Itt gombonként felülírhatod. Ha törlöd, a Beállítások értéke lép életbe.', 'mtmt-sync' ),
				'dynamic'       => array( 'active' => true ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_btn_content_section',
			array(
				'label' => __( 'Gomb', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Szöveg', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Publikációk megtekintése', 'mtmt-sync' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'button_icon',
			array(
				'label'       => __( 'Ikon', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
			)
		);

		$this->add_control(
			'icon_position',
			array(
				'label'     => __( 'Ikon helye', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'default'   => 'after',
				'toggle'    => false,
				'options'   => array(
					'before' => array(
						'title' => __( 'Szöveg előtt', 'mtmt-sync' ),
						'icon'  => 'eicon-h-align-left',
					),
					'after'  => array(
						'title' => __( 'Szöveg után', 'mtmt-sync' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'condition' => array( 'button_icon[value]!' => '' ),
			)
		);

		$this->add_responsive_control(
			'button_align',
			array(
				'label'                => __( 'Igazítás', 'mtmt-sync' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array(
						'title' => __( 'Balra', 'mtmt-sync' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Középre', 'mtmt-sync' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Jobbra', 'mtmt-sync' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors_dictionary' => array(
					'left'   => 'flex-start',
					'center' => 'center',
					'right'  => 'flex-end',
				),
				'selectors'            => array( '{{WRAPPER}} .mtmt-search-button-wrap' => 'justify-content: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'button_aria_label',
			array(
				'label'       => __( 'Képernyőolvasó-felirat (aria-label)', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'description' => __( 'Opcionális. Ha üres, a gomb szövege az irányadó.', 'mtmt-sync' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->end_controls_section();
	}

	private function register_button_style_controls(): void {
		$selector       = '{{WRAPPER}} .mtmt-search-button';
		$selector_hover = '{{WRAPPER}} .mtmt-search-button:hover, {{WRAPPER}} .mtmt-search-button:focus';

		$this->start_controls_section(
			'mtmt_btn_style_section',
			array(
				'label' => __( 'Gomb', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'button_width',
			array(
				'label'       => __( 'Szélesség', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%', 'em', 'vw' ),
				'range'       => array(
					'px' => array(
						'min' => 40,
						'max' => 800,
					),
					'%'  => array(
						'min' => 5,
						'max' => 100,
					),
					'em' => array(
						'min' => 4,
						'max' => 60,
					),
					'vw' => array(
						'min' => 5,
						'max' => 100,
					),
				),
				'selectors'   => array( $selector => 'width: {{SIZE}}{{UNIT}};' ),
				'description' => __( 'Üresen hagyva a gomb a tartalmához igazodik. 100% = teljes szélesség.', 'mtmt-sync' ),
			)
		);

		$this->add_responsive_control(
			'button_min_height',
			array(
				'label'      => __( 'Minimális magasság', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'vh' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 300,
					),
					'em' => array(
						'min' => 0,
						'max' => 20,
					),
					'vh' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array( $selector => 'min-height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => __( 'Belső margó', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%', 'vw' ),
				'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->start_controls_tabs( 'mtmt_btn_state_tabs' );

		// ---- Normál állapot ----
		$this->start_controls_tab(
			'mtmt_btn_tab_normal',
			array( 'label' => __( 'Normál', 'mtmt-sync' ) )
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => __( 'Szövegszín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $selector => 'color: {{VALUE}}; fill: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'           => 'button_background',
				'label'          => __( 'Háttér', 'mtmt-sync' ),
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => $selector,
				'fields_options' => array(
					'background' => array( 'default' => 'classic' ),
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => __( 'Lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_box_shadow',
				'selector' => $selector,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'button_text_shadow',
				'selector' => $selector,
			)
		);

		if ( class_exists( '\Elementor\Group_Control_Text_Stroke' ) ) {
			$this->add_group_control(
				\Elementor\Group_Control_Text_Stroke::get_type(),
				array(
					'name'     => 'button_text_stroke',
					'selector' => $selector,
				)
			);
		}

		$this->end_controls_tab();

		// ---- Hover állapot ----
		$this->start_controls_tab(
			'mtmt_btn_tab_hover',
			array( 'label' => __( 'Hover', 'mtmt-sync' ) )
		);

		$this->add_control(
			'button_text_color_hover',
			array(
				'label'     => __( 'Szövegszín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $selector_hover => 'color: {{VALUE}}; fill: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'           => 'button_background_hover',
				'label'          => __( 'Háttér', 'mtmt-sync' ),
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => $selector_hover,
				'fields_options' => array(
					'background' => array( 'default' => 'classic' ),
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border_hover',
				'selector' => $selector_hover,
			)
		);

		$this->add_responsive_control(
			'button_border_radius_hover',
			array(
				'label'      => __( 'Lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( $selector_hover => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_box_shadow_hover',
				'selector' => $selector_hover,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'button_text_shadow_hover',
				'selector' => $selector_hover,
			)
		);

		if ( class_exists( '\Elementor\Group_Control_Text_Stroke' ) ) {
			$this->add_group_control(
				\Elementor\Group_Control_Text_Stroke::get_type(),
				array(
					'name'     => 'button_text_stroke_hover',
					'selector' => $selector_hover,
				)
			);
		}

		$this->add_control(
			'button_text_decoration_hover',
			array(
				'label'     => __( 'Szöveg-díszítés', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''             => __( 'Alapértelmezett', 'mtmt-sync' ),
					'none'         => __( 'Nincs', 'mtmt-sync' ),
					'underline'    => __( 'Aláhúzott', 'mtmt-sync' ),
					'overline'     => __( 'Felülvonalas', 'mtmt-sync' ),
					'line-through' => __( 'Áthúzott', 'mtmt-sync' ),
				),
				'selectors' => array( $selector_hover => 'text-decoration: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'mtmt_btn_hover_motion_heading',
			array(
				'label'     => __( 'Mozgás hoverre', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'hover_animation',
			array(
				'label' => __( 'Hover-animáció', 'mtmt-sync' ),
				'type'  => \Elementor\Controls_Manager::HOVER_ANIMATION,
			)
		);

		// A három transzformáció egyetlen `transform` szabályba áll össze a
		// widget.css-ben (CSS-változókból), így egymást nem írják felül.
		$this->add_control(
			'hover_translate_y',
			array(
				'label'      => __( 'Eltolás függőlegesen', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => -40,
						'max' => 40,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-search-button' => '--mtmt-btn-hover-ty: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'hover_scale',
			array(
				'label'     => __( 'Méretezés', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 0.5,
				'max'       => 1.5,
				'step'      => 0.01,
				'selectors' => array( '{{WRAPPER}} .mtmt-search-button' => '--mtmt-btn-hover-scale: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'hover_rotate',
			array(
				'label'      => __( 'Forgatás', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'range'      => array(
					'deg' => array(
						'min' => -45,
						'max' => 45,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-search-button' => '--mtmt-btn-hover-rot: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'mtmt_btn_transition_heading',
			array(
				'label'     => __( 'Átmenet (animáció sebessége)', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'transition_duration',
			array(
				'label'     => __( 'Időtartam (másodperc)', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 0.3,
				'min'       => 0,
				'max'       => 5,
				'step'      => 0.05,
				'selectors' => array(
					'{{WRAPPER}} .mtmt-search-button, {{WRAPPER}} .mtmt-search-button__icon' => 'transition-duration: {{VALUE}}s;',
				),
			)
		);

		$this->add_control(
			'transition_easing',
			array(
				'label'     => __( 'Lefutás', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'ease',
				'options'   => array(
					'ease'        => __( 'Ease', 'mtmt-sync' ),
					'linear'      => __( 'Lineáris', 'mtmt-sync' ),
					'ease-in'     => __( 'Ease in', 'mtmt-sync' ),
					'ease-out'    => __( 'Ease out', 'mtmt-sync' ),
					'ease-in-out' => __( 'Ease in-out', 'mtmt-sync' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .mtmt-search-button, {{WRAPPER}} .mtmt-search-button__icon' => 'transition-timing-function: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_icon_style_controls(): void {
		$icon_condition = array( 'button_icon[value]!' => '' );

		$this->start_controls_section(
			'mtmt_btn_icon_style_section',
			array(
				'label'     => __( 'Ikon', 'mtmt-sync' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $icon_condition,
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'Méret', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 6,
						'max' => 100,
					),
					'em' => array(
						'min'  => 0.5,
						'max'  => 6,
						'step' => 0.1,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-search-button__icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'icon_gap',
			array(
				'label'      => __( 'Távolság a szövegtől', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 4,
						'step' => 0.1,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-search-button' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->start_controls_tabs( 'mtmt_btn_icon_state_tabs' );

		$this->start_controls_tab(
			'mtmt_btn_icon_tab_normal',
			array( 'label' => __( 'Normál', 'mtmt-sync' ) )
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Ikon színe', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-search-button__icon' => 'color: {{VALUE}}; fill: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'icon_rotate',
			array(
				'label'      => __( 'Forgatás', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'range'      => array(
					'deg' => array(
						'min' => -180,
						'max' => 180,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-search-button__icon' => '--mtmt-icon-rot: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'mtmt_btn_icon_tab_hover',
			array( 'label' => __( 'Hover', 'mtmt-sync' ) )
		);

		$this->add_control(
			'icon_color_hover',
			array(
				'label'     => __( 'Ikon színe', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mtmt-search-button:hover .mtmt-search-button__icon, {{WRAPPER}} .mtmt-search-button:focus .mtmt-search-button__icon' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_shift_hover',
			array(
				'label'      => __( 'Eltolás vízszintesen', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => -30,
						'max' => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .mtmt-search-button:hover .mtmt-search-button__icon, {{WRAPPER}} .mtmt-search-button:focus .mtmt-search-button__icon' => '--mtmt-icon-shift: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'icon_rotate_hover',
			array(
				'label'      => __( 'Forgatás', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'range'      => array(
					'deg' => array(
						'min' => -180,
						'max' => 180,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .mtmt-search-button:hover .mtmt-search-button__icon, {{WRAPPER}} .mtmt-search-button:focus .mtmt-search-button__icon' => '--mtmt-icon-rot: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * A cél-URL feloldása: a widget saját értéke, ennek hiányában a Beállításokban
	 * megadott listázó oldal.
	 *
	 * @param array<string,mixed> $settings
	 * @return string Üres, ha nincs használható cél.
	 */
	private function resolve_target_url( array $settings ): string {
		$page_url = isset( $settings['list_page']['url'] ) ? trim( (string) $settings['list_page']['url'] ) : '';
		if ( '' === $page_url ) {
			$page_url = Mtmt_Search_Link::get_list_page_url();
		}
		if ( '' === $page_url ) {
			return '';
		}

		$search = isset( $settings['search_value'] ) ? (string) $settings['search_value'] : '';

		// esc_url_raw() a nem engedélyezett protokollokat (pl. javascript:) üresre cseréli.
		return esc_url_raw( Mtmt_Search_Link::build_url( $page_url, $search ) );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$href     = $this->resolve_target_url( $settings );

		if ( '' === $href ) {
			// Látogatónak semmit nem mutatunk; a szerkesztőben jelezzük, mi hiányzik.
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p class="mtmt-search-button-notice">' . esc_html__( 'MTMT keresés-gomb: nincs listázó oldal megadva. Állítsd be az MTMT Publikációk → Beállítások oldalon, vagy add meg a „Listázó oldal" mezőben.', 'mtmt-sync' ) . '</p>';
			}
			return;
		}

		$this->add_render_attribute( 'button', 'class', 'mtmt-search-button' );
		$this->add_render_attribute( 'button', 'href', $href );

		if ( ! empty( $settings['hover_animation'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . sanitize_html_class( (string) $settings['hover_animation'] ) );
		}

		$has_motion = ! empty( $settings['hover_translate_y']['size'] )
			|| ( ! empty( $settings['hover_scale']['size'] ) && 1.0 !== (float) $settings['hover_scale']['size'] )
			|| ! empty( $settings['hover_rotate']['size'] );
		if ( $has_motion ) {
			$this->add_render_attribute( 'button', 'class', 'mtmt-has-hover-transform' );
		}

		$has_icon = ! empty( $settings['button_icon']['value'] );
		if ( $has_icon && 'before' === ( $settings['icon_position'] ?? 'after' ) ) {
			$this->add_render_attribute( 'button', 'class', 'mtmt-search-button--icon-before' );
		}

		if ( ! empty( $settings['list_page']['is_external'] ) ) {
			$this->add_render_attribute( 'button', 'target', '_blank' );
			$rel = 'noopener';
			if ( ! empty( $settings['list_page']['nofollow'] ) ) {
				$rel .= ' nofollow';
			}
			$this->add_render_attribute( 'button', 'rel', $rel );
		} elseif ( ! empty( $settings['list_page']['nofollow'] ) ) {
			$this->add_render_attribute( 'button', 'rel', 'nofollow' );
		}

		$aria = isset( $settings['button_aria_label'] ) ? trim( (string) $settings['button_aria_label'] ) : '';
		if ( '' !== $aria ) {
			$this->add_render_attribute( 'button', 'aria-label', $aria );
		}

		$text = isset( $settings['button_text'] ) ? (string) $settings['button_text'] : '';
		?>
		<div class="mtmt-search-button-wrap">
			<a <?php $this->print_render_attribute_string( 'button' ); ?>>
				<?php if ( '' !== $text ) : ?>
					<span class="mtmt-search-button__text"><?php echo esc_html( $text ); ?></span>
				<?php endif; ?>
				<?php if ( $has_icon ) : ?>
					<span class="mtmt-search-button__icon" aria-hidden="true">
						<?php \Elementor\Icons_Manager::render_icon( $settings['button_icon'], array( 'aria-hidden' => 'true' ) ); ?>
					</span>
				<?php endif; ?>
			</a>
		</div>
		<?php
	}
}
