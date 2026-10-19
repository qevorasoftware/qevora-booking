<?php
/**
 * Nonces, form tokens, spam protection and rate limiting.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class QVB_Security
 */
final class QVB_Security {

	/**
	 * Form token lifetime in seconds (2 days).
	 */
	const TOKEN_LIFETIME = 172800;

	/**
	 * Verifies an admin action: capability first, then nonce.
	 *
	 * @param string $action Nonce action.
	 * @param string $cap    Capability.
	 * @return void
	 */
	public static function verify_admin( $action, $cap = QVB_Capabilities::MANAGE ) {
		if ( ! is_user_logged_in() || ! current_user_can( $cap ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'qevora-booking' ),
				esc_html__( 'Permission denied', 'qevora-booking' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $action );
	}

	/**
	 * Verifies a REST nonce header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function verify_rest_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! $nonce || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'qvb_invalid_nonce',
				__( 'Your session has expired. Please reload the page and try again.', 'qevora-booking' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Issues a signed timestamp used to reject instant bot submissions.
	 *
	 * @return string
	 */
	public static function form_token() {
		$issued = (string) time();
		$signature = hash_hmac( 'sha256', 'qvb_form|' . $issued, wp_salt( 'nonce' ) );

		return $issued . '|' . $signature;
	}

	/**
	 * Validates a signed form token and its age.
	 *
	 * @param string $token   Token.
	 * @param int    $minimum Minimum age in seconds.
	 * @return true|WP_Error
	 */
	public static function verify_form_token( $token, $minimum = 0 ) {
		$token = (string) $token;

		if ( false === strpos( $token, '|' ) ) {
			return new WP_Error(
				'qvb_invalid_token',
				__( 'This form has expired. Please reload the page and try again.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		list( $issued, $signature ) = explode( '|', $token, 2 );

		if ( ! ctype_digit( $issued ) ) {
			return new WP_Error(
				'qvb_invalid_token',
				__( 'This form has expired. Please reload the page and try again.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		$expected = hash_hmac( 'sha256', 'qvb_form|' . $issued, wp_salt( 'nonce' ) );

		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error(
				'qvb_invalid_token',
				__( 'This form has expired. Please reload the page and try again.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		$age = time() - (int) $issued;

		if ( $age < 0 || $age > self::TOKEN_LIFETIME ) {
			return new WP_Error(
				'qvb_expired_token',
				__( 'This form has expired. Please reload the page and try again.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		if ( $minimum > 0 && $age < $minimum ) {
			return new WP_Error(
				'qvb_form_too_fast',
				__( 'Please wait a moment before submitting the form.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Simple transient-based rate limiter keyed by action and salted IP.
	 *
	 * @param string $bucket  Action bucket.
	 * @param int    $limit   Maximum attempts in the window.
	 * @param int    $window  Window in seconds.
	 * @param string $extra   Optional extra key (for example an email address).
	 * @return true|WP_Error
	 */
	public static function rate_limit( $bucket, $limit, $window, $extra = '' ) {
		$limit  = max( 1, (int) $limit );
		$window = max( 60, (int) $window );
		$ip     = QVB_Utils::ip_hash();
		$key    = 'qvb_rl_' . md5( $bucket . '|' . $ip . '|' . strtolower( (string) $extra ) );
		$count  = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return new WP_Error(
				'qvb_rate_limited',
				__( 'Too many requests. Please wait a little and try again, or contact the salon directly.', 'qevora-booking' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Clears a rate limit bucket (used after a successful booking).
	 *
	 * @param string $bucket Bucket.
	 * @param string $extra  Extra key.
	 * @return void
	 */
	public static function clear_rate_limit( $bucket, $extra = '' ) {
		$key = 'qvb_rl_' . md5( $bucket . '|' . QVB_Utils::ip_hash() . '|' . strtolower( (string) $extra ) );
		delete_transient( $key );
	}

	/**
	 * Public form spam checks.
	 *
	 * @param array $payload Form payload.
	 * @return true|WP_Error
	 */
	public static function spam_check( $payload ) {
		if ( ! QVB_Settings::get( 'spam_protection' ) ) {
			return true;
		}

		if ( ! empty( $payload['qvb_website'] ) ) {
			return new WP_Error(
				'qvb_spam',
				__( 'Your booking could not be submitted. Please try again.', 'qevora-booking' ),
				array( 'status' => 400 )
			);
		}

		$token = isset( $payload['form_token'] ) ? (string) $payload['form_token'] : '';
		$check = self::verify_form_token( $token, (int) QVB_Settings::get( 'min_form_seconds' ) );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$limit = QVB_Security::rate_limit(
			'booking',
			(int) QVB_Settings::get( 'rate_limit_bookings' ),
			HOUR_IN_SECONDS,
			isset( $payload['email'] ) ? (string) $payload['email'] : ''
		);

		if ( is_wp_error( $limit ) ) {
			return $limit;
		}

		return true;
	}

	/**
	 * Rate limits booking lookups (reference + email guesses).
	 *
	 * @return true|WP_Error
	 */
	public static function lookup_rate_limit() {
		if ( ! QVB_Settings::get( 'spam_protection' ) ) {
			return true;
		}

		return self::rate_limit( 'lookup', (int) QVB_Settings::get( 'rate_limit_lookups' ), HOUR_IN_SECONDS );
	}

	/**
	 * Escapes a value for use inside an HTML attribute.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function attr( $value ) {
		return esc_attr( (string) $value );
	}
}
