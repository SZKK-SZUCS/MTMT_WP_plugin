<?php
/**
 * Közös Elementor-controlok (szövegek + stílus) az "A" és "B" widgethez.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

trait Mtmt_Widget_Common_Controls {

	/**
	 * @param array<string,string> $defaults kulcs => alapérték.
	 */
	protected function register_mtmt_text_controls( array $defaults ): void {
		$this->start_controls_section(
			'mtmt_texts_section',
			array(
				'label' => __( 'Szövegek', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$fields = array(
			'header_eyebrow'           => __( 'Felső kiskapitális felirat', 'mtmt-sync' ),
			'header_title'             => __( 'Widget címe', 'mtmt-sync' ),
			'search_placeholder'       => __( 'Kereső mező helyőrző szövege', 'mtmt-sync' ),
			'area_filter_all_label'    => __( 'Terület-szűrő "minden terület" felirata', 'mtmt-sync' ),
			'project_filter_all_label' => __( 'Projekt-szűrő "minden projekt" felirata', 'mtmt-sync' ),
			'project_label'            => __( 'Projektazonosító sor felirata a kártyán', 'mtmt-sync' ),
			'funding_label'            => __( 'Támogatás sor felirata a kártyán', 'mtmt-sync' ),
			'year_tab_all_label'       => __( 'Év-fül "összes év" felirata', 'mtmt-sync' ),
			'empty_state_text'         => __( 'Szöveg, ha nincs találat', 'mtmt-sync' ),
			'pagination_prev_label'    => __( 'Lapozó "előző" felirata', 'mtmt-sync' ),
			'pagination_next_label'    => __( 'Lapozó "következő" felirata', 'mtmt-sync' ),
			'no_scope_message'         => __( 'Üzenet, ha nincs kiválasztva terület/profil (csak szerkesztőknek látszik)', 'mtmt-sync' ),
		);

		if ( array_key_exists( 'header_subtitle', $defaults ) ) {
			$this->add_control(
				'header_subtitle',
				array(
					'label'       => __( 'Widget alcíme (leíró mondat a cím alatt)', 'mtmt-sync' ),
					'type'        => \Elementor\Controls_Manager::TEXTAREA,
					'default'     => $defaults['header_subtitle'],
					'label_block' => true,
					'description' => __( 'Üresen hagyva nem jelenik meg alcím-sor.', 'mtmt-sync' ),
				)
			);
		}

		foreach ( $fields as $key => $label ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			$this->add_control(
				$key,
				array(
					'label'       => $label,
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => $defaults[ $key ],
					'label_block' => true,
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * "Alapértelmezett nézet": melyik év-fül legyen aktív a widget betöltésekor.
	 * Egy már nyitott controls-section-be kerül (a hívó widget Tartalom fülére).
	 * Alapértelmezés: "Összes" — a régi viselkedés (legfrissebb év) választható.
	 */
	protected function register_mtmt_default_view_controls(): void {
		$this->add_control(
			'default_view',
			array(
				'label'       => __( 'Alapértelmezett nézet', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'all',
				'options'     => array(
					'all'    => __( 'Összes év', 'mtmt-sync' ),
					'latest' => __( 'Legfrissebb év', 'mtmt-sync' ),
					'year'   => __( 'Adott év', 'mtmt-sync' ),
				),
				'description' => __( 'Melyik év-fül legyen kiválasztva, amikor a látogató megnyitja az oldalt.', 'mtmt-sync' ),
			)
		);

		$this->add_control(
			'default_view_year',
			array(
				'label'       => __( 'Év', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => (int) gmdate( 'Y' ),
				'min'         => 1900,
				'max'         => 2100,
				'condition'   => array( 'default_view' => 'year' ),
				'description' => __( 'Ha erre az évre nincs megjeleníthető publikáció, az "Összes" nézet nyílik meg.', 'mtmt-sync' ),
			)
		);
	}

	/**
	 * A kezdeti aktív év a beállítás és a ténylegesen elérhető évek alapján.
	 *
	 * @param array $settings Elementor-beállítások.
	 * @param int[] $years    Elérhető évek, csökkenő sorrendben.
	 * @return int 0 = "Összes".
	 */
	protected function mtmt_resolve_initial_year( array $settings, array $years ): int {
		$mode = (string) ( $settings['default_view'] ?? 'all' );

		if ( 'latest' === $mode ) {
			return (int) ( $years[0] ?? 0 );
		}

		if ( 'year' === $mode ) {
			$year = absint( $settings['default_view_year'] ?? 0 );
			return in_array( $year, array_map( 'intval', $years ), true ) ? $year : 0;
		}

		return 0;
	}

	/**
	 * Projektazonosító-megjelenítés (és az "A" widgetnél a projekt-szűrő)
	 * kapcsolói — egy már nyitott controls-section-be kerül.
	 *
	 * @param bool $with_filter Kerüljön-e be a "projekt-szűrő megjelenítése" kapcsoló.
	 */
	protected function register_mtmt_project_controls( bool $with_filter ): void {
		$this->add_control(
			'show_funding',
			array(
				'label'       => __( 'Támogatás megjelenítése', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'A moderációs felületen megadott támogatás-szöveg (pl. köszönetnyilvánítás) a kártyán, ha ki van töltve.', 'mtmt-sync' ),
			)
		);

		$this->add_control(
			'show_project_ids',
			array(
				'label'       => __( 'Projektazonosító megjelenítése', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'A moderációs felületen megadott projektazonosító(k) a kártyán.', 'mtmt-sync' ),
			)
		);

		if ( $with_filter ) {
			$this->add_control(
				'show_project_filter',
				array(
					'label'       => __( 'Projekt szűrő megjelenítése', 'mtmt-sync' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Lenyíló a kereső mellett. Csak akkor jelenik meg, ha legalább egy jóváhagyott publikációhoz van projektazonosító rögzítve.', 'mtmt-sync' ),
				)
			);
		}
	}

	/**
	 * "Idézettség (Dimensions)" szekció a Tartalom fülön.
	 */
	protected function register_mtmt_dimensions_controls(): void {
		$this->start_controls_section(
			'mtmt_dimensions_section',
			array(
				'label' => __( 'Idézettség (Dimensions)', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_dimensions_badge',
			array(
				'label'       => __( 'Dimensions idézettségi badge megjelenítése', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'DOI-val rendelkező publikációknál mutatja, hányszor idézték (forrás: dimensions.ai). A badge-et a látogató böngészője a badge.dimensions.ai oldalról tölti be, csak amikor a widget láthatóvá válik — érdemes ezt az adatkezelési tájékoztatóban is jelezni.', 'mtmt-sync' ),
			)
		);

		$this->add_control(
			'dimensions_style',
			array(
				'label'     => __( 'Badge stílusa', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'small_rectangle',
				'options'   => array(
					'small_rectangle' => __( 'Kis téglalap', 'mtmt-sync' ),
					'large_rectangle' => __( 'Nagy téglalap', 'mtmt-sync' ),
					'small_circle'    => __( 'Kis kör', 'mtmt-sync' ),
					'medium_circle'   => __( 'Közepes kör', 'mtmt-sync' ),
					'large_circle'    => __( 'Nagy kör', 'mtmt-sync' ),
				),
				'condition' => array( 'show_dimensions_badge' => 'yes' ),
			)
		);

		$this->add_control(
			'dimensions_legend',
			array(
				'label'     => __( 'Részletek (jelmagyarázat)', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'hover-right',
				'options'   => array(
					'hover-right'  => __( 'Rámutatáskor, jobbra', 'mtmt-sync' ),
					'hover-left'   => __( 'Rámutatáskor, balra', 'mtmt-sync' ),
					'hover-top'    => __( 'Rámutatáskor, fent', 'mtmt-sync' ),
					'hover-bottom' => __( 'Rámutatáskor, lent', 'mtmt-sync' ),
					'always'       => __( 'Mindig látszik', 'mtmt-sync' ),
					'never'        => __( 'Soha', 'mtmt-sync' ),
				),
				'condition' => array( 'show_dimensions_badge' => 'yes' ),
			)
		);

		$this->add_control(
			'dimensions_hide_zero',
			array(
				'label'     => __( 'Elrejtés, ha még nincs idézet', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array( 'show_dimensions_badge' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * A projekt- és Dimensions-beállításokból képzett kártya-megjelenítési
	 * opciók (Mtmt_Card_Renderer::render() $display_options kiegészítése).
	 *
	 * @param array $settings          Elementor-beállítások.
	 * @param bool  $project_clickable A projekt-chipek szűrő-gombok legyenek-e.
	 * @return array
	 */
	protected function mtmt_extra_display_options( array $settings, bool $project_clickable ): array {
		$style  = (string) ( $settings['dimensions_style'] ?? '' );
		$legend = (string) ( $settings['dimensions_legend'] ?? '' );

		return array(
			'show_funding'         => 'yes' === ( $settings['show_funding'] ?? 'yes' ),
			'funding_label'        => (string) ( $settings['funding_label'] ?? '' ),
			'show_project_ids'     => 'yes' === ( $settings['show_project_ids'] ?? 'yes' ),
			'project_label'        => (string) ( $settings['project_label'] ?? '' ),
			'project_clickable'    => $project_clickable,
			'show_dimensions'      => 'yes' === ( $settings['show_dimensions_badge'] ?? '' ),
			'dimensions_style'     => in_array( $style, Mtmt_Card_Renderer::DIMENSIONS_STYLES, true ) ? $style : 'small_rectangle',
			'dimensions_legend'    => in_array( $legend, Mtmt_Card_Renderer::DIMENSIONS_LEGENDS, true ) ? $legend : 'hover-right',
			'dimensions_hide_zero' => 'yes' === ( $settings['dimensions_hide_zero'] ?? 'yes' ),
		);
	}

	/**
	 * Az mtmt_extra_display_options() data-* attribútumokként — a JS minden
	 * AJAX-kérésnél visszaküldi őket, hogy a fragment-csere ugyanúgy rendereljen.
	 *
	 * @param array $opts mtmt_extra_display_options() kimenete.
	 * @return string Escape-elt attribútum-sztring (vezető szóközzel).
	 */
	protected function mtmt_extra_data_attrs( array $opts ): string {
		return ' data-show-funding="' . ( $opts['show_funding'] ? '1' : '0' ) . '"'
			. ' data-funding-label="' . esc_attr( $opts['funding_label'] ) . '"'
			. ' data-show-project-ids="' . ( $opts['show_project_ids'] ? '1' : '0' ) . '"'
			. ' data-project-label="' . esc_attr( $opts['project_label'] ) . '"'
			. ' data-project-clickable="' . ( $opts['project_clickable'] ? '1' : '0' ) . '"'
			. ' data-dimensions="' . ( $opts['show_dimensions'] ? '1' : '0' ) . '"'
			. ' data-dimensions-style="' . esc_attr( $opts['dimensions_style'] ) . '"'
			. ' data-dimensions-legend="' . esc_attr( $opts['dimensions_legend'] ) . '"'
			. ' data-dimensions-hide-zero="' . ( $opts['dimensions_hide_zero'] ? '1' : '0' ) . '"';
	}

	protected function register_mtmt_style_controls(): void {
		$this->start_controls_section(
			'mtmt_style_colors_section',
			array(
				'label' => __( 'Színek', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'accent_color',
			array(
				'label'     => __( 'Kiemelő szín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'border_color',
			array(
				'label'     => __( 'Szegély szín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-border: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'muted_text_color',
			array(
				'label'     => __( 'Másodlagos szöveg szín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-muted: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'soft_bg_color',
			array(
				'label'     => __( 'Halvány háttérszín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-bg-soft: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Cím szín (widget-cím, kártya-cím, aktív év-fül)', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-heading: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_typography_section',
			array(
				'label' => __( 'Tipográfia', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'eyebrow_typography',
				'label'    => __( 'Felső kiskapitális felirat', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-eyebrow',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'label'    => __( 'Widget cím', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-widget-title',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'card_title_typography',
				'label'    => __( 'Kártya-cím', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-pub-card-title',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'source_typography',
				'label'    => __( 'Forrás + év sor', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-pub-card-source-line',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'body_typography',
				'label'    => __( 'Törzsszöveg (alcím, szerzők, meta)', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-widget-subtitle, {{WRAPPER}} .mtmt-pub-card-authors, {{WRAPPER}} .mtmt-pub-card-meta',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_card_section',
			array(
				'label' => __( 'Kártya', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'     => __( 'Kártya háttérszíne', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-pub-card' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => __( 'Kártya lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-pub-card' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Kártya belső margó', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-pub-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'label'    => __( 'Kártya-sor árnyéka', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-pub-card',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_media_section',
			array(
				'label' => __( 'Előnézeti kép', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'media_width',
			array(
				'label'      => __( 'Szélesség', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 60, 'max' => 320 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 112,
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-pub-card-media' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'media_height',
			array(
				'label'      => __( 'Magasság', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 260 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 78,
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-pub-card-media' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'media_border_radius',
			array(
				'label'      => __( 'Lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-pub-card-media' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_badges_section',
			array(
				'label' => __( 'Badge-ek (típus, SJR, szakmai terület)', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'badge_border_radius',
			array(
				'label'      => __( 'Lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 999,
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-badge' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'badge_padding',
			array(
				'label'      => __( 'Belső margó', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_arrow_section',
			array(
				'label' => __( 'Nyíl-gomb (sor jobb szélén)', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'arrow_size',
			array(
				'label'      => __( 'Méret', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 16,
						'max' => 56,
					),
					'em' => array(
						'min'  => 1,
						'max'  => 4,
						'step' => 0.1,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-arrow-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'arrow_border_radius',
			array(
				'label'      => __( 'Lekerekítés (100% = kör)', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array(
					'%'  => array(
						'min' => 0,
						'max' => 50,
					),
					'px' => array(
						'min' => 0,
						'max' => 30,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-arrow-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_form_section',
			array(
				'label' => __( 'Kereső / szűrő mezők', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'form_border_radius',
			array(
				'label'      => __( 'Lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-search-input, {{WRAPPER}} .mtmt-area-filter' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_pagination_section',
			array(
				'label' => __( 'Lapozás', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'pagination_border_radius',
			array(
				'label'      => __( 'Oldalszám-gombok lekerekítése', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-page-btn-number' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'pagination_active_bg_color',
			array(
				'label'     => __( 'Aktuális oldal háttérszíne', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-page-btn-number.is-current' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'pagination_active_text_color',
			array(
				'label'     => __( 'Aktuális oldal szövegszíne', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-page-btn-number.is-current' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_ext_ids_section',
			array(
				'label' => __( 'Egyéb azonosítók (WoS/Scopus/…)', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'ext_id_icon_size',
			array(
				'label'      => __( 'Ikon méret', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 48,
					),
					'em' => array(
						'min'  => 0.5,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-ext-id-icon-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'ext_id_icon_color',
			array(
				'label'     => __( 'Ikon szín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-ext-id-icon-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'ext_id_text_color',
			array(
				'label'     => __( 'Szöveg szín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-ext-id-text-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'ext_id_bg_color',
			array(
				'label'     => __( 'Pill háttérszín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-ext-id-bg-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'ext_id_border_color',
			array(
				'label'     => __( 'Pill szegély szín', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-ext-id-border-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'ext_id_border_radius',
			array(
				'label'      => __( 'Pill lekerekítés', 'mtmt-sync' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .mtmt-widget' => '--mtmt-ext-id-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}
}
