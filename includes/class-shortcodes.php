<?php
/**
 * Shortcodes.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode registration and rendering.
 */
final class QVB_Shortcodes {

	/**
	 * Registers shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'qevora_booking', array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders the booking form or the manage-booking view.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'service'    => 0,
				'staff'      => 0,
				'category'   => 0,
				'title'      => '',
				'show_title' => '1',
				'class'      => '',
			),
			$atts,
			'qevora_booking'
		);

		$atts['service']    = absint( $atts['service'] );
		$atts['staff']      = absint( $atts['staff'] );
		$atts['category']   = absint( $atts['category'] );
		$atts['title']      = sanitize_text_field( $atts['title'] );
		$atts['show_title'] = QVB_Utils::bool_int( $atts['show_title'] );
		$atts['class']      = sanitize_html_class( $atts['class'] );

		QVB_Public::enqueue();

		if ( isset( $_GET['qvb_booking'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed token is verified below.
			$reference = sanitize_text_field( wp_unslash( $_GET['qvb_booking'] ) );
			$token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
			return QVB_Public::render_manage( $reference, $token );
		}

		$services = QVB_Services::active( $atts['category'] );

		if ( $atts['staff'] ) {
			$service_ids = QVB_Staff::service_ids( $atts['staff'] );
			$services    = array_values(
				array_filter(
					$services,
					static function ( $service ) use ( $service_ids ) {
						return in_array( (int) $service->id, $service_ids, true );
					}
				)
			);
		}

		if ( ! $services ) {
			return self::notice( __( 'No services are available to book right now. Please check back soon.', 'qevora-booking' ) );
		}

		$staff = QVB_Staff::active();

		if ( ! $staff ) {
			return self::notice( __( 'Online booking is temporarily unavailable. Please call or email us to arrange an appointment.', 'qevora-booking' ) );
		}

		$selected_service = 0;
		if ( $atts['service'] ) {
			foreach ( $services as $service ) {
				if ( (int) $service->id === $atts['service'] ) {
					$selected_service = (int) $service->id;
					break;
				}
			}
		}

		if ( ! $selected_service && 1 === count( $services ) ) {
			$selected_service = (int) $services[0]->id;
		}

		$selected_staff = 0;
		if ( $atts['staff'] ) {
			foreach ( $staff as $member ) {
				if ( (int) $member->id === $atts['staff'] ) {
					$selected_staff = (int) $member->id;
					break;
				}
			}
		}

		$groups = self::group_services( $services );

		$args = array(
			'instance'          => wp_unique_id( 'qvb-' ),
			'groups'            => $groups,
			'staff'             => $staff,
			'selected_service'  => $selected_service,
			'selected_staff'    => $selected_staff,
			'title'             => $atts['title'],
			'show_title'        => $atts['show_title'],
			'extra_class'       => $atts['class'],
			'min_date'          => QVB_Utils::today(),
			'max_date'          => QVB_Utils::now()->modify( '+' . max( 1, (int) QVB_Settings::get( 'max_advance' ) ) . ' days' )->format( 'Y-m-d' ),
			'form_token'        => QVB_Security::form_token(),
			'approval_manual'   => QVB_Settings::requires_approval(),
			'require_phone'     => (bool) QVB_Settings::get( 'require_phone' ),
			'require_consent'   => (bool) QVB_Settings::get( 'require_consent' ),
			'consent_text'      => (string) QVB_Settings::get( 'consent_text' ),
			'policy'            => (string) QVB_Settings::get( 'cancellation_policy' ),
			'business_name'     => (string) QVB_Settings::get( 'business_name' ),
			'business_phone'    => (string) QVB_Settings::get( 'business_phone' ),
			'business_email'    => (string) QVB_Settings::get( 'business_email' ),
		);

		return QVB_Public::template( 'booking-form', $args );
	}

	/**
	 * Groups active services by category.
	 *
	 * @param object[] $services Services.
	 * @return array
	 */
	private static function group_services( $services ) {
		$categories = array();
		foreach ( QVB_Categories::all() as $category ) {
			$categories[ (int) $category->id ] = $category->name;
		}

		$groups = array();

		foreach ( $services as $service ) {
			$category_id = (int) $service->category_id;
			if ( ! isset( $groups[ $category_id ] ) ) {
				$groups[ $category_id ] = array(
					'id'       => $category_id,
					'name'     => isset( $categories[ $category_id ] ) ? $categories[ $category_id ] : __( 'Treatments', 'qevora-booking' ),
					'services' => array(),
				);
			}
			$groups[ $category_id ]['services'][] = $service;
		}

		return array_values( $groups );
	}

	/**
	 * Small front-end notice.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function notice( $message ) {
		return '<div class="qvb-app qvb-app--notice"><div class="qvb-notice" role="status">' . esc_html( $message ) . '</div></div>';
	}
}
