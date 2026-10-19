<?php
/**
 * Admin menus, assets, routing and shared UI helpers.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class QVB_Admin {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		require_once QVB_DIR . 'admin/class-admin-actions.php';
		require_once QVB_DIR . 'admin/class-bookings-table.php';
		require_once QVB_DIR . 'admin/class-customers-table.php';

		QVB_Admin_Actions::init();

		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_init', array( __CLASS__, 'activation_redirect' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Registers the admin menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$cap = QVB_Capabilities::MANAGE;

		add_menu_page(
			__( 'Qevora Booking', 'qevora-booking' ),
			__( 'Qevora Booking', 'qevora-booking' ),
			$cap,
			'qevora-booking',
			array( __CLASS__, 'render_page' ),
			'dashicons-calendar-alt',
			26
		);

		$pages = array(
			'dashboard'   => __( 'Dashboard', 'qevora-booking' ),
			'calendar'    => __( 'Calendar', 'qevora-booking' ),
			'bookings'    => __( 'Bookings', 'qevora-booking' ),
			'services'    => __( 'Services', 'qevora-booking' ),
			'categories'  => __( 'Categories', 'qevora-booking' ),
			'staff'       => __( 'Staff', 'qevora-booking' ),
			'availability' => __( 'Availability', 'qevora-booking' ),
			'exceptions'  => __( 'Holidays / Exceptions', 'qevora-booking' ),
			'customers'   => __( 'Customers', 'qevora-booking' ),
		);

		foreach ( $pages as $slug => $label ) {
			add_submenu_page(
				'qevora-booking',
				$label,
				$label,
				$cap,
				'qevora-booking-' . $slug,
				array( __CLASS__, 'render_page' )
			);
		}

		add_submenu_page(
			'qevora-booking',
			__( 'Email Notifications', 'qevora-booking' ),
			__( 'Email Notifications', 'qevora-booking' ),
			QVB_Capabilities::SETTINGS,
			'qevora-booking-emails',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'qevora-booking',
			__( 'Settings', 'qevora-booking' ),
			__( 'Settings', 'qevora-booking' ),
			QVB_Capabilities::SETTINGS,
			'qevora-booking-settings',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'qevora-booking',
			__( 'Help / System Status', 'qevora-booking' ),
			__( 'Help / System Status', 'qevora-booking' ),
			$cap,
			'qevora-booking-help',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Builds an admin URL.
	 *
	 * @param string $page Page slug without prefix, empty for dashboard.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page = '', $args = array() ) {
		$slug = 'qevora-booking' . ( $page ? '-' . $page : '' );
		$url  = admin_url( 'admin.php?page=' . $slug );
		if ( $args ) {
			$url = add_query_arg( $args, $url );
		}
		return $url;
	}

	/**
	 * Builds an admin-post URL.
	 *
	 * @param string $action Action.
	 * @param array  $args   Args.
	 * @return string
	 */
	public static function action_url( $action, $args = array() ) {
		$args = array_merge( array( 'action' => $action ), $args );
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Redirects and exits.
	 *
	 * @param string $url URL.
	 * @return void
	 */
	public static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Stores a one-time admin notice for the current user.
	 *
	 * @param string $message Message.
	 * @param string $type    success|error|warning|info.
	 * @return void
	 */
	public static function flash( $message, $type = 'success' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		set_transient(
			'qvb_admin_notice_' . $user_id,
			array(
				'message' => (string) $message,
				'type'    => $type,
			),
			120
		);
	}

	/**
	 * Prints admin notices.
	 *
	 * @return void
	 */
	public static function notices() {
		$user_id  = get_current_user_id();
		$transient = 'qvb_admin_notice_' . $user_id;
		$notice    = get_transient( $transient );

		if ( $notice && is_array( $notice ) ) {
			delete_transient( $transient );
			$type = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'success';
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( isset( $notice['message'] ) ? $notice['message'] : '' )
			);
		}

		if ( isset( $_GET['qvb_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Changes saved.', 'qevora-booking' ) . '</p></div>';
		}
	}

	/**
	 * Redirects first-time activations to the dashboard.
	 *
	 * @return void
	 */
	public static function activation_redirect() {
		if ( wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() || ! current_user_can( QVB_Capabilities::MANAGE ) ) {
			return;
		}

		$transient = 'qvb_activation_redirect_' . get_current_user_id();
		if ( ! get_transient( $transient ) ) {
			return;
		}
		delete_transient( $transient );

		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		self::redirect( self::url() );
	}

	/**
	 * Enqueues admin assets on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, 'qevora-booking' ) ) {
			return;
		}

		wp_enqueue_style( 'qvb-admin', QVB_URL . 'admin/css/admin.css', array(), QVB_VERSION );
		wp_enqueue_script( 'qvb-admin', QVB_URL . 'admin/js/admin.js', array(), QVB_VERSION, true );
		wp_enqueue_media();

		wp_localize_script(
			'qvb-admin',
			'qvbAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'qevora/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'today'   => QVB_Utils::today(),
				'strings' => array(
					'chooseDate'    => __( 'Choose a date to load available times.', 'qevora-booking' ),
					'loading'       => __( 'Loading…', 'qevora-booking' ),
					'noSlots'       => __( 'No times available on this date.', 'qevora-booking' ),
					'loadError'     => __( 'Could not load availability.', 'qevora-booking' ),
					'selectService' => __( 'Please select a service first.', 'qevora-booking' ),
					'removeRow'     => __( 'Remove', 'qevora-booking' ),
					'copied'        => __( 'Copied.', 'qevora-booking' ),
					'confirmDelete' => __( 'Delete this item? This cannot be undone.', 'qevora-booking' ),
					'confirmCancel' => __( 'Cancel this booking?', 'qevora-booking' ),
					'mediaTitle'    => __( 'Choose an image', 'qevora-booking' ),
					'mediaButton'   => __( 'Use this image', 'qevora-booking' ),
					'mediaRemove'   => __( 'Remove image', 'qevora-booking' ),
				),
			)
		);
	}

	/**
	 * Routes the current admin page to a view.
	 *
	 * @return void
	 */
	public static function render_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'qevora-booking'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = str_replace( 'qevora-booking-', '', $page );
		if ( '' === $page || 'qevora-booking' === $page ) {
			$page = 'dashboard';
		}

		$settings_pages = array( 'settings', 'emails' );
		$cap            = in_array( $page, $settings_pages, true ) ? QVB_Capabilities::SETTINGS : QVB_Capabilities::MANAGE;

		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'qevora-booking' ), 403 );
		}

		$views = array(
			'dashboard'    => 'dashboard',
			'calendar'     => 'calendar',
			'bookings'     => 'bookings',
			'services'     => ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ? 'service-edit' : 'services', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'categories'   => ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ? 'category-edit' : 'categories', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'staff'        => ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ? 'staff-edit' : 'staff', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'availability' => 'availability',
			'exceptions'   => ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] ? 'exception-edit' : 'exceptions', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'customers'    => ! empty( $_GET['action'] ) && 'view' === $_GET['action'] ? 'customer-view' : 'customers', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'emails'       => 'emails',
			'settings'     => 'settings',
			'help'         => 'help',
		);

		if ( ! isset( $views[ $page ] ) ) {
			$page = 'dashboard';
		}

		$view = $views[ $page ];

		if ( 'bookings' === $page && ! empty( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
			if ( 'new' === $action ) {
				$view = 'booking-new';
			} elseif ( 'edit' === $action ) {
				$view = 'booking-edit';
			}
		}

		$file = QVB_DIR . 'admin/views/' . $view . '.php';

		if ( ! file_exists( $file ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'This screen could not be found.', 'qevora-booking' ) . '</p></div>';
			return;
		}

		$current_page = $page;
		wp_enqueue_style( 'qvb-admin' );

		include $file;
	}

	/**
	 * Page header.
	 *
	 * @param string $title    Title.
	 * @param string $subtitle Subtitle.
	 * @param array  $actions  Each: label, url, class.
	 * @return void
	 */
	public static function page_header( $title, $subtitle = '', $actions = array() ) {
		echo '<div class="qvb-admin-header"><div class="qvb-admin-header__text">';
		echo '<span class="qvb-admin-eyebrow">' . esc_html__( 'Qevora Booking', 'qevora-booking' ) . '</span>';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		if ( $subtitle ) {
			echo '<p class="qvb-admin-subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div>';

		if ( $actions ) {
			echo '<div class="qvb-admin-header__actions">';
			foreach ( $actions as $action ) {
				$class = isset( $action['class'] ) ? $action['class'] : 'page-title-action';
				printf(
					'<a class="%s" href="%s">%s</a>',
					esc_attr( $class ),
					esc_url( $action['url'] ),
					esc_html( $action['label'] )
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Status badge markup.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_badge( $status ) {
		return sprintf(
			'<span class="qvb-badge qvb-badge--%s">%s</span>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_html( QVB_Utils::status_label( $status ) )
		);
	}

	/**
	 * Renders option tags.
	 *
	 * @param array $options  value => label.
	 * @param mixed $selected Selected value.
	 * @return string
	 */
	public static function options_html( $options, $selected ) {
		$html = '';
		foreach ( $options as $value => $label ) {
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( (string) $selected, (string) $value, false ),
				esc_html( $label )
			);
		}
		return $html;
	}

	/**
	 * Timezone choices.
	 *
	 * @return array
	 */
	public static function timezone_choices() {
		$choices = array( '' => sprintf( /* translators: %s: site timezone. */ __( 'Site timezone (%s)', 'qevora-booking' ), wp_timezone_string() ) );
		foreach ( timezone_identifiers_list() as $timezone ) {
			$choices[ $timezone ] = $timezone;
		}
		return $choices;
	}

	/**
	 * Published pages for the booking-page selector.
	 *
	 * @return array
	 */
	public static function page_choices() {
		$choices = array( 0 => __( 'Auto-detect a page with the booking shortcode', 'qevora-booking' ) );
		$pages   = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
		foreach ( $pages as $page ) {
			$choices[ (int) $page->ID ] = $page->post_title;
		}
		return $choices;
	}

	/**
	 * Whether the current user can see full PII.
	 *
	 * @return bool
	 */
	public static function can_view_pii() {
		return current_user_can( QVB_Capabilities::PII );
	}

	/**
	 * Customer email, masked when needed.
	 *
	 * @param object $booking Booking.
	 * @return string
	 */
	public static function display_email( $booking ) {
		return self::can_view_pii() ? $booking->customer_email : QVB_Utils::mask_email( $booking->customer_email );
	}

	/**
	 * Customer phone, masked when needed.
	 *
	 * @param object $booking Booking.
	 * @return string
	 */
	public static function display_phone( $booking ) {
		return self::can_view_pii() ? $booking->customer_phone : QVB_Utils::mask_phone( $booking->customer_phone );
	}

	/**
	 * Staff color map.
	 *
	 * @return array<int,string>
	 */
	public static function staff_colors() {
		$colors = array();
		foreach ( QVB_Staff::all() as $member ) {
			$colors[ (int) $member->id ] = QVB_Utils::hex_color( $member->color );
		}
		return $colors;
	}

	/**
	 * Returns a booking or dies with a 404.
	 *
	 * @param int $id Booking ID.
	 * @return object
	 */
	public static function get_booking_or_die( $id ) {
		$booking = QVB_Bookings::get( $id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'That booking could not be found.', 'qevora-booking' ), 404 );
		}
		return $booking;
	}
}
