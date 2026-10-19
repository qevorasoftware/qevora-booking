<?php
/**
 * Activation routine.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activation handler.
 */
final class QVB_Activator {

	/**
	 * Runs on activation (including network activation).
	 *
	 * @param bool $network_wide Whether the plugin is network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_site();
	}

	/**
	 * Installs schema, defaults, capabilities and cron for one site.
	 *
	 * @return void
	 */
	private static function activate_site() {
		QVB_Database::install();
		QVB_Settings::install_defaults();
		QVB_Capabilities::add();
		QVB_Cron::schedule();

		update_option( 'qvb_version', QVB_VERSION, false );

		if ( ! get_option( 'qvb_installed_at' ) ) {
			add_option( 'qvb_installed_at', current_time( 'mysql' ), '', false );
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			set_transient( 'qvb_activation_redirect_' . $user_id, time(), 60 );
		}

		/**
		 * Fires after the plugin is activated on a site.
		 */
		do_action( 'qvb_activated' );
	}
}
