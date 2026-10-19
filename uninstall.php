<?php
/**
 * Uninstall routine.
 *
 * Data is preserved by default. Tables, settings, templates, capabilities and
 * scheduled events are only removed when the site owner opted in via
 * Settings → Privacy & data → “Delete all plugin data when the plugin is deleted”.
 *
 * @package Qevora_Booking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-capabilities.php';

if ( ! function_exists( 'qvb_uninstall_site' ) ) {
	/**
	 * Removes Qevora Booking data for the current site.
	 *
	 * @return void
	 */
	function qvb_uninstall_site() {

		$settings = get_option( 'qvb_settings', array() );
		$purge    = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

		// Always remove scheduled work and caches; these are not customer data.
		wp_clear_scheduled_hook( 'qvb_daily_maintenance' );
		wp_clear_scheduled_hook( 'qvb_cleanup_rate_limits' );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_qvb_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_qvb_' ) . '%'
			)
		);

		if ( ! $purge ) {
			return;
		}

		QVB_Database::drop_tables();
		QVB_Capabilities::remove();

		$options = array(
			'qvb_settings',
			'qvb_emails',
			'qvb_db_version',
			'qvb_version',
			'qvb_installed_at',
			'qvb_last_maintenance',
			'qvb_activation_redirect',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Remove any remaining per-user plugin transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_qvb_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_qvb_' ) . '%'
			)
		);

		if ( is_multisite() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
					$wpdb->esc_like( 'qvb_' ) . '%'
				)
			);
		}
	}
}

if ( is_multisite() ) {
	$qvb_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $qvb_site_ids as $qvb_site_id ) {
		switch_to_blog( (int) $qvb_site_id );
		qvb_uninstall_site();
		restore_current_blog();
	}
} else {
	qvb_uninstall_site();
}
