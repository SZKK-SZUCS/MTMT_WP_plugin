<?php
/**
 * Közös Elementor-controlok az "A" és "B" widgethez (Fázis 5 utólagos
 * kiegészítés, élő visszajelzés alapján, lásd docs/decisions.md #60):
 *
 * 1. Minden korábban kőbe vésett ("statikus") felirat mostantól szerkeszthető
 *    a widget Tartalom fülén ("Szövegek" szekció) — pl. a fejléc-cím, a
 *    kereső helyőrző szövege, az üres-lista üzenet, a lapozó "előző/következő"
 *    felirata.
 * 2. Egy Stílus fül (Elementor Style-tab) — színek (a widget.css-ben már
 *    amúgy is CSS-változóként tárolt kiemelő/szegély/másodlagos/halvány-háttér
 *    szín), tipográfia (cím/kártya-cím/törzsszöveg), kártya-megjelenés
 *    (háttér, lekerekítés, belső margó).
 *
 * Trait, mert két widget-osztály (`Mtmt_Widget_All_Publications`,
 * `Mtmt_Widget_Topic_Publications`) osztozik rajta — mindkettő
 * `\Elementor\Widget_Base`-t terjeszt ki, a trait csak a közös
 * control-regisztrációt DRY-osítja, nem önálló osztály.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

trait Mtmt_Widget_Common_Controls {

	/**
	 * "Szövegek" szekció a Tartalom fülön — csak azokhoz a kulcsokhoz ad
	 * controlt, amik szerepelnek a `$defaults`-ban, így az "A" és "B" widget
	 * a saját releváns mezőkészletét kapja (pl. a terület-szűrő felirata csak
	 * "A"-nál értelmes).
	 *
	 * @param array<string,string> $defaults kulcs => jelenlegi (korábban hardcode-olt) alapérték.
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
			'header_eyebrow'        => __( 'Felső kiskapitális felirat', 'mtmt-sync' ),
			'header_title'          => __( 'Widget címe', 'mtmt-sync' ),
			'search_placeholder'    => __( 'Kereső mező helyőrző szövege', 'mtmt-sync' ),
			'area_filter_all_label' => __( 'Terület-szűrő "minden terület" felirata', 'mtmt-sync' ),
			'year_tab_all_label'    => __( 'Év-fül "összes év" felirata', 'mtmt-sync' ),
			'empty_state_text'      => __( 'Szöveg, ha nincs találat', 'mtmt-sync' ),
			'pagination_prev_label' => __( 'Lapozó "előző" felirata', 'mtmt-sync' ),
			'pagination_next_label' => __( 'Lapozó "következő" felirata', 'mtmt-sync' ),
			'no_scope_message'      => __( 'Üzenet, ha nincs kiválasztva terület/profil (csak szerkesztőknek látszik)', 'mtmt-sync' ),
		);

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
	 * Stílus fül: színek, tipográfia, kártya-megjelenés. A színek a
	 * widget.css-ben már meglévő CSS-változókat (`--mtmt-accent` stb.)
	 * írják felül `{{WRAPPER}} .mtmt-widget`-en — egyetlen ponton hatnak,
	 * a css minden szabálya ezekre a változókra épül.
	 */
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
				'name'     => 'body_typography',
				'label'    => __( 'Törzsszöveg (szerzők, meta)', 'mtmt-sync' ),
				'selector' => '{{WRAPPER}} .mtmt-pub-card-authors, {{WRAPPER}} .mtmt-pub-card-meta',
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

		$this->end_controls_section();

		$this->start_controls_section(
			'mtmt_style_ext_ids_section',
			array(
				'label' => __( 'Egyéb azonosítók (WoS/Scopus/…)', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
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
