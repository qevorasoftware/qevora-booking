<?php
/**
 * Admin form handlers (admin-post.php endpoints).
 *
 * Every handler: capability check + nonce check + sanitisation + domain call +
 * flash notice + redirect (POST/Redirect/GET).
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin action handlers.
 */
final class QVB_Admin_Actions {

	/**
	 * Registers admin-post hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$actions = array(
			'save_service',
			'delete_service',
			'save_category',
			'delete_category',
			'save_staff',
			'delete_staff',
			'save_availability',
			'save_exception',
			'delete_exception',
			'create_booking',
			'update_booking_status',
			'reschedule_booking',
			'save_booking_notes',
			'resend_booking_email',
			'bulk_bookings',
			'save_settings',
			'save_email_template',
			'reset_email_template',
			'test_email',
			'save_customer',
			'anonymize_customer',
			'repair_tables',
			'run_maintenance',
		);

		foreach ( $actions as $action ) {
			add_action( 'admin_post_qvb_' . $action, array( __CLASS__, $action ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Reads an unslashed POST value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
	}

	/**
	 * Reads an unslashed GET value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private static function get( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : $default;
	}

	/**
	 * Flashes an error and redirects.
	 *
	 * @param string $message Message.
	 * @param string $url     URL.
	 * @return void
	 */
	private static function fail( $message, $url ) {
		QVB_Admin::flash( $message, 'error' );
		QVB_Admin::redirect( $url );
	}

	/**
	 * Redirects to the previous screen or a fallback.
	 *
	 * @param string $fallback Fallback URL.
	 * @return void
	 */
	private static function back( $fallback ) {
		$referer = wp_get_referer();
		QVB_Admin::redirect( $referer ? $referer : $fallback );
	}

	/* ------------------------------------------------------------------ */
	/* Services                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves a service.
	 *
	 * @return void
	 */
	public static function save_service() {
		QVB_Security::verify_admin( 'qvb_save_service', QVB_Capabilities::MANAGE );

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'id'          => $id,
			'name'        => self::post( 'name' ),
			'slug'        => self::post( 'slug' ),
			'category_id' => self::post( 'category_id' ),
			'description' => self::post( 'description' ),
			'image_id'    => self::post( 'image_id' ),
			'duration'    => self::post( 'duration' ),
			'buffer_time' => self::post( 'buffer_time' ),
			'price'       => self::post( 'price' ),
			'status'      => self::post( 'status', 'active' ),
			'sort_order'  => self::post( 'sort_order' ),
		);

		$result = QVB_Services::save( $data );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), QVB_Admin::url( 'services', array( 'action' => 'edit', 'id' => $id ) ) );
		}

		QVB_Admin::flash( __( 'Service saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'services', array( 'action' => 'edit', 'id' => $result ) ) );
	}

	/**
	 * Deletes a service.
	 *
	 * @return void
	 */
	public static function delete_service() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_delete_service_' . $id, QVB_Capabilities::MANAGE );

		if ( QVB_Services::delete( $id ) ) {
			QVB_Admin::flash( __( 'Service deleted. Historical bookings keep their saved details.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The service could not be deleted.', 'qevora-booking' ), 'error' );
		}

		QVB_Admin::redirect( QVB_Admin::url( 'services' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Categories                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves a category.
	 *
	 * @return void
	 */
	public static function save_category() {
		QVB_Security::verify_admin( 'qvb_save_category', QVB_Capabilities::MANAGE );

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'id'          => $id,
			'name'        => self::post( 'name' ),
			'slug'        => self::post( 'slug' ),
			'description' => self::post( 'description' ),
			'image_id'    => self::post( 'image_id' ),
			'status'      => self::post( 'status', 'active' ),
			'sort_order'  => self::post( 'sort_order' ),
		);

		$result = QVB_Categories::save( $data );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), QVB_Admin::url( 'categories', array( 'action' => 'edit', 'id' => $id ) ) );
		}

		QVB_Admin::flash( __( 'Category saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'categories', array( 'action' => 'edit', 'id' => $result ) ) );
	}

	/**
	 * Deletes a category.
	 *
	 * @return void
	 */
	public static function delete_category() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_delete_category_' . $id, QVB_Capabilities::MANAGE );

		if ( QVB_Categories::delete( $id ) ) {
			QVB_Admin::flash( __( 'Category deleted. Its services are now uncategorised.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The category could not be deleted.', 'qevora-booking' ), 'error' );
		}

		QVB_Admin::redirect( QVB_Admin::url( 'categories' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Staff                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves a staff member and their service assignments.
	 *
	 * @return void
	 */
	public static function save_staff() {
		QVB_Security::verify_admin( 'qvb_save_staff', QVB_Capabilities::MANAGE );

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'id'                 => $id,
			'name'               => self::post( 'name' ),
			'email'              => self::post( 'email' ),
			'phone'              => self::post( 'phone' ),
			'job_title'          => self::post( 'job_title' ),
			'bio'                => self::post( 'bio' ),
			'image_id'           => self::post( 'image_id' ),
			'color'              => self::post( 'color', '#7c3aed' ),
			'status'             => self::post( 'status', 'active' ),
			'sort_order'         => self::post( 'sort_order' ),
			'use_business_hours' => ! empty( $_POST['use_business_hours'] ) ? 1 : 0,
		);

		$result = QVB_Staff::save( $data );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), QVB_Admin::url( 'staff', array( 'action' => 'edit', 'id' => $id ) ) );
		}

		$services = isset( $_POST['services'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['services'] ) ) : array();
		QVB_Staff::set_services( $result, $services );

		QVB_Admin::flash( __( 'Staff member saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'staff', array( 'action' => 'edit', 'id' => $result ) ) );
	}

	/**
	 * Deletes a staff member.
	 *
	 * @return void
	 */
	public static function delete_staff() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_delete_staff_' . $id, QVB_Capabilities::MANAGE );

		if ( QVB_Staff::delete( $id ) ) {
			QVB_Admin::flash( __( 'Staff member deleted. Historical bookings keep their saved details.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The staff member could not be deleted.', 'qevora-booking' ), 'error' );
		}

		QVB_Admin::redirect( QVB_Admin::url( 'staff' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Availability + exceptions                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves a weekly schedule or switches to business hours.
	 *
	 * @return void
	 */
	public static function save_availability() {
		QVB_Security::verify_admin( 'qvb_save_availability', QVB_Capabilities::MANAGE );

		$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$mode     = isset( $_POST['mode'] ) && 'custom' === $_POST['mode'] ? 'custom' : 'business';
		$url      = QVB_Admin::url( 'availability', array( 'staff_id' => $staff_id ) );

		if ( ! $staff_id ) {
			self::fail( __( 'Please choose a staff member.', 'qevora-booking' ), $url );
		}

		if ( 'business' === $mode ) {
			QVB_Availability::set_use_business_hours( $staff_id, 1 );
			QVB_Admin::flash( __( 'This staff member now follows the business hours.', 'qevora-booking' ) );
			QVB_Admin::redirect( $url );
		}

		$rows = isset( $_POST['rows'] ) ? (array) wp_unslash( $_POST['rows'] ) : array();
		$result = QVB_Availability::save_schedule( $staff_id, $rows );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), $url );
		}

		QVB_Availability::set_use_business_hours( $staff_id, 0 );

		QVB_Admin::flash( __( 'Custom schedule saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( $url );
	}

	/**
	 * Saves a holiday / special hours exception.
	 *
	 * @return void
	 */
	public static function save_exception() {
		QVB_Security::verify_admin( 'qvb_save_exception', QVB_Capabilities::MANAGE );

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$data = array(
			'id'         => $id,
			'staff_id'   => self::post( 'staff_id' ),
			'from_date'  => self::post( 'from_date' ),
			'to_date'    => self::post( 'to_date' ),
			'type'       => self::post( 'type', 'closed' ),
			'start_time' => self::post( 'start_time' ),
			'end_time'   => self::post( 'end_time' ),
			'note'       => self::post( 'note' ),
		);

		$result = QVB_Availability::save_exception( $data );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), QVB_Admin::url( 'exceptions', array( 'action' => 'edit', 'id' => $id ) ) );
		}

		QVB_Admin::flash( __( 'Holiday / exception saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'exceptions', array( 'action' => 'edit', 'id' => $result ) ) );
	}

	/**
	 * Deletes an exception.
	 *
	 * @return void
	 */
	public static function delete_exception() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_delete_exception_' . $id, QVB_Capabilities::MANAGE );

		if ( QVB_Availability::delete_exception( $id ) ) {
			QVB_Admin::flash( __( 'Exception deleted.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The exception could not be deleted.', 'qevora-booking' ), 'error' );
		}

		QVB_Admin::redirect( QVB_Admin::url( 'exceptions' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Bookings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Creates a booking from the admin.
	 *
	 * @return void
	 */
	public static function create_booking() {
		QVB_Security::verify_admin( 'qvb_create_booking', QVB_Capabilities::MANAGE );

		$data = array(
			'service_id' => self::post( 'service_id' ),
			'staff_id'   => self::post( 'staff_id' ),
			'date'       => self::post( 'date' ),
			'time'       => self::post( 'time' ),
			'name'       => self::post( 'name' ),
			'email'      => self::post( 'email' ),
			'phone'      => self::post( 'phone' ),
			'notes'      => self::post( 'notes' ),
			'status'     => self::post( 'status', 'confirmed' ),
			'consent'    => 1,
		);

		$result = QVB_Bookings::create( $data, 'admin' );

		if ( is_wp_error( $result ) ) {
			$back = QVB_Admin::url(
				'bookings',
				array(
					'action'     => 'new',
					'service_id' => absint( $data['service_id'] ),
					'staff_id'   => absint( $data['staff_id'] ),
					'date'       => sanitize_text_field( $data['date'] ),
					'time'       => sanitize_text_field( $data['time'] ),
				)
			);
			self::fail( $result->get_error_message(), $back );
		}

		QVB_Admin::flash( __( 'Booking created.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => $result['id'] ) ) );
	}

	/**
	 * Updates a booking status from the edit screen or a quick link.
	 *
	 * @return void
	 */
	public static function update_booking_status() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : absint( $_REQUEST['booking'] ?? 0 );
		QVB_Security::verify_admin( 'qvb_update_booking_status_' . $id, QVB_Capabilities::MANAGE );

		$booking = QVB_Bookings::get( $id );
		if ( ! $booking ) {
			self::fail( __( 'That booking no longer exists.', 'qevora-booking' ), QVB_Admin::url( 'bookings' ) );
		}

		$status = sanitize_key( (string) self::post( 'status', self::get( 'status' ) ) );
		$reason = sanitize_text_field( (string) self::post( 'reason' ) );

		$result = QVB_Bookings::update_status(
			$id,
			$status,
			array(
				'context' => 'admin',
				'reason'  => $reason,
				'user_id' => get_current_user_id(),
			)
		);

		$back = QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => $id ) );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), $back );
		}

		QVB_Admin::flash( __( 'Booking status updated.', 'qevora-booking' ) );
		self::back( $back );
	}

	/**
	 * Reschedules a booking from the admin.
	 *
	 * @return void
	 */
	public static function reschedule_booking() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_reschedule_booking_' . $id, QVB_Capabilities::MANAGE );

		$back = QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => $id ) );

		$date     = sanitize_text_field( (string) self::post( 'date' ) );
		$time     = sanitize_text_field( (string) self::post( 'time' ) );
		$staff_id = absint( self::post( 'staff_id' ) );

		$result = QVB_Bookings::reschedule( $id, $date, $time, $staff_id, array( 'context' => 'admin' ) );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), $back );
		}

		QVB_Admin::flash( __( 'Booking rescheduled.', 'qevora-booking' ) );
		QVB_Admin::redirect( $back );
	}

	/**
	 * Saves private admin notes.
	 *
	 * @return void
	 */
	public static function save_booking_notes() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_save_booking_notes_' . $id, QVB_Capabilities::MANAGE );

		QVB_Bookings::update_notes( $id, (string) self::post( 'admin_notes' ) );

		QVB_Admin::flash( __( 'Notes saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => $id ) ) );
	}

	/**
	 * Resends a status email for a booking.
	 *
	 * @return void
	 */
	public static function resend_booking_email() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_resend_booking_email_' . $id, QVB_Capabilities::MANAGE );

		$booking = QVB_Bookings::get( $id );
		$back    = QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => $id ) );

		if ( ! $booking ) {
			self::fail( __( 'That booking no longer exists.', 'qevora-booking' ), QVB_Admin::url( 'bookings' ) );
		}

		$event = 'customer_status_changed';
		if ( 'pending' === $booking->status ) {
			$event = 'customer_pending';
		} elseif ( 'confirmed' === $booking->status ) {
			$event = 'customer_confirmed';
		} elseif ( 'cancelled' === $booking->status ) {
			$event = 'customer_cancelled';
		}

		$sent = QVB_Notifications::send( $event, $booking );

		if ( $sent ) {
			QVB_Admin::flash( __( 'Email sent.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The email could not be sent. Check your mail configuration and the template settings.', 'qevora-booking' ), 'warning' );
		}

		QVB_Admin::redirect( $back );
	}

	/**
	 * Handles bulk status changes.
	 *
	 * @return void
	 */
	public static function bulk_bookings() {
		QVB_Security::verify_admin( 'qvb_bulk_bookings', QVB_Capabilities::MANAGE );

		$action = isset( $_POST['qvb_bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['qvb_bulk_action'] ) ) : '';
		$ids    = isset( $_POST['booking'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['booking'] ) ) : array();

		$map = array(
			'confirm'  => 'confirmed',
			'complete' => 'completed',
			'cancel'   => 'cancelled',
			'reject'   => 'rejected',
			'no_show'  => 'no_show',
		);

		if ( ! isset( $map[ $action ] ) || ! $ids ) {
			self::fail( __( 'Please choose bookings and a bulk action.', 'qevora-booking' ), QVB_Admin::url( 'bookings' ) );
		}

		$done = 0;
		$fail = 0;
		foreach ( $ids as $id ) {
			$result = QVB_Bookings::update_status(
				$id,
				$map[ $action ],
				array(
					'context' => 'admin',
					'reason'  => __( 'Bulk update', 'qevora-booking' ),
					'user_id' => get_current_user_id(),
				)
			);
			if ( is_wp_error( $result ) ) {
				$fail++;
			} else {
				$done++;
			}
		}

		QVB_Admin::flash(
			sprintf(
				/* translators: 1: updated count, 2: failed count. */
				__( '%1$d bookings updated, %2$d skipped.', 'qevora-booking' ),
				$done,
				$fail
			),
			$fail ? 'warning' : 'success'
		);

		self::back( QVB_Admin::url( 'bookings' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings + emails                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves plugin settings.
	 *
	 * @return void
	 */
	public static function save_settings() {
		QVB_Security::verify_admin( 'qvb_save_settings', QVB_Capabilities::SETTINGS );

		$input = isset( $_POST['qvb_settings'] ) ? (array) wp_unslash( $_POST['qvb_settings'] ) : array();
		QVB_Settings::update( $input );

		QVB_Admin::flash( __( 'Settings saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'settings' ) . '#general' );
	}

	/**
	 * Saves an email template.
	 *
	 * @return void
	 */
	public static function save_email_template() {
		QVB_Security::verify_admin( 'qvb_save_email_template', QVB_Capabilities::SETTINGS );

		$event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
		$templates = QVB_Notifications::templates();

		if ( ! isset( $templates[ $event ] ) ) {
			self::fail( __( 'Unknown email template.', 'qevora-booking' ), QVB_Admin::url( 'emails' ) );
		}

		QVB_Notifications::save_template(
			$event,
			array(
				'enabled' => ! empty( $_POST['enabled'] ) ? 1 : 0,
				'subject' => (string) self::post( 'subject' ),
				'body'    => (string) self::post( 'body' ),
			)
		);

		QVB_Admin::flash( __( 'Email template saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'emails', array( 'template' => $event ) ) );
	}

	/**
	 * Resets an email template to the default copy.
	 *
	 * @return void
	 */
	public static function reset_email_template() {
		$event = isset( $_REQUEST['event'] ) ? sanitize_key( wp_unslash( $_REQUEST['event'] ) ) : '';
		QVB_Security::verify_admin( 'qvb_reset_email_template_' . $event, QVB_Capabilities::SETTINGS );

		QVB_Notifications::reset_template( $event );
		QVB_Admin::flash( __( 'Template reset to the default.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'emails', array( 'template' => $event ) ) );
	}

	/**
	 * Sends a test email.
	 *
	 * @return void
	 */
	public static function test_email() {
		QVB_Security::verify_admin( 'qvb_test_email', QVB_Capabilities::SETTINGS );

		$to = sanitize_email( (string) self::post( 'to' ) );
		if ( ! $to ) {
			$to = sanitize_email( (string) QVB_Settings::get( 'admin_email' ) );
		}

		$sent = QVB_Notifications::send_test( $to );

		if ( $sent ) {
			QVB_Admin::flash( __( 'Test email sent.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The test email could not be sent. Your host may need a mail plugin or SMTP configuration.', 'qevora-booking' ), 'error' );
		}

		QVB_Admin::redirect( QVB_Admin::url( 'emails' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Customers + maintenance                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves customer profile fields.
	 *
	 * @return void
	 */
	public static function save_customer() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_save_customer_' . $id, QVB_Capabilities::PII );

		$result = QVB_Customers::update(
			$id,
			array(
				'name'  => (string) self::post( 'name' ),
				'email' => (string) self::post( 'email' ),
				'phone' => (string) self::post( 'phone' ),
				'notes' => (string) self::post( 'notes' ),
			)
		);

		$back = QVB_Admin::url( 'customers', array( 'action' => 'view', 'customer' => $id ) );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message(), $back );
		}

		QVB_Admin::flash( __( 'Customer saved.', 'qevora-booking' ) );
		QVB_Admin::redirect( $back );
	}

	/**
	 * Anonymises a customer (privacy erasure).
	 *
	 * @return void
	 */
	public static function anonymize_customer() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		QVB_Security::verify_admin( 'qvb_anonymize_customer_' . $id, QVB_Capabilities::PII );

		if ( QVB_Customers::anonymize( $id ) ) {
			QVB_Admin::flash( __( 'Customer personal data has been anonymised. Booking statistics are retained.', 'qevora-booking' ) );
		} else {
			QVB_Admin::flash( __( 'The customer could not be anonymised.', 'qevora-booking' ), 'error' );
		}

		QVB_Admin::redirect( QVB_Admin::url( 'customers' ) );
	}

	/**
	 * Repairs/creates missing database tables.
	 *
	 * @return void
	 */
	public static function repair_tables() {
		QVB_Security::verify_admin( 'qvb_repair_tables', QVB_Capabilities::SETTINGS );

		QVB_Database::install();
		QVB_Admin::flash( __( 'Database tables checked and repaired.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'help' ) );
	}

	/**
	 * Runs the daily maintenance routine now.
	 *
	 * @return void
	 */
	public static function run_maintenance() {
		QVB_Security::verify_admin( 'qvb_run_maintenance', QVB_Capabilities::SETTINGS );

		QVB_Cron::run();
		QVB_Admin::flash( __( 'Maintenance completed. Old records were purged according to your retention setting.', 'qevora-booking' ) );
		QVB_Admin::redirect( QVB_Admin::url( 'help' ) );
	}
}
