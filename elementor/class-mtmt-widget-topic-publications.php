<?php
/**
 * "B" — terület-aloldal widget (CLAUDE.md §14/10, §14/11).
 *
 * Csak `is_featured=1` tételek, EGY konkrét szakmai területre VAGY lekérdezési
 * profilra szűkítve (CLAUDE.md §14/10: "területre/profilra szűkítve" — a két
 * mód kölcsönösen kizárja egymást a widget-beállításban). Csak akkor kerül
 * regisztrálásra (lásd Mtmt_Elementor_Loader::register_widgets()), ha a
 * "kiemelt cikk" funkció be van kapcsolva — ez FÜGGETLEN a "szakmai terület"
 * togletől, ezért a "profil" mód akkor is működik, ha a terület-funkció ki van
 * kapcsolva (docs/widget-design.md).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget: egy terület/profil kiemelt publikációi.
 */
final class Mtmt_Widget_Topic_Publications extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'mtmt_topic_publications';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'MTMT publikációk – szakmai terület', 'mtmt-sync' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-star';
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
		return array( 'mtmt', 'publikáció', 'publication', 'kiemelt', 'terület' );
	}

	/**
	 * Widget-beállítások — a scope-választó DB-t olvas (Elementor-szerkesztő
	 * kontextusban fut, admin-only, elfogadható).
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'mtmt_scope_section',
			array(
				'label' => __( 'Szűkítés', 'mtmt-sync' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'scope_mode',
			array(
				'label'   => __( 'Szűkítés módja', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'area',
				'options' => array(
					'area'    => __( 'Szakmai terület', 'mtmt-sync' ),
					'profile' => __( 'Lekérdezési profil', 'mtmt-sync' ),
				),
			)
		);

		$this->add_control(
			'area_id',
			array(
				'label'     => __( 'Szakmai terület', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $this->get_area_options(),
				'condition' => array( 'scope_mode' => 'area' ),
			)
		);

		$this->add_control(
			'profile_id',
			array(
				'label'     => __( 'Lekérdezési profil', 'mtmt-sync' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $this->get_profile_options(),
				'condition' => array( 'scope_mode' => 'profile' ),
			)
		);

		$this->end_controls_section();

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
				'label'   => __( 'DOI megjelenítése', 'mtmt-sync' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
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
	 * @return array<int,string> id => label, "0" ha még nincs egy terület sem.
	 */
	private function get_area_options(): array {
		if ( ! class_exists( 'Mtmt_Topic_Area_Repository' ) ) {
			return array( '0' => __( '(nincs elérhető terület)', 'mtmt-sync' ) );
		}
		global $wpdb;
		$areas   = ( new Mtmt_Topic_Area_Repository( $wpdb ) )->get_all();
		$options = array();
		foreach ( $areas as $area ) {
			$options[ (string) $area['id'] ] = $area['label'];
		}
		return $options ?: array( '0' => __( '(nincs elérhető terület — hozz létre egyet a Területek oldalon)', 'mtmt-sync' ) );
	}

	/**
	 * @return array<int,string> id => label.
	 */
	private function get_profile_options(): array {
		if ( ! class_exists( 'Mtmt_Query_Profile_Repository' ) ) {
			return array( '0' => __( '(nincs elérhető profil)', 'mtmt-sync' ) );
		}
		global $wpdb;
		$profiles = ( new Mtmt_Query_Profile_Repository( $wpdb ) )->get_all();
		$options  = array();
		foreach ( $profiles as $profile ) {
			$options[ (string) $profile['id'] ] = $profile['label'];
		}
		return $options ?: array( '0' => __( '(nincs elérhető profil)', 'mtmt-sync' ) );
	}

	/**
	 * Kezdeti (nem-AJAX) szerver-oldali render.
	 */
	protected function render() {
		if ( ! class_exists( 'Mtmt_Publication_Repository' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();

		$scope_mode = 'profile' === $settings['scope_mode'] ? 'profile' : 'area';
		$area_id    = ( 'area' === $scope_mode ) ? absint( $settings['area_id'] ?? 0 ) : 0;
		$profile_id = ( 'profile' === $scope_mode ) ? absint( $settings['profile_id'] ?? 0 ) : 0;

		if ( ! $area_id && ! $profile_id ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<p class="mtmt-widget-empty">' . esc_html__( 'Válassz egy szakmai területet vagy lekérdezési profilt a widget beállításaiban.', 'mtmt-sync' ) . '</p>';
			}
			return;
		}

		global $wpdb;
		$repo        = new Mtmt_Publication_Repository( $wpdb );
		$topic_repo  = new Mtmt_Topic_Area_Repository( $wpdb );
		$widget_data = new Mtmt_Widget_Data( $repo, $topic_repo );

		$topic_areas_enabled = (bool) get_option( 'mtmt_enable_topic_areas' );
		$show_search         = 'yes' === $settings['show_search'];
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

		$scope = array(
			'area_id'       => $area_id,
			'profile_id'    => $profile_id,
			'featured_only' => true,
		);

		$years        = $widget_data->get_available_years( $scope );
		$initial_year = $years[0] ?? 0;

		$initial = $widget_data->query(
			array_merge(
				$scope,
				array(
					'year'     => $initial_year,
					'per_page' => $per_page,
				)
			)
		);

		$total_pages = $per_page > 0 ? max( 1, (int) ceil( $initial['total'] / $per_page ) ) : 1;
		?>
		<div class="mtmt-widget mtmt-widget-topic"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( Mtmt_Widget_Ajax::NONCE_ACTION ) ); ?>"
			data-widget-type="topic"
			data-area-id="<?php echo esc_attr( (string) $area_id ); ?>"
			data-profile-id="<?php echo esc_attr( (string) $profile_id ); ?>"
			data-featured-only="1"
			data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
			data-citation-style="<?php echo esc_attr( $citation_style ); ?>"
			data-show-doi-link="<?php echo $show_doi_link ? '1' : '0'; ?>"
			data-show-sjr-badge="<?php echo $show_sjr_badge ? '1' : '0'; ?>"
			data-show-topic-area="<?php echo $topic_areas_enabled ? '1' : '0'; ?>"
			data-year="<?php echo esc_attr( (string) $initial_year ); ?>">

			<?php if ( $show_search ) : ?>
			<div class="mtmt-widget-controls">
				<input type="search" class="mtmt-search-input" placeholder="<?php esc_attr_e( 'Keresés cím, szerző vagy forrás szerint…', 'mtmt-sync' ); ?>">
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
					<p class="mtmt-widget-empty"><?php esc_html_e( 'Nincs kiemelt publikáció ebben a körben.', 'mtmt-sync' ); ?></p>
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
