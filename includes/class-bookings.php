<?php
/**
 * Booking creation, status handling and queries.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Bookings
 */
final class QVB_Bookings {

	/**
	 * Creates a booking after re-validating availability server-side.
	 *
	 * @param array  $data    Booking fields.
	 * @param string $context frontend|admin.
	 * @return array|WP_Error
	 */
	public static function create( $data, $context = 'frontend' ) {
		global $wpdb;

		$context  = 'admin' === $context ? 'admin' : 'frontend';
		$is_admin = 'admin' === $context;

		$service_id = isset( $data['service_id'] ) ? absint( $data['service_id'] ) : 0;
		$staff_id   = isset( $data['staff_id'] ) ? absint( $data['staff_id'] ) : 0;
		$date       = QVB_Utils::sanitize_date( isset( $data['date'] ) ? $data['date'] : '' );
		$time       = QVB_Utils::sanitize_time( isset( $data['time'] ) ? $data['time'] : '' );
		$name       = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$email      = isset( $data['email'] ) ? strtolower( sanitize_email( (string) $data['email'] ) ) : '';
		$phone      = isset( $data['phone'] ) ? sanitize_text_field( (string) $data['phone'] ) : '';
		$notes      = isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '';

		$service = QVB_Services::get( $service_id );

		if ( ! $service || 'active' !== $service->status ) {
			return new WP_Error( 'qvb_invalid_service', __( 'That service is not available for booking.', 'qevora-booking' ) );
		}

		if ( ! $date || ! $time ) {
			return new WP_Error( 'qvb_invalid_datetime', __( 'Please choose a valid date and time.', 'qevora-booking' ) );
		}

		if ( '' === $name ) {
			return new WP_Error( 'qvb_name_required', __( 'Please enter your name.', 'qevora-booking' ) );
		}

		if ( mb_strlen( $name ) > 191 ) {
			$name = mb_substr( $name, 0, 191 );
		}

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'qvb_email_invalid', __( 'Please enter a valid email address.', 'qevora-booking' ) );
		}

		if ( QVB_Settings::get( 'require_phone' ) && '' === $phone ) {
			return new WP_Error( 'qvb_phone_required', __( 'Please enter your phone number.', 'qevora-booking' ) );
		}

		if ( $phone ) {
			$digits = preg_replace( '/\D+/', '', $phone );

			if ( strlen( $digits ) < 6 || strlen( $digits ) > 20 ) {
				return new WP_Error( 'qvb_phone_invalid', __( 'Please enter a valid phone number.', 'qevora-booking' ) );
			}

			$phone = mb_substr( $phone, 0, 40 );
		}

		if ( mb_strlen( $notes ) > 2000 ) {
			$notes = mb_substr( $notes, 0, 2000 );
		}

		if ( ! $is_admin && QVB_Settings::get( 'require_consent' ) && empty( $data['consent'] ) ) {
			return new WP_Error( 'qvb_consent_required', __( 'Please accept the cancellation policy to continue.', 'qevora-booking' ) );
		}

		$requested_status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : '';
		$status           = $is_admin
			? ( in_array( $requested_status, array( 'pending', 'confirmed' ), true ) ? $requested_status : 'confirmed' )
			: ( QVB_Settings::requires_approval() ? 'pending' : 'confirmed' );

		$lock_key = 'create|' . $service_id . '|' . $staff_id . '|' . $date;
		$lock     = self::acquire_lock( $lock_key );

		try {
			$slot_args = array(
				'admin' => $is_admin,
			);

			$slot = QVB_Availability::validate_slot( $service_id, $date, $time, $staff_id, $slot_args );

			if ( is_wp_error( $slot ) ) {
				return $slot;
			}

			$chosen_staff   = QVB_Staff::get( (int) $slot['staff_id'] );
			$duration       = max( 5, (int) $service->duration );
			$buffer         = QVB_Availability::service_buffer( $service );
			$end_time       = QVB_Utils::add_minutes_to_time( $time, $duration );
			$buffer_end     = QVB_Utils::add_minutes_to_time( $time, $duration + $buffer );

			if ( ! $chosen_staff || ! $end_time || ! $buffer_end ) {
				return new WP_Error(
					'qvb_slot_unavailable',
					__( 'That time is no longer available. Please choose another slot.', 'qevora-booking' ),
					array( 'status' => 409 )
				);
			}

			$customer_id = QVB_Customers::upsert(
				array(
					'name'       => $name,
					'email'      => $email,
					'phone'      => $phone,
					'wp_user_id' => get_current_user_id(),
				)
			);

			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			if ( self::customer_conflict( $email, $date, $time, $buffer_end ) ) {
				return new WP_Error(
					'qvb_duplicate_booking',
					__( 'You already have an appointment that overlaps this time.', 'qevora-booking' ),
					array( 'status' => 409 )
				);
			}

			$reference = self::generate_reference();
			$now       = QVB_Utils::now_sql();
			$row       = array(
				'reference'      => $reference,
				'customer_id'    => (int) $customer_id,
				'service_id'     => $service_id,
				'staff_id'       => (int) $chosen_staff->id,
				'booking_date'   => $date,
				'start_time'     => $time,
				'end_time'       => $end_time,
				'buffer_end_time' => $buffer_end,
				'duration'       => $duration,
				'buffer_time'    => $buffer,
				'status'         => $status,
				'price'          => (float) $service->price,
				'currency'       => strtoupper( (string) QVB_Settings::get( 'currency' ) ),
				'service_name'   => $service->name,
				'staff_name'     => $chosen_staff->name,
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'notes'          => $notes,
				'admin_notes'    => '',
				'source'         => $context,
				'created_by'     => $is_admin ? get_current_user_id() : 0,
				'ip_hash'        => $is_admin ? '' : QVB_Utils::ip_hash(),
				'created_at'     => $now,
				'updated_at'     => $now,
			);

			$formats = array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert( QVB_Database::table( 'bookings' ), $row, $formats );

			if ( ! $inserted ) {
				return new WP_Error( 'qvb_booking_save', __( 'The booking could not be saved. Please try again.', 'qevora-booking' ) );
			}

			$booking_id = (int) $wpdb->insert_id;

			$conflict = self::verify_after_insert( $booking_id, (int) $chosen_staff->id, $email, $date, $time, $buffer_end );

			if ( is_wp_error( $conflict ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( QVB_Database::table( 'bookings' ), array( 'id' => $booking_id ), array( '%d' ) );
				QVB_Customers::recalc( (int) $customer_id );
				QVB_Availability::flush_cache();

				return $conflict;
			}

			QVB_Customers::recalc( (int) $customer_id );
			QVB_Availability::flush_cache();

			$booking = self::get( $booking_id );

			/**
			 * Fires after a booking is created.
			 *
			 * @param int    $booking_id Booking ID.
			 * @param object $booking    Booking row.
			 * @param string $context    frontend|admin.
			 */
			do_action( 'qvb_booking_created', $booking_id, $booking, $context );

			if ( $booking ) {
				QVB_Notifications::send( 'pending' === $status ? 'customer_pending' : 'customer_confirmed', $booking );
				QVB_Notifications::send( 'admin_new_booking', $booking );
			}

			return array(
				'id'         => $booking_id,
				'reference'  => $reference,
				'status'     => $status,
				'booking'    => $booking,
				'manage_url' => $booking ? self::manage_url( $booking ) : '',
			);
		} finally {
			self::release_lock( $lock );
		}
	}

	/**
	 * Updates a booking status with transition validation.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $status New status.
	 * @param array  $args   context, reason, notify, user_id.
	 * @return object|WP_Error
	 */
	public static function update_status( $id, $status, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'context' => 'admin',
				'reason'  => '',
				'notify'  => true,
				'user_id' => get_current_user_id(),
			)
		);

		$booking = self::get( $id );
		$status  = sanitize_key( $status );

		if ( ! $booking ) {
			return new WP_Error( 'qvb_booking_missing', __( 'That booking no longer exists.', 'qevora-booking' ) );
		}

		if ( ! QVB_Utils::is_status( $status ) ) {
			return new WP_Error( 'qvb_invalid_status', __( 'That booking status is not valid.', 'qevora-booking' ) );
		}

		if ( $booking->status === $status ) {
			return $booking;
		}

		$allowed = self::allowed_transitions( $booking->status );

		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error(
				'qvb_invalid_transition',
				sprintf(
					/* translators: 1: current status, 2: requested status. */
					__( 'A %1$s booking cannot be changed to %2$s.', 'qevora-booking' ),
					QVB_Utils::status_label( $booking->status ),
					QVB_Utils::status_label( $status )
				)
			);
		}

		$was_released = in_array( $booking->status, array( 'cancelled', 'rejected' ), true );

		if ( in_array( $status, array( 'pending', 'confirmed' ), true ) ) {
			if ( QVB_Utils::is_past( $booking->booking_date, $booking->start_time ) ) {
				return new WP_Error( 'qvb_past_booking', __( 'This appointment time has already passed. Mark it completed or no-show instead.', 'qevora-booking' ) );
			}

			if ( $was_released ) {
				$check = QVB_Availability::validate_slot(
					(int) $booking->service_id,
					$booking->booking_date,
					$booking->start_time,
					(int) $booking->staff_id,
					array(
						'admin'    => 'admin' === $args['context'],
						'exclude'  => (int) $booking->id,
						'duration' => (int) $booking->duration,
						'buffer'   => (int) $booking->buffer_time,
					)
				);

				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}
		}

		$now    = QVB_Utils::now_sql();
		$update = array(
			'status'     => $status,
			'updated_at' => $now,
		);
		$formats = array( '%s', '%s' );

		if ( 'cancelled' === $status ) {
			$update['cancelled_at']        = $now;
			$update['cancelled_by']        = (int) $args['user_id'];
			$update['cancellation_reason'] = mb_substr( sanitize_text_field( (string) $args['reason'] ), 0, 255 );
			$formats[]                     = '%s';
			$formats[]                     = '%d';
			$formats[]                     = '%s';
		} elseif ( 'rejected' === $status ) {
			$update['cancellation_reason'] = mb_substr( sanitize_text_field( (string) $args['reason'] ), 0, 255 );
			$formats[]                     = '%s';
		} elseif ( $was_released ) {
			$update['cancelled_at']        = null;
			$update['cancelled_by']        = 0;
			$update['cancellation_reason'] = '';
			$formats[]                     = '%s';
			$formats[]                     = '%d';
			$formats[]                     = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update( QVB_Database::table( 'bookings' ), $update, array( 'id' => (int) $booking->id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return new WP_Error( 'qvb_status_save', __( 'The status could not be updated. Please try again.', 'qevora-booking' ) );
		}

		if ( $booking->customer_id ) {
			QVB_Customers::recalc( (int) $booking->customer_id );
		}

		QVB_Availability::flush_cache();

		$fresh = self::get( (int) $booking->id );

		/**
		 * Fires after a booking status changes.
		 *
		 * @param int    $id      Booking ID.
		 * @param string $old     Old status.
		 * @param string $new     New status.
		 * @param object $booking Fresh booking.
		 */
		do_action( 'qvb_booking_status_changed', (int) $booking->id, $booking->status, $status, $fresh );

		if ( $args['notify'] && $fresh ) {
			if ( 'cancelled' === $status ) {
				QVB_Notifications::send( 'customer_cancelled', $fresh, array( 'reason' => $args['reason'] ) );

				if ( 'customer' === $args['context'] ) {
					QVB_Notifications::send( 'admin_booking_cancelled', $fresh, array( 'reason' => $args['reason'] ) );
				}
			} elseif ( 'confirmed' === $status && in_array( $booking->status, array( 'pending', 'cancelled', 'rejected' ), true ) ) {
				QVB_Notifications::send( 'customer_confirmed', $fresh );
			} elseif ( in_array( $status, array( 'completed', 'rejected', 'no_show' ), true ) ) {
				QVB_Notifications::send( 'customer_status_changed', $fresh );
			}
		}

		return $fresh;
	}

	/**
	 * Cancels a booking, applying customer policy when needed.
	 *
	 * @param int   $id   Booking ID.
	 * @param array $args context, reason, notify, user_id.
	 * @return object|WP_Error
	 */
	public static function cancel( $id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'context' => 'customer',
				'reason'  => '',
				'notify'  => true,
				'user_id' => 0,
			)
		);

		$booking = self::get( $id );

		if ( ! $booking ) {
			return new WP_Error( 'qvb_booking_missing', __( 'That booking no longer exists.', 'qevora-booking' ) );
		}

		if ( 'customer' === $args['context'] ) {
			$allowed = self::can_cancel( $booking, 'customer' );

			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
		} elseif ( ! in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			return new WP_Error( 'qvb_cannot_cancel', __( 'Only pending or confirmed bookings can be cancelled.', 'qevora-booking' ) );
		}

		return self::update_status(
			$id,
			'cancelled',
			array(
				'context' => $args['context'],
				'reason'  => $args['reason'],
				'notify'  => $args['notify'],
				'user_id' => (int) $args['user_id'],
			)
		);
	}

	/**
	 * Moves a booking to a new date/time/staff member.
	 *
	 * @param int    $id       Booking ID.
	 * @param string $date     New date.
	 * @param string $time     New time.
	 * @param int    $staff_id New staff ID, 0 for any.
	 * @param array  $args     context, notify.
	 * @return object|WP_Error
	 */
	public static function reschedule( $id, $date, $time, $staff_id = 0, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'context' => 'admin',
				'notify'  => true,
			)
		);

		$booking = self::get( $id );
		$date    = QVB_Utils::sanitize_date( $date );
		$time    = QVB_Utils::sanitize_time( $time );
		$staff_id = absint( $staff_id );

		if ( ! $booking ) {
			return new WP_Error( 'qvb_booking_missing', __( 'That booking no longer exists.', 'qevora-booking' ) );
		}

		if ( ! $date || ! $time ) {
			return new WP_Error( 'qvb_invalid_datetime', __( 'Please choose a valid date and time.', 'qevora-booking' ) );
		}

		if ( ! in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			return new WP_Error( 'qvb_cannot_reschedule', __( 'Only pending or confirmed bookings can be rescheduled.', 'qevora-booking' ) );
		}

		if ( 'customer' === $args['context'] ) {
			$allowed = self::can_reschedule( $booking, 'customer' );

			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
		}

		if ( $booking->booking_date === $date && $booking->start_time === $time && ( ! $staff_id || (int) $booking->staff_id === $staff_id ) ) {
			return new WP_Error( 'qvb_same_slot', __( 'Please choose a different date or time.', 'qevora-booking' ) );
		}

		$lock = self::acquire_lock( 'reschedule|' . $booking->service_id . '|' . $staff_id . '|' . $date );

		try {
			$slot = QVB_Availability::validate_slot(
				(int) $booking->service_id,
				$date,
				$time,
				$staff_id,
				array(
					'admin'    => 'admin' === $args['context'],
					'exclude'  => (int) $booking->id,
					'duration' => (int) $booking->duration,
					'buffer'   => (int) $booking->buffer_time,
				)
			);

			if ( is_wp_error( $slot ) ) {
				return $slot;
			}

			$staff = QVB_Staff::get( (int) $slot['staff_id'] );
			$end   = QVB_Utils::add_minutes_to_time( $time, (int) $booking->duration );
			$buffer_end = QVB_Utils::add_minutes_to_time( $time, (int) $booking->duration + (int) $booking->buffer_time );

			if ( ! $staff || ! $end || ! $buffer_end ) {
				return new WP_Error( 'qvb_slot_unavailable', __( 'That time is no longer available. Please choose another slot.', 'qevora-booking' ), array( 'status' => 409 ) );
			}

			$old = array(
				'booking_date'    => $booking->booking_date,
				'start_time'      => $booking->start_time,
				'end_time'        => $booking->end_time,
				'buffer_end_time' => $booking->buffer_end_time,
				'staff_id'        => (int) $booking->staff_id,
				'staff_name'      => $booking->staff_name,
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				QVB_Database::table( 'bookings' ),
				array(
					'booking_date'     => $date,
					'start_time'       => $time,
					'end_time'         => $end,
					'buffer_end_time'  => $buffer_end,
					'staff_id'         => (int) $staff->id,
					'staff_name'       => $staff->name,
					'reschedule_count' => (int) $booking->reschedule_count + 1,
					'updated_at'       => QVB_Utils::now_sql(),
				),
				array( 'id' => (int) $booking->id ),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error( 'qvb_reschedule_save', __( 'The booking could not be rescheduled. Please try again.', 'qevora-booking' ) );
			}

			if ( self::overlaps_exist( $date, $time, $buffer_end, (int) $staff->id, $booking->customer_email, (int) $booking->id ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					QVB_Database::table( 'bookings' ),
					array(
						'booking_date'    => $old['booking_date'],
						'start_time'      => $old['start_time'],
						'end_time'        => $old['end_time'],
						'buffer_end_time' => $old['buffer_end_time'],
						'staff_id'         => $old['staff_id'],
						'staff_name'       => $old['staff_name'],
						'reschedule_count' => (int) $booking->reschedule_count,
						'updated_at'       => QVB_Utils::now_sql(),
					),
					array( 'id' => (int) $booking->id ),
					array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ),
					array( '%d' )
				);

				QVB_Availability::flush_cache();

				return new WP_Error(
					'qvb_slot_unavailable',
					__( 'That time is no longer available. Please choose another slot.', 'qevora-booking' ),
					array( 'status' => 409 )
				);
			}

			QVB_Availability::flush_cache();
			QVB_Customers::recalc( (int) $booking->customer_id );

			$fresh = self::get( (int) $booking->id );

			/**
			 * Fires after a booking is rescheduled.
			 *
			 * @param int    $id      Booking ID.
			 * @param object $fresh   Fresh booking.
			 * @param string $old_date Previous date.
			 * @param string $old_time Previous time.
			 */
			do_action( 'qvb_booking_rescheduled', (int) $booking->id, $fresh, $booking->booking_date, $booking->start_time );

			if ( $args['notify'] && $fresh ) {
				QVB_Notifications::send(
					'customer_rescheduled',
					$fresh,
					array(
						'previous_date' => $booking->booking_date,
						'previous_time' => $booking->start_time,
					)
				);
			}

			return $fresh;
		} finally {
			self::release_lock( $lock );
		}
	}

	/**
	 * Allowed status transitions.
	 *
	 * @param string $from Current status.
	 * @return string[]
	 */
	public static function allowed_transitions( $from ) {
		$map = array(
			'pending'   => array( 'confirmed', 'cancelled', 'rejected' ),
			'confirmed' => array( 'completed', 'cancelled', 'no_show' ),
			'completed' => array(),
			'cancelled' => array( 'pending', 'confirmed' ),
			'rejected'  => array( 'pending', 'confirmed' ),
			'no_show'   => array( 'completed', 'cancelled' ),
		);

		$allowed = isset( $map[ $from ] ) ? $map[ $from ] : array();

		/**
		 * Filters the allowed booking status transitions.
		 *
		 * @param string[] $allowed Destination statuses.
		 * @param string   $from    Current status.
		 */
		return (array) apply_filters( 'qvb_allowed_transitions', $allowed, $from );
	}

	/**
	 * Whether a customer may cancel.
	 *
	 * @param object $booking Booking.
	 * @param string $context customer|admin.
	 * @return true|WP_Error
	 */
	public static function can_cancel( $booking, $context = 'customer' ) {
		if ( ! $booking ) {
			return new WP_Error( 'qvb_booking_missing', __( 'That booking no longer exists.', 'qevora-booking' ) );
		}

		if ( ! in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			return new WP_Error( 'qvb_cannot_cancel', __( 'This booking can no longer be cancelled.', 'qevora-booking' ) );
		}

		if ( 'admin' === $context ) {
			return true;
		}

		if ( ! QVB_Settings::get( 'allow_cancel' ) ) {
			return new WP_Error( 'qvb_cancel_disabled', __( 'Online cancellation is not available. Please contact the salon directly.', 'qevora-booking' ) );
		}

		$start = QVB_Utils::timestamp( $booking->booking_date, $booking->start_time );

		if ( false === $start || $start <= time() ) {
			return new WP_Error( 'qvb_cancel_past', __( 'This appointment has already started and can no longer be cancelled online.', 'qevora-booking' ) );
		}

		$limit = max( 0, (int) QVB_Settings::get( 'cancel_limit_hours' ) );

		if ( $limit > 0 && ( $start - time() ) < ( $limit * HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'qvb_cancel_window',
				sprintf(
					/* translators: %s: number of hours. */
					__( 'Online cancellation closes %s before the appointment. Please contact the salon directly.', 'qevora-booking' ),
					QVB_Utils::duration_label( $limit * 60 )
				)
			);
		}

		return true;
	}

	/**
	 * Whether a customer may reschedule.
	 *
	 * @param object $booking Booking.
	 * @param string $context customer|admin.
	 * @return true|WP_Error
	 */
	public static function can_reschedule( $booking, $context = 'customer' ) {
		if ( ! $booking ) {
			return new WP_Error( 'qvb_booking_missing', __( 'That booking no longer exists.', 'qevora-booking' ) );
		}

		if ( ! in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			return new WP_Error( 'qvb_cannot_reschedule', __( 'This booking can no longer be rescheduled.', 'qevora-booking' ) );
		}

		if ( 'admin' === $context ) {
			return true;
		}

		if ( ! QVB_Settings::get( 'allow_reschedule' ) ) {
			return new WP_Error( 'qvb_reschedule_disabled', __( 'Online rescheduling is not available. Please contact the salon directly.', 'qevora-booking' ) );
		}

		$limit = max( 0, (int) QVB_Settings::get( 'reschedule_limit_hours' ) );
		$start = QVB_Utils::timestamp( $booking->booking_date, $booking->start_time );

		if ( false === $start || $start <= time() ) {
			return new WP_Error( 'qvb_reschedule_past', __( 'This appointment has already started and can no longer be rescheduled online.', 'qevora-booking' ) );
		}

		if ( $limit > 0 && ( $start - time() ) < ( $limit * HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'qvb_reschedule_window',
				sprintf(
					/* translators: %s: number of hours. */
					__( 'Online rescheduling closes %s before the appointment. Please contact the salon directly.', 'qevora-booking' ),
					QVB_Utils::duration_label( $limit * 60 )
				)
			);
		}

		$max = (int) QVB_Settings::get( 'max_reschedules' );

		if ( $max > 0 && (int) $booking->reschedule_count >= $max ) {
			return new WP_Error(
				'qvb_reschedule_limit',
				__( 'This booking has reached its reschedule limit. Please contact the salon directly.', 'qevora-booking' )
			);
		}

		return true;
	}

	/**
	 * Gets a booking.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = QVB_Database::table( 'bookings' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Gets a booking by reference.
	 *
	 * @param string $reference Reference.
	 * @return object|null
	 */
	public static function get_by_reference( $reference ) {
		global $wpdb;

		$reference = strtoupper( sanitize_text_field( (string) $reference ) );

		if ( ! $reference ) {
			return null;
		}

		$table = QVB_Database::table( 'bookings' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE reference = %s LIMIT 1", $reference ) );
	}

	/**
	 * Signed management token for a booking.
	 *
	 * @param object $booking Booking.
	 * @return string
	 */
	public static function token( $booking ) {
		$payload = $booking->id . '|' . $booking->reference . '|' . strtolower( (string) $booking->customer_email ) . '|' . (string) $booking->created_at;

		return hash_hmac( 'sha256', 'qvb_manage|' . $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Verifies a management token.
	 *
	 * @param object $booking Booking.
	 * @param string $token   Token.
	 * @return bool
	 */
	public static function verify_token( $booking, $token ) {
		if ( ! $booking || ! $token ) {
			return false;
		}

		return hash_equals( self::token( $booking ), (string) $token );
	}

	/**
	 * Gets a booking by reference and validates its token.
	 *
	 * @param string $reference Reference.
	 * @param string $token     Token.
	 * @return object|WP_Error
	 */
	public static function get_by_token( $reference, $token ) {
		$booking = self::get_by_reference( $reference );

		if ( ! $booking || ! self::verify_token( $booking, $token ) ) {
			return new WP_Error( 'qvb_invalid_token', __( 'This booking link is invalid or has expired. Please contact the salon.', 'qevora-booking' ), array( 'status' => 403 ) );
		}

		return $booking;
	}

	/**
	 * Customer management URL.
	 *
	 * @param object $booking Booking.
	 * @return string
	 */
	public static function manage_url( $booking ) {
		return add_query_arg(
			array(
				'qvb_booking' => $booking->reference,
				'token'       => self::token( $booking ),
			),
			QVB_Utils::booking_page_url()
		);
	}

	/**
	 * Paginated booking query.
	 *
	 * @param array $args Query args.
	 * @return array{items:object[],total:int,pages:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'      => '',
				'statuses'    => array(),
				'service_id'  => 0,
				'staff_id'    => 0,
				'customer_id' => 0,
				'date'        => '',
				'date_from'   => '',
				'date_to'     => '',
				'search'      => '',
				'orderby'     => 'booking_date',
				'order'       => 'DESC',
				'per_page'    => 20,
				'page'        => 1,
			)
		);

		$table  = QVB_Database::table( 'bookings' );
		$where  = array( '1=1' );
		$params = array();

		$statuses = array();

		if ( $args['status'] ) {
			$statuses = array( sanitize_key( $args['status'] ) );
		} elseif ( ! empty( $args['statuses'] ) ) {
			$statuses = array_map( 'sanitize_key', (array) $args['statuses'] );
		}

		$statuses = array_values( array_intersect( $statuses, QVB_Utils::statuses() ) );

		if ( $statuses ) {
			$where[] = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params  = array_merge( $params, $statuses );
		}

		foreach ( array( 'service_id', 'staff_id', 'customer_id' ) as $field ) {
			if ( ! empty( $args[ $field ] ) ) {
				$where[]  = "{$field} = %d";
				$params[] = absint( $args[ $field ] );
			}
		}

		$date = QVB_Utils::sanitize_date( $args['date'] );

		if ( $date ) {
			$where[]  = 'booking_date = %s';
			$params[] = $date;
		} else {
			$from = QVB_Utils::sanitize_date( $args['date_from'] );
			$to   = QVB_Utils::sanitize_date( $args['date_to'] );

			if ( $from ) {
				$where[]  = 'booking_date >= %s';
				$params[] = $from;
			}

			if ( $to ) {
				$where[]  = 'booking_date <= %s';
				$params[] = $to;
			}
		}

		if ( '' !== trim( (string) $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(reference LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$order_map = array(
			'booking_date'  => 'booking_date',
			'start_time'    => 'start_time',
			'created_at'    => 'created_at',
			'customer_name' => 'customer_name',
			'price'         => 'price',
			'status'        => 'status',
			'id'            => 'id',
		);

		$orderby   = isset( $order_map[ $args['orderby'] ] ) ? $order_map[ $args['orderby'] ] : 'booking_date';
		$order     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$secondary = in_array( $orderby, array( 'booking_date', 'start_time' ), true ) ? 'start_time' : 'id';
		$per_page  = max( 1, min( 200, (int) $args['per_page'] ) );
		$page      = max( 1, (int) $args['page'] );
		$offset    = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, {$secondary} {$order} LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql  = $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) );
		$rows = $wpdb->get_results( $sql );

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Bookings on a single date.
	 *
	 * @param string $date     Date.
	 * @param array  $statuses Optional statuses.
	 * @return object[]
	 */
	public static function for_date( $date, $statuses = array() ) {
		$date = QVB_Utils::sanitize_date( $date );

		if ( ! $date ) {
			return array();
		}

		$result = self::query(
			array(
				'date'     => $date,
				'statuses' => $statuses,
				'orderby'  => 'start_time',
				'order'    => 'ASC',
				'per_page' => 200,
			)
		);

		return $result['items'];
	}

	/**
	 * Bookings between two dates (calendar).
	 *
	 * @param string $from Start.
	 * @param string $to   End.
	 * @param array  $args staff_id, service_id, statuses.
	 * @return object[]
	 */
	public static function calendar( $from, $to, $args = array() ) {
		$result = self::query(
			array_merge(
				$args,
				array(
					'date_from' => $from,
					'date_to'   => $to,
					'orderby'   => 'booking_date',
					'order'     => 'ASC',
					'per_page'  => 500,
				)
			)
		);

		return $result['items'];
	}

	/**
	 * Upcoming pending/confirmed bookings.
	 *
	 * @param int $limit Maximum rows.
	 * @return object[]
	 */
	public static function upcoming( $limit = 5 ) {
		global $wpdb;

		$table = QVB_Database::table( 'bookings' );
		$today = QVB_Utils::today();
		$now   = QVB_Utils::now()->format( 'H:i' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('pending','confirmed') AND (booking_date > %s OR (booking_date = %s AND start_time >= %s)) ORDER BY booking_date ASC, start_time ASC LIMIT %d",
				$today,
				$today,
				$now,
				max( 1, (int) $limit )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Status counts, optionally within a date range.
	 *
	 * @param array $args date_from, date_to.
	 * @return array<string,int>
	 */
	public static function status_counts( $args = array() ) {
		global $wpdb;

		$table  = QVB_Database::table( 'bookings' );
		$where  = array( '1=1' );
		$params = array();

		$from = isset( $args['date_from'] ) ? QVB_Utils::sanitize_date( $args['date_from'] ) : '';
		$to   = isset( $args['date_to'] ) ? QVB_Utils::sanitize_date( $args['date_to'] ) : '';

		if ( $from ) {
			$where[]  = 'booking_date >= %s';
			$params[] = $from;
		}

		if ( $to ) {
			$where[]  = 'booking_date <= %s';
			$params[] = $to;
		}

		$sql = "SELECT status, COUNT(*) AS total FROM {$table} WHERE " . implode( ' AND ', $where ) . ' GROUP BY status';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		$counts = array_fill_keys( QVB_Utils::statuses(), 0 );

		foreach ( (array) $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}

		$counts['all'] = array_sum( $counts );

		return $counts;
	}

	/**
	 * Count and expected revenue for a range.
	 *
	 * @param string   $from     Start.
	 * @param string   $to       End.
	 * @param string[] $statuses Statuses to include.
	 * @return array{count:int,revenue:float}
	 */
	public static function stats( $from, $to, $statuses = array( 'confirmed', 'completed' ) ) {
		global $wpdb;

		$table    = QVB_Database::table( 'bookings' );
		$statuses = array_values( array_intersect( (array) $statuses, QVB_Utils::statuses() ) );

		if ( ! $statuses ) {
			return array(
				'count'   => 0,
				'revenue' => 0.0,
			);
		}

		$in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, COALESCE(SUM(price),0) AS revenue FROM {$table} WHERE booking_date BETWEEN %s AND %s AND status IN ({$in})",
				array_merge( array( $from, $to ), $statuses )
			)
		);

		return array(
			'count'   => $row ? (int) $row->total : 0,
			'revenue' => $row ? (float) $row->revenue : 0.0,
		);
	}

	/**
	 * Updates private admin notes.
	 *
	 * @param int    $id   Booking ID.
	 * @param string $note Notes.
	 * @return bool
	 */
	public static function update_notes( $id, $note ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			QVB_Database::table( 'bookings' ),
			array(
				'admin_notes' => mb_substr( sanitize_textarea_field( (string) $note ), 0, 2000 ),
				'updated_at'  => QVB_Utils::now_sql(),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes old completed/cancelled bookings according to the retention setting.
	 *
	 * @param int $days Retention days. 0 disables.
	 * @return int Number of bookings deleted.
	 */
	public static function purge_old( $days ) {
		global $wpdb;

		$days = (int) $days;

		if ( $days <= 0 ) {
			return 0;
		}

		$table  = QVB_Database::table( 'bookings' );
		$cutoff = QVB_Utils::date_from_now( -1 * $days );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$customer_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT customer_id FROM {$table} WHERE booking_date < %s AND status IN ('completed','cancelled','rejected','no_show')",
				$cutoff
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE booking_date < %s AND status IN ('completed','cancelled','rejected','no_show')",
				$cutoff
			)
		);

		foreach ( (array) $customer_ids as $customer_id ) {
			QVB_Customers::recalc( (int) $customer_id );
		}

		// Remove customer records left with no bookings and no recent activity.
		$customers = QVB_Database::table( 'customers' );
		$orphans   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id FROM {$customers} c WHERE c.created_at < %s AND NOT EXISTS (SELECT 1 FROM {$table} b WHERE b.customer_id = c.id) LIMIT 200",
				$cutoff . ' 00:00:00'
			)
		);

		foreach ( (array) $orphans as $orphan_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $customers, array( 'id' => (int) $orphan_id ), array( '%d' ) );
		}

		QVB_Availability::flush_cache();

		return $deleted;
	}

	/**
	 * Generates a unique booking reference.
	 *
	 * @return string
	 */
	public static function generate_reference() {
		global $wpdb;

		$table    = QVB_Database::table( 'bookings' );
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

		for ( $attempt = 0; $attempt < 12; $attempt++ ) {
			$suffix = '';

			for ( $i = 0; $i < 5; $i++ ) {
				$suffix .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}

			$reference = 'QVB-' . QVB_Utils::now()->format( 'ymd' ) . '-' . $suffix;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE reference = %s LIMIT 1", $reference ) );

			if ( ! $exists ) {
				return $reference;
			}
		}

		return 'QVB-' . QVB_Utils::now()->format( 'ymd' ) . '-' . strtoupper( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Finds overlapping booking IDs after insert and resolves races.
	 *
	 * @param int    $id         New booking ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $email      Customer email.
	 * @param string $date       Date.
	 * @param string $start      Start time.
	 * @param string $buffer_end Buffer end time.
	 * @return true|WP_Error
	 */
	private static function verify_after_insert( $id, $staff_id, $email, $date, $start, $buffer_end ) {
		global $wpdb;

		$table = QVB_Database::table( 'bookings' );
		$stati = QVB_Utils::blocking_statuses();
		$in    = implode( ',', array_fill( 0, count( $stati ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, staff_id, customer_email, start_time, end_time, buffer_end_time FROM {$table} WHERE booking_date = %s AND status IN ({$in}) AND id != %d",
				array_merge( array( $date ), $stati, array( (int) $id ) )
			)
		);

		$staff_conflict    = 0;
		$customer_conflict = 0;

		foreach ( (array) $rows as $row ) {
			$row_end = ! empty( $row->buffer_end_time ) ? $row->buffer_end_time : $row->end_time;

			if ( ! QVB_Utils::times_overlap( $start, $buffer_end, $row->start_time, $row_end ) ) {
				continue;
			}

			$row_id = (int) $row->id;

			if ( (int) $row->staff_id === (int) $staff_id && ( ! $staff_conflict || $row_id < $staff_conflict ) ) {
				$staff_conflict = $row_id;
			}

			if ( strtolower( (string) $row->customer_email ) === strtolower( (string) $email ) && ( ! $customer_conflict || $row_id < $customer_conflict ) ) {
				$customer_conflict = $row_id;
			}
		}

		if ( ( $staff_conflict && $staff_conflict < (int) $id ) || ( $customer_conflict && $customer_conflict < (int) $id ) ) {
			return new WP_Error(
				'qvb_slot_unavailable',
				__( 'That time was just booked by someone else. Please choose another slot.', 'qevora-booking' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Whether overlapping blocking bookings exist.
	 *
	 * @param string $date     Date.
	 * @param string $start    Start.
	 * @param string $end      End including buffer.
	 * @param int    $staff_id Staff ID.
	 * @param string $email    Customer email.
	 * @param int    $exclude  Booking ID to ignore.
	 * @return bool
	 */
	private static function overlaps_exist( $date, $start, $end, $staff_id, $email, $exclude = 0 ) {
		global $wpdb;

		$table = QVB_Database::table( 'bookings' );
		$stati = QVB_Utils::blocking_statuses();
		$in    = implode( ',', array_fill( 0, count( $stati ), '%s' ) );
		$params = array_merge( array( $date ), $stati, array( absint( $exclude ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT staff_id, customer_email, start_time, end_time, buffer_end_time FROM {$table} WHERE booking_date = %s AND status IN ({$in}) AND id != %d",
				$params
			)
		);

		foreach ( (array) $rows as $row ) {
			$row_end = ! empty( $row->buffer_end_time ) ? $row->buffer_end_time : $row->end_time;

			if ( ! QVB_Utils::times_overlap( $start, $end, $row->start_time, $row_end ) ) {
				continue;
			}

			if ( (int) $row->staff_id === (int) $staff_id ) {
				return true;
			}

			if ( $email && strtolower( (string) $row->customer_email ) === strtolower( (string) $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a customer already has an overlapping active booking.
	 *
	 * @param string $email Email.
	 * @param string $date  Date.
	 * @param string $start Start.
	 * @param string $end   End including buffer.
	 * @return bool
	 */
	private static function customer_conflict( $email, $date, $start, $end ) {
		global $wpdb;

		$email = strtolower( sanitize_email( $email ) );

		if ( ! $email ) {
			return false;
		}

		$table = QVB_Database::table( 'bookings' );
		$stati = QVB_Utils::blocking_statuses();
		$in    = implode( ',', array_fill( 0, count( $stati ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_time, end_time, buffer_end_time FROM {$table} WHERE LOWER(customer_email) = %s AND booking_date = %s AND status IN ({$in})",
				array_merge( array( $email, $date ), $stati )
			)
		);

		foreach ( (array) $rows as $row ) {
			$row_end = ! empty( $row->buffer_end_time ) ? $row->buffer_end_time : $row->end_time;

			if ( QVB_Utils::times_overlap( $start, $end, $row->start_time, $row_end ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Acquires a MySQL advisory lock when available.
	 *
	 * On SQLite/other drivers this returns false; post-write verification is the
	 * concurrency safety net in that case.
	 *
	 * @param string $key Lock key.
	 * @return string|false
	 */
	private static function acquire_lock( $key ) {
		global $wpdb;

		if ( ! class_exists( 'mysqli' ) || ! ( $wpdb->dbh instanceof mysqli ) ) {
			return false;
		}

		$name = 'qvb_' . substr( md5( $key . '|' . $wpdb->prefix ), 0, 40 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) );

		return '1' === (string) $got ? $name : false;
	}

	/**
	 * Releases a MySQL advisory lock.
	 *
	 * @param string|false $name Lock name.
	 * @return void
	 */
	private static function release_lock( $name ) {
		global $wpdb;

		if ( ! $name ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	/**
	 * Safe public summary of a booking.
	 *
	 * @param object $booking Booking.
	 * @param bool   $include_links Include manage URL.
	 * @return array
	 */
	public static function summary( $booking, $include_links = true ) {
		if ( ! $booking ) {
			return array();
		}

		$summary = array(
			'reference'      => $booking->reference,
			'status'         => $booking->status,
			'status_label'   => QVB_Utils::status_label( $booking->status ),
			'date'           => $booking->booking_date,
			'date_formatted' => QVB_Utils::format_date_weekday( $booking->booking_date ),
			'time'           => $booking->start_time,
			'time_formatted' => QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ),
			'duration'       => (int) $booking->duration,
			'service'        => $booking->service_name,
			'staff'          => $booking->staff_name,
			'price'          => (float) $booking->price,
			'price_formatted' => QVB_Utils::money( $booking->price, $booking->currency ),
			'customer_name'  => $booking->customer_name,
		);

		if ( $include_links ) {
			$summary['manage_url'] = self::manage_url( $booking );
		}

		return $summary;
	}
}
