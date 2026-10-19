<?php
/**
 * Staff profiles, services and schedules.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Staff
 */
final class QVB_Staff {

	/**
	 * Returns staff profiles.
	 *
	 * @param array $args status, service_id, search.
	 * @return object[]
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$table  = QVB_Database::table( 'staff' );
		$where  = array( '1=1' );
		$params = array();
		$join   = '';

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['service_id'] ) ) {
			$join     = 'INNER JOIN ' . QVB_Database::table( 'staff_services' ) . ' ss ON ss.staff_id = s.id';
			$where[]  = 'ss.service_id = %d';
			$params[] = absint( $args['service_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(s.name LIKE %s OR s.email LIKE %s OR s.job_title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT s.* FROM {$table} s {$join} WHERE " . implode( ' AND ', $where ) . ' ORDER BY s.sort_order ASC, s.name ASC';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Active staff, optionally qualified for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return object[]
	 */
	public static function active( $service_id = 0 ) {
		$args = array( 'status' => 'active' );

		if ( $service_id ) {
			$args['service_id'] = absint( $service_id );
		}

		return self::all( $args );
	}

	/**
	 * Gets a staff member.
	 *
	 * @param int $id Staff ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = QVB_Database::table( 'staff' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Gets the staff profile linked to a WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;

		$table = QVB_Database::table( 'staff' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", absint( $user_id ) ) );
	}

	/**
	 * Creates or updates a staff member.
	 *
	 * @param array $data Profile data.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;

		$id       = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$name     = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$email    = isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '';
		$phone    = isset( $data['phone'] ) ? sanitize_text_field( (string) $data['phone'] ) : '';
		$title    = isset( $data['job_title'] ) ? sanitize_text_field( (string) $data['job_title'] ) : '';
		$bio      = isset( $data['bio'] ) ? sanitize_textarea_field( (string) $data['bio'] ) : '';
		$image_id = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
		$color    = QVB_Utils::hex_color( isset( $data['color'] ) ? $data['color'] : '', '#7c3aed' );
		$status   = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';
		$sort     = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		$hours    = ! empty( $data['use_business_hours'] ) ? 1 : 0;
		$user_id  = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;

		if ( '' === $name ) {
			return new WP_Error( 'qvb_staff_name', __( 'Please enter a staff name.', 'qevora-booking' ) );
		}

		if ( $email && ! is_email( $email ) ) {
			return new WP_Error( 'qvb_staff_email', __( 'Please enter a valid staff email address.', 'qevora-booking' ) );
		}

		if ( $user_id && ! get_user_by( 'id', $user_id ) ) {
			$user_id = 0;
		}

		$name = mb_substr( $name, 0, 191 );
		$now  = QVB_Utils::now_sql();
		$row  = array(
			'user_id'            => $user_id,
			'name'               => $name,
			'email'              => $email,
			'phone'              => mb_substr( $phone, 0, 40 ),
			'job_title'          => mb_substr( $title, 0, 191 ),
			'bio'                => $bio,
			'image_id'           => $image_id,
			'color'              => $color,
			'use_business_hours' => $hours,
			'status'             => $status,
			'sort_order'         => $sort,
			'updated_at'         => $now,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' );

		if ( $id ) {
			if ( ! self::get( $id ) ) {
				return new WP_Error( 'qvb_staff_missing', __( 'That staff member no longer exists.', 'qevora-booking' ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update( QVB_Database::table( 'staff' ), $row, array( 'id' => $id ), $formats, array( '%d' ) );

			if ( false === $updated ) {
				return new WP_Error( 'qvb_staff_save', __( 'The staff member could not be saved. Please try again.', 'qevora-booking' ) );
			}
		} else {
			$row['created_at'] = $now;
			$formats[]         = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert( QVB_Database::table( 'staff' ), $row, $formats );

			if ( ! $inserted ) {
				return new WP_Error( 'qvb_staff_save', __( 'The staff member could not be saved. Please try again.', 'qevora-booking' ) );
			}

			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a staff member is saved.
		 *
		 * @param int   $id   Staff ID.
		 * @param array $data Profile data.
		 */
		do_action( 'qvb_staff_saved', $id, $data );

		return $id;
	}

	/**
	 * Deletes a staff member, their assignments and schedule.
	 *
	 * @param int $id Staff ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id || ! self::get( $id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( QVB_Database::table( 'staff_services' ), array( 'staff_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( QVB_Database::table( 'availability' ), array( 'staff_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( QVB_Database::table( 'exceptions' ), array( 'staff_id' => $id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( QVB_Database::table( 'staff' ), array( 'id' => $id ), array( '%d' ) );

		if ( $deleted ) {
			/**
			 * Fires after a staff member is deleted.
			 *
			 * @param int $id Staff ID.
			 */
			do_action( 'qvb_staff_deleted', $id );
		}

		return (bool) $deleted;
	}

	/**
	 * Service IDs assigned to a staff member.
	 *
	 * @param int $staff_id Staff ID.
	 * @return int[]
	 */
	public static function services( $staff_id ) {
		global $wpdb;

		$table = QVB_Database::table( 'staff_services' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT service_id FROM {$table} WHERE staff_id = %d", absint( $staff_id ) ) );

		return array_map( 'absint', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Alias kept for readability.
	 *
	 * @param int $staff_id Staff ID.
	 * @return int[]
	 */
	public static function service_ids( $staff_id ) {
		return self::services( $staff_id );
	}

	/**
	 * Replaces a staff member's service assignments.
	 *
	 * @param int   $staff_id    Staff ID.
	 * @param int[] $service_ids Service IDs.
	 * @return bool
	 */
	public static function set_services( $staff_id, $service_ids ) {
		global $wpdb;

		$staff_id = absint( $staff_id );

		if ( ! $staff_id || ! self::get( $staff_id ) ) {
			return false;
		}

		$service_ids = array_unique( array_filter( array_map( 'absint', (array) $service_ids ) ) );
		$valid       = array();

		foreach ( $service_ids as $service_id ) {
			if ( QVB_Services::get( $service_id ) ) {
				$valid[] = $service_id;
			}
		}

		$table = QVB_Database::table( 'staff_services' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'staff_id' => $staff_id ), array( '%d' ) );

		foreach ( $valid as $service_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				array(
					'staff_id'   => $staff_id,
					'service_id' => $service_id,
				),
				array( '%d', '%d' )
			);
		}

		/**
		 * Fires after staff service assignments change.
		 *
		 * @param int   $staff_id Staff ID.
		 * @param int[] $valid    Service IDs.
		 */
		do_action( 'qvb_staff_services_updated', $staff_id, $valid );

		return true;
	}

	/**
	 * Whether a staff member performs a service.
	 *
	 * @param int $staff_id   Staff ID.
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function does_service( $staff_id, $service_id ) {
		global $wpdb;

		$table = QVB_Database::table( 'staff_services' );
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE staff_id = %d AND service_id = %d",
				absint( $staff_id ),
				absint( $service_id )
			)
		);

		return (bool) $found;
	}

	/**
	 * Raw weekly availability rows for a staff member.
	 *
	 * @param int $staff_id Staff ID.
	 * @return object[]
	 */
	public static function schedule( $staff_id ) {
		return QVB_Availability::rows_for_staff( $staff_id );
	}

	/**
	 * Select options.
	 *
	 * @param bool   $include_any Include an "any" option.
	 * @param bool   $active_only Only active staff.
	 * @param string $any_label   Label for the any option.
	 * @return array<int,string>
	 */
	public static function options( $include_any = true, $active_only = false, $any_label = '' ) {
		$options = array();

		if ( $include_any ) {
			$options[0] = $any_label ? $any_label : __( 'Any available staff', 'qevora-booking' );
		}

		$staff = $active_only ? self::active() : self::all();

		foreach ( $staff as $member ) {
			$options[ (int) $member->id ] = $member->name;
		}

		return $options;
	}

	/**
	 * Color palette.
	 *
	 * @return string[]
	 */
	public static function palette() {
		return array( '#7c3aed', '#db2777', '#0891b2', '#059669', '#d97706', '#dc2626', '#4f46e5', '#0d9488' );
	}

	/**
	 * A deterministic suggestion for a new staff member.
	 *
	 * @param int $index Index.
	 * @return string
	 */
	public static function suggested_color( $index = 0 ) {
		$palette = self::palette();

		return $palette[ absint( $index ) % count( $palette ) ];
	}

	/**
	 * Total staff count.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . QVB_Database::table( 'staff' ) );
	}
}
