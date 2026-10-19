<?php
/**
 * WordPress privacy tools integration.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Privacy exporter, eraser and policy suggestions.
 */
final class QVB_Privacy {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_policy_content' ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['qevora-booking'] = array(
			'exporter_friendly_name' => __( 'Qevora Booking', 'qevora-booking' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['qevora-booking'] = array(
			'eraser_friendly_name' => __( 'Qevora Booking', 'qevora-booking' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports customer data and booking history.
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page.
	 * @return array
	 */
	public static function export( $email_address, $page = 1 ) {
		$email    = strtolower( sanitize_email( $email_address ) );
		$page     = max( 1, (int) $page );
		$per_page = 100;

		if ( ! $email || ! is_email( $email ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array();
		$customer = QVB_Customers::get_by_email( $email );

		if ( $customer && 1 === $page ) {
			$data[] = array(
				'group_id'          => 'qevora-customer',
				'group_label'       => __( 'Qevora Booking – customer profile', 'qevora-booking' ),
				'group_description' => __( 'Your contact details held for appointment booking.', 'qevora-booking' ),
				'item_id'           => 'qevora-customer-' . $customer->id,
				'data'              => array(
					array(
						'name'  => __( 'Name', 'qevora-booking' ),
						'value' => $customer->name,
					),
					array(
						'name'  => __( 'Email', 'qevora-booking' ),
						'value' => $customer->email,
					),
					array(
						'name'  => __( 'Phone', 'qevora-booking' ),
						'value' => $customer->phone,
					),
					array(
						'name'  => __( 'First seen', 'qevora-booking' ),
						'value' => $customer->created_at,
					),
				),
			);
		}

		$result = QVB_Bookings::query(
			array(
				'search'   => $email,
				'per_page' => $per_page,
				'page'     => $page,
				'orderby'  => 'booking_date',
				'order'    => 'ASC',
			)
		);

		foreach ( $result['items'] as $booking ) {
			if ( strtolower( $booking->customer_email ) !== $email ) {
				continue;
			}
			$data[] = array(
				'group_id'          => 'qevora-bookings',
				'group_label'       => __( 'Qevora Booking – appointments', 'qevora-booking' ),
				'group_description' => __( 'Appointments you have booked.', 'qevora-booking' ),
				'item_id'           => 'qevora-booking-' . $booking->id,
				'data'              => array(
					array(
						'name'  => __( 'Reference', 'qevora-booking' ),
						'value' => $booking->reference,
					),
					array(
						'name'  => __( 'Service', 'qevora-booking' ),
						'value' => $booking->service_name,
					),
					array(
						'name'  => __( 'Staff', 'qevora-booking' ),
						'value' => $booking->staff_name,
					),
					array(
						'name'  => __( 'Date', 'qevora-booking' ),
						'value' => QVB_Utils::format_date_weekday( $booking->booking_date ),
					),
					array(
						'name'  => __( 'Time', 'qevora-booking' ),
						'value' => QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ),
					),
					array(
						'name'  => __( 'Status', 'qevora-booking' ),
						'value' => QVB_Utils::status_label( $booking->status ),
					),
					array(
						'name'  => __( 'Price', 'qevora-booking' ),
						'value' => QVB_Utils::money( $booking->price, $booking->currency ),
					),
					array(
						'name'  => __( 'Notes you provided', 'qevora-booking' ),
						'value' => $booking->notes,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => $page >= max( 1, (int) $result['pages'] ),
		);
	}

	/**
	 * Erases (anonymises) customer data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page.
	 * @return array
	 */
	public static function erase( $email_address, $page = 1 ) {
		global $wpdb;

		$email = strtolower( sanitize_email( $email_address ) );
		$page  = max( 1, (int) $page );

		if ( ! $email || ! is_email( $email ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$removed = false;
		$customer = QVB_Customers::get_by_email( $email );

		if ( $customer ) {
			$removed = QVB_Customers::anonymize( (int) $customer->id );
		}

		// Bookings created before the customer table existed, or detached rows.
		$bookings = QVB_Database::table( 'bookings' );
		$updated  = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$bookings}
				 SET customer_name = %s, customer_email = '', customer_phone = '', notes = '', ip_hash = ''
				 WHERE customer_email = %s",
				__( 'Deleted customer', 'qevora-booking' ),
				$email
			)
		);

		if ( $updated ) {
			$removed = true;
		}

		return array(
			'items_removed'  => (bool) $removed,
			'items_retained' => false,
			'messages'       => $removed ? array( __( 'Customer contact details and appointment notes were anonymised.', 'qevora-booking' ) ) : array(),
			'done'           => true,
		);
	}

	/**
	 * Adds a suggested privacy policy section.
	 *
	 * @return void
	 */
	public static function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'When you book an appointment through this site we collect your name, email address, phone number and any notes you add to the booking. We also record the service, staff member, date and time you selected.', 'qevora-booking' ) . '</p>'
			. '<p>' . esc_html__( 'This information is used only to manage your appointment, send booking confirmations and updates, and keep a record of your visit. It is not used for unrelated marketing.', 'qevora-booking' ) . '</p>'
			. '<p>' . esc_html__( 'Appointment history is retained according to the salon’s data retention setting. You can ask the salon to access or erase your data at any time.', 'qevora-booking' ) . '</p>'
			. '<p>' . esc_html__( 'Booking requests are rate-limited and protected with a signed form token. A salted, non-reversible hash of your IP address may be stored for abuse prevention.', 'qevora-booking' ) . '</p>';

		wp_add_privacy_policy_content( 'Qevora Booking', wp_kses_post( $content ) );
	}
}
