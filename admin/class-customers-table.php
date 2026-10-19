<?php
/**
 * Customers list table.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Customer records list.
 */
class QVB_Customers_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'customer',
				'plural'   => 'customers',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'name'            => __( 'Customer', 'qevora-booking' ),
			'contact'         => __( 'Contact', 'qevora-booking' ),
			'total_bookings'  => __( 'Bookings', 'qevora-booking' ),
			'total_spent'     => __( 'Value', 'qevora-booking' ),
			'last_booking_at' => __( 'Last visit', 'qevora-booking' ),
			'created_at'      => __( 'First seen', 'qevora-booking' ),
			'actions'         => __( 'Actions', 'qevora-booking' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'            => array( 'name', false ),
			'total_bookings'  => array( 'total_bookings', false ),
			'total_spent'     => array( 'total_spent', false ),
			'last_booking_at' => array( 'last_booking_at', true ),
			'created_at'      => array( 'created_at', false ),
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No customers yet. Customer records are created automatically with the first booking.', 'qevora-booking' );
	}

	/**
	 * Name column.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_name( $item ) {
		$url  = QVB_Admin::url( 'customers', array( 'action' => 'view', 'customer' => (int) $item->id ) );
		$html = '<strong>' . esc_html( $item->name ) . '</strong>';

		if ( QVB_Admin::can_view_pii() ) {
			$html .= '<div class="row-actions"><span class="edit"><a href="' . esc_url( $url ) . '">' . esc_html__( 'View', 'qevora-booking' ) . '</a> | ';
			$html .= '<a href="' . esc_url( admin_url( 'admin.php?page=qevora-booking-bookings&s=' . rawurlencode( $item->email ) ) ) . '">' . esc_html__( 'Bookings', 'qevora-booking' ) . '</a></span></div>';
		}

		return $html;
	}

	/**
	 * Contact details.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_contact( $item ) {
		if ( ! QVB_Admin::can_view_pii() ) {
			return esc_html( QVB_Utils::mask_email( $item->email ) );
		}

		$html = '<a href="mailto:' . esc_attr( $item->email ) . '">' . esc_html( $item->email ) . '</a>';
		if ( $item->phone ) {
			$html .= '<br /><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $item->phone ) ) . '">' . esc_html( $item->phone ) . '</a>';
		}
		return $html;
	}

	/**
	 * Booking count.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_total_bookings( $item ) {
		return esc_html( number_format_i18n( (int) $item->total_bookings ) );
	}

	/**
	 * Lifetime value.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_total_spent( $item ) {
		return esc_html( QVB_Utils::money( (float) $item->total_spent ) );
	}

	/**
	 * Last visit.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_last_booking_at( $item ) {
		if ( empty( $item->last_booking_at ) ) {
			return '&mdash;';
		}
		return esc_html( QVB_Utils::format_admin_datetime( $item->last_booking_at ) );
	}

	/**
	 * First seen.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( QVB_Utils::format_admin_datetime( $item->created_at ) );
	}

	/**
	 * Row actions.
	 *
	 * @param object $item Customer row.
	 * @return string
	 */
	public function column_actions( $item ) {
		if ( ! QVB_Admin::can_view_pii() ) {
			return '<span class="qvb-muted">' . esc_html__( 'Restricted', 'qevora-booking' ) . '</span>';
		}

		$url = QVB_Admin::url( 'customers', array( 'action' => 'view', 'customer' => (int) $item->id ) );
		return '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'qevora-booking' ) . '</a>';
	}

	/**
	 * Default column.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Loads rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_booking_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = QVB_Customers::search(
			array(
				'search'   => $search,
				'paged'    => $paged,
				'per_page' => 20,
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => (int) $result['total'],
				'per_page'    => 20,
				'total_pages' => (int) $result['pages'],
			)
		);
	}
}
