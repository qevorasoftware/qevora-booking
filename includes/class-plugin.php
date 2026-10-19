<?php
/**
 * Plugin bootstrap.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
final class QVB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var QVB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return QVB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers the boot hook only.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_filter( 'plugin_action_links_' . QVB_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'qevora-booking', false, dirname( QVB_BASENAME ) . '/languages' );
	}

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public function boot() {
		QVB_Database::maybe_upgrade();

		QVB_Cron::init();
		QVB_REST_API::init();
		QVB_Privacy::init();
		QVB_Shortcodes::init();
		QVB_Notifications::init();
		QVB_Gutenberg::init();
		QVB_Elementor::init();

		if ( is_admin() ) {
			require_once QVB_DIR . 'admin/class-admin.php';
			QVB_Admin::init();
		}

		require_once QVB_DIR . 'public/class-public.php';
		QVB_Public::init();

		/**
		 * Fires once Qevora Booking has loaded all of its modules.
		 *
		 * @param QVB_Plugin $plugin Plugin instance.
		 */
		do_action( 'qvb_loaded', $this );
	}

	/**
	 * Adds quick links on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		if ( current_user_can( QVB_Capabilities::MANAGE ) ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=qevora-booking-settings' ) ),
				esc_html__( 'Settings', 'qevora-booking' )
			);
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=qevora-booking-help' ) ),
				esc_html__( 'Help', 'qevora-booking' )
			);
		}
		return $links;
	}
}
