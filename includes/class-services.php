<?php
/**
 * Services.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Services
 */
final class QVB_Services {

	/**
	 * Returns services.
	 *
	 * @param array $args status, category_id, search, ids, orderby.
	 * @return object[]
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$table  = QVB_Database::table( 'services' );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$params[] = absint( $args['category_id'] );
		}

		if ( ! empty( $args['ids'] ) && is_array( $args['ids'] ) ) {
			$ids = array_filter( array_map( 'absint', $args['ids'] ) );

			if ( $ids ) {
				$where[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
				$params  = array_merge( $params, $ids );
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(name LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$order = 'sort_order ASC, name ASC';

		if ( ! empty( $args['orderby'] ) && 'price' === $args['orderby'] ) {
			$order = 'price ASC, name ASC';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY ' . $order;

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Active services.
	 *
	 * @param int $category_id Optional category filter.
	 * @return object[]
	 */
	public static function active( $category_id = 0 ) {
		$args = array( 'status' => 'active' );

		if ( $category_id ) {
			$args['category_id'] = $category_id;
		}

		return self::all( $args );
	}

	/**
	 * Gets a service.
	 *
	 * @param int $id Service ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = QVB_Database::table( 'services' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Gets a service by slug.
	 *
	 * @param string $slug Slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;

		$table = QVB_Database::table( 'services' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", sanitize_title( $slug ) ) );
	}

	/**
	 * Creates or updates a service.
	 *
	 * @param array $data Service data.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;

		$id          = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$name        = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$category_id = isset( $data['category_id'] ) ? absint( $data['category_id'] ) : 0;
		$description = isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '';
		$image_id    = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
		$duration    = isset( $data['duration'] ) ? absint( $data['duration'] ) : 60;
		$buffer      = isset( $data['buffer_time'] ) ? absint( $data['buffer_time'] ) : 0;
		$price_raw   = isset( $data['price'] ) ? (string) $data['price'] : '0';
		$status      = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';
		$sort_order  = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;

		if ( '' === $name ) {
			return new WP_Error( 'qvb_service_name', __( 'Please enter a service name.', 'qevora-booking' ) );
		}

		if ( $duration < 5 || $duration > 1440 ) {
			return new WP_Error( 'qvb_service_duration', __( 'Duration must be between 5 and 1440 minutes.', 'qevora-booking' ) );
		}

		if ( $buffer > 240 ) {
			$buffer = 240;
		}

		$price = (float) str_replace( array( ',', ' ' ), '', $price_raw );

		if ( $price < 0 || ! is_finite( $price ) ) {
			$price = 0;
		}

		$price = round( $price, 2 );

		if ( $category_id && ! QVB_Categories::get( $category_id ) ) {
			$category_id = 0;
		}

		$name = mb_substr( $name, 0, 191 );
		$slug = isset( $data['slug'] ) && '' !== trim( (string) $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
		$slug = self::unique_slug( $slug ? $slug : 'service', $id );

		$now = QVB_Utils::now_sql();
		$row = array(
			'category_id' => $category_id,
			'name'        => $name,
			'slug'        => $slug,
			'description' => $description,
			'image_id'    => $image_id,
			'duration'    => $duration,
			'buffer_time' => $buffer,
			'price'       => $price,
			'status'      => $status,
			'sort_order'  => $sort_order,
			'updated_at'  => $now,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%d', '%s' );

		if ( $id ) {
			if ( ! self::get( $id ) ) {
				return new WP_Error( 'qvb_service_missing', __( 'That service no longer exists.', 'qevora-booking' ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update( QVB_Database::table( 'services' ), $row, array( 'id' => $id ), $formats, array( '%d' ) );

			if ( false === $updated ) {
				return new WP_Error( 'qvb_service_save', __( 'The service could not be saved. Please try again.', 'qevora-booking' ) );
			}
		} else {
			$row['created_at'] = $now;
			$formats[]         = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert( QVB_Database::table( 'services' ), $row, $formats );

			if ( ! $inserted ) {
				return new WP_Error( 'qvb_service_save', __( 'The service could not be saved. Please try again.', 'qevora-booking' ) );
			}

			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a service is saved.
		 *
		 * @param int   $id   Service ID.
		 * @param array $data Saved data.
		 */
		do_action( 'qvb_service_saved', $id, $data );

		return $id;
	}

	/**
	 * Deletes a service, its staff assignments and its category link.
	 *
	 * Historical bookings are untouched because they store snapshots.
	 *
	 * @param int $id Service ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id || ! self::get( $id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( QVB_Database::table( 'staff_services' ), array( 'service_id' => $id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( QVB_Database::table( 'services' ), array( 'id' => $id ), array( '%d' ) );

		if ( $deleted ) {
			/**
			 * Fires after a service is deleted.
			 *
			 * @param int $id Service ID.
			 */
			do_action( 'qvb_service_deleted', $id );
		}

		return (bool) $deleted;
	}

	/**
	 * Staff assigned to a service.
	 *
	 * @param int $service_id Service ID.
	 * @return int[]
	 */
	public static function staff_ids( $service_id ) {
		global $wpdb;

		$table = QVB_Database::table( 'staff_services' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT staff_id FROM {$table} WHERE service_id = %d", absint( $service_id ) ) );

		return array_map( 'absint', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Generates a unique slug.
	 *
	 * @param string $slug      Desired slug.
	 * @param int    $ignore_id ID to ignore.
	 * @return string
	 */
	public static function unique_slug( $slug, $ignore_id = 0 ) {
		global $wpdb;

		$table = QVB_Database::table( 'services' );
		$base  = sanitize_title( $slug );
		$base  = $base ? $base : 'service';
		$slug  = $base;
		$i     = 2;

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1", $slug, absint( $ignore_id ) ) );

			if ( ! $exists ) {
				return $slug;
			}

			$slug = $base . '-' . $i;
			$i++;

			if ( $i > 100 ) {
				return $base . '-' . wp_generate_password( 4, false, false );
			}
		}
	}

	/**
	 * Select options.
	 *
	 * @param bool $include_none Include an empty option.
	 * @param bool $active_only  Only active services.
	 * @return array<int,string>
	 */
	public static function options( $include_none = true, $active_only = false ) {
		$options = array();

		if ( $include_none ) {
			$options[0] = __( 'All services', 'qevora-booking' );
		}

		$services = $active_only ? self::active() : self::all();

		foreach ( $services as $service ) {
			$options[ (int) $service->id ] = $service->name;
		}

		return $options;
	}

	/**
	 * Total service count.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . QVB_Database::table( 'services' ) );
	}

	/**
	 * Image URL for a service.
	 *
	 * @param object $service Service row.
	 * @param string $size    Image size.
	 * @return string
	 */
	public static function image_url( $service, $size = 'medium' ) {
		if ( empty( $service->image_id ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( (int) $service->image_id, $size );

		return $url ? $url : '';
	}
}
