<?php
/**
 * Capabilities.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Capabilities
 */
final class QVB_Capabilities {

	/**
	 * Manage bookings, services, staff, calendar and exceptions.
	 */
	const MANAGE = 'qvb_manage_bookings';

	/**
	 * Change plugin settings and email templates.
	 */
	const SETTINGS = 'qvb_manage_settings';

	/**
	 * View customer contact details (PII).
	 */
	const PII = 'qvb_view_customer_data';

	/**
	 * All capabilities.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::MANAGE, self::SETTINGS, self::PII );
	}

	/**
	 * Grants capabilities to administrators on activation.
	 *
	 * Developers can extend the list with the qvb_capabilities filter.
	 *
	 * @return void
	 */
	public static function add() {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Removes capabilities from all roles (used on uninstall).
	 *
	 * @return void
	 */
	public static function remove() {
		$roles = wp_roles();

		foreach ( $roles->roles as $role_name => $role_data ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::all() as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Current user can manage bookings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Current user can change settings.
	 *
	 * @return bool
	 */
	public static function can_manage_settings() {
		return current_user_can( self::SETTINGS );
	}

	/**
	 * Current user can see customer PII.
	 *
	 * @return bool
	 */
	public static function can_view_pii() {
		return current_user_can( self::PII );
	}
}
