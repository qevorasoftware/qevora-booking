<?php
/**
 * Plugin settings.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Settings
 */
final class QVB_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'qvb_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$admin_mail = sanitize_email( get_option( 'admin_email' ) );

		$business_hours = array();

		for ( $day = 0; $day < 7; $day++ ) {
			$open = ( 0 !== $day ); // Sunday closed by default.

			$business_hours[ $day ] = array(
				'enabled'     => $open ? 1 : 0,
				'start'       => '09:00',
				'end'         => '19:00',
				'break_start' => $open ? '13:00' : '',
				'break_end'   => $open ? '14:00' : '',
			);
		}

		return array(
			// Business identity.
			'business_name'            => $site_name ? $site_name : __( 'My Salon', 'qevora-booking' ),
			'business_address'         => '',
			'business_phone'           => '',
			'business_email'           => $admin_mail,
			'timezone'                 => '',
			'date_format'              => get_option( 'date_format' ) ? get_option( 'date_format' ) : 'F j, Y',
			'time_format'              => get_option( 'time_format' ) ? get_option( 'time_format' ) : 'g:i a',
			'week_starts_on'           => 1,
			'currency'                 => 'INR',
			'currency_symbol'          => '',
			'currency_position'        => 'before',
			'currency_decimals'        => 2,
			'booking_page_id'          => 0,
			'accent_color'             => '#7c3aed',

			// Booking engine.
			'slot_interval'            => 30,
			'default_buffer'           => 0,
			'min_notice'               => 120,
			'max_advance'              => 60,
			'approval_mode'            => 'auto',
			'allow_cancel'             => 1,
			'cancel_limit_hours'       => 24,
			'allow_reschedule'         => 1,
			'reschedule_limit_hours'   => 24,
			'max_reschedules'          => 1,
			'require_phone'            => 1,
			'require_consent'          => 0,
			'consent_text'             => __( 'I agree to the cancellation and rescheduling policy.', 'qevora-booking' ),
			'cancellation_policy'      => __( 'Appointments may be cancelled or rescheduled up to 24 hours in advance. Please contact us as early as possible if your plans change.', 'qevora-booking' ),
			'business_hours'           => $business_hours,

			// Email.
			'enable_emails'            => 1,
			'admin_email'              => $admin_mail,
			'from_name'                => $site_name,
			'from_email'               => $admin_mail,

			// Abuse protection.
			'spam_protection'          => 1,
			'min_form_seconds'         => 3,
			'rate_limit_bookings'      => 5,
			'rate_limit_lookups'       => 10,

			// Privacy and data.
			'retention_days'           => 0,
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * Installs defaults only when the option does not exist.
	 *
	 * @return void
	 */
	public static function install_defaults() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}

		self::flush_cache();
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings                   = wp_parse_args( $saved, self::defaults() );
		$settings['business_hours'] = self::normalise_business_hours( $settings['business_hours'] );

		self::$cache = $settings;

		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::all();

		if ( ! array_key_exists( $key, $settings ) ) {
			return $default;
		}

		return $settings[ $key ];
	}

	/**
	 * Business hours, always seven normalised days.
	 *
	 * @return array<int,array>
	 */
	public static function business_hours() {
		return self::get( 'business_hours', array() );
	}

	/**
	 * Opening hours for a weekday.
	 *
	 * @param int $day Day number, 0 = Sunday.
	 * @return array|null Array with start, end and breaks; null when closed.
	 */
	public static function business_hours_for_day( $day ) {
		$hours = self::business_hours();
		$day   = (int) $day;

		if ( empty( $hours[ $day ]['enabled'] ) ) {
			return null;
		}

		$start = QVB_Utils::sanitize_time( isset( $hours[ $day ]['start'] ) ? $hours[ $day ]['start'] : '' );
		$end   = QVB_Utils::sanitize_time( isset( $hours[ $day ]['end'] ) ? $hours[ $day ]['end'] : '' );

		if ( ! $start || ! $end || $end <= $start ) {
			return null;
		}

		$breaks      = array();
		$break_start = QVB_Utils::sanitize_time( isset( $hours[ $day ]['break_start'] ) ? $hours[ $day ]['break_start'] : '' );
		$break_end   = QVB_Utils::sanitize_time( isset( $hours[ $day ]['break_end'] ) ? $hours[ $day ]['break_end'] : '' );

		if ( $break_start && $break_end && $break_end > $break_start && $break_start > $start && $break_end < $end ) {
			$breaks[] = array(
				'start' => $break_start,
				'end'   => $break_end,
			);
		}

		return array(
			'start'  => $start,
			'end'    => $end,
			'breaks' => $breaks,
		);
	}

	/**
	 * Whether bookings require manual approval.
	 *
	 * @return bool
	 */
	public static function requires_approval() {
		return 'manual' === self::get( 'approval_mode' );
	}

	/**
	 * Normalises business hours read from the option.
	 *
	 * @param mixed $hours Raw hours.
	 * @return array<int,array>
	 */
	private static function normalise_business_hours( $hours ) {
		$defaults = self::defaults();
		$default  = $defaults['business_hours'];
		$hours    = is_array( $hours ) ? $hours : array();
		$out      = array();

		for ( $day = 0; $day < 7; $day++ ) {
			$row = isset( $hours[ $day ] ) && is_array( $hours[ $day ] ) ? $hours[ $day ] : array();

			$enabled = ! empty( $row['enabled'] );
			$start   = QVB_Utils::sanitize_time( isset( $row['start'] ) ? $row['start'] : '' );
			$end     = QVB_Utils::sanitize_time( isset( $row['end'] ) ? $row['end'] : '' );

			if ( ! $start ) {
				$start = $default[ $day ]['start'];
			}

			if ( ! $end || $end <= $start ) {
				$end = $default[ $day ]['end'];
			}

			$break_start = QVB_Utils::sanitize_time( isset( $row['break_start'] ) ? $row['break_start'] : '' );
			$break_end   = QVB_Utils::sanitize_time( isset( $row['break_end'] ) ? $row['break_end'] : '' );

			if ( ! $break_start || ! $break_end || $break_end <= $break_start || $break_start <= $start || $break_end >= $end ) {
				$break_start = '';
				$break_end   = '';
			}

			$out[ $day ] = array(
				'enabled'     => $enabled ? 1 : 0,
				'start'       => $start,
				'end'         => $end,
				'break_start' => $break_start,
				'break_end'   => $break_end,
			);
		}

		return $out;
	}

	/**
	 * Sanitises and merges an incoming settings payload.
	 *
	 * Missing keys are left untouched, so each settings tab can post only its
	 * own fields. Checkboxes use a hidden zero input before the checkbox.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function update( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$text_fields = array(
			'business_name',
			'business_address',
			'business_phone',
			'from_name',
			'currency_symbol',
			'consent_text',
			'cancellation_policy',
		);

		foreach ( $text_fields as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$value = sanitize_textarea_field( (string) $input[ $key ] );

				if ( 'business_name' === $key || 'from_name' === $key || 'currency_symbol' === $key ) {
					$value = sanitize_text_field( $value );
				}

				$clean[ $key ] = $value;
			}
		}

		foreach ( array( 'business_email', 'admin_email', 'from_email' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$value = sanitize_email( (string) $input[ $key ] );
				$clean[ $key ] = $value;
			}
		}

		if ( array_key_exists( 'timezone', $input ) ) {
			$timezone = sanitize_text_field( (string) $input['timezone'] );

			if ( '' !== $timezone && ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
				$timezone = '';
			}

			$clean['timezone'] = $timezone;
		}

		if ( array_key_exists( 'date_format', $input ) ) {
			$format = sanitize_text_field( (string) $input['date_format'] );
			$clean['date_format'] = $format ? $format : self::get( 'date_format' );
		}

		if ( array_key_exists( 'time_format', $input ) ) {
			$format = sanitize_text_field( (string) $input['time_format'] );
			$clean['time_format'] = $format ? $format : self::get( 'time_format' );
		}

		if ( array_key_exists( 'week_starts_on', $input ) ) {
			$start = (int) $input['week_starts_on'];
			$clean['week_starts_on'] = in_array( $start, array( 0, 1, 6 ), true ) ? $start : 1;
		}

		if ( array_key_exists( 'currency', $input ) ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency'] ) );

			if ( 3 === strlen( $code ) || 'XXX' === $code ) {
				$clean['currency'] = $code;
			}
		}

		if ( array_key_exists( 'currency_position', $input ) ) {
			$clean['currency_position'] = 'after' === $input['currency_position'] ? 'after' : 'before';
		}

		if ( array_key_exists( 'currency_decimals', $input ) ) {
			$clean['currency_decimals'] = max( 0, min( 3, (int) $input['currency_decimals'] ) );
		}

		if ( array_key_exists( 'booking_page_id', $input ) ) {
			$page_id = absint( $input['booking_page_id'] );
			$clean['booking_page_id'] = ( $page_id && get_post( $page_id ) ) ? $page_id : 0;
		}

		if ( array_key_exists( 'accent_color', $input ) ) {
			$clean['accent_color'] = QVB_Utils::hex_color( $input['accent_color'], '#7c3aed' );
		}

		if ( array_key_exists( 'slot_interval', $input ) ) {
			$intervals = array( 5, 10, 15, 20, 30, 45, 60, 90, 120 );
			$interval  = (int) $input['slot_interval'];
			$closest   = 30;
			$distance  = PHP_INT_MAX;

			foreach ( $intervals as $allowed ) {
				$diff = abs( $allowed - $interval );
				if ( $diff < $distance ) {
					$distance = $diff;
					$closest  = $allowed;
				}
			}

			$clean['slot_interval'] = $interval > 0 ? $closest : 30;
		}

		$ranges = array(
			'default_buffer'         => array( 0, 240 ),
			'min_notice'             => array( 0, 10080 ),
			'max_advance'            => array( 1, 730 ),
			'cancel_limit_hours'     => array( 0, 8760 ),
			'reschedule_limit_hours' => array( 0, 8760 ),
			'max_reschedules'        => array( 0, 10 ),
			'min_form_seconds'       => array( 0, 120 ),
			'rate_limit_bookings'    => array( 1, 1000 ),
			'rate_limit_lookups'     => array( 1, 1000 ),
			'retention_days'         => array( 0, 3650 ),
		);

		foreach ( $ranges as $key => $range ) {
			if ( array_key_exists( $key, $input ) ) {
				$value = (int) $input[ $key ];
				$clean[ $key ] = max( $range[0], min( $range[1], $value ) );
			}
		}

		if ( array_key_exists( 'approval_mode', $input ) ) {
			$clean['approval_mode'] = 'manual' === $input['approval_mode'] ? 'manual' : 'auto';
		}

		$booleans = array(
			'allow_cancel',
			'allow_reschedule',
			'require_phone',
			'require_consent',
			'enable_emails',
			'spam_protection',
			'delete_data_on_uninstall',
		);

		foreach ( $booleans as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = QVB_Utils::to_bool( $input[ $key ] ) ? 1 : 0;
			}
		}

		if ( array_key_exists( 'business_hours', $input ) && is_array( $input['business_hours'] ) ) {
			$clean['business_hours'] = self::sanitize_business_hours( $input['business_hours'] );
		}

		$settings = wp_parse_args( $clean, self::all() );

		update_option( self::OPTION, $settings, false );
		self::flush_cache();

		/**
		 * Fires after settings are saved.
		 *
		 * @param array $settings New settings.
		 * @param array $clean    Sanitised keys that changed.
		 */
		do_action( 'qvb_settings_saved', $settings, $clean );

		return $settings;
	}

	/**
	 * Sanitises posted business hours.
	 *
	 * @param array $input Raw days.
	 * @return array<int,array>
	 */
	private static function sanitize_business_hours( $input ) {
		$current = self::business_hours();
		$out     = array();

		for ( $day = 0; $day < 7; $day++ ) {
			$row      = isset( $input[ $day ] ) && is_array( $input[ $day ] ) ? $input[ $day ] : array();
			$existing = isset( $current[ $day ] ) ? $current[ $day ] : array(
				'enabled'     => 0,
				'start'       => '09:00',
				'end'         => '18:00',
				'break_start' => '',
				'break_end'   => '',
			);

			$enabled = ! empty( $row['enabled'] ) ? 1 : 0;
			$start   = QVB_Utils::sanitize_time( isset( $row['start'] ) ? $row['start'] : '' );
			$end     = QVB_Utils::sanitize_time( isset( $row['end'] ) ? $row['end'] : '' );

			if ( ! $start ) {
				$start = $existing['start'];
			}

			if ( ! $end || $end <= $start ) {
				$end = $existing['end'] > $start ? $existing['end'] : '18:00';
			}

			if ( $end <= $start ) {
				$end = QVB_Utils::add_minutes_to_time( $start, 60 );
			}

			$break_start = QVB_Utils::sanitize_time( isset( $row['break_start'] ) ? $row['break_start'] : '' );
			$break_end   = QVB_Utils::sanitize_time( isset( $row['break_end'] ) ? $row['break_end'] : '' );

			if ( ! $break_start || ! $break_end || $break_end <= $break_start || $break_start <= $start || $break_end >= $end ) {
				$break_start = '';
				$break_end   = '';
			}

			$out[ $day ] = array(
				'enabled'     => $enabled,
				'start'       => $start,
				'end'         => $end,
				'break_start' => $break_start,
				'break_end'   => $break_end,
			);
		}

		return $out;
	}

	/**
	 * Clears the in-memory cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
