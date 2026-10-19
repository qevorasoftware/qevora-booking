<?php
/**
 * Front-end assets and view helpers.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public-facing integration.
 */
final class QVB_Public {

	/**
	 * Whether handles were registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 5 );
	}

	/**
	 * Registers style and script handles.
	 *
	 * @return void
	 */
	public static function register_assets() {
		if ( self::$registered ) {
			return;
		}

		wp_register_style(
			'qvb-public',
			QVB_URL . 'public/css/public.css',
			array(),
			QVB_VERSION
		);

		wp_register_style(
			'qvb-public-glass',
			QVB_URL . 'public/css/glass.css',
			array( 'qvb-public' ),
			QVB_VERSION
		);

		wp_register_script(
			'qvb-public',
			QVB_URL . 'public/js/public.js',
			array(),
			QVB_VERSION,
			true
		);

		wp_localize_script( 'qvb-public', 'qvbData', self::script_data() );
		wp_script_add_data( 'qvb-public', 'strategy', 'defer' );

		self::$registered = true;
	}

	/**
	 * Enqueues assets. Safe to call from shortcode/block render.
	 *
	 * @return void
	 */
	public static function enqueue() {
		self::register_assets();
		wp_enqueue_style( 'qvb-public' );
		wp_enqueue_style( 'qvb-public-glass' );
		wp_enqueue_script( 'qvb-public' );
	}

	/**
	 * Auto-enqueues on pages containing the shortcode or block.
	 *
	 * @return void
	 */
	public static function maybe_enqueue() {
		if ( isset( $_GET['qvb_booking'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::enqueue();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		$has_shortcode = has_shortcode( (string) $post->post_content, 'qevora_booking' );
		$has_block     = function_exists( 'has_block' ) && has_block( 'qevora/booking', $post );

		if ( $has_shortcode || $has_block ) {
			self::enqueue();
		}
	}

	/**
	 * Localised script configuration.
	 *
	 * @return array
	 */
	private static function script_data() {
		return array(
			'restUrl'   => esc_url_raw( rest_url( 'qevora/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'today'     => QVB_Utils::today(),
			'isAdmin'   => current_user_can( QVB_Capabilities::MANAGE ) ? 1 : 0,
			'strings'   => array(
				'selectService'     => __( 'Please select a service to continue.', 'qevora-booking' ),
				'selectDate'        => __( 'Please choose a date.', 'qevora-booking' ),
				'selectTime'        => __( 'Please choose a time slot.', 'qevora-booking' ),
				'nameRequired'      => __( 'Please enter your name.', 'qevora-booking' ),
				'emailRequired'     => __( 'Please enter a valid email address.', 'qevora-booking' ),
				'phoneRequired'     => __( 'Please enter your phone number.', 'qevora-booking' ),
				'consentRequired'   => __( 'Please accept the cancellation policy.', 'qevora-booking' ),
				'loading'           => __( 'Checking availability…', 'qevora-booking' ),
				'loadingSlots'      => __( 'Loading time slots…', 'qevora-booking' ),
				'noSlots'           => __( 'No times are available on this date. Please try another day.', 'qevora-booking' ),
				'noDates'           => __( 'No availability in the next two weeks. Please contact us directly.', 'qevora-booking' ),
				'loadError'         => __( 'We could not load availability. Please check your connection and try again.', 'qevora-booking' ),
				'submitError'       => __( 'We could not complete your booking. Please try again.', 'qevora-booking' ),
				'submitting'        => __( 'Booking…', 'qevora-booking' ),
				'chooseDate'        => __( 'Choose a date to see available times.', 'qevora-booking' ),
				'chooseServiceFirst' => __( 'Choose a service and date to see available times.', 'qevora-booking' ),
				'morning'           => __( 'Morning', 'qevora-booking' ),
				'afternoon'         => __( 'Afternoon', 'qevora-booking' ),
				'evening'           => __( 'Evening', 'qevora-booking' ),
				'nextAvailable'     => __( 'Next available dates', 'qevora-booking' ),
				'slotsAvailable'    => __( 'times available', 'qevora-booking' ),
				'available'         => __( 'available', 'qevora-booking' ),
				'slotTaken'         => __( 'That slot was just taken. Please choose another time.', 'qevora-booking' ),
				'cancelConfirm'     => __( 'Cancel this booking? This cannot be undone.', 'qevora-booking' ),
				'rescheduleConfirm' => __( 'Move your appointment to this time?', 'qevora-booking' ),
				'cancelled'         => __( 'Your booking has been cancelled.', 'qevora-booking' ),
				'rescheduled'       => __( 'Your booking has been rescheduled.', 'qevora-booking' ),
				'cancelReason'      => __( 'Reason (optional)', 'qevora-booking' ),
				'somethingWrong'    => __( 'Something went wrong. Please try again.', 'qevora-booking' ),
				'back'              => __( 'Back', 'qevora-booking' ),
				'continue'          => __( 'Continue', 'qevora-booking' ),
				'step'              => __( 'Step', 'qevora-booking' ),
				'of'                => __( 'of', 'qevora-booking' ),
				'anyStaff'          => __( 'Any available staff', 'qevora-booking' ),
				'selected'          => __( 'Selected', 'qevora-booking' ),
				'summary'           => __( 'Booking summary', 'qevora-booking' ),
				'date'              => __( 'Date', 'qevora-booking' ),
				'time'              => __( 'Time', 'qevora-booking' ),
				'service'           => __( 'Service', 'qevora-booking' ),
				'staff'             => __( 'Staff', 'qevora-booking' ),
				'price'             => __( 'Price', 'qevora-booking' ),
				'duration'          => __( 'Duration', 'qevora-booking' ),
				'receivedTitle'     => __( 'Booking received', 'qevora-booking' ),
				'confirmedTitle'    => __( 'Booking confirmed', 'qevora-booking' ),
				'reloadPage'        => __( 'Your session expired. Please reload the page.', 'qevora-booking' ),
			),
		);
	}

	/**
	 * Renders a public template.
	 *
	 * @param string $name Template name without extension.
	 * @param array  $args Variables exposed to the template.
	 * @return string
	 */
	public static function template( $name, $args = array() ) {
		$name = preg_replace( '/[^a-z0-9\-]/', '', (string) $name );
		$file = QVB_DIR . 'public/templates/' . $name . '.php';

		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- isolated template scope.
		extract( $args, EXTR_SKIP );
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Renders the manage-booking view.
	 *
	 * @param string $reference Booking reference.
	 * @param string $token     Signed token.
	 * @return string
	 */
	public static function render_manage( $reference, $token ) {
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		self::enqueue();

		$booking = QVB_Bookings::get_by_reference( $reference );
		$valid   = $booking && QVB_Bookings::verify_token( $booking, $token );

		if ( ! $valid ) {
			return self::template(
				'manage-invalid',
				array(
					'business_name'  => (string) QVB_Settings::get( 'business_name' ),
					'business_phone' => (string) QVB_Settings::get( 'business_phone' ),
					'business_email' => (string) QVB_Settings::get( 'business_email' ),
				)
			);
		}

		$can_cancel     = QVB_Bookings::can_cancel( $booking, 'customer' );
		$can_reschedule = QVB_Bookings::can_reschedule( $booking, 'customer' );
		$active_status  = in_array( $booking->status, array( 'pending', 'confirmed' ), true );

		$args = array(
			'booking'            => $booking,
			'token'              => $token,
			'can_cancel'         => ! is_wp_error( $can_cancel ),
			'can_reschedule'     => ! is_wp_error( $can_reschedule ),
			'cancel_error'       => $active_status && is_wp_error( $can_cancel ) ? $can_cancel->get_error_message() : '',
			'reschedule_error'   => $active_status && is_wp_error( $can_reschedule ) ? $can_reschedule->get_error_message() : '',
			'policy'             => (string) QVB_Settings::get( 'cancellation_policy' ),
			'cancel_limit_hours' => (int) QVB_Settings::get( 'cancel_limit_hours' ),
			'reschedule_limit_hours' => (int) QVB_Settings::get( 'reschedule_limit_hours' ),
			'business_name'      => (string) QVB_Settings::get( 'business_name' ),
			'business_phone'     => (string) QVB_Settings::get( 'business_phone' ),
			'business_email'     => (string) QVB_Settings::get( 'business_email' ),
			'max_date'           => QVB_Utils::now()->modify( '+' . max( 1, (int) QVB_Settings::get( 'max_advance' ) ) . ' days' )->format( 'Y-m-d' ),
			'min_date'           => QVB_Utils::today(),
		);

		return self::template( 'manage-booking', $args );
	}
}
