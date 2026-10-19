<?php
/**
 * Customer records.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Customers
 */
final class QVB_Customers {

	/**
	 * Gets a customer.
	 *
	 * @param int $id Customer ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = QVB_Database::table( 'customers' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Gets a customer by email (case-insensitive).
	 *
	 * @param string $email Email.
	 * @return object|null
	 */
	public static function get_by_email( $email ) {
		global $wpdb;

		$email = strtolower( sanitize_email( $email ) );

		if ( ! $email ) {
			return null;
		}

		$table = QVB_Database::table( 'customers' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE LOWER(email) = %s LIMIT 1", $email ) );
	}

	/**
	 * Creates or updates a customer from a booking.
	 *
	 * @param array $data name, email, phone, wp_user_id.
	 * @return int|WP_Error
	 */
	public static function upsert( $data ) {
		global $wpdb;

		$name       = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$email      = isset( $data['email'] ) ? strtolower( sanitize_email( (string) $data['email'] ) ) : '';
		$phone      = isset( $data['phone'] ) ? sanitize_text_field( (string) $data['phone'] ) : '';
		$wp_user_id = isset( $data['wp_user_id'] ) ? absint( $data['wp_user_id'] ) : 0;

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'qvb_customer_email', __( 'Please enter a valid email address.', 'qevora-booking' ) );
		}

		if ( '' === $name ) {
			return new WP_Error( 'qvb_customer_name', __( 'Please enter a name.', 'qevora-booking' ) );
		}

		$existing = self::get_by_email( $email );
		$now      = QVB_Utils::now_sql();
		$table    = QVB_Database::table( 'customers' );

		if ( $existing ) {
			$row = array(
				'name'       => mb_substr( $name, 0, 191 ),
				'updated_at' => $now,
			);

			$formats = array( '%s', '%s' );

			if ( $phone ) {
				$row['phone']  = mb_substr( $phone, 0, 40 );
				$formats[]     = '%s';
			}

			if ( $wp_user_id ) {
				$row['wp_user_id'] = $wp_user_id;
				$formats[]         = '%d';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, $row, array( 'id' => (int) $existing->id ), $formats, array( '%d' ) );

			return (int) $existing->id;
		}

		$row = array(
			'name'       => mb_substr( $name, 0, 191 ),
			'email'      => $email,
			'phone'      => mb_substr( $phone, 0, 40 ),
			'wp_user_id' => $wp_user_id,
			'created_at' => $now,
			'updated_at' => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $table, $row, array( '%s', '%s', '%s', '%d', '%s', '%s' ) );

		if ( $inserted ) {
			return (int) $wpdb->insert_id;
		}

		// A concurrent request may have created the same email.
		$existing = self::get_by_email( $email );

		if ( $existing ) {
			return (int) $existing->id;
		}

		return new WP_Error( 'qvb_customer_save', __( 'The customer record could not be saved.', 'qevora-booking' ) );
	}

	/**
	 * Updates customer contact details from the admin.
	 *
	 * @param int   $id   Customer ID.
	 * @param array $data name, email, phone, notes.
	 * @return true|WP_Error
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$id       = absint( $id );
		$existing = self::get( $id );

		if ( ! $existing ) {
			return new WP_Error( 'qvb_customer_missing', __( 'That customer no longer exists.', 'qevora-booking' ) );
		}

		$name  = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : $existing->name;
		$email = isset( $data['email'] ) ? strtolower( sanitize_email( (string) $data['email'] ) ) : strtolower( $existing->email );
		$phone = isset( $data['phone'] ) ? sanitize_text_field( (string) $data['phone'] ) : $existing->phone;
		$notes = isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : (string) $existing->notes;

		if ( '' === $name ) {
			return new WP_Error( 'qvb_customer_name', __( 'Please enter a customer name.', 'qevora-booking' ) );
		}

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'qvb_customer_email', __( 'Please enter a valid email address.', 'qevora-booking' ) );
		}

		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . QVB_Database::table( 'customers' ) . ' WHERE LOWER(email) = %s AND id != %d LIMIT 1',
				$email,
				$id
			)
		);

		if ( $duplicate ) {
			return new WP_Error( 'qvb_customer_duplicate', __( 'Another customer already uses that email address.', 'qevora-booking' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			QVB_Database::table( 'customers' ),
			array(
				'name'       => mb_substr( $name, 0, 191 ),
				'email'      => $email,
				'phone'      => mb_substr( $phone, 0, 40 ),
				'notes'      => $notes,
				'updated_at' => QVB_Utils::now_sql(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'qvb_customer_save', __( 'The customer could not be saved. Please try again.', 'qevora-booking' ) );
		}

		return true;
	}

	/**
	 * Paginated customer search.
	 *
	 * @param array $args search, paged, per_page, orderby, order.
	 * @return array{items:array,total:int,pages:int}
	 */
	public static function search( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'paged'    => 1,
				'per_page' => 20,
				'orderby'  => 'last_booking_at',
				'order'    => 'desc',
			)
		);

		$customers = QVB_Database::table( 'customers' );
		$bookings  = QVB_Database::table( 'bookings' );
		$where     = array( '1=1' );
		$params    = array();

		if ( '' !== trim( (string) $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(c.name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$order_map = array(
			'name'            => 'c.name',
			'created_at'      => 'c.created_at',
			'last_booking_at' => 'c.last_booking_at',
			'total_bookings'  => 'total_bookings',
			'total_spent'     => 'total_spent',
			'id'              => 'c.id',
		);
		$orderby   = isset( $order_map[ $args['orderby'] ] ) ? $order_map[ $args['orderby'] ] : 'c.last_booking_at';
		$order     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per_page  = max( 1, min( 100, (int) $args['per_page'] ) );
		$paged     = max( 1, (int) $args['paged'] );
		$offset    = ( $paged - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$customers} c WHERE {$where_sql}";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT c.*,
			(SELECT COUNT(*) FROM {$bookings} b WHERE b.customer_id = c.id AND b.status NOT IN ('cancelled','rejected')) AS total_bookings,
			(SELECT COALESCE(SUM(b.price),0) FROM {$bookings} b WHERE b.customer_id = c.id AND b.status = 'completed') AS total_spent
			FROM {$customers} c WHERE {$where_sql} ORDER BY {$orderby} {$order}, c.id DESC LIMIT %d OFFSET %d";

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
	 * Recalculates stored counters for a customer.
	 *
	 * @param int $id Customer ID.
	 * @return void
	 */
	public static function recalc( $id ) {
		global $wpdb;

		$id       = absint( $id );
		$bookings = QVB_Database::table( 'bookings' );

		if ( ! $id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, MAX(CONCAT(booking_date, ' 00:00:00')) AS last_at FROM {$bookings} WHERE customer_id = %d AND status NOT IN ('cancelled','rejected')",
				$id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			QVB_Database::table( 'customers' ),
			array(
				'total_bookings'  => $row ? (int) $row->total : 0,
				'last_booking_at' => $row && $row->last_at ? $row->last_at : null,
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Booking history for a customer.
	 *
	 * @param int $id    Customer ID.
	 * @param int $limit Maximum rows.
	 * @return object[]
	 */
	public static function bookings( $id, $limit = 100 ) {
		$result = QVB_Bookings::query(
			array(
				'customer_id' => absint( $id ),
				'per_page'    => max( 1, min( 500, (int) $limit ) ),
				'page'        => 1,
				'orderby'     => 'booking_date',
				'order'       => 'DESC',
			)
		);

		return $result['items'];
	}

	/**
	 * Deletes a customer and detaches their bookings.
	 *
	 * Prefer anonymise_customer() for privacy requests so reporting survives.
	 *
	 * @param int $id Customer ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id || ! self::get( $id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( QVB_Database::table( 'bookings' ), array( 'customer_id' => 0 ), array( 'customer_id' => $id ), array( '%d' ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( QVB_Database::table( 'customers' ), array( 'id' => $id ), array( '%d' ) );

		return (bool) $deleted;
	}

	/**
	 * Anonymises a customer and their bookings for privacy requests.
	 *
	 * @param int $id Customer ID.
	 * @return bool
	 */
	public static function anonymise( $id ) {
		global $wpdb;

		$id       = absint( $id );
		$customer = self::get( $id );

		if ( ! $customer ) {
			return false;
		}

		$placeholder = 'removed-' . $id . '@' . 'example.invalid';
		$now         = QVB_Utils::now_sql();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			QVB_Database::table( 'customers' ),
			array(
				'name'       => __( 'Removed customer', 'qevora-booking' ),
				'email'      => $placeholder,
				'phone'      => '',
				'notes'      => '',
				'wp_user_id' => 0,
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			QVB_Database::table( 'bookings' ),
			array(
				'customer_name'  => __( 'Removed customer', 'qevora-booking' ),
				'customer_email' => $placeholder,
				'customer_phone' => '',
				'notes'          => '',
				'admin_notes'    => '',
				'ip_hash'        => '',
				'updated_at'     => $now,
			),
			array( 'customer_id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a customer is anonymised.
		 *
		 * @param int $id Customer ID.
		 */
		do_action( 'qvb_customer_anonymised', $id );

		return true;
	}

	/**
	 * US spelling alias for anonymise().
	 *
	 * @param int $id Customer ID.
	 * @return bool
	 */
	public static function anonymize( $id ) {
		return self::anonymise( $id );
	}

	/**
	 * Total customers.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . QVB_Database::table( 'customers' ) );
	}
}
