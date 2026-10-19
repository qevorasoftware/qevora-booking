<?php
/**
 * REST API.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class QVB_REST_API {

	/**
	 * Namespace.
	 */
	const NAMESPACE_V1 = 'qevora/v1';

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers all routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_services' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'category_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/staff',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_staff' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/availability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'staff_id'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'date'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'exclude'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'reference'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'token'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/availability/days',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_availability_days' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'staff_id'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'start_date' => array(
						'type'    => 'string',
						'default' => '',
					),
					'days'       => array(
						'type'    => 'integer',
						'default' => 14,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_booking' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/booking',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_booking' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'reference' => array(
						'type'     => 'string',
						'required' => true,
					),
					'token'     => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/booking/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'cancel_booking' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/booking/reschedule',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'reschedule_booking' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/admin/bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'admin_get_bookings' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'from'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'to'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'staff_id'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'service_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'status'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/admin/bookings/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'admin_update_status' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'status' => array(
						'type'     => 'string',
						'required' => true,
					),
					'reason' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/admin/bookings/(?P<id>\d+)/reschedule',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'admin_reschedule' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'date'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'time'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'staff_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Admin route capability check.
	 *
	 * @return bool
	 */
	public static function admin_permission() {
		return current_user_can( QVB_Capabilities::MANAGE );
	}

	/**
	 * GET /services.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_services( $request ) {
		$category_id = absint( $request->get_param( 'category_id' ) );
		$services    = QVB_Services::active( $category_id );
		$categories  = array();

		foreach ( QVB_Categories::all() as $category ) {
			$categories[ (int) $category->id ] = $category->name;
		}

		$items = array();
		foreach ( $services as $service ) {
			$items[] = array(
				'id'           => (int) $service->id,
				'name'         => $service->name,
				'slug'         => $service->slug,
				'description'  => wp_kses_post( $service->description ),
				'category_id'  => (int) $service->category_id,
				'category'     => isset( $categories[ (int) $service->category_id ] ) ? $categories[ (int) $service->category_id ] : '',
				'duration'     => (int) $service->duration,
				'buffer_time'  => (int) $service->buffer_time,
				'price'        => (float) $service->price,
				'price_html'   => QVB_Utils::money( $service->price ),
				'image'        => $service->image_id ? wp_get_attachment_image_url( (int) $service->image_id, 'medium' ) : '',
				'staff_ids'    => QVB_Services::staff_ids( (int) $service->id ),
			);
		}

		return new WP_REST_Response( array( 'services' => $items ), 200 );
	}

	/**
	 * GET /staff.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_staff( $request ) {
		$service_id = absint( $request->get_param( 'service_id' ) );
		$staff      = QVB_Staff::active( $service_id );

		$items = array();
		foreach ( $staff as $member ) {
			$items[] = array(
				'id'          => (int) $member->id,
				'name'        => $member->name,
				'job_title'   => $member->job_title,
				'bio'         => $member->bio,
				'color'       => QVB_Utils::hex_color( $member->color ),
				'image'       => $member->image_id ? wp_get_attachment_image_url( (int) $member->image_id, 'medium' ) : '',
				'service_ids' => QVB_Staff::service_ids( (int) $member->id ),
			);
		}

		return new WP_REST_Response( array( 'staff' => $items ), 200 );
	}

	/**
	 * GET /availability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_availability( $request ) {
		$service_id = absint( $request->get_param( 'service_id' ) );
		$staff_id   = absint( $request->get_param( 'staff_id' ) );
		$date       = QVB_Utils::sanitize_date( $request->get_param( 'date' ) );
		$service    = QVB_Services::get( $service_id );

		if ( ! $service || 'active' !== $service->status ) {
			return new WP_Error( 'qvb_service_unavailable', __( 'That service is not available.', 'qevora-booking' ), array( 'status' => 404 ) );
		}

		if ( ! $date ) {
			return new WP_Error( 'qvb_invalid_date', __( 'Please choose a valid date.', 'qevora-booking' ), array( 'status' => 400 ) );
		}

		if ( $staff_id && ! QVB_Staff::does_service( $staff_id, $service_id ) ) {
			return new WP_Error( 'qvb_staff_unavailable', __( 'That staff member does not offer this service.', 'qevora-booking' ), array( 'status' => 400 ) );
		}

		$args = array();
		$exclude = absint( $request->get_param( 'exclude' ) );
		if ( $exclude ) {
			$booking = QVB_Bookings::get( $exclude );
			$token   = (string) $request->get_param( 'token' );
			$allowed = $booking && (int) $booking->service_id === $service_id
				&& ( current_user_can( QVB_Capabilities::MANAGE ) || QVB_Bookings::verify_token( $booking, $token ) );
			if ( ! $allowed ) {
				return new WP_Error( 'qvb_invalid_token', __( 'This booking link is not valid.', 'qevora-booking' ), array( 'status' => 403 ) );
			}
			$args['exclude_booking_id'] = $exclude;
		}

		if ( current_user_can( QVB_Capabilities::MANAGE ) ) {
			$args['admin'] = true;
		}

		$slots = QVB_Availability::get_slots( $service_id, $staff_id, $date, $args );

		$out = array();
		foreach ( $slots as $slot ) {
			$out[] = array(
				'time'         => $slot['time'],
				'label'        => $slot['label'],
				'staff_count'  => count( $slot['staff_ids'] ),
			);
		}

		return new WP_REST_Response(
			array(
				'date'     => $date,
				'service'  => array(
					'id'       => (int) $service->id,
					'name'     => $service->name,
					'duration' => (int) $service->duration,
					'price'    => (float) $service->price,
				),
				'slots'    => $out,
			),
			200
		);
	}

	/**
	 * GET /availability/days.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_availability_days( $request ) {
		$service_id = absint( $request->get_param( 'service_id' ) );
		$staff_id   = absint( $request->get_param( 'staff_id' ) );
		$start      = QVB_Utils::sanitize_date( (string) $request->get_param( 'start_date' ) );
		$days       = absint( $request->get_param( 'days' ) );
		$service    = QVB_Services::get( $service_id );

		if ( ! $service || 'active' !== $service->status ) {
			return new WP_Error( 'qvb_service_unavailable', __( 'That service is not available.', 'qevora-booking' ), array( 'status' => 404 ) );
		}

		if ( ! $start ) {
			$start = QVB_Utils::today();
		}

		$days = $days ? min( 31, $days ) : 14;
		$list = QVB_Availability::get_date_availability(
			$service_id,
			$staff_id,
			$start,
			$days,
			current_user_can( QVB_Capabilities::MANAGE )
		);

		return new WP_REST_Response( array( 'days' => $list ), 200 );
	}

	/**
	 * POST /bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_booking( $request ) {
		$nonce = QVB_Security::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$payload = self::payload( $request );

		$spam = QVB_Security::spam_check( $payload );
		if ( is_wp_error( $spam ) ) {
			return $spam;
		}

		$context = current_user_can( QVB_Capabilities::MANAGE ) ? 'admin' : 'frontend';

		$result = QVB_Bookings::create( $payload, $context );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = array(
			'reference'  => $result['reference'],
			'status'     => $result['status'],
			'status_label' => QVB_Utils::status_label( $result['status'] ),
			'booking'    => self::summary( $result['booking'] ),
			'manage_url' => $result['manage_url'],
			'message'    => 'pending' === $result['status']
				? __( 'Your booking request has been received. We will confirm it by email shortly.', 'qevora-booking' )
				: __( 'Your appointment is confirmed. A confirmation email is on its way.', 'qevora-booking' ),
		);

		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * GET /booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_booking( $request ) {
		$booking = self::authorized_booking( $request );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$limit = QVB_Security::lookup_rate_limit();
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}

		return new WP_REST_Response(
			array(
				'booking' => self::summary( $booking, true ),
				'policy'  => array(
					'allow_cancel'     => QVB_Settings::get( 'allow_cancel' ),
					'allow_reschedule' => QVB_Settings::get( 'allow_reschedule' ),
					'cancel_limit'     => (int) QVB_Settings::get( 'cancel_limit_hours' ),
					'reschedule_limit' => (int) QVB_Settings::get( 'reschedule_limit_hours' ),
					'cancellation_policy' => (string) QVB_Settings::get( 'cancellation_policy' ),
				),
			),
			200
		);
	}

	/**
	 * POST /booking/cancel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cancel_booking( $request ) {
		$nonce = QVB_Security::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$limit = QVB_Security::lookup_rate_limit();
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}

		$booking = self::authorized_booking( $request );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$reason = sanitize_text_field( (string) $request->get_param( 'reason' ) );

		$result = QVB_Bookings::cancel(
			(int) $booking->id,
			array(
				'context' => 'customer',
				'reason'  => $reason,
				'user_id' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'booking' => self::summary( $result, true ),
				'message' => __( 'Your booking has been cancelled.', 'qevora-booking' ),
			),
			200
		);
	}

	/**
	 * POST /booking/reschedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reschedule_booking( $request ) {
		$nonce = QVB_Security::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$limit = QVB_Security::lookup_rate_limit();
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}

		$booking = self::authorized_booking( $request );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$date = QVB_Utils::sanitize_date( (string) $request->get_param( 'date' ) );
		$time = QVB_Utils::sanitize_time( (string) $request->get_param( 'time' ) );

		if ( ! $date || ! $time ) {
			return new WP_Error( 'qvb_invalid_datetime', __( 'Please choose a valid date and time.', 'qevora-booking' ), array( 'status' => 400 ) );
		}

		$result = QVB_Bookings::reschedule(
			(int) $booking->id,
			$date,
			$time,
			(int) $booking->staff_id,
			array( 'context' => 'customer' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'booking' => self::summary( $result, true ),
				'message' => __( 'Your booking has been rescheduled.', 'qevora-booking' ),
			),
			200
		);
	}

	/**
	 * GET /admin/bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function admin_get_bookings( $request ) {
		$from = QVB_Utils::sanitize_date( (string) $request->get_param( 'from' ) );
		$to   = QVB_Utils::sanitize_date( (string) $request->get_param( 'to' ) );

		$result = QVB_Bookings::query(
			array(
				'date_from'  => $from ? $from : QVB_Utils::today(),
				'date_to'    => $to ? $to : QVB_Utils::today(),
				'staff_id'   => absint( $request->get_param( 'staff_id' ) ),
				'service_id' => absint( $request->get_param( 'service_id' ) ),
				'status'     => (string) $request->get_param( 'status' ),
				'per_page'   => 200,
				'orderby'    => 'start_time',
				'order'      => 'ASC',
			)
		);

		$items = array();
		foreach ( $result['items'] as $booking ) {
			$items[] = self::admin_summary( $booking );
		}

		return new WP_REST_Response( array( 'bookings' => $items ), 200 );
	}

	/**
	 * POST /admin/bookings/{id}/status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function admin_update_status( $request ) {
		$result = QVB_Bookings::update_status(
			absint( $request['id'] ),
			sanitize_key( (string) $request->get_param( 'status' ) ),
			array(
				'context' => 'admin',
				'reason'  => (string) $request->get_param( 'reason' ),
				'user_id' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'booking' => self::admin_summary( $result ) ), 200 );
	}

	/**
	 * POST /admin/bookings/{id}/reschedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function admin_reschedule( $request ) {
		$result = QVB_Bookings::reschedule(
			absint( $request['id'] ),
			(string) $request->get_param( 'date' ),
			(string) $request->get_param( 'time' ),
			absint( $request->get_param( 'staff_id' ) ),
			array( 'context' => 'admin' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'booking' => self::admin_summary( $result ) ), 200 );
	}

	/**
	 * Resolves reference+token into a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return object|WP_Error
	 */
	private static function authorized_booking( $request ) {
		$reference = sanitize_text_field( (string) $request->get_param( 'reference' ) );
		$token     = (string) $request->get_param( 'token' );
		$booking   = QVB_Bookings::get_by_reference( $reference );

		if ( ! $booking || ! QVB_Bookings::verify_token( $booking, $token ) ) {
			return new WP_Error( 'qvb_invalid_booking', __( 'We could not find that booking. Please check the link in your confirmation email.', 'qevora-booking' ), array( 'status' => 404 ) );
		}

		return $booking;
	}

	/**
	 * Request payload merged from JSON/body and query.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function payload( $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		return array_merge( $request->get_params(), $payload );
	}

	/**
	 * Public booking summary.
	 *
	 * @param object|null $booking Booking.
	 * @param bool        $with_links Include manage URL.
	 * @return array
	 */
	public static function summary( $booking, $with_links = false ) {
		if ( ! $booking ) {
			return array();
		}

		$data = array(
			'id'             => (int) $booking->id,
			'reference'      => $booking->reference,
			'status'         => $booking->status,
			'status_label'   => QVB_Utils::status_label( $booking->status ),
			'service_id'     => (int) $booking->service_id,
			'service_name'   => $booking->service_name,
			'staff_id'       => (int) $booking->staff_id,
			'staff_name'     => $booking->staff_name,
			'date'           => $booking->booking_date,
			'date_label'     => QVB_Utils::format_date_weekday( $booking->booking_date ),
			'start_time'     => $booking->start_time,
			'end_time'       => $booking->end_time,
			'time_label'     => QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ),
			'duration'       => (int) $booking->duration,
			'duration_label' => QVB_Utils::duration_label( $booking->duration ),
			'price'          => (float) $booking->price,
			'price_html'     => QVB_Utils::money( $booking->price, $booking->currency ),
			'created_at'     => $booking->created_at,
			'can_cancel'     => ! is_wp_error( QVB_Bookings::can_cancel( $booking, 'customer' ) ),
			'can_reschedule' => ! is_wp_error( QVB_Bookings::can_reschedule( $booking, 'customer' ) ),
			'reschedule_count' => (int) $booking->reschedule_count,
		);

		if ( $with_links ) {
			$data['manage_url'] = QVB_Bookings::manage_url( $booking );
		}

		return $data;
	}

	/**
	 * Admin booking summary with capability-aware PII.
	 *
	 * @param object $booking Booking.
	 * @return array
	 */
	private static function admin_summary( $booking ) {
		$data = self::summary( $booking, false );

		if ( current_user_can( QVB_Capabilities::PII ) ) {
			$data['customer_name']  = $booking->customer_name;
			$data['customer_email'] = $booking->customer_email;
			$data['customer_phone'] = $booking->customer_phone;
		} else {
			$data['customer_name']  = $booking->customer_name;
			$data['customer_email'] = QVB_Utils::mask_email( $booking->customer_email );
			$data['customer_phone'] = QVB_Utils::mask_phone( $booking->customer_phone );
		}

		$data['service_id'] = (int) $booking->service_id;
		$data['staff_id']   = (int) $booking->staff_id;

		return $data;
	}
}
