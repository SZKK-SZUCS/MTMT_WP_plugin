<?php
/**
 * "A" — összesítő publikációs lista widget: minden jóváhagyott tétel,
 * év-fülekkel, kereséssel és (ha be van kapcsolva) terület-szűrővel.
 *
 * Az Elementor néhány kódúton saját maga hozza létre a widget-példányt
 * (`new static($data, $args)`), ezért nincs egyedi konstruktor-paraméter —
 * a repository-kat a render() építi fel.
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Mtmt_Widget_All_Publications extends \Elementor\Widget_Base {

	use Mtmt_Widget_Common_Controls;

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
			'sort_order',
			array(
				'label'   => __( 'Rendezés', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'newest',
				'options' => array(
					'newest' => __( 'Legújabb elöl', 'mtmt-sync' ),
					'oldest' => __( 'Legrégebbi elöl', 'mtmt-sync' ),
					'title'  => __( 'Cím szerint (A–Z)', 'mtmt-sync' ),
					'sjr'    => __( 'SJR-negyed szerint (legjobb elöl)', 'mtmt-sync' ),
				),
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
				'description' => __( 'DOI hiányában ez dönti el, hogy a teljes kártya az MTMT oldalára linkeljen-e.', 'mtmt-sync' ),
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

		$this->add_control(
			'ext_id_badge_mode',
			array(
				'label'       => __( 'Egyéb azonosítók megjelenítése', 'mtmt-sync' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'both',
				'options'     => array(
					'both' => __( 'Ikon és szöveg', 'mtmt-sync' ),
					'icon' => __( 'Csak ikon', 'mtmt-sync' ),
					'text' => __( 'Csak szöveg', 'mtmt-sync' ),
				),
				'description' => __( 'Ha egy forráshoz nincs betöltve ikon-fájl, "Csak ikon" módban is a felirat jelenik meg helyette.', 'mtmt-sync' ),
			)
		);

		$this->end_controls_section();

		$this->register_mtmt_text_controls(
			array(
				'header_eyebrow'        => __( 'PUBLIKÁCIÓK', 'mtmt-sync' ),
				'header_title'          => __( 'Lektorált publikációk', 'mtmt-sync' ),
				'header_subtitle'       => '',
				'search_placeholder'    => __( 'Keresés cím, szerző vagy forrás szerint…', 'mtmt-sync' ),
				'area_filter_all_label' => __( 'Minden szakmai terület', 'mtmt-sync' ),
				'year_tab_all_label'    => __( 'Összes', 'mtmt-sync' ),
				'empty_state_text'      => __( 'Nincs a szűrésnek megfelelő publikáció.', 'mtmt-sync' ),
				'pagination_prev_label' => __( 'Előző', 'mtmt-sync' ),
				'pagination_next_label' => __( 'Következő', 'mtmt-sync' ),
			)
		);

		$this->register_mtmt_style_controls();
	}

	/**
	 * Kezdeti render — a keresés/év-váltás/lapozás/szűrés AJAX-frissítéssel történik.
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
		$sort_order          = in_array( $settings['sort_order'] ?? '', array( 'newest', 'oldest', 'title', 'sjr' ), true ) ? $settings['sort_order'] : 'newest';
		$citation_style      = ( 'compact' === $settings['citation_style'] ) ? 'compact' : 'full';
		$show_doi_link       = 'yes' === $settings['show_doi_link'];
		$show_sjr_badge      = 'yes' === $settings['show_sjr_badge'];
		$ext_id_badge_mode   = in_array( $settings['ext_id_badge_mode'] ?? '', array( 'icon', 'text' ), true ) ? $settings['ext_id_badge_mode'] : 'both';

		$header_eyebrow        = (string) $settings['header_eyebrow'];
		$header_title          = (string) $settings['header_title'];
		$header_subtitle       = (string) ( $settings['header_subtitle'] ?? '' );
		$search_placeholder    = (string) $settings['search_placeholder'];
		$area_filter_all_label = (string) $settings['area_filter_all_label'];
		$year_tab_all_label    = (string) $settings['year_tab_all_label'];
		$empty_state_text      = (string) $settings['empty_state_text'];
		$prev_label            = (string) $settings['pagination_prev_label'];
		$next_label            = (string) $settings['pagination_next_label'];

		$display_options = array(
			'show_topic_area'   => $topic_areas_enabled,
			'show_doi_link'     => $show_doi_link,
			'show_sjr_badge'    => $show_sjr_badge,
			'citation_style'    => $citation_style,
			'ext_id_badge_mode' => $ext_id_badge_mode,
		);

		$years        = $widget_data->get_available_years();
		$initial_year = $years[0] ?? 0;

		$initial = $widget_data->query(
			array(
				'year'     => $initial_year,
				'sort'     => $sort_order,
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
			data-sort-order="<?php echo esc_attr( $sort_order ); ?>"
			data-citation-style="<?php echo esc_attr( $citation_style ); ?>"
			data-show-doi-link="<?php echo $show_doi_link ? '1' : '0'; ?>"
			data-show-sjr-badge="<?php echo $show_sjr_badge ? '1' : '0'; ?>"
			data-ext-id-badge-mode="<?php echo esc_attr( $ext_id_badge_mode ); ?>"
			data-show-topic-area="<?php echo $topic_areas_enabled ? '1' : '0'; ?>"
			data-year="<?php echo esc_attr( (string) $initial_year ); ?>"
			data-empty-text="<?php echo esc_attr( $empty_state_text ); ?>"
			data-prev-label="<?php echo esc_attr( $prev_label ); ?>"
			data-next-label="<?php echo esc_attr( $next_label ); ?>">

			<div class="mtmt-widget-header">
				<p class="mtmt-eyebrow"><?php echo esc_html( $header_eyebrow ); ?></p>
				<h2 class="mtmt-widget-title"><?php echo esc_html( $header_title ); ?></h2>
				<?php if ( '' !== $header_subtitle ) : ?>
					<p class="mtmt-widget-subtitle"><?php echo esc_html( $header_subtitle ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $show_search || ( $show_topic_filter && ! empty( $areas ) ) ) : ?>
			<div class="mtmt-widget-controls">
				<?php if ( $show_search ) : ?>
					<input type="search" class="mtmt-search-input" placeholder="<?php echo esc_attr( $search_placeholder ); ?>">
				<?php endif; ?>
				<?php if ( $show_topic_filter && ! empty( $areas ) ) : ?>
					<select class="mtmt-area-filter">
						<option value="0"><?php echo esc_html( $area_filter_all_label ); ?></option>
						<?php foreach ( $areas as $area ) : ?>
							<option value="<?php echo esc_attr( (string) $area['id'] ); ?>"><?php echo esc_html( $area['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $years ) ) : ?>
			<div class="mtmt-year-tabs" role="tablist">
				<button type="button" class="mtmt-year-tab<?php echo ( 0 === $initial_year ) ? ' is-active' : ''; ?>" data-year="0"><?php echo esc_html( $year_tab_all_label ); ?></button>
				<?php foreach ( $years as $year ) : ?>
					<button type="button" class="mtmt-year-tab<?php echo ( $year === $initial_year ) ? ' is-active' : ''; ?>" data-year="<?php echo esc_attr( (string) $year ); ?>"><?php echo esc_html( (string) $year ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<div class="mtmt-widget-list">
				<?php if ( empty( $initial['items'] ) ) : ?>
					<p class="mtmt-widget-empty"><?php echo esc_html( $empty_state_text ); ?></p>
				<?php else : ?>
					<?php foreach ( $initial['items'] as $item ) : ?>
						<?php echo Mtmt_Card_Renderer::render( $item, $display_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a renderer maga escape-el. ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="mtmt-widget-pagination">
				<?php echo Mtmt_Widget_Ajax::render_pagination( 1, $total_pages, $prev_label, $next_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saját, escape-elt HTML-t épít. ?>
			</div>
		</div>
		<?php
	}
}
