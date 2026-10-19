<?php
/**
 * Daily maintenance.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron service.
 */
final class QVB_Cron {

	/**
	 * Hook name.
	 */
	const HOOK = 'qvb_daily_maintenance';

	/**
	 * Registers hooks and makes sure the event exists.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedules the daily event.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Clears the scheduled event.
	 *
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Runs maintenance.
	 *
	 * @return void
	 */
	public static function run() {
		$days    = (int) QVB_Settings::get( 'retention_days' );
		$deleted = QVB_Bookings::purge_old( $days );

		/**
		 * Fires after daily maintenance finishes.
		 *
		 * @param int $deleted Number of purged bookings.
		 */
		do_action( 'qvb_daily_maintenance_complete', $deleted );

		update_option( 'qvb_last_maintenance', time(), false );
	}
}
