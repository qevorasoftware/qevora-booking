<?php
/**
 * Deactivation routine.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation handler.
 */
final class QVB_Deactivator {

	/**
	 * Runs on deactivation. Data is never deleted here.
	 *
	 * @return void
	 */
	public static function deactivate() {
		QVB_Cron::unschedule();

		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_transient( 'qvb_activation_redirect_' . $user_id );
		}

		QVB_Availability::flush_cache();
		QVB_Settings::flush_cache();

		/**
		 * Fires after the plugin is deactivated.
		 */
		do_action( 'qvb_deactivated' );
	}
}
