<?php
/**
 * Service categories.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Categories
 */
final class QVB_Categories {

	/**
	 * Returns categories.
	 *
	 * @param array $args status, search.
	 * @return object[]
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$table  = QVB_Database::table( 'categories' );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(name LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort_order ASC, name ASC';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Active categories.
	 *
	 * @return object[]
	 */
	public static function active() {
		return self::all( array( 'status' => 'active' ) );
	}

	/**
	 * Gets a category.
	 *
	 * @param int $id Category ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = QVB_Database::table( 'categories' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Creates or updates a category.
	 *
	 * @param array $data Category data.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;

		$id          = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$name        = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$description = isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '';
		$image_id    = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
		$status      = isset( $data['status'] ) && 'inactive' === $data['status'] ? 'inactive' : 'active';
		$sort_order  = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;

		if ( '' === $name ) {
			return new WP_Error( 'qvb_category_name', __( 'Please enter a category name.', 'qevora-booking' ) );
		}

		$name = mb_substr( $name, 0, 191 );
		$slug = isset( $data['slug'] ) && '' !== trim( (string) $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
		$slug = self::unique_slug( $slug ? $slug : 'category', $id );

		$now = QVB_Utils::now_sql();
		$row = array(
			'name'        => $name,
			'slug'        => $slug,
			'description' => $description,
			'image_id'    => $image_id,
			'status'      => $status,
			'sort_order'  => $sort_order,
			'updated_at'  => $now,
		);

		$formats = array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' );

		if ( $id ) {
			if ( ! self::get( $id ) ) {
				return new WP_Error( 'qvb_category_missing', __( 'That category no longer exists.', 'qevora-booking' ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update( QVB_Database::table( 'categories' ), $row, array( 'id' => $id ), $formats, array( '%d' ) );

			if ( false === $updated ) {
				return new WP_Error( 'qvb_category_save', __( 'The category could not be saved. Please try again.', 'qevora-booking' ) );
			}
		} else {
			$row['created_at'] = $now;
			$formats[]         = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert( QVB_Database::table( 'categories' ), $row, $formats );

			if ( ! $inserted ) {
				return new WP_Error( 'qvb_category_save', __( 'The category could not be saved. Please try again.', 'qevora-booking' ) );
			}

			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a category is saved.
		 *
		 * @param int   $id   Category ID.
		 * @param array $data Saved data.
		 */
		do_action( 'qvb_category_saved', $id, $data );

		return $id;
	}

	/**
	 * Deletes a category and detaches its services.
	 *
	 * @param int $id Category ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id || ! self::get( $id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( QVB_Database::table( 'services' ), array( 'category_id' => 0 ), array( 'category_id' => $id ), array( '%d' ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( QVB_Database::table( 'categories' ), array( 'id' => $id ), array( '%d' ) );

		if ( $deleted ) {
			/**
			 * Fires after a category is deleted.
			 *
			 * @param int $id Category ID.
			 */
			do_action( 'qvb_category_deleted', $id );
		}

		return (bool) $deleted;
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

		$table = QVB_Database::table( 'categories' );
		$base  = sanitize_title( $slug );
		$base  = $base ? $base : 'category';
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
	 * Service counts keyed by category ID.
	 *
	 * @return array<int,int>
	 */
	public static function counts() {
		global $wpdb;

		$table = QVB_Database::table( 'services' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT category_id, COUNT(*) AS total FROM {$table} GROUP BY category_id", OBJECT_K );

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->category_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Select options.
	 *
	 * @param bool $include_none Include "Uncategorised".
	 * @return array<int,string>
	 */
	public static function options( $include_none = true ) {
		$options = array();

		if ( $include_none ) {
			$options[0] = __( 'Uncategorised', 'qevora-booking' );
		}

		foreach ( self::all() as $category ) {
			$options[ (int) $category->id ] = $category->name;
		}

		return $options;
	}
}
