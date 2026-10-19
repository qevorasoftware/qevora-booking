<?php
/**
 * Database schema and migrations.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Database
 */
final class QVB_Database {

	/**
	 * Schema version option.
	 */
	const VERSION_OPTION = 'qvb_db_version';

	/**
	 * Table names (without prefix).
	 *
	 * @var string[]
	 */
	private static $tables = array(
		'categories',
		'services',
		'staff',
		'staff_services',
		'availability',
		'exceptions',
		'customers',
		'bookings',
	);

	/**
	 * Full table name for a plugin table.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'qvb_' . sanitize_key( $name );
	}

	/**
	 * Creates or updates all tables. Safe to run repeatedly.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = 'CREATE TABLE ' . self::table( 'categories' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			description text NULL,
			image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'services' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			description longtext NULL,
			image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			duration smallint(5) unsigned NOT NULL DEFAULT 60,
			buffer_time smallint(5) unsigned NOT NULL DEFAULT 0,
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			status varchar(20) NOT NULL DEFAULT 'active',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY category_id (category_id),
			KEY status (status)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'staff' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL,
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			job_title varchar(191) NOT NULL DEFAULT '',
			bio text NULL,
			image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			color varchar(7) NOT NULL DEFAULT '#7c3aed',
			use_business_hours tinyint(1) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'active',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY user_id (user_id)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'staff_services' ) . " (
			staff_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (staff_id,service_id),
			KEY service_id (service_id)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'availability' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL,
			day_of_week tinyint(2) unsigned NOT NULL DEFAULT 0,
			start_time varchar(5) NOT NULL DEFAULT '09:00',
			end_time varchar(5) NOT NULL DEFAULT '18:00',
			break_start varchar(5) NOT NULL DEFAULT '',
			break_end varchar(5) NOT NULL DEFAULT '',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY staff_day (staff_id,day_of_week)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'exceptions' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) unsigned NOT NULL DEFAULT 0,
			from_date varchar(10) NOT NULL,
			to_date varchar(10) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'closed',
			start_time varchar(5) NOT NULL DEFAULT '',
			end_time varchar(5) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY staff_dates (staff_id,from_date,to_date),
			KEY date_range (from_date,to_date)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'customers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			email varchar(191) NOT NULL,
			phone varchar(40) NOT NULL DEFAULT '',
			notes text NULL,
			wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			total_bookings int(11) NOT NULL DEFAULT 0,
			last_booking_at datetime DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY phone (phone)
		) $charset_collate;";

		$sql[] = 'CREATE TABLE ' . self::table( 'bookings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(32) NOT NULL,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			service_id bigint(20) unsigned NOT NULL DEFAULT 0,
			staff_id bigint(20) unsigned NOT NULL DEFAULT 0,
			booking_date varchar(10) NOT NULL,
			start_time varchar(5) NOT NULL,
			end_time varchar(5) NOT NULL,
			buffer_end_time varchar(5) NOT NULL DEFAULT '',
			duration smallint(5) unsigned NOT NULL DEFAULT 0,
			buffer_time smallint(5) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT '',
			service_name varchar(191) NOT NULL DEFAULT '',
			staff_name varchar(191) NOT NULL DEFAULT '',
			customer_name varchar(191) NOT NULL DEFAULT '',
			customer_email varchar(191) NOT NULL DEFAULT '',
			customer_phone varchar(40) NOT NULL DEFAULT '',
			notes text NULL,
			admin_notes text NULL,
			source varchar(20) NOT NULL DEFAULT 'frontend',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_hash varchar(64) NOT NULL DEFAULT '',
			reschedule_count smallint(5) unsigned NOT NULL DEFAULT 0,
			cancelled_at datetime DEFAULT NULL,
			cancelled_by bigint(20) unsigned NOT NULL DEFAULT 0,
			cancellation_reason varchar(255) NOT NULL DEFAULT '',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY booking_date (booking_date),
			KEY staff_date (staff_id,booking_date),
			KEY status (status),
			KEY customer_id (customer_id),
			KEY service_id (service_id),
			KEY customer_email (customer_email)
		) $charset_collate;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::VERSION_OPTION, QVB_DB_VERSION, false );
	}

	/**
	 * Runs migrations when the stored schema version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '' );

		if ( ! $installed || version_compare( (string) $installed, QVB_DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Drops all plugin tables.
	 *
	 * Used by uninstall when the site opted in to data deletion.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		foreach ( self::$tables as $table ) {
			$name = self::table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is built from a whitelist.
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $name );
		}
	}

	/**
	 * Whether a plugin table exists.
	 *
	 * @param string $name Short table name.
	 * @return bool
	 */
	public static function table_exists( $name ) {
		global $wpdb;

		$table = self::table( $name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return (string) $found === $table;
	}

	/**
	 * Status of every table.
	 *
	 * @return array<string,bool>
	 */
	public static function status() {
		$status = array();

		foreach ( self::$tables as $name ) {
			$status[ $name ] = self::table_exists( $name );
		}

		return $status;
	}

	/**
	 * Row counts used on the system status screen.
	 *
	 * @return array<string,int>
	 */
	public static function row_counts() {
		global $wpdb;

		$counts = array();

		foreach ( self::$tables as $name ) {
			if ( ! self::table_exists( $name ) ) {
				$counts[ $name ] = 0;
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$counts[ $name ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( $name ) );
		}

		return $counts;
	}
}
