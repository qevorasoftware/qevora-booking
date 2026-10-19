<?php
/**
 * Email notifications and templates.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Notifications
 */
final class QVB_Notifications {

	/**
	 * Email templates option.
	 */
	const OPTION = 'qvb_emails';

	/**
	 * Template cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'update_option_' . QVB_Settings::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'switch_theme', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Clears the template cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Builds the default templates.
	 *
	 * @return array<string,array>
	 */
	public static function default_templates() {
		$templates = array();

		$button = '<p style="margin:24px 0;"><a href="{manage_url}" style="background:{accent_color};color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;display:inline-block;font-weight:600;">' . esc_html__( 'Manage your booking', 'qevora-booking' ) . '</a></p>';

		$templates['customer_pending'] = array(
			'label'     => __( 'Customer: booking request received', 'qevora-booking' ),
			'recipient' => 'customer',
			'enabled'   => 1,
			'subject'   => __( 'We received your booking request ({reference})', 'qevora-booking' ),
			'body'      => '<p>' . __( 'Hi {customer_name},', 'qevora-booking' ) . '</p>'
				. '<p>' . __( 'Thank you for booking with <strong>{business_name}</strong>. Your request has been received and is currently <strong>pending confirmation</strong>.', 'qevora-booking' ) . '</p>'
				. '{booking_details}'
				. '<p>' . __( 'We will email you as soon as your appointment is confirmed. No payment is needed online — you can pay at the salon after your appointment.', 'qevora-booking' ) . '</p>'
				. $button
				. '<p style="color:#6b7280;font-size:13px;">{cancellation_policy}</p>',
		);

		$templates['customer_confirmed'] = array(
			'label'     => __( 'Customer: booking confirmed', 'qevora-booking' ),
			'recipient' => 'customer',
			'enabled'   => 1,
			'subject'   => __( 'Your booking is confirmed ({reference})', 'qevora-booking' ),
			'body'      => '<p>' . __( 'Hi {customer_name},', 'qevora-booking' ) . '</p>'
				. '<p>' . __( 'Good news — your appointment at <strong>{business_name}</strong> is confirmed. We look forward to seeing you!', 'qevora-booking' ) . '</p>'
				. '{booking_details}'
				. '<p>' . __( 'Please arrive a few minutes before your appointment. Payment is made at the salon.', 'qevora-booking' ) . '</p>'
				. $button
				. '<p style="color:#6b7280;font-size:13px;">{cancellation_policy}</p>',
		);

		$templates['customer_cancelled'] = array(
			'label'     => __( 'Customer: booking cancelled', 'qevora-booking' ),
			'recipient' => 'customer',
			'enabled'   => 1,
			'subject'   => __( 'Your booking has been cancelled ({reference})', 'qevora-booking' ),
			'body'      => '<p>' . __( 'Hi {customer_name},', 'qevora-booking' ) . '</p>'
				. '<p>' . __( 'Your booking at <strong>{business_name}</strong> has been cancelled.', 'qevora-booking' ) . '</p>'
				. '{booking_details}'
				. '<p><strong>' . __( 'Reason:', 'qevora-booking' ) . '</strong> {reason}</p>'
				. '<p>' . __( 'If you would like to book again, simply choose a new time on our website.', 'qevora-booking' ) . '</p>'
				. '<p style="margin:24px 0;"><a href="{site_url}" style="background:{accent_color};color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;display:inline-block;font-weight:600;">' . esc_html__( 'Book a new appointment', 'qevora-booking' ) . '</a></p>'
				. '<p style="color:#6b7280;font-size:13px;">{cancellation_policy}</p>',
		);

		$templates['customer_rescheduled'] = array(
			'label'     => __( 'Customer: booking rescheduled', 'qevora-booking' ),
			'recipient' => 'customer',
			'enabled'   => 1,
			'subject'   => __( 'Your booking has been rescheduled ({reference})', 'qevora-booking' ),
			'body'      => '<p>' . __( 'Hi {customer_name},', 'qevora-booking' ) . '</p>'
				. '<p>' . __( 'Your appointment at <strong>{business_name}</strong> has been moved to a new time.', 'qevora-booking' ) . '</p>'
				. '<p>' . __( 'Previous appointment:', 'qevora-booking' ) . ' <strong>{previous_date} at {previous_time}</strong></p>'
				. '{booking_details}'
				. '<p>' . __( 'No payment is needed online — please pay at the salon.', 'qevora-booking' ) . '</p>'
				. $button,
		);

		$templates['customer_status_changed'] = array(
			'label'     => __( 'Customer: booking status updated', 'qevora-booking' ),
			'recipient' => 'customer',
			'enabled'   => 1,
			'subject'   => __( 'Update on your booking ({reference})', 'qevora-booking' ),
			'body'      => '<p>' . __( 'Hi {customer_name},', 'qevora-booking' ) . '</p>'
				. '<p>' . __( 'There is an update on your booking at <strong>{business_name}</strong>. The new status is <strong>{status}</strong>.', 'qevora-booking' ) . '</p>'
				. '{booking_details}'
				. '<p>' . __( 'If you have any questions, please contact us.', 'qevora-booking' ) . '</p>'
				. $button,
		);

		$templates['admin_new_booking'] = array(
			'label'     => __( 'Staff: new booking', 'qevora-booking' ),
			'recipient' => 'admin',
			'enabled'   => 1,
			'subject'   => __( 'New booking: {customer_name} — {date} at {time}', 'qevora-booking' ),
			'body'      => '<p>' . __( 'A new booking has been created.', 'qevora-booking' ) . '</p>'
				. '{booking_details}'
				. '<p><strong>' . __( 'Customer:', 'qevora-booking' ) . '</strong> {customer_name}<br />'
				. '<strong>' . __( 'Email:', 'qevora-booking' ) . '</strong> {customer_email}<br />'
				. '<strong>' . __( 'Phone:', 'qevora-booking' ) . '</strong> {customer_phone}</p>'
				. '<p><strong>' . __( 'Customer notes:', 'qevora-booking' ) . '</strong><br />{notes}</p>'
				. '<p><a href="{admin_booking_url}" style="background:{accent_color};color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;display:inline-block;font-weight:600;">' . esc_html__( 'Open booking in WordPress', 'qevora-booking' ) . '</a></p>',
		);

		$templates['admin_booking_cancelled'] = array(
			'label'     => __( 'Staff: booking cancelled by customer', 'qevora-booking' ),
			'recipient' => 'admin',
			'enabled'   => 1,
			'subject'   => __( 'Booking cancelled: {customer_name} — {date} at {time}', 'qevora-booking' ),
			'body'      => '<p>' . __( 'A customer has cancelled their booking.', 'qevora-booking' ) . '</p>'
				. '{booking_details}'
				. '<p><strong>' . __( 'Reason:', 'qevora-booking' ) . '</strong> {reason}</p>'
				. '<p><a href="{admin_booking_url}">' . __( 'Open booking in WordPress', 'qevora-booking' ) . '</a></p>',
		);

		/**
		 * Filters the default email templates.
		 *
		 * @param array $templates Templates keyed by event.
		 */
		return apply_filters( 'qvb_default_email_templates', $templates );
	}

	/**
	 * All templates merged with saved overrides.
	 *
	 * @return array<string,array>
	 */
	public static function templates() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$defaults = self::default_templates();
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();

		foreach ( $defaults as $event => $template ) {
			if ( isset( $saved[ $event ] ) && is_array( $saved[ $event ] ) ) {
				$defaults[ $event ]['enabled'] = isset( $saved[ $event ]['enabled'] ) ? ( QVB_Utils::to_bool( $saved[ $event ]['enabled'] ) ? 1 : 0 ) : $template['enabled'];
				$defaults[ $event ]['subject'] = isset( $saved[ $event ]['subject'] ) && '' !== trim( (string) $saved[ $event ]['subject'] ) ? (string) $saved[ $event ]['subject'] : $template['subject'];
				$defaults[ $event ]['body']    = isset( $saved[ $event ]['body'] ) && '' !== trim( (string) $saved[ $event ]['body'] ) ? (string) $saved[ $event ]['body'] : $template['body'];
			}
		}

		self::$cache = $defaults;

		return self::$cache;
	}

	/**
	 * Single template.
	 *
	 * @param string $event Event.
	 * @return array|null
	 */
	public static function get_template( $event ) {
		$templates = self::templates();

		return isset( $templates[ $event ] ) ? $templates[ $event ] : null;
	}

	/**
	 * Saves a template override.
	 *
	 * @param string $event Event key.
	 * @param array  $data  enabled, subject, body.
	 * @return bool
	 */
	public static function save_template( $event, $data ) {
		$templates = self::default_templates();

		if ( ! isset( $templates[ $event ] ) ) {
			return false;
		}

		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		$saved[ $event ] = array(
			'enabled' => ! empty( $data['enabled'] ) ? 1 : 0,
			'subject' => sanitize_text_field( isset( $data['subject'] ) ? $data['subject'] : '' ),
			'body'    => wp_kses_post( isset( $data['body'] ) ? $data['body'] : '' ),
		);

		update_option( self::OPTION, $saved, false );

		self::$cache = null;

		return true;
	}

	/**
	 * Removes a template override.
	 *
	 * @param string $event Event.
	 * @return void
	 */
	public static function reset_template( $event ) {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) || ! isset( $saved[ $event ] ) ) {
			return;
		}

		unset( $saved[ $event ] );

		update_option( self::OPTION, $saved, false );

		self::$cache = null;
	}

	/**
	 * Placeholders available in templates, for the admin UI.
	 *
	 * @return array<string,string>
	 */
	public static function placeholders() {
		return array(
			'{customer_name}'      => __( 'Customer name', 'qevora-booking' ),
			'{customer_email}'     => __( 'Customer email', 'qevora-booking' ),
			'{customer_phone}'     => __( 'Customer phone', 'qevora-booking' ),
			'{reference}'          => __( 'Booking reference', 'qevora-booking' ),
			'{service_name}'       => __( 'Service name', 'qevora-booking' ),
			'{staff_name}'         => __( 'Staff name', 'qevora-booking' ),
			'{date}'               => __( 'Appointment date', 'qevora-booking' ),
			'{time}'               => __( 'Appointment time range', 'qevora-booking' ),
			'{duration}'           => __( 'Duration', 'qevora-booking' ),
			'{price}'              => __( 'Price', 'qevora-booking' ),
			'{status}'             => __( 'Status label', 'qevora-booking' ),
			'{notes}'              => __( 'Customer notes', 'qevora-booking' ),
			'{booking_details}'    => __( 'Booking details table', 'qevora-booking' ),
			'{manage_url}'         => __( 'Manage/cancel link', 'qevora-booking' ),
			'{cancellation_policy}' => __( 'Cancellation policy text', 'qevora-booking' ),
			'{business_name}'      => __( 'Business name', 'qevora-booking' ),
			'{business_address}'   => __( 'Business address', 'qevora-booking' ),
			'{business_phone}'     => __( 'Business phone', 'qevora-booking' ),
			'{business_email}'     => __( 'Business email', 'qevora-booking' ),
			'{site_name}'          => __( 'Site name', 'qevora-booking' ),
			'{site_url}'           => __( 'Site URL', 'qevora-booking' ),
			'{reason}'             => __( 'Cancellation reason', 'qevora-booking' ),
			'{previous_date}'      => __( 'Previous date (reschedule)', 'qevora-booking' ),
			'{previous_time}'      => __( 'Previous time (reschedule)', 'qevora-booking' ),
			'{admin_booking_url}'  => __( 'Admin booking URL', 'qevora-booking' ),
			'{accent_color}'       => __( 'Accent colour', 'qevora-booking' ),
		);
	}

	/**
	 * Sends an event email.
	 *
	 * @param string $event   Event key.
	 * @param object $booking Booking.
	 * @param array  $extra   reason, previous_date, previous_time.
	 * @return bool
	 */
	public static function send( $event, $booking, $extra = array() ) {
		return self::dispatch( $event, $booking, $extra, false );
	}

	/**
	 * Internal sender.
	 *
	 * @param string $event   Event.
	 * @param object $booking Booking.
	 * @param array  $extra   Extras.
	 * @param bool   $force   Ignore global/template enable switches.
	 * @return bool
	 */
	private static function dispatch( $event, $booking, $extra, $force ) {
		if ( ! $booking ) {
			return false;
		}

		if ( ! $force && ! QVB_Settings::get( 'enable_emails' ) ) {
			return false;
		}

		$template = self::get_template( $event );

		if ( ! $template ) {
			return false;
		}

		if ( ! $force && empty( $template['enabled'] ) ) {
			return false;
		}

		$to = self::recipient_for( $template['recipient'], $booking, $force );

		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}

		$placeholders = self::build_placeholders( $booking, $extra );
		$subject      = strtr( (string) $template['subject'], $placeholders['plain'] );
		$content      = strtr( (string) $template['body'], $placeholders['html'] );
		$body         = self::wrap( $content, $booking );

		if ( $force && ! QVB_Settings::get( 'enable_emails' ) ) {
			$subject = '[Test] ' . $subject;
		}

		/**
		 * Filters the email subject.
		 *
		 * @param string $subject Subject.
		 * @param string $event   Event.
		 * @param object $booking Booking.
		 */
		$subject = apply_filters( 'qvb_email_subject', $subject, $event, $booking );

		/**
		 * Filters the HTML email body.
		 *
		 * @param string $body    HTML.
		 * @param string $event   Event.
		 * @param object $booking Booking.
		 */
		$body = apply_filters( 'qvb_email_body', $body, $event, $booking );

		$from_email = sanitize_email( (string) QVB_Settings::get( 'from_email' ) );
		$from_name  = sanitize_text_field( (string) QVB_Settings::get( 'from_name' ) );
		$reply_to   = 'admin' === $template['recipient'] ? $booking->customer_email : sanitize_email( (string) QVB_Settings::get( 'business_email' ) );

		if ( ! $from_email || ! is_email( $from_email ) ) {
			$from_email = sanitize_email( get_option( 'admin_email' ) );
		}

		if ( ! $from_name ) {
			$from_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( $from_email ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
		}

		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = sprintf( 'Reply-To: %s', $reply_to );
		}

		/**
		 * Filters the email headers.
		 *
		 * @param string[] $headers Headers.
		 * @param string   $event   Event.
		 * @param object   $booking Booking.
		 */
		$headers = apply_filters( 'qvb_email_headers', $headers, $event, $booking );

		$sent = false;

		try {
			$sent = wp_mail( $to, $subject, $body, $headers );
		} catch ( Exception $e ) {
			$sent = false;
		}

		/**
		 * Fires after a notification is attempted.
		 *
		 * @param bool   $sent    Whether wp_mail accepted the email.
		 * @param string $event   Event.
		 * @param string $to      Recipient.
		 * @param string $subject Subject.
		 * @param object $booking Booking.
		 */
		do_action( $sent ? 'qvb_email_sent' : 'qvb_email_failed', $event, $to, $subject, $booking, $sent );

		return (bool) $sent;
	}

	/**
	 * Resolves the recipient for a template.
	 *
	 * @param string $type    customer|admin.
	 * @param object $booking Booking.
	 * @param bool   $force   Use the admin address when customer email is missing.
	 * @return string
	 */
	private static function recipient_for( $type, $booking, $force = false ) {
		if ( 'admin' === $type ) {
			$email = sanitize_email( (string) QVB_Settings::get( 'admin_email' ) );

			return $email ? $email : sanitize_email( get_option( 'admin_email' ) );
		}

		$email = sanitize_email( (string) $booking->customer_email );

		if ( ! $email && $force ) {
			$email = sanitize_email( (string) QVB_Settings::get( 'admin_email' ) );
		}

		return $email;
	}

	/**
	 * Builds escaped and plain placeholder maps.
	 *
	 * @param object $booking Booking.
	 * @param array  $extra   reason, previous_date, previous_time.
	 * @return array{html:array,plain:array}
	 */
	private static function build_placeholders( $booking, $extra = array() ) {
		$date       = QVB_Utils::format_date_weekday( $booking->booking_date );
		$time       = QVB_Utils::format_time_range( $booking->start_time, $booking->end_time );
		$price      = QVB_Utils::money( $booking->price, isset( $booking->currency ) ? $booking->currency : null );
		$status     = QVB_Utils::status_label( $booking->status );
		$duration   = QVB_Utils::duration_label( (int) $booking->duration );
		$business   = (string) QVB_Settings::get( 'business_name' );
		$policy     = (string) QVB_Settings::get( 'cancellation_policy' );
		$reason     = isset( $extra['reason'] ) ? sanitize_text_field( (string) $extra['reason'] ) : '';
		$prev_date  = ! empty( $extra['previous_date'] ) ? QVB_Utils::format_date_weekday( $extra['previous_date'] ) : '';
		$prev_time  = ! empty( $extra['previous_time'] ) ? QVB_Utils::format_time( $extra['previous_time'] ) : '';
		$manage_url = (int) $booking->id ? QVB_Bookings::manage_url( $booking ) : QVB_Utils::booking_page_url();
		$admin_url  = (int) $booking->id
			? admin_url( 'admin.php?page=qevora-booking-bookings&action=edit&booking=' . (int) $booking->id )
			: admin_url( 'admin.php?page=qevora-booking-bookings' );
		$notes      = (string) $booking->notes;
		$accent     = QVB_Utils::hex_color( QVB_Settings::get( 'accent_color' ), '#7c3aed' );

		$raw = array(
			'customer_name'       => (string) $booking->customer_name,
			'customer_email'      => (string) $booking->customer_email,
			'customer_phone'      => (string) $booking->customer_phone,
			'reference'           => (string) $booking->reference,
			'service_name'        => (string) $booking->service_name,
			'staff_name'          => (string) $booking->staff_name,
			'date'                => $date,
			'time'                => $time,
			'duration'            => $duration,
			'price'               => $price,
			'status'              => $status,
			'notes'               => $notes,
			'reason'              => $reason,
			'previous_date'       => $prev_date,
			'previous_time'       => $prev_time,
			'business_name'       => $business,
			'business_address'    => (string) QVB_Settings::get( 'business_address' ),
			'business_phone'      => (string) QVB_Settings::get( 'business_phone' ),
			'business_email'      => (string) QVB_Settings::get( 'business_email' ),
			'site_name'           => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'cancellation_policy' => $policy,
		);

		$links = array(
			'manage_url'        => $manage_url,
			'admin_booking_url' => $admin_url,
			'site_url'          => home_url( '/' ),
		);

		$html  = array();
		$plain = array();

		foreach ( $raw as $key => $value ) {
			$html[ '{' . $key . '}' ] = esc_html( $value );

			if ( 'notes' === $key || 'reason' === $key || 'cancellation_policy' === $key ) {
				$html[ '{' . $key . '}' ] = nl2br( esc_html( $value ) );
			}

			$plain[ '{' . $key . '}' ] = sanitize_text_field( $value );
		}

		foreach ( $links as $key => $value ) {
			$html[ '{' . $key . '}' ]  = esc_url( $value );
			$plain[ '{' . $key . '}' ] = esc_url_raw( $value );
		}

		$html['{accent_color}']  = esc_attr( $accent );
		$plain['{accent_color}'] = $accent;

		$html['{booking_details}']  = self::booking_details_html( $booking );
		$plain['{booking_details}'] = wp_strip_all_tags( self::booking_details_html( $booking ) );

		return array(
			'html'  => $html,
			'plain' => $plain,
		);
	}

	/**
	 * HTML booking details table.
	 *
	 * @param object $booking Booking.
	 * @return string
	 */
	public static function booking_details_html( $booking ) {
		$rows = array(
			__( 'Reference', 'qevora-booking' ) => $booking->reference,
			__( 'Service', 'qevora-booking' )   => $booking->service_name,
			__( 'Staff', 'qevora-booking' )     => $booking->staff_name,
			__( 'Date', 'qevora-booking' )      => QVB_Utils::format_date_weekday( $booking->booking_date ),
			__( 'Time', 'qevora-booking' )      => QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ),
			__( 'Duration', 'qevora-booking' )  => QVB_Utils::duration_label( (int) $booking->duration ),
			__( 'Price', 'qevora-booking' )     => QVB_Utils::money( $booking->price, $booking->currency ) . ' · ' . __( 'pay at salon', 'qevora-booking' ),
			__( 'Status', 'qevora-booking' )    => QVB_Utils::status_label( $booking->status ),
		);

		$html = '<table role="presentation" style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px;">';

		foreach ( $rows as $label => $value ) {
			$html .= '<tr>'
				. '<td style="padding:9px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;width:38%;">' . esc_html( $label ) . '</td>'
				. '<td style="padding:9px 0;border-bottom:1px solid #e5e7eb;color:#111827;font-weight:600;">' . esc_html( $value ) . '</td>'
				. '</tr>';
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Wraps template content in the branded email layout.
	 *
	 * @param string $content Inner HTML.
	 * @param object $booking Booking.
	 * @return string
	 */
	private static function wrap( $content, $booking ) {
		$business = esc_html( (string) QVB_Settings::get( 'business_name' ) );
		$address  = esc_html( (string) QVB_Settings::get( 'business_address' ) );
		$phone    = esc_html( (string) QVB_Settings::get( 'business_phone' ) );
		$email    = esc_html( (string) QVB_Settings::get( 'business_email' ) );
		$accent   = esc_attr( QVB_Utils::hex_color( QVB_Settings::get( 'accent_color' ), '#7c3aed' ) );
		$site     = esc_url( home_url( '/' ) );
		$site_name = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		$footer_lines = array_filter( array( $business, $address, $phone, $email ) );

		$html  = '<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />';
		$html .= '<title>' . $business . '</title></head>';
		$html .= '<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#111827;">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;"><tr><td align="center">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(17,24,39,0.08);">';
		$html .= '<tr><td style="background:' . $accent . ';padding:22px 28px;color:#ffffff;font-size:19px;font-weight:700;">' . $business . '</td></tr>';
		$html .= '<tr><td style="padding:28px;font-size:15px;line-height:1.6;">' . $content . '</td></tr>';
		$html .= '<tr><td style="padding:18px 28px;border-top:1px solid #e5e7eb;background:#f9fafb;font-size:12px;line-height:1.6;color:#6b7280;">';

		if ( $footer_lines ) {
			$html .= implode( '<br />', $footer_lines ) . '<br /><br />';
		}

		$html .= '<a href="' . $site . '" style="color:#6b7280;">' . $site_name . '</a><br />';
		$html .= esc_html__( 'You are receiving this email because a booking was made with us. No online payment is collected.', 'qevora-booking' );
		$html .= '</td></tr></table></td></tr></table></body></html>';

		/**
		 * Filters the email wrapper.
		 *
		 * @param string $html    Full HTML.
		 * @param string $content Inner content.
		 * @param object $booking Booking.
		 */
		return apply_filters( 'qvb_email_wrapper', $html, $content, $booking );
	}

	/**
	 * Sends a test email using the confirmation template.
	 *
	 * @param string $to Recipient.
	 * @return bool
	 */
	public static function send_test( $to ) {
		$to = sanitize_email( $to );

		if ( ! is_email( $to ) ) {
			return false;
		}

		$sample = self::sample_booking();

		$template = self::get_template( 'customer_confirmed' );

		if ( ! $template ) {
			return false;
		}

		$placeholders = self::build_placeholders( $sample, array() );
		$subject      = '[Test] ' . strtr( (string) $template['subject'], $placeholders['plain'] );
		$content      = strtr( (string) $template['body'], $placeholders['html'] );
		$body         = self::wrap( $content, $sample );

		$from_email = sanitize_email( (string) QVB_Settings::get( 'from_email' ) );
		$from_name  = sanitize_text_field( (string) QVB_Settings::get( 'from_name' ) );
		$headers    = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( $from_email && is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name ? $from_name : $to, $from_email );
		}

		try {
			$sent = wp_mail( $to, $subject, $body, $headers );
		} catch ( Exception $e ) {
			$sent = false;
		}

		do_action( 'qvb_email_sent', 'test', $to, $subject, $sample, (bool) $sent );

		return (bool) $sent;
	}

	/**
	 * Preview HTML for one event using sample data.
	 *
	 * @param string $event Event key.
	 * @return string
	 */
	public static function preview( $event ) {
		$template = self::get_template( $event );

		if ( ! $template ) {
			return '';
		}

		$sample       = self::sample_booking();
		$placeholders = self::build_placeholders( $sample, array() );

		$body = strtr( (string) $template['body'], $placeholders['html'] );

		return self::wrap( $body, $sample );
	}

	/**
	 * Sample booking object used by tests and previews.
	 *
	 * @return object
	 */
	public static function sample_booking() {
		$now = QVB_Utils::now();

		return (object) array(
			'id'                  => 0,
			'reference'           => 'QVB-000000-DEMO1',
			'customer_id'         => 0,
			'service_id'          => 0,
			'staff_id'            => 0,
			'booking_date'        => $now->modify( '+1 day' )->format( 'Y-m-d' ),
			'start_time'          => '10:00',
			'end_time'            => '11:00',
			'buffer_end_time'     => '11:15',
			'duration'            => 60,
			'buffer_time'         => 15,
			'status'              => 'confirmed',
			'price'               => 1500,
			'currency'            => (string) QVB_Settings::get( 'currency' ),
			'service_name'        => __( 'Signature massage', 'qevora-booking' ),
			'staff_name'          => __( 'Aisha', 'qevora-booking' ),
			'customer_name'       => __( 'Priya Sharma', 'qevora-booking' ),
			'customer_email'      => 'priya@example.com',
			'customer_phone'      => '+91 98765 43210',
			'notes'               => __( 'Please use unscented oil.', 'qevora-booking' ),
			'admin_notes'         => '',
			'source'              => 'frontend',
			'created_by'          => 0,
			'ip_hash'             => '',
			'reschedule_count'    => 0,
			'cancelled_at'        => null,
			'cancelled_by'        => 0,
			'cancellation_reason' => '',
			'created_at'          => QVB_Utils::now_sql(),
			'updated_at'          => QVB_Utils::now_sql(),
		);
	}
}
