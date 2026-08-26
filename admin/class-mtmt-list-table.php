<?php
/**
 * Moderációs lista (CLAUDE.md §8.1).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Oszlopok: indexkép, cím+szerzők, forrás, év, típus, SJR, MTMT-státusz,
 * linkek (DOI/MTMT), státusz. (A "kutatócsoport" oszlop Fázis 4-ig hiányzik,
 * addig nincs mihez kötni.) Sor-műveletek és tömeges jóváhagyás/elutasítás.
 */
final class Mtmt_List_Table extends WP_List_Table {

	/**
	 * @var Mtmt_Publication_Repository
	 */
	private $repository;

	/**
	 * @var string
	 */
	private $page_slug;

	/**
	 * @var array Az admin oldal évek/profilok dropdownjaihoz.
	 */
	private $filter_years;

	/**
	 * @var array{id:int,label:string}[]
	 */
	private $filter_profiles;

	/**
	 * @param Mtmt_Publication_Repository $repository
	 * @param string                      $page_slug
	 * @param int[]                       $filter_years
	 * @param array                       $filter_profiles
	 */
	public function __construct( Mtmt_Publication_Repository $repository, string $page_slug, array $filter_years, array $filter_profiles ) {
		parent::__construct(
			array(
				'singular' => 'publication',
				'plural'   => 'publications',
				'ajax'     => false,
			)
		);

		$this->repository      = $repository;
		$this->page_slug       = $page_slug;
		$this->filter_years    = $filter_years;
		$this->filter_profiles = $filter_profiles;
	}

	/**
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'thumbnail'  => '',
			'title'      => __( 'Cím / szerzők', 'mtmt-sync' ),
			'source'     => __( 'Forrás', 'mtmt-sync' ),
			'year'       => __( 'Év', 'mtmt-sync' ),
			'pub_type'   => __( 'Típus', 'mtmt-sync' ),
			'sjr'        => __( 'SJR', 'mtmt-sync' ),
			'mtmt_state' => __( 'MTMT-státusz', 'mtmt-sync' ),
			'links'      => __( 'Linkek', 'mtmt-sync' ),
			'status'     => __( 'Státusz', 'mtmt-sync' ),
		);
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'title' => array( 'title', false ),
			'year'  => array( 'published_year', false ),
		);
	}

	/**
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'approve' => __( 'Jóváhagyás', 'mtmt-sync' ),
			'reject'  => __( 'Elutasítás', 'mtmt-sync' ),
		);
	}

	/**
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'Nincs publikáció.', 'mtmt-sync' );
	}

	/**
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$status     = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$year       = isset( $_REQUEST['year'] ) ? absint( $_REQUEST['year'] ) : 0;
		$profile_id = isset( $_REQUEST['profile_id'] ) ? absint( $_REQUEST['profile_id'] ) : 0;
		$orderby    = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'published_year';
		$order      = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

		$result = $this->repository->get_list(
			array(
				'status'     => $status,
				'year'       => $year,
				'profile_id' => $profile_id,
				'orderby'    => $orderby,
				'order'      => $order,
				'paged'      => $paged,
				'per_page'   => $per_page,
			)
		);

		$this->items = $result['items'];

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="id[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_thumbnail( $item ): string {
		$thumb_id = (int) ( $item['thumbnail_id'] ?? 0 );
		if ( $thumb_id ) {
			$html = wp_get_attachment_image( $thumb_id, array( 48, 48 ), false, array( 'style' => 'border-radius:4px;' ) );
			return $html ?: '—';
		}
		return '<span class="dashicons dashicons-format-image" style="opacity:.3"></span>';
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_title( $item ): string {
		$id       = (int) $item['id'];
		$edit_url = $this->action_url( 'edit', $id );

		$title   = '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item['title'] ? $item['title'] : __( '(cím nélkül)', 'mtmt-sync' ) ) . '</a></strong>';
		$authors = ! empty( $item['authors_text'] ) ? '<br><span class="description">' . esc_html( $item['authors_text'] ) . '</span>' : '';

		$status  = $item['status'] ?? 'pending';
		$actions = array(
			'edit' => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Szerkesztés/Gazdagítás', 'mtmt-sync' ) . '</a>',
		);

		if ( 'approved' !== $status ) {
			$actions['approve'] = '<a href="' . esc_url( $this->row_action_url( 'approve', $id ) ) . '">' . esc_html__( 'Jóváhagyás', 'mtmt-sync' ) . '</a>';
		}
		if ( 'rejected' !== $status ) {
			$actions['reject'] = '<a href="' . esc_url( $this->row_action_url( 'reject', $id ) ) . '">' . esc_html__( 'Elutasítás', 'mtmt-sync' ) . '</a>';
		}
		if ( 'rejected' === $status ) {
			$actions['undo_reject'] = '<a href="' . esc_url( $this->row_action_url( 'undo_reject', $id ) ) . '">' . esc_html__( 'Elutasítás visszavonása', 'mtmt-sync' ) . '</a>';
		}

		return $title . $authors . $this->row_actions( $actions );
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_sjr( $item ): string {
		$q = $item['sjr_quartile'] ?? '';
		if ( '' === (string) $q ) {
			return '—';
		}
		return '<span style="display:inline-block;padding:2px 7px;border-radius:3px;background:#eef1f5;font-weight:600;font-size:11px;">' . esc_html( $q ) . '</span>';
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_links( $item ): string {
		$links = array();

		if ( ! empty( $item['doi'] ) ) {
			$links[] = '<a href="' . esc_url( 'https://doi.org/' . rawurlencode( $item['doi'] ) ) . '" target="_blank" rel="noopener">DOI</a>';
		}
		if ( ! empty( $item['mtid'] ) ) {
			$links[] = '<a href="' . esc_url( 'https://m2.mtmt.hu/gui2/?mode=browse&params=publication;' . (int) $item['mtid'] ) . '" target="_blank" rel="noopener">MTMT</a>';
		}

		return $links ? implode( ' | ', $links ) : '—';
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_status( $item ): string {
		$labels = array(
			'pending'  => __( 'Függőben', 'mtmt-sync' ),
			'approved' => __( 'Jóváhagyva', 'mtmt-sync' ),
			'rejected' => __( 'Elutasítva', 'mtmt-sync' ),
		);
		$status = $item['status'] ?? 'pending';

		return esc_html( $labels[ $status ] ?? $status );
	}

	/**
	 * @param array  $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'source':
				return esc_html( (string) ( $item['source_title'] ?? '' ) );
			case 'year':
				return esc_html( (string) ( $item['published_year'] ?? '' ) );
			case 'pub_type':
				return esc_html( (string) ( $item['pub_type'] ?? '' ) );
			case 'mtmt_state':
				return esc_html( (string) ( $item['mtmt_state'] ?? '' ) );
			default:
				return '';
		}
	}

	/**
	 * Szűrő-lenyílók a lista felett (CLAUDE.md §8.1 "Szűrők felül").
	 *
	 * @param string $which
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current_status  = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$current_year    = isset( $_REQUEST['year'] ) ? absint( $_REQUEST['year'] ) : 0;
		$current_profile = isset( $_REQUEST['profile_id'] ) ? absint( $_REQUEST['profile_id'] ) : 0;
		?>
		<div class="alignleft actions">
			<select name="status">
				<option value=""><?php esc_html_e( 'Minden státusz', 'mtmt-sync' ); ?></option>
				<option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Függőben', 'mtmt-sync' ); ?></option>
				<option value="approved" <?php selected( $current_status, 'approved' ); ?>><?php esc_html_e( 'Jóváhagyva', 'mtmt-sync' ); ?></option>
				<option value="rejected" <?php selected( $current_status, 'rejected' ); ?>><?php esc_html_e( 'Elutasítva', 'mtmt-sync' ); ?></option>
			</select>
			<select name="year">
				<option value=""><?php esc_html_e( 'Minden év', 'mtmt-sync' ); ?></option>
				<?php foreach ( $this->filter_years as $year ) : ?>
					<option value="<?php echo esc_attr( (string) $year ); ?>" <?php selected( $current_year, $year ); ?>><?php echo esc_html( (string) $year ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="profile_id">
				<option value=""><?php esc_html_e( 'Minden profil', 'mtmt-sync' ); ?></option>
				<?php foreach ( $this->filter_profiles as $profile ) : ?>
					<option value="<?php echo esc_attr( (string) $profile['id'] ); ?>" <?php selected( $current_profile, (int) $profile['id'] ); ?>><?php echo esc_html( $profile['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Szűrés', 'mtmt-sync' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * @param string $action
	 * @param int    $id
	 * @return string
	 */
	private function action_url( string $action, int $id ): string {
		return add_query_arg(
			array(
				'page'   => $this->page_slug,
				'action' => $action,
				'id'     => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param string $action
	 * @param int    $id
	 * @return string
	 */
	private function row_action_url( string $action, int $id ): string {
		return wp_nonce_url( $this->action_url( $action, $id ), 'mtmt_row_action_' . $action . '_' . $id );
	}
}
