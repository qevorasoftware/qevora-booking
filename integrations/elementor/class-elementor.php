<?php
/**
 * Elementor integration. Loaded only when Elementor is active.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the Elementor widget.
 */
final class QVB_Elementor {

	/**
	 * Registers the Elementor hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( did_action( 'elementor/loaded' ) ) {
			self::load();
			return;
		}

		add_action( 'elementor/loaded', array( __CLASS__, 'load' ) );
	}

	/**
	 * Loads widget files and hooks.
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once QVB_DIR . 'integrations/elementor/class-elementor-widget.php';

		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Adds the Qevora widget category.
	 *
	 * @param \Elementor\Elements_Manager $manager Elementor elements manager.
	 * @return void
	 */
	public static function register_category( $manager ) {
		if ( ! method_exists( $manager, 'add_category' ) ) {
			return;
		}

		$manager->add_category(
			'qevora',
			array(
				'title' => __( 'Qevora Booking', 'qevora-booking' ),
				'icon'  => 'eicon-calendar',
			)
		);
	}

	/**
	 * Registers the booking widget.
	 *
	 * @param \Elementor\Widgets_Manager $manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widget( $manager ) {
		if ( ! method_exists( $manager, 'register' ) || ! class_exists( 'QVB_Elementor_Widget' ) ) {
			return;
		}

		$manager->register( new QVB_Elementor_Widget() );
	}
}
