<?php
/**
 * Bookings list table.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Bookings table.
 */
class QVB_Bookings_Table extends WP_List_Table {

	/**
	 * Filters applied to the query.
	 *
	 * @var array
	 */
	public $filters = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'booking',
				'plural'   => 'bookings',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column definitions.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'reference' => __( 'Reference', 'qevora-booking' ),
			'customer'  => __( 'Customer', 'qevora-booking' ),
			'service'   => __( 'Service', 'qevora-booking' ),
			'staff'     => __( 'Staff', 'qevora-booking' ),
			'when'      => __( 'Date & time', 'qevora-booking' ),
			'status'    => __( 'Status', 'qevora-booking' ),
			'price'     => __( 'Price', 'qevora-booking' ),
			'actions'   => __( 'Actions', 'qevora-booking' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'reference' => array( 'id', false ),
			'when'      => array( 'booking_date', true ),
			'status'    => array( 'status', false ),
			'customer'  => array( 'customer_name', false ),
			'price'     => array( 'price', false ),
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No bookings match this view.', 'qevora-booking' );
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="booking[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Reference column with row actions.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_reference( $item ) {
		$edit = QVB_Admin::url(
			'bookings',
			array(
				'action'  => 'edit',
				'booking' => (int) $item->id,
			)
		);

		$html = sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>',
			esc_url( $edit ),
			esc_html( $item->reference )
		);

		$html .= '<div class="row-actions">';
		$actions = array(
			'edit' => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'View', 'qevora-booking' ) ),
		);

		if ( QVB_Bookings::can_cancel( $item, 'admin' ) === true && in_array( $item->status, array( 'pending', 'confirmed' ), true ) ) {
			$actions['cancel'] = sprintf(
				'<a href="%s" class="qvb-confirm" data-confirm="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						QVB_Admin::action_url(
							'qvb_update_booking_status',
							array(
								'id'     => (int) $item->id,
								'status' => 'cancelled',
							)
						),
						'qvb_update_booking_status_' . (int) $item->id
					)
				),
				esc_attr__( 'Cancel this booking?', 'qevora-booking' ),
				esc_html__( 'Cancel', 'qevora-booking' )
			);
		}

		$html .= implode( ' | ', $actions );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Customer column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_customer( $item ) {
		$html = '<strong>' . esc_html( $item->customer_name ) . '</strong>';

		if ( QVB_Admin::can_view_pii() ) {
			$html .= '<br /><a href="mailto:' . esc_attr( $item->customer_email ) . '">' . esc_html( $item->customer_email ) . '</a>';
			if ( $item->customer_phone ) {
				$html .= '<br /><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $item->customer_phone ) ) . '">' . esc_html( $item->customer_phone ) . '</a>';
			}
		} else {
			$html .= '<br /><span class="description">' . esc_html__( 'Contact details restricted', 'qevora-booking' ) . '</span>';
		}

		return $html;
	}

	/**
	 * Service column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_service( $item ) {
		$html = esc_html( $item->service_name );
		$html .= '<br /><span class="qvb-muted">' . esc_html( QVB_Utils::duration_label( $item->duration ) ) . '</span>';
		return $html;
	}

	/**
	 * Staff column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_staff( $item ) {
		return $item->staff_name ? esc_html( $item->staff_name ) : '<span class="qvb-muted">' . esc_html__( 'Any', 'qevora-booking' ) . '</span>';
	}

	/**
	 * Date and time column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_when( $item ) {
		return sprintf(
			'<strong>%s</strong><br /><span class="qvb-muted">%s</span>',
			esc_html( QVB_Utils::format_date_weekday( $item->booking_date ) ),
			esc_html( QVB_Utils::format_time_range( $item->start_time, $item->end_time ) )
		);
	}

	/**
	 * Status column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_status( $item ) {
		return QVB_Admin::status_badge( $item->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Price column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_price( $item ) {
		return esc_html( QVB_Utils::money( $item->price, $item->currency ) );
	}

	/**
	 * Actions column.
	 *
	 * @param object $item Booking.
	 * @return string
	 */
	public function column_actions( $item ) {
		$edit = QVB_Admin::url(
			'bookings',
			array(
				'action'  => 'edit',
				'booking' => (int) $item->id,
			)
		);

		return sprintf( '<a class="button button-small" href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Manage', 'qevora-booking' ) );
	}

	/**
	 * Default column output.
	 *
	 * @param object $item        Booking.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$counts  = QVB_Bookings::status_counts( $this->filters );
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views   = array();

		$base = QVB_Admin::url( 'bookings' );

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( remove_query_arg( array( 'status', 'paged' ), $base ) ),
			'' === $current ? 'current' : '',
			esc_html__( 'All', 'qevora-booking' ),
			(int) $counts['all']
		);

		foreach ( QVB_Utils::statuses() as $status ) {
			$views[ $status ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$current === $status ? 'current' : '',
				esc_html( QVB_Utils::status_label( $status ) ),
				(int) $counts[ $status ]
			);
		}

		return $views;
	}

	/**
	 * Loads rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = 20;
		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1;
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'booking_date';
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$this->filters = array(
			'date_from' => isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '',
			'date_to'   => isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = QVB_Bookings::query(
			array(
				'status'     => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'service_id' => isset( $_REQUEST['service_id'] ) ? absint( $_REQUEST['service_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'staff_id'   => isset( $_REQUEST['staff_id'] ) ? absint( $_REQUEST['staff_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'date_from'  => $this->filters['date_from'],
				'date_to'    => $this->filters['date_to'],
				'search'     => $search,
				'orderby'    => $orderby,
				'order'      => $order,
				'per_page'   => $per_page,
				'page'       => $paged,
			)
		);

		$this->items           = $result['items'];
		$this->filters         = array_intersect_key( $this->filters, array_flip( array( 'date_from', 'date_to' ) ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => (int) $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) $result['pages'],
			)
		);
	}
}
