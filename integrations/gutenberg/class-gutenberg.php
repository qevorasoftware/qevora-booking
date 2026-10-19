<?php
/**
 * Gutenberg block integration (no build step required).
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dynamic booking block.
 */
final class QVB_Gutenberg {

	/**
	 * Block name.
	 */
	const BLOCK = 'qevora/booking';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Registers the block from its metadata.
	 *
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			QVB_DIR . 'integrations/gutenberg',
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		$atts = array(
			'service'    => isset( $attributes['serviceId'] ) ? absint( $attributes['serviceId'] ) : 0,
			'staff'      => isset( $attributes['staffId'] ) ? absint( $attributes['staffId'] ) : 0,
			'title'      => isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : '',
			'show_title' => ! isset( $attributes['showTitle'] ) || $attributes['showTitle'] ? '1' : '0',
		);

		return QVB_Shortcodes::render( $atts );
	}
}
