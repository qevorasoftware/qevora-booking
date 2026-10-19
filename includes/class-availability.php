<?php
/**
 * Availability engine: weekly schedules, exceptions and bookable slots.
 *
 * All times are stored and compared as local wall-clock strings in the
 * business timezone. Slots that do not exist because of a DST gap are skipped.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Availability
 */
final class QVB_Availability {

	/**
	 * Staff row cache.
	 *
	 * @var array<int,object|null>
	 */
	private static $staff_cache = array();

	/**
	 * Weekly availability rows keyed by staff and day.
	 *
	 * @var array<int,array<int,array>>
	 */
	private static $weekly = array();

	/**
	 * Staff IDs whose weekly rows are loaded.
	 *
	 * @var array<int,bool>
	 */
	private static $weekly_loaded = array();

	/**
	 * All exception rows.
	 *
	 * @var array|null
	 */
	private static $exceptions = null;

	/**
	 * Bookings keyed by staff and date.
	 *
	 * @var array<int,array<string,array>>
	 */
	private static $bookings = array();

	/**
	 * Dates whose bookings are loaded, keyed by staff.
	 *
	 * @var array<int,array<string,bool>>
	 */
	private static $bookings_loaded = array();

	/**
	 * Resolved shifts keyed by "staff|date".
	 *
	 * @var array<string,array>
	 */
	private static $shifts = array();

	/**
	 * Clears all in-request caches.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$staff_cache    = array();
		self::$weekly         = array();
		self::$weekly_loaded  = array();
		self::$exceptions     = null;
		self::$bookings       = array();
		self::$bookings_loaded = array();
		self::$shifts         = array();
	}

	/**
	 * Cached staff lookup.
	 *
	 * @param int $staff_id Staff ID.
	 * @return object|null
	 */
	private static function staff( $staff_id ) {
		$staff_id = absint( $staff_id );

		if ( ! array_key_exists( $staff_id, self::$staff_cache ) ) {
			self::$staff_cache[ $staff_id ] = QVB_Staff::get( $staff_id );
		}

		return self::$staff_cache[ $staff_id ];
	}

	/**
	 * Weekly availability rows for a staff member.
	 *
	 * @param int $staff_id Staff ID.
	 * @return object[]
	 */
	public static function rows_for_staff( $staff_id ) {
		global $wpdb;

		$staff_id = absint( $staff_id );

		if ( ! $staff_id ) {
			return array();
		}

		$table = QVB_Database::table( 'availability' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE staff_id = %d ORDER BY day_of_week ASC, start_time ASC",
				$staff_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Loads weekly rows for a staff member into the cache.
	 *
	 * @param int $staff_id Staff ID.
	 * @return void
	 */
	private static function load_weekly( $staff_id ) {
		$staff_id = absint( $staff_id );

		if ( isset( self::$weekly_loaded[ $staff_id ] ) ) {
			return;
		}

		self::$weekly_loaded[ $staff_id ] = true;
		self::$weekly[ $staff_id ]        = array();

		foreach ( self::rows_for_staff( $staff_id ) as $row ) {
			self::$weekly[ $staff_id ][ (int) $row->day_of_week ][] = $row;
		}
	}

	/**
	 * Loads every exception row once.
	 *
	 * @return void
	 */
	private static function load_exceptions() {
		global $wpdb;

		if ( null !== self::$exceptions ) {
			return;
		}

		$table = QVB_Database::table( 'exceptions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY from_date ASC, id ASC" );

		self::$exceptions = is_array( $rows ) ? $rows : array();
	}

	/**
	 * Exception-defined shifts for a staff member and date.
	 *
	 * Staff-specific rows override business-wide rows. Returns null when no
	 * exception applies, an empty array when the day is closed.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @return array|null
	 */
	private static function exception_shifts( $staff_id, $date ) {
		self::load_exceptions();

		$staff_rows  = array();
		$global_rows = array();

		foreach ( self::$exceptions as $row ) {
			if ( $date < $row->from_date || $date > $row->to_date ) {
				continue;
			}

			if ( (int) $row->staff_id === (int) $staff_id ) {
				$staff_rows[] = $row;
			} elseif ( 0 === (int) $row->staff_id ) {
				$global_rows[] = $row;
			}
		}

		$rows = $staff_rows ? $staff_rows : $global_rows;

		if ( ! $rows ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( 'closed' === $row->type ) {
				return array();
			}
		}

		$shifts = array();

		foreach ( $rows as $row ) {
			if ( 'custom' !== $row->type ) {
				continue;
			}

			$start = QVB_Utils::sanitize_time( $row->start_time );
			$end   = QVB_Utils::sanitize_time( $row->end_time );

			if ( $start && $end && $end > $start ) {
				$shifts[] = array(
					'start'  => $start,
					'end'    => $end,
					'breaks' => array(),
				);
			}
		}

		return self::normalise_shifts( $shifts );
	}

	/**
	 * Resolved shifts for a staff member on a date.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @return array
	 */
	public static function get_shifts( $staff_id, $date ) {
		$staff_id = absint( $staff_id );
		$date     = QVB_Utils::sanitize_date( $date );
		$key      = $staff_id . '|' . $date;

		if ( isset( self::$shifts[ $key ] ) ) {
			return self::$shifts[ $key ];
		}

		self::$shifts[ $key ] = array();
		$staff                = self::staff( $staff_id );

		if ( ! $staff || 'active' !== $staff->status || ! $date ) {
			return self::$shifts[ $key ];
		}

		$exceptions = self::exception_shifts( $staff_id, $date );

		if ( null !== $exceptions ) {
			self::$shifts[ $key ] = $exceptions;
			return self::$shifts[ $key ];
		}

		$day    = QVB_Utils::day_of_week( $date );
		$shifts = array();

		if ( ! empty( $staff->use_business_hours ) ) {
			$hours = QVB_Settings::business_hours_for_day( $day );

			if ( $hours ) {
				$shifts[] = array(
					'start'  => $hours['start'],
					'end'    => $hours['end'],
					'breaks' => $hours['breaks'],
				);
			}
		} else {
			self::load_weekly( $staff_id );
			$rows = isset( self::$weekly[ $staff_id ][ $day ] ) ? self::$weekly[ $staff_id ][ $day ] : array();

			foreach ( $rows as $row ) {
				$start = QVB_Utils::sanitize_time( $row->start_time );
				$end   = QVB_Utils::sanitize_time( $row->end_time );

				if ( ! $start || ! $end || $end <= $start ) {
					continue;
				}

				$breaks      = array();
				$break_start = QVB_Utils::sanitize_time( $row->break_start );
				$break_end   = QVB_Utils::sanitize_time( $row->break_end );

				if ( $break_start && $break_end && $break_end > $break_start && $break_start > $start && $break_end < $end ) {
					$breaks[] = array(
						'start' => $break_start,
						'end'   => $break_end,
					);
				}

				$shifts[] = array(
					'start'  => $start,
					'end'    => $end,
					'breaks' => $breaks,
				);
			}
		}

		self::$shifts[ $key ] = self::normalise_shifts( $shifts );

		return self::$shifts[ $key ];
	}

	/**
	 * Validates and sorts shift rows.
	 *
	 * @param array $shifts Shifts.
	 * @return array
	 */
	private static function normalise_shifts( $shifts ) {
		$clean = array();

		foreach ( (array) $shifts as $shift ) {
			$start = QVB_Utils::sanitize_time( isset( $shift['start'] ) ? $shift['start'] : '' );
			$end   = QVB_Utils::sanitize_time( isset( $shift['end'] ) ? $shift['end'] : '' );

			if ( ! $start || ! $end || $end <= $start ) {
				continue;
			}

			$breaks = array();

			foreach ( (array) ( isset( $shift['breaks'] ) ? $shift['breaks'] : array() ) as $break ) {
				$break_start = QVB_Utils::sanitize_time( isset( $break['start'] ) ? $break['start'] : '' );
				$break_end   = QVB_Utils::sanitize_time( isset( $break['end'] ) ? $break['end'] : '' );

				if ( $break_start && $break_end && $break_end > $break_start && $break_start > $start && $break_end < $end ) {
					$breaks[] = array(
						'start' => $break_start,
						'end'   => $break_end,
					);
				}
			}

			$clean[] = array(
				'start'  => $start,
				'end'    => $end,
				'breaks' => $breaks,
			);
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				return strcmp( $a['start'], $b['start'] );
			}
		);

		return $clean;
	}

	/**
	 * Preloads schedules and bookings for a date range.
	 *
	 * @param int[]  $staff_ids Staff IDs.
	 * @param string $from      Start date.
	 * @param string $to        End date.
	 * @return void
	 */
	public static function prefetch( $staff_ids, $from, $to ) {
		global $wpdb;

		$staff_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $staff_ids ) ) ) );
		$from      = QVB_Utils::sanitize_date( $from );
		$to        = QVB_Utils::sanitize_date( $to );

		if ( ! $staff_ids || ! $from || ! $to || $to < $from ) {
			return;
		}

		self::load_exceptions();

		foreach ( $staff_ids as $staff_id ) {
			self::staff( $staff_id );
			self::load_weekly( $staff_id );
		}

		$dates = QVB_Utils::date_range( $from, $to );
		$needed = array();

		foreach ( $staff_ids as $staff_id ) {
			foreach ( $dates as $date ) {
				if ( ! isset( self::$bookings_loaded[ $staff_id ][ $date ] ) ) {
					$needed[ $staff_id . '|' . $date ] = array( $staff_id, $date );
				}
			}
		}

		if ( ! $needed ) {
			return;
		}

		$table     = QVB_Database::table( 'bookings' );
		$in        = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
		$stati     = QVB_Utils::blocking_statuses();
		$status_in = implode( ',', array_fill( 0, count( $stati ), '%s' ) );
		$params    = array_merge( $staff_ids, array( $from, $to ), $stati );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE staff_id IN ({$in}) AND booking_date BETWEEN %s AND %s AND status IN ({$status_in}) ORDER BY start_time ASC",
				$params
			)
		);

		$grouped = array();

		foreach ( (array) $rows as $row ) {
			$grouped[ (int) $row->staff_id ][ $row->booking_date ][] = $row;
		}

		foreach ( $needed as $key => $pair ) {
			list( $staff_id, $date ) = $pair;

			self::$bookings[ $staff_id ][ $date ]        = isset( $grouped[ $staff_id ][ $date ] ) ? $grouped[ $staff_id ][ $date ] : array();
			self::$bookings_loaded[ $staff_id ][ $date ] = true;
		}
	}

	/**
	 * Blocking bookings for a staff member on a date.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @return object[]
	 */
	private static function bookings_for( $staff_id, $date ) {
		$staff_id = absint( $staff_id );

		if ( isset( self::$bookings_loaded[ $staff_id ][ $date ] ) ) {
			return isset( self::$bookings[ $staff_id ][ $date ] ) ? self::$bookings[ $staff_id ][ $date ] : array();
		}

		global $wpdb;

		$table = QVB_Database::table( 'bookings' );
		$stati = QVB_Utils::blocking_statuses();
		$in    = implode( ',', array_fill( 0, count( $stati ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE staff_id = %d AND booking_date = %s AND status IN ({$in}) ORDER BY start_time ASC",
				array_merge( array( $staff_id, $date ), $stati )
			)
		);

		self::$bookings[ $staff_id ][ $date ]        = is_array( $rows ) ? $rows : array();
		self::$bookings_loaded[ $staff_id ][ $date ] = true;

		return self::$bookings[ $staff_id ][ $date ];
	}

	/**
	 * Active staff qualified for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return int[]
	 */
	public static function staff_for_service( $service_id ) {
		$ids = array();

		foreach ( QVB_Staff::active( absint( $service_id ) ) as $member ) {
			$ids[] = (int) $member->id;
		}

		return $ids;
	}

	/**
	 * Effective service buffer: service buffer plus the global default.
	 *
	 * @param object $service Service row.
	 * @return int
	 */
	public static function service_buffer( $service ) {
		$buffer = isset( $service->buffer_time ) ? (int) $service->buffer_time : 0;
		$buffer += (int) QVB_Settings::get( 'default_buffer' );

		return max( 0, min( 240 * 2, $buffer ) );
	}

	/**
	 * Generates bookable slots.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID, 0 for any available staff.
	 * @param string $date       Date.
	 * @param array  $args       exclude (booking ID), admin (bypass window limits).
	 * @return array<string,array> Keyed by HH:MM.
	 */
	public static function get_slots( $service_id, $staff_id, $date, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'exclude' => 0,
				'admin'   => false,
			)
		);

		$service_id = absint( $service_id );
		$staff_id   = absint( $staff_id );
		$date       = QVB_Utils::sanitize_date( $date );
		$service    = QVB_Services::get( $service_id );

		if ( ! $service || ! $date || $date < QVB_Utils::today() ) {
			return array();
		}

		if ( ! $args['admin'] && ! QVB_Utils::within_max_advance( $date ) ) {
			return array();
		}

		$staff_ids = array();

		if ( $staff_id ) {
			$member = self::staff( $staff_id );

			if ( $member && 'active' === $member->status && QVB_Staff::does_service( $staff_id, $service_id ) ) {
				$staff_ids = array( $staff_id );
			}
		} else {
			$staff_ids = self::staff_for_service( $service_id );
		}

		if ( ! $staff_ids ) {
			return array();
		}

		self::prefetch( $staff_ids, $date, $date );

		$duration = isset( $args['duration'] ) && $args['duration'] ? max( 5, absint( $args['duration'] ) ) : max( 5, (int) $service->duration );
		$buffer   = isset( $args['buffer'] ) && null !== $args['buffer'] ? max( 0, absint( $args['buffer'] ) ) : self::service_buffer( $service );
		$interval  = max( 5, (int) QVB_Settings::get( 'slot_interval' ) );
		$now       = QVB_Utils::now();
		$now_ts    = $now->getTimestamp();
		$notice_ts = $now_ts + ( (int) QVB_Settings::get( 'min_notice' ) * 60 );
		$exclude   = absint( $args['exclude'] );
		$slots     = array();

		foreach ( $staff_ids as $id ) {
			foreach ( self::get_shifts( $id, $date ) as $shift ) {
				$shift_start = QVB_Utils::time_to_minutes( $shift['start'] );
				$shift_end   = QVB_Utils::time_to_minutes( $shift['end'] );

				if ( null === $shift_start || null === $shift_end || $shift_end <= $shift_start ) {
					continue;
				}

				for ( $minute = $shift_start; ( $minute + $duration ) <= $shift_end; $minute += $interval ) {
					$time       = QVB_Utils::minutes_to_time( $minute );
					$end_time   = QVB_Utils::minutes_to_time( $minute + $duration );
					$buffer_end = QVB_Utils::minutes_to_time( $minute + $duration + $buffer );

					if ( ! $time || ! $end_time || ! $buffer_end ) {
						continue;
					}

					$local = QVB_Utils::local_datetime( $date, $time );

					if ( ! $local ) {
						continue; // DST gap.
					}

					$slot_ts = $local->getTimestamp();

					if ( $slot_ts < $now_ts ) {
						continue;
					}

					if ( ! $args['admin'] && $slot_ts < $notice_ts ) {
						continue;
					}

					$blocked = false;

					foreach ( $shift['breaks'] as $break ) {
						if ( QVB_Utils::times_overlap( $time, $end_time, $break['start'], $break['end'] ) ) {
							$blocked = true;
							break;
						}
					}

					if ( $blocked ) {
						continue;
					}

					foreach ( self::bookings_for( $id, $date ) as $booking ) {
						if ( $exclude && (int) $booking->id === $exclude ) {
							continue;
						}

						$existing_end = ! empty( $booking->buffer_end_time ) ? $booking->buffer_end_time : $booking->end_time;

						if ( QVB_Utils::times_overlap( $time, $buffer_end, $booking->start_time, $existing_end ) ) {
							$blocked = true;
							break;
						}
					}

					if ( $blocked ) {
						continue;
					}

					if ( ! isset( $slots[ $time ] ) ) {
						$slots[ $time ] = array(
							'time'      => $time,
							'label'     => QVB_Utils::format_time( $time ),
							'staff_ids' => array(),
						);
					}

					$slots[ $time ]['staff_ids'][] = (int) $id;
				}
			}
		}

		ksort( $slots );

		/**
		 * Filters the slots generated for a service, staff selection and date.
		 *
		 * @param array  $slots      Slots keyed by time.
		 * @param int    $service_id Service ID.
		 * @param int    $staff_id   Requested staff ID (0 = any).
		 * @param string $date       Date.
		 * @param array  $args       Generation args.
		 */
		return apply_filters( 'qvb_available_slots', $slots, $service_id, $staff_id, $date, $args );
	}

	/**
	 * Picks the least-loaded staff member from a slot.
	 *
	 * @param int[]  $staff_ids Candidate staff IDs.
	 * @param string $date      Date.
	 * @return int
	 */
	private static function pick_staff( $staff_ids, $date ) {
		$best       = 0;
		$best_load  = PHP_INT_MAX;
		$best_order = PHP_INT_MAX;

		foreach ( $staff_ids as $id ) {
			$member = self::staff( $id );

			if ( ! $member || 'active' !== $member->status ) {
				continue;
			}

			$load  = count( self::bookings_for( $id, $date ) );
			$order = (int) $member->sort_order;

			if ( $load < $best_load || ( $load === $best_load && $order < $best_order ) ) {
				$best       = (int) $id;
				$best_load  = $load;
				$best_order = $order;
			}
		}

		return $best;
	}

	/**
	 * Validates a requested slot server-side and resolves the staff member.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @param int    $staff_id   Requested staff ID (0 = any).
	 * @param array  $args       Generation args.
	 * @return array|WP_Error
	 */
	public static function validate_slot( $service_id, $date, $time, $staff_id = 0, $args = array() ) {
		$date = QVB_Utils::sanitize_date( $date );
		$time = QVB_Utils::sanitize_time( $time );

		if ( ! $date || ! $time ) {
			return new WP_Error(
				'qvb_invalid_datetime',
				__( 'Please choose a valid date and time.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		$window = self::check_booking_window( $date, $time, ! empty( $args['admin'] ) );

		if ( is_wp_error( $window ) ) {
			return $window;
		}

		$slots = self::get_slots( $service_id, $staff_id, $date, $args );

		if ( ! isset( $slots[ $time ] ) ) {
			return new WP_Error(
				'qvb_slot_unavailable',
				__( 'That time is no longer available. Please choose another slot.', 'qevora-booking' ),
				array( 'status' => 409 )
			);
		}

		$slot = $slots[ $time ];

		if ( $staff_id ) {
			if ( ! in_array( (int) $staff_id, $slot['staff_ids'], true ) ) {
				return new WP_Error(
					'qvb_slot_unavailable',
					__( 'That staff member is not available at this time. Please choose another slot.', 'qevora-booking' ),
					array( 'status' => 409 )
				);
			}

			$chosen = (int) $staff_id;
		} else {
			$chosen = self::pick_staff( $slot['staff_ids'], $date );

			if ( ! $chosen ) {
				return new WP_Error(
					'qvb_slot_unavailable',
					__( 'That time is no longer available. Please choose another slot.', 'qevora-booking' ),
					array( 'status' => 409 )
				);
			}
		}

		return array(
			'staff_id' => $chosen,
			'date'     => $date,
			'time'     => $time,
			'label'    => $slot['label'],
		);
	}

	/**
	 * Checks the customer booking window for a date/time.
	 *
	 * @param string $date  Date.
	 * @param string $time  Time.
	 * @param bool   $admin Whether to bypass minimum notice and advance limits.
	 * @return true|WP_Error
	 */
	public static function check_booking_window( $date, $time, $admin = false ) {
		$date = QVB_Utils::sanitize_date( $date );
		$time = QVB_Utils::sanitize_time( $time );

		if ( ! $date || ! $time ) {
			return new WP_Error(
				'qvb_invalid_datetime',
				__( 'Please choose a valid date and time.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		if ( QVB_Utils::is_past( $date, $time ) ) {
			return new WP_Error(
				'qvb_past_booking',
				__( 'That date and time has already passed. Please choose a future appointment.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		if ( $admin ) {
			return true;
		}

		if ( ! QVB_Utils::within_max_advance( $date ) ) {
			return new WP_Error(
				'qvb_too_far',
				sprintf(
					/* translators: %d: number of days. */
					__( 'Bookings can be made at most %d days in advance.', 'qevora-booking' ),
					(int) QVB_Settings::get( 'max_advance' )
				),
				array( 'status' => 400 )
			);
		}

		if ( ! QVB_Utils::respects_min_notice( $date, $time ) ) {
			return new WP_Error(
				'qvb_too_soon',
				sprintf(
					/* translators: %s: human readable notice period. */
					__( 'This time is too soon. Please choose a slot at least %s from now.', 'qevora-booking' ),
					QVB_Utils::duration_label( (int) QVB_Settings::get( 'min_notice' ) )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Availability for the next N days (used by the quick date chips).
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID, 0 for any.
	 * @param string $start_date Start date.
	 * @param int    $days       Number of days (max 31).
	 * @param bool   $admin      Whether to bypass window limits.
	 * @return array
	 */
	public static function get_date_availability( $service_id, $staff_id, $start_date, $days = 14, $admin = false ) {
		$start_date = QVB_Utils::sanitize_date( $start_date );
		$days       = max( 1, min( 31, (int) $days ) );

		if ( ! $start_date || $start_date < QVB_Utils::today() ) {
			$start_date = QVB_Utils::today();
		}

		$staff_ids = $staff_id ? array( absint( $staff_id ) ) : self::staff_for_service( $service_id );
		$end_date  = gmdate( 'Y-m-d', strtotime( $start_date . ' +' . ( $days - 1 ) . ' days' ) );

		self::prefetch( $staff_ids, $start_date, $end_date );

		$out    = array();
		$cursor = $start_date;

		for ( $i = 0; $i < $days; $i++ ) {
			$slots = self::get_slots( $service_id, $staff_id, $cursor, array( 'admin' => $admin ) );
			$first = $slots ? (string) array_key_first( $slots ) : '';

			$out[] = array(
				'date'      => $cursor,
				'label'     => QVB_Utils::format_date( $cursor, 'D, M j' ),
				'weekday'   => QVB_Utils::format_date( $cursor, 'D' ),
				'available' => ! empty( $slots ),
				'count'     => count( $slots ),
				'first'     => $first,
			);

			$cursor = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}

		return $out;
	}

	/**
	 * Persists a weekly schedule for a staff member.
	 *
	 * @param int   $staff_id Staff ID.
	 * @param array $rows     Rows with day_of_week, start, end, break_start, break_end.
	 * @return true|WP_Error
	 */
	public static function save_schedule( $staff_id, $rows ) {
		global $wpdb;

		$staff_id = absint( $staff_id );

		if ( ! $staff_id || ! self::staff( $staff_id ) ) {
			return new WP_Error( 'qvb_staff_missing', __( 'That staff member no longer exists.', 'qevora-booking' ) );
		}

		$clean = array();

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$day   = isset( $row['day_of_week'] ) ? (int) $row['day_of_week'] : -1;
			$start = QVB_Utils::sanitize_time( isset( $row['start'] ) ? $row['start'] : '' );
			$end   = QVB_Utils::sanitize_time( isset( $row['end'] ) ? $row['end'] : '' );

			if ( $day < 0 || $day > 6 ) {
				continue;
			}

			if ( ! $start || ! $end || $end <= $start ) {
				return new WP_Error( 'qvb_schedule_time', __( 'Each shift needs a start time before its end time.', 'qevora-booking' ) );
			}

			$break_start = QVB_Utils::sanitize_time( isset( $row['break_start'] ) ? $row['break_start'] : '' );
			$break_end   = QVB_Utils::sanitize_time( isset( $row['break_end'] ) ? $row['break_end'] : '' );

			if ( ( $break_start && ! $break_end ) || ( ! $break_start && $break_end ) ) {
				return new WP_Error( 'qvb_schedule_break', __( 'A break needs both a start and end time.', 'qevora-booking' ) );
			}

			if ( $break_start && $break_end && ( $break_end <= $break_start || $break_start <= $start || $break_end >= $end ) ) {
				return new WP_Error( 'qvb_schedule_break', __( 'Breaks must start and end inside the shift.', 'qevora-booking' ) );
			}

			$clean[] = array(
				'staff_id'    => $staff_id,
				'day_of_week' => $day,
				'start_time'  => $start,
				'end_time'    => $end,
				'break_start' => $break_start,
				'break_end'   => $break_end,
			);
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				if ( $a['day_of_week'] === $b['day_of_week'] ) {
					return strcmp( $a['start_time'], $b['start_time'] );
				}

				return $a['day_of_week'] <=> $b['day_of_week'];
			}
		);

		$previous = array();

		foreach ( $clean as $row ) {
			$day = $row['day_of_week'];

			if ( isset( $previous[ $day ] ) && $row['start_time'] < $previous[ $day ] ) {
				return new WP_Error( 'qvb_schedule_overlap', __( 'Shifts on the same day cannot overlap.', 'qevora-booking' ) );
			}

			$previous[ $day ] = $row['end_time'];
		}

		$table = QVB_Database::table( 'availability' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'staff_id' => $staff_id ), array( '%d' ) );

		$now = QVB_Utils::now_sql();

		foreach ( $clean as $row ) {
			$row['created_at'] = $now;
			$row['updated_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert( $table, $row, array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );

			if ( false === $inserted ) {
				return new WP_Error( 'qvb_schedule_save', __( 'The schedule could not be saved. Please try again.', 'qevora-booking' ) );
			}
		}

		self::flush_cache();

		/**
		 * Fires after a staff schedule is saved.
		 *
		 * @param int   $staff_id Staff ID.
		 * @param array $clean    Saved rows.
		 */
		do_action( 'qvb_schedule_saved', $staff_id, $clean );

		return true;
	}

	/**
	 * Switches a staff member between business hours and a custom schedule.
	 *
	 * @param int $staff_id Staff ID.
	 * @param int $enabled  Whether to follow business hours.
	 * @return bool
	 */
	public static function set_use_business_hours( $staff_id, $enabled ) {
		global $wpdb;

		$staff_id = absint( $staff_id );

		if ( ! $staff_id || ! self::staff( $staff_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			QVB_Database::table( 'staff' ),
			array(
				'use_business_hours' => $enabled ? 1 : 0,
				'updated_at'         => QVB_Utils::now_sql(),
			),
			array( 'id' => $staff_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		self::flush_cache();

		return true;
	}

	/**
	 * Exceptions (holidays and special hours) for admin lists.
	 *
	 * @param array $args staff_id, scope (upcoming|past|all), type.
	 * @return object[]
	 */
	public static function get_exceptions( $args = array() ) {
		global $wpdb;

		$args   = wp_parse_args(
			$args,
			array(
				'staff_id' => '',
				'scope'    => 'all',
				'type'     => '',
			)
		);
		$table  = QVB_Database::table( 'exceptions' );
		$staff  = QVB_Database::table( 'staff' );
		$where  = array( '1=1' );
		$params = array();

		if ( null !== $args['staff_id'] && '' !== $args['staff_id'] ) {
			$where[]  = 'e.staff_id = %d';
			$params[] = absint( $args['staff_id'] );
		}

		if ( 'upcoming' === $args['scope'] ) {
			$where[]  = 'e.to_date >= %s';
			$params[] = QVB_Utils::today();
		} elseif ( 'past' === $args['scope'] ) {
			$where[]  = 'e.to_date < %s';
			$params[] = QVB_Utils::today();
		}

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'e.type = %s';
			$params[] = sanitize_key( $args['type'] );
		}

		$sql = "SELECT e.*, s.name AS staff_name FROM {$table} e LEFT JOIN {$staff} s ON s.id = e.staff_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY e.from_date DESC, e.id DESC';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Alias for get_exceptions().
	 *
	 * @param array $args Args.
	 * @return object[]
	 */
	public static function exceptions( $args = array() ) {
		return self::get_exceptions( $args );
	}

	/**
	 * Single exception.
	 *
	 * @param int $id Exception ID.
	 * @return object|null
	 */
	public static function get_exception( $id ) {
		global $wpdb;

		$table = QVB_Database::table( 'exceptions' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Saves an exception.
	 *
	 * @param array $data id, staff_id, from_date, to_date, type, start_time, end_time, note.
	 * @return int|WP_Error
	 */
	public static function save_exception( $data ) {
		global $wpdb;

		$id        = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$staff_id  = isset( $data['staff_id'] ) ? absint( $data['staff_id'] ) : 0;
		$from_date = QVB_Utils::sanitize_date( isset( $data['from_date'] ) ? $data['from_date'] : '' );
		$to_date   = QVB_Utils::sanitize_date( isset( $data['to_date'] ) ? $data['to_date'] : '' );
		$type      = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'closed';
		$note      = isset( $data['note'] ) ? sanitize_text_field( (string) $data['note'] ) : '';

		if ( 'holiday' === $type ) {
			$type = 'closed';
		}

		if ( 'custom' !== $type ) {
			$type = 'closed';
		}

		if ( ! $from_date ) {
			return new WP_Error( 'qvb_exception_date', __( 'Please choose a start date.', 'qevora-booking' ) );
		}

		if ( ! $to_date ) {
			$to_date = $from_date;
		}

		if ( $to_date < $from_date ) {
			return new WP_Error( 'qvb_exception_dates', __( 'The end date cannot be before the start date.', 'qevora-booking' ) );
		}

		if ( $staff_id && ! self::staff( $staff_id ) ) {
			return new WP_Error( 'qvb_exception_staff', __( 'That staff member no longer exists.', 'qevora-booking' ) );
		}

		$start = '';
		$end   = '';

		if ( 'custom' === $type ) {
			$start = QVB_Utils::sanitize_time( isset( $data['start_time'] ) ? $data['start_time'] : '' );
			$end   = QVB_Utils::sanitize_time( isset( $data['end_time'] ) ? $data['end_time'] : '' );

			if ( ! $start || ! $end || $end <= $start ) {
				return new WP_Error( 'qvb_exception_time', __( 'Special hours need a start time before the end time.', 'qevora-booking' ) );
			}
		}

		$now = QVB_Utils::now_sql();
		$row = array(
			'staff_id'   => $staff_id,
			'from_date'  => $from_date,
			'to_date'    => $to_date,
			'type'       => $type,
			'start_time' => $start,
			'end_time'   => $end,
			'note'       => mb_substr( $note, 0, 255 ),
			'updated_at' => $now,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		$table   = QVB_Database::table( 'exceptions' );

		if ( $id ) {
			if ( ! self::get_exception( $id ) ) {
				return new WP_Error( 'qvb_exception_missing', __( 'That exception no longer exists.', 'qevora-booking' ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$saved = $wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );

			if ( false === $saved ) {
				return new WP_Error( 'qvb_exception_save', __( 'The exception could not be saved. Please try again.', 'qevora-booking' ) );
			}
		} else {
			$row['created_at'] = $now;
			$formats[]         = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$saved = $wpdb->insert( $table, $row, $formats );

			if ( ! $saved ) {
				return new WP_Error( 'qvb_exception_save', __( 'The exception could not be saved. Please try again.', 'qevora-booking' ) );
			}

			$id = (int) $wpdb->insert_id;
		}

		self::flush_cache();

		/**
		 * Fires after an exception is saved.
		 *
		 * @param int   $id  Exception ID.
		 * @param array $row Saved row.
		 */
		do_action( 'qvb_exception_saved', $id, $row );

		return $id;
	}

	/**
	 * Deletes an exception.
	 *
	 * @param int $id Exception ID.
	 * @return bool
	 */
	public static function delete_exception( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id || ! self::get_exception( $id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( QVB_Database::table( 'exceptions' ), array( 'id' => $id ), array( '%d' ) );

		if ( $deleted ) {
			self::flush_cache();

			/**
			 * Fires after an exception is deleted.
			 *
			 * @param int $id Exception ID.
			 */
			do_action( 'qvb_exception_deleted', $id );
		}

		return (bool) $deleted;
	}
}
