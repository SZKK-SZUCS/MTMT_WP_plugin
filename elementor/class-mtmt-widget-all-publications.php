<?php
/**
 * "A" — összesítő központi widget (CLAUDE.md §14/10).
 *
 * Minden jóváhagyott tétel (nem csak kiemelt), év-fülekkel, kereséssel és
 * (ha be van kapcsolva) szakmai terület szerinti szűrő-lenyílóval.
 *
 * Ez a fájl csak akkor töltődik be, ha az Elementor fut (lásd Mtmt_Elementor_Loader) —
 * ezért itt bátran ki lehet terjeszteni az \Elementor\Widget_Base-t.
 *
 * FONTOS: az Elementor néhány kódúton (pl. szerkesztő-AJAX, dokumentum-betöltés)
 * saját maga hozza létre a widget-példányt `new static($data, $args)` alakban —
 * ezért ez az osztály SZÁNDÉKOSAN nem kap semmilyen extra konstruktor-paramétert
 * (nincs dependency injection), a repository-kat a render()-ben, lazy módon építi
 * fel — ugyanaz a minta, mint a mtmt-sync.php admin-hookjaiban (`global $wpdb`).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget: összesítő publikációs lista.
 */
final class Mtmt_Widget_All_Publications extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'mtmt_all_publications';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'MTMT publikációk – összesítő', 'mtmt-sync' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-list';
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
		return array( 'mtmt', 'publikáció', 'publication', 'tudományos' );
	}

	/**
	 * Widget-beállítások (CLAUDE.md §9.1).
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'mtmt_content_section',
			array(
				'label' => __( 'Tartalom', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'   => __( 'Kereső mező megjelenítése', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_topic_filter',
			array(
				'label'       => __( 'Szakmai terület szűrő megjelenítése', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Csak akkor jelenik meg ténylegesen, ha a "Szakmai terület" funkció be van kapcsolva a plugin Beállítások oldalán.', 'mtmt-sync' ),
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Tételek száma oldalanként', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 20,
				'min'     => 1,
				'max'     => 50,
			)
		);

		$this->add_control(
			'citation_style',
			array(
				'label'   => __( 'Hivatkozás-stílus', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'full',
				'options' => array(
					'full'    => __( 'Teljes', 'mtmt-sync' ),
					'compact' => __( 'Kompakt', 'mtmt-sync' ),
				),
			)
		);

		$this->add_control(
			'show_doi_link',
			array(
				'label'       => __( 'DOI megjelenítése', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'DOI hiányában ez dönti el, hogy a teljes kártya az MTMT nyilvános oldalára linkeljen-e (CLAUDE.md §14/12).', 'mtmt-sync' ),
			)
		);

		$this->add_control(
			'show_sjr_badge',
			array(
				'label'   => __( 'SJR-negyed badge megjelenítése', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Kezdeti (nem-AJAX) szerver-oldali render — a további interakció
	 * (keresés/év-váltás/lapozás/terület-szűrés) az assets/js/widget-frontend.js
	 * AJAX-fragment cseréjével történik.
	 */
	protected function render() {
		if ( ! class_exists( 'Mtmt_Publication_Repository' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();

		global $wpdb;
		$repo        = new Mtmt_Publication_Repository( $wpdb );
		$topic_repo  = new Mtmt_Topic_Area_Repository( $wpdb );
		$widget_data = new Mtmt_Widget_Data( $repo, $topic_repo );

		$topic_areas_enabled = (bool) get_option( 'mtmt_enable_topic_areas' );
		$show_search         = 'yes' === $settings['show_search'];
		$show_topic_filter   = $topic_areas_enabled && 'yes' === $settings['show_topic_filter'];
		$per_page            = max( 1, min( 50, (int) $settings['per_page'] ) );
		$citation_style      = ( 'compact' === $settings['citation_style'] ) ? 'compact' : 'full';
		$show_doi_link       = 'yes' === $settings['show_doi_link'];
		$show_sjr_badge      = 'yes' === $settings['show_sjr_badge'];

		$display_options = array(
			'show_topic_area' => $topic_areas_enabled,
			'show_doi_link'   => $show_doi_link,
			'show_sjr_badge'  => $show_sjr_badge,
			'citation_style'  => $citation_style,
		);

		$years        = $widget_data->get_available_years();
		$initial_year = $years[0] ?? 0;

		$initial = $widget_data->query(
			array(
				'year'     => $initial_year,
				'per_page' => $per_page,
			)
		);

		$areas       = $show_topic_filter ? $topic_repo->get_all() : array();
		$total_pages = $per_page > 0 ? max( 1, (int) ceil( $initial['total'] / $per_page ) ) : 1;
		?>
		<div class="mtmt-widget mtmt-widget-all"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( Mtmt_Widget_Ajax::NONCE_ACTION ) ); ?>"
			data-widget-type="all"
			data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
			data-citation-style="<?php echo esc_attr( $citation_style ); ?>"
			data-show-doi-link="<?php echo $show_doi_link ? '1' : '0'; ?>"
			data-show-sjr-badge="<?php echo $show_sjr_badge ? '1' : '0'; ?>"
			data-show-topic-area="<?php echo $topic_areas_enabled ? '1' : '0'; ?>"
			data-year="<?php echo esc_attr( (string) $initial_year ); ?>">

			<div class="mtmt-widget-header">
				<p class="mtmt-eyebrow"><?php esc_html_e( 'PUBLIKÁCIÓK', 'mtmt-sync' ); ?></p>
				<h2 class="mtmt-widget-title"><?php esc_html_e( 'Lektorált publikációk', 'mtmt-sync' ); ?></h2>
			</div>

			<?php if ( $show_search || ( $show_topic_filter && ! empty( $areas ) ) ) : ?>
			<div class="mtmt-widget-controls">
				<?php if ( $show_search ) : ?>
					<input type="search" class="mtmt-search-input" placeholder="<?php esc_attr_e( 'Keresés cím, szerző vagy forrás szerint…', 'mtmt-sync' ); ?>">
				<?php endif; ?>
				<?php if ( $show_topic_filter && ! empty( $areas ) ) : ?>
					<select class="mtmt-area-filter">
						<option value="0"><?php esc_html_e( 'Minden szakmai terület', 'mtmt-sync' ); ?></option>
						<?php foreach ( $areas as $area ) : ?>
							<option value="<?php echo esc_attr( (string) $area['id'] ); ?>"><?php echo esc_html( $area['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $years ) ) : ?>
			<div class="mtmt-year-tabs" role="tablist">
				<button type="button" class="mtmt-year-tab<?php echo ( 0 === $initial_year ) ? ' is-active' : ''; ?>" data-year="0"><?php esc_html_e( 'Összes', 'mtmt-sync' ); ?></button>
				<?php foreach ( $years as $year ) : ?>
					<button type="button" class="mtmt-year-tab<?php echo ( $year === $initial_year ) ? ' is-active' : ''; ?>" data-year="<?php echo esc_attr( (string) $year ); ?>"><?php echo esc_html( (string) $year ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<div class="mtmt-widget-list">
				<?php if ( empty( $initial['items'] ) ) : ?>
					<p class="mtmt-widget-empty"><?php esc_html_e( 'Nincs a szűrésnek megfelelő publikáció.', 'mtmt-sync' ); ?></p>
				<?php else : ?>
					<?php foreach ( $initial['items'] as $item ) : ?>
						<?php echo Mtmt_Card_Renderer::render( $item, $display_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a renderer maga escape-el. ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="mtmt-widget-pagination">
				<?php echo Mtmt_Widget_Ajax::render_pagination( 1, $total_pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saját, escape-elt HTML-t épít. ?>
			</div>
		</div>
		<?php
	}
}
