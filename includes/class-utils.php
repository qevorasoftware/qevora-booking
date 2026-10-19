<?php
/**
 * Shared helpers: time zones, dates, formatting and sanitising.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Utils
 */
final class QVB_Utils {

	/**
	 * All supported booking statuses.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'pending', 'confirmed', 'completed', 'cancelled', 'rejected', 'no_show' );
	}

	/**
	 * Statuses that occupy a staff member's calendar.
	 *
	 * Cancelled and rejected bookings release the slot.
	 *
	 * @return string[]
	 */
	public static function blocking_statuses() {
		return array( 'pending', 'confirmed', 'completed', 'no_show' );
	}

	/**
	 * Business timezone object. Defaults to the WordPress site timezone.
	 *
	 * @return DateTimeZone
	 */
	public static function timezone() {
		$timezone = (string) QVB_Settings::get( 'timezone' );

		if ( $timezone ) {
			try {
				return new DateTimeZone( $timezone );
			} catch ( Exception $e ) {
				// Fall through to the site timezone.
			}
		}

		return wp_timezone();
	}

	/**
	 * Human readable timezone name.
	 *
	 * @return string
	 */
	public static function timezone_name() {
		$timezone = (string) QVB_Settings::get( 'timezone' );

		return $timezone ? $timezone : wp_timezone_string();
	}

	/**
	 * Current local time in the business timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public static function now() {
		return new DateTimeImmutable( 'now', self::timezone() );
	}

	/**
	 * Current local date (Y-m-d).
	 *
	 * @return string
	 */
	public static function today() {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Current local date/time for database timestamps.
	 *
	 * Stored in the WordPress site timezone so admin screens can use core
	 * formatting helpers; booking dates and times are stored as local
	 * wall-clock strings.
	 *
	 * @return string
	 */
	public static function now_sql() {
		return current_time( 'mysql' );
	}

	/**
	 * Validates a Y-m-d date string.
	 *
	 * @param mixed $date Date.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_date( $date ) {
		$date = trim( (string) $date );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
			return '';
		}

		list( , $year, $month, $day ) = $matches;

		if ( ! checkdate( (int) $month, (int) $day, (int) $year ) ) {
			return '';
		}

		return $date;
	}

	/**
	 * Validates a H:i time string.
	 *
	 * @param mixed $time Time.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_time( $time ) {
		$time = trim( sanitize_text_field( (string) $time ) );

		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches ) ) {
			return '';
		}

		$hours   = (int) $matches[1];
		$minutes = (int) $matches[2];

		if ( $hours > 23 || $minutes > 59 ) {
			return '';
		}

		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	/**
	 * Builds a DateTimeImmutable from a local date and time.
	 *
	 * Returns null for non-existent local times (DST spring-forward gap) so
	 * those slots are never offered.
	 *
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return DateTimeImmutable|null
	 */
	public static function local_datetime( $date, $time = '00:00' ) {
		$date = self::sanitize_date( $date );
		$time = self::sanitize_time( $time );

		if ( ! $date || ! $time ) {
			return null;
		}

		try {
			$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, self::timezone() );
		} catch ( Exception $e ) {
			return null;
		}

		if ( ! $datetime ) {
			return null;
		}

		// createFromFormat normalises skipped DST times; compare to detect them.
		if ( $datetime->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
			return null;
		}

		return $datetime;
	}

	/**
	 * Unix timestamp for a local date/time.
	 *
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return int|false
	 */
	public static function timestamp( $date, $time = '00:00' ) {
		$datetime = self::local_datetime( $date, $time );

		return $datetime ? $datetime->getTimestamp() : false;
	}

	/**
	 * Whether the given local date/time is in the past.
	 *
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return bool
	 */
	public static function is_past( $date, $time = '00:00' ) {
		$timestamp = self::timestamp( $date, $time );

		if ( false === $timestamp ) {
			return true;
		}

		return $timestamp < self::now()->getTimestamp();
	}

	/**
	 * Whether a date (end of day) is in the past.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function date_is_past( $date ) {
		$timestamp = self::timestamp( $date, '23:59' );

		return false === $timestamp || $timestamp < self::now()->getTimestamp();
	}

	/**
	 * Formats a local date.
	 *
	 * @param string      $date   Date.
	 * @param string|null $format Optional PHP date format.
	 * @return string
	 */
	public static function format_date( $date, $format = null ) {
		$datetime = self::local_datetime( $date, '12:00' );

		if ( ! $datetime ) {
			return (string) $date;
		}

		$format = $format ? $format : (string) QVB_Settings::get( 'date_format' );

		return wp_date( $format, $datetime->getTimestamp(), self::timezone() );
	}

	/**
	 * Formats a local date with its weekday.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	public static function format_date_weekday( $date ) {
		return self::format_date( $date, 'l, ' . QVB_Settings::get( 'date_format' ) );
	}

	/**
	 * Formats a local time.
	 *
	 * A neutral date is used so the output never depends on today's DST rules.
	 *
	 * @param string      $time   Time.
	 * @param string|null $format Optional PHP time format.
	 * @return string
	 */
	public static function format_time( $time, $format = null ) {
		$time = self::sanitize_time( $time );

		if ( ! $time ) {
			return (string) $time;
		}

		$format    = $format ? $format : (string) QVB_Settings::get( 'time_format' );
		$timezone  = self::timezone();
		$candidate = null;

		foreach ( array( '2000-06-15', '2000-01-15' ) as $neutral_date ) {
			$candidate = self::local_datetime( (string) $neutral_date, $time );
			if ( $candidate ) {
				break;
			}
		}

		if ( ! $candidate ) {
			return $time;
		}

		return wp_date( $format, $candidate->getTimestamp(), $timezone );
	}

	/**
	 * Formats a time range.
	 *
	 * @param string $start Start time.
	 * @param string $end   End time.
	 * @return string
	 */
	public static function format_time_range( $start, $end ) {
		return self::format_time( $start ) . ' – ' . self::format_time( $end );
	}

	/**
	 * Formats a booking date and time together.
	 *
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return string
	 */
	public static function format_datetime( $date, $time ) {
		return self::format_date( $date ) . ' · ' . self::format_time( $time );
	}

	/**
	 * Formats a stored MySQL timestamp for admin screens.
	 *
	 * @param string $mysql Date/time.
	 * @return string
	 */
	public static function format_admin_datetime( $mysql ) {
		if ( ! $mysql || '0000-00-00 00:00:00' === $mysql ) {
			return '—';
		}

		$format = self::format_date( substr( $mysql, 0, 10 ), QVB_Settings::get( 'date_format' ) );

		return mysql2date( QVB_Settings::get( 'date_format' ) . ' ' . QVB_Settings::get( 'time_format' ), $mysql );
	}

	/**
	 * Converts a H:i time to minutes after midnight.
	 *
	 * @param string $time Time.
	 * @return int|null
	 */
	public static function time_to_minutes( $time ) {
		$time = self::sanitize_time( $time );

		if ( ! $time ) {
			return null;
		}

		$parts = explode( ':', $time );

		return ( (int) $parts[0] * 60 ) + (int) $parts[1];
	}

	/**
	 * Converts minutes after midnight to H:i.
	 *
	 * Returns an empty string when the value falls outside a single day.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function minutes_to_time( $minutes ) {
		$minutes = (int) $minutes;

		if ( $minutes < 0 || $minutes >= 1440 ) {
			return '';
		}

		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * Adds minutes to a time, returning an empty string when it overflows the day.
	 *
	 * @param string $time    Start time.
	 * @param int    $minutes Minutes.
	 * @return string
	 */
	public static function add_minutes_to_time( $time, $minutes ) {
		$start = self::time_to_minutes( $time );

		if ( null === $start ) {
			return '';
		}

		return self::minutes_to_time( $start + (int) $minutes );
	}

	/**
	 * Whether two time ranges overlap. End times are exclusive.
	 *
	 * @param string $start_a Start A.
	 * @param string $end_a   End A.
	 * @param string $start_b Start B.
	 * @param string $end_b   End B.
	 * @return bool
	 */
	public static function times_overlap( $start_a, $end_a, $start_b, $end_b ) {
		$start_a = self::time_to_minutes( $start_a );
		$end_a   = self::time_to_minutes( $end_a );
		$start_b = self::time_to_minutes( $start_b );
		$end_b   = self::time_to_minutes( $end_b );

		if ( null === $start_a || null === $end_a || null === $start_b || null === $end_b ) {
			return false;
		}

		return $start_a < $end_b && $start_b < $end_a;
	}

	/**
	 * Day of week for a local date (0 = Sunday).
	 *
	 * @param string $date Date.
	 * @return int
	 */
	public static function day_of_week( $date ) {
		$datetime = self::local_datetime( $date, '12:00' );

		return $datetime ? (int) $datetime->format( 'w' ) : 0;
	}

	/**
	 * Setting: first day of the week.
	 *
	 * @return int
	 */
	public static function week_starts_on() {
		$start = (int) QVB_Settings::get( 'week_starts_on' );

		return in_array( $start, array( 0, 1, 6 ), true ) ? $start : 1;
	}

	/**
	 * Date of the first day of the week containing a date.
	 *
	 * @param string|null $date Optional date, defaults to today.
	 * @return string
	 */
	public static function week_start( $date = null ) {
		$date = $date ? self::sanitize_date( $date ) : self::today();

		if ( ! $date ) {
			$date = self::today();
		}

		$day   = self::day_of_week( $date );
		$start = self::week_starts_on();
		$diff  = ( $day - $start + 7 ) % 7;

		return gmdate( 'Y-m-d', strtotime( $date . ' -' . $diff . ' days' ) );
	}

	/**
	 * Day names ordered from the configured first day of the week.
	 *
	 * Keys are day numbers (0 = Sunday).
	 *
	 * @return array<int,string>
	 */
	public static function weekdays() {
		$names = self::weekday_names();
		$order = array();
		$start = self::week_starts_on();

		for ( $i = 0; $i < 7; $i++ ) {
			$order[] = ( $start + $i ) % 7;
		}

		$out = array();
		foreach ( $order as $day ) {
			$out[ $day ] = $names[ $day ];
		}

		return $out;
	}

	/**
	 * Translated weekday names keyed by day number.
	 *
	 * @return array<int,string>
	 */
	public static function weekday_names() {
		global $wp_locale;

		$names = array();

		for ( $day = 0; $day < 7; $day++ ) {
			if ( isset( $wp_locale ) && is_object( $wp_locale ) && method_exists( $wp_locale, 'get_weekday' ) ) {
				$names[ $day ] = $wp_locale->get_weekday( $day );
			} else {
				$names[ $day ] = gmdate( 'l', strtotime( '2024-01-07 +' . $day . ' days' ) );
			}
		}

		return $names;
	}

	/**
	 * Single weekday name.
	 *
	 * @param int $day Day number 0-6.
	 * @return string
	 */
	public static function weekday_name( $day ) {
		$names = self::weekday_names();
		$day   = (int) $day;

		return isset( $names[ $day ] ) ? $names[ $day ] : '';
	}

	/**
	 * One or two letter monogram for a name, used when no image is available.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function initials( $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );

		if ( '' === $name ) {
			return '?';
		}

		$parts = preg_split( '/\s+/u', $name );
		$first = mb_substr( (string) $parts[0], 0, 1 );

		if ( count( $parts ) > 1 ) {
			$last = mb_substr( (string) $parts[ count( $parts ) - 1 ], 0, 1 );

			return mb_strtoupper( $first . $last );
		}

		return mb_strtoupper( mb_substr( (string) $parts[0], 0, 2 ) );
	}

	/**
	 * Date N days from today.
	 *
	 * @param int $days Days (can be negative).
	 * @return string
	 */
	public static function date_from_now( $days ) {
		return self::now()->modify( ( (int) $days >= 0 ? '+' : '' ) . (int) $days . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Label for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'qevora-booking' ),
			'confirmed' => __( 'Confirmed', 'qevora-booking' ),
			'completed' => __( 'Completed', 'qevora-booking' ),
			'cancelled' => __( 'Cancelled', 'qevora-booking' ),
			'rejected'  => __( 'Rejected', 'qevora-booking' ),
			'no_show'   => __( 'No-show', 'qevora-booking' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * Whether a status key is valid.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_status( $status ) {
		return in_array( (string) $status, self::statuses(), true );
	}

	/**
	 * Formats a duration in minutes.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function duration_label( $minutes ) {
		$minutes = max( 0, (int) $minutes );

		if ( $minutes < 60 ) {
			/* translators: %d: number of minutes. */
			return sprintf( _n( '%d min', '%d min', $minutes, 'qevora-booking' ), $minutes );
		}

		$hours = intdiv( $minutes, 60 );
		$rest  = $minutes % 60;

		if ( ! $rest ) {
			/* translators: %d: number of hours. */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'qevora-booking' ), $hours );
		}

		return sprintf(
			/* translators: 1: hours, 2: minutes. */
			__( '%1$d hr %2$d min', 'qevora-booking' ),
			$hours,
			$rest
		);
	}

	/**
	 * Currency symbols supported out of the box.
	 *
	 * @return array<string,string>
	 */
	public static function currency_symbols() {
		return array(
			'INR' => '₹',
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'AED' => 'د.إ',
			'SAR' => '﷼',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'SGD' => 'S$',
			'NZD' => 'NZ$',
			'ZAR' => 'R',
			'NGN' => '₦',
			'KES' => 'KSh',
			'PKR' => '₨',
			'BDT' => '৳',
			'LKR' => '₨',
			'NPR' => '₨',
			'MYR' => 'RM',
			'PHP' => '₱',
			'IDR' => 'Rp',
			'THB' => '฿',
			'VND' => '₫',
			'CNY' => '¥',
			'JPY' => '¥',
			'KRW' => '₩',
			'HKD' => 'HK$',
			'TWD' => 'NT$',
			'BRL' => 'R$',
			'MXN' => 'MX$',
			'CHF' => 'CHF',
			'TRY' => '₺',
			'RUB' => '₽',
			'EGP' => 'E£',
			'QAR' => 'ر.ق',
			'KWD' => 'د.ك',
			'BHD' => 'ب.د',
			'OMR' => 'ر.ع.',
			'JOD' => 'د.ا',
		);
	}

	/**
	 * Symbol for a currency code.
	 *
	 * @param string|null $code Currency code.
	 * @return string
	 */
	public static function currency_symbol( $code = null ) {
		$settings_symbol = (string) QVB_Settings::get( 'currency_symbol' );
		$code            = strtoupper( (string) ( $code ? $code : QVB_Settings::get( 'currency' ) ) );
		$symbols         = self::currency_symbols();

		if ( $code === strtoupper( (string) QVB_Settings::get( 'currency' ) ) && '' !== $settings_symbol ) {
			return $settings_symbol;
		}

		if ( isset( $symbols[ $code ] ) ) {
			return $symbols[ $code ];
		}

		return $code;
	}

	/**
	 * Formats an amount using the configured currency.
	 *
	 * @param float       $amount   Amount.
	 * @param string|null $currency Optional booking currency snapshot.
	 * @return string
	 */
	public static function money( $amount, $currency = null ) {
		$decimals = (int) QVB_Settings::get( 'currency_decimals' );
		$decimals = max( 0, min( 3, $decimals ) );
		$number   = number_format_i18n( (float) $amount, $decimals );
		$symbol   = self::currency_symbol( $currency );

		if ( 'after' === QVB_Settings::get( 'currency_position' ) ) {
			return $number . ' ' . $symbol;
		}

		return $symbol . $number;
	}

	/**
	 * Boolean helper used when saving settings.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Converts a truthy value to 1 or 0.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function bool_int( $value ) {
		return self::to_bool( $value ) ? 1 : 0;
	}

	/**
	 * Sanitises a hex colour with a fallback.
	 *
	 * @param string $color    Colour.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function hex_color( $color, $fallback = '#7c3aed' ) {
		$color = sanitize_hex_color( (string) $color );

		return $color ? $color : $fallback;
	}

	/**
	 * Masks an email address for users without the PII capability.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function mask_email( $email ) {
		$email = (string) $email;

		if ( ! is_email( $email ) ) {
			return '•••';
		}

		list( $local, $domain ) = explode( '@', $email, 2 );

		return mb_substr( $local, 0, 1 ) . '•••@' . $domain;
	}

	/**
	 * Masks a phone number, keeping the last three digits.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function mask_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );

		if ( ! $digits ) {
			return '•••';
		}

		return '••• ' . substr( $digits, -3 );
	}

	/**
	 * Client IP address.
	 *
	 * Only REMOTE_ADDR is trusted by default. Sites behind a reverse proxy can
	 * use the qvb_client_ip filter to read a validated forwarded header.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( $ip && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '';
		}

		/**
		 * Filters the detected client IP address.
		 *
		 * @param string $ip IP address.
		 */
		return (string) apply_filters( 'qvb_client_ip', $ip );
	}

	/**
	 * Salted IP hash for abuse protection without storing the raw address.
	 *
	 * @return string
	 */
	public static function ip_hash() {
		$ip = self::client_ip();

		if ( ! $ip ) {
			return '';
		}

		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Truncates text safely.
	 *
	 * @param string $text   Text.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	public static function truncate( $text, $length = 80 ) {
		$text = wp_strip_all_tags( (string) $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return mb_substr( $text, 0, max( 1, $length - 1 ) ) . '…';
	}

	/**
	 * Page ID that contains the booking shortcode.
	 *
	 * @return int
	 */
	public static function booking_page_id() {
		static $found = null;

		if ( null !== $found ) {
			return $found;
		}

		$configured = absint( QVB_Settings::get( 'booking_page_id' ) );

		if ( $configured && 'page' === get_post_type( $configured ) && 'publish' === get_post_status( $configured ) ) {
			$found = $configured;
			return $found;
		}

		$ids = get_posts(
			array(
				'post_type'        => array( 'page', 'post' ),
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $ids as $post_id ) {
			$content = (string) get_post_field( 'post_content', $post_id );

			if ( has_shortcode( $content, 'qevora_booking' ) || has_block( 'qevora/booking', $post_id ) ) {
				$found = (int) $post_id;
				return $found;
			}
		}

		$found = 0;

		return $found;
	}

	/**
	 * URL of the booking page (fallback: home page).
	 *
	 * @return string
	 */
	public static function booking_page_url() {
		$page_id = self::booking_page_id();

		if ( $page_id ) {
			$permalink = get_permalink( $page_id );
			if ( $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Appends query args to a URL safely.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return string
	 */
	public static function add_query_args( $url, $args ) {
		return add_query_arg( array_map( 'rawurlencode', $args ), $url );
	}

	/**
	 * Whether a date is within the maximum advance window.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function within_max_advance( $date ) {
		$days = max( 1, (int) QVB_Settings::get( 'max_advance' ) );

		return self::sanitize_date( $date ) && $date <= self::date_from_now( $days );
	}

	/**
	 * Whether a local date/time is inside the minimum notice window.
	 *
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return bool
	 */
	public static function respects_min_notice( $date, $time ) {
		$timestamp = self::timestamp( $date, $time );

		if ( false === $timestamp ) {
			return false;
		}

		$notice = max( 0, (int) QVB_Settings::get( 'min_notice' ) ) * 60;

		return $timestamp >= ( self::now()->getTimestamp() + $notice );
	}

	/**
	 * Returns a MySQL-friendly list of placeholders.
	 *
	 * @param int $count Count.
	 * @return string
	 */
	public static function placeholders( $count ) {
		return implode( ',', array_fill( 0, max( 1, (int) $count ), '%s' ) );
	}

	/**
	 * Builds an array of date strings between two dates (inclusive).
	 *
	 * @param string $from Start.
	 * @param string $to   End.
	 * @return string[]
	 */
	public static function date_range( $from, $to ) {
		$from = self::sanitize_date( $from );
		$to   = self::sanitize_date( $to );

		if ( ! $from || ! $to || $to < $from ) {
			return array();
		}

		$dates = array();
		$start = new DateTimeImmutable( $from, self::timezone() );
		$end   = new DateTimeImmutable( $to, self::timezone() );

		while ( $start <= $end ) {
			$dates[] = $start->format( 'Y-m-d' );
			$start   = $start->modify( '+1 day' );

			if ( count( $dates ) > 400 ) {
				break;
			}
		}

		return $dates;
	}
}
