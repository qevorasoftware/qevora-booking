<?php
/**
 * Qevora Booking smoke test.
 *
 * Runs inside WordPress Playground (PHP 8.x + SQLite). Covers the schema, the
 * booking engine, every admin screen, the public shortcode and the REST API.
 *
 * Usage:
 *   node node_modules/@wp-playground/cli/cli.js php \
 *     --auto-mount=/path/to/wordpress \
 *     --mount=/path/to/qevora-booking:/wordpress/wp-content/plugins/qevora-booking \
 *     --mount=/path/to/tools:/tools \
 *     --wordpress-install-mode=install-from-existing-files \
 *     -- /wordpress/wp-content/plugins/qevora-booking/tests/smoke.php
 *
 * Exit code 0 means every assertion passed.
 */

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php';
$_SERVER['SERVER_NAME'] = 'localhost';

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$GLOBALS['qvb_pass'] = 0;
$GLOBALS['qvb_fail'] = 0;

/**
 * Records a single assertion.
 *
 * @param bool   $condition Condition.
 * @param string $label     Human readable label.
 * @param string $detail    Extra context on failure.
 * @return void
 */
function qvb_assert( $condition, $label, $detail = '' ) {
	if ( $condition ) {
		++$GLOBALS['qvb_pass'];
		echo "PASS {$label}\n";
		return;
	}

	++$GLOBALS['qvb_fail'];
	echo 'FAIL ' . $label . ( $detail ? " -> {$detail}" : '' ) . "\n";
}

/**
 * Turns a WP_Error into a short string.
 *
 * @param mixed $value Value.
 * @return string
 */
function qvb_error_text( $value ) {
	if ( ! is_wp_error( $value ) ) {
		return '';
	}

	$data = $value->get_error_data();

	return $value->get_error_code() . ( is_array( $data ) && isset( $data['status'] ) ? ':' . $data['status'] : '' ) . ' ' . $value->get_error_message();
}

echo "== Bootstrap\n";

$qvb_late_activation = false;

if ( ! is_plugin_active( 'qevora-booking/qevora-booking.php' ) ) {
	activate_plugin( 'qevora-booking/qevora-booking.php' );
	$qvb_late_activation = true;
}

wp_set_current_user( 1 );

if ( $qvb_late_activation ) {
	// Activation happens after WordPress has already fired `init`, so the
	// plugin's normal boot hook never runs. Register the public-facing pieces
	// that this suite exercises by hand.
	if ( ! shortcode_exists( 'qevora_booking' ) ) {
		QVB_Shortcodes::init();
	}

	if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'qevora/booking' ) ) {
		QVB_Gutenberg::register_block();
	}

	require_once QVB_DIR . 'public/class-public.php';
	QVB_Public::init();
	QVB_REST_API::init();
	QVB_Notifications::init();
}

qvb_assert( class_exists( 'QVB_Bookings' ), 'plugin classes loaded' );

qvb_assert( current_user_can( QVB_Capabilities::MANAGE ), 'administrator has the manage capability' );

$tables = QVB_Database::status();
qvb_assert( ! in_array( false, $tables, true ), 'all eight tables exist', wp_json_encode( $tables ) );

// Deterministic fixture set.
QVB_Database::drop_tables();
delete_option( 'qvb_settings' );
delete_option( 'qvb_emails' );
QVB_Settings::flush_cache();
QVB_Notifications::flush_cache();
QVB_Database::install();
QVB_Settings::install_defaults();

$hours = array();

for ( $day = 0; $day < 7; $day++ ) {
	$hours[ $day ] = array(
		'enabled'     => 1,
		'start'       => '09:00',
		'end'         => '19:00',
		'break_start' => '13:00',
		'break_end'   => '14:00',
	);
}

QVB_Settings::update(
	array(
		'timezone'        => 'Asia/Kolkata',
		'slot_interval'   => 30,
		'min_notice'      => 0,
		'max_advance'     => 365,
		'approval_mode'   => 'auto',
		'enable_emails'   => 1,
		'spam_protection' => 0,
		'date_format'     => 'D, M j, Y',
		'time_format'     => 'g:i a',
	)
);

$settings                   = QVB_Settings::all();
$settings['business_hours'] = $hours;
update_option( 'qvb_settings', $settings );
QVB_Settings::flush_cache();

echo "\n== Services, staff and availability\n";

$category = QVB_Categories::save( array( 'name' => 'Massage', 'status' => 'active' ) );
$service  = QVB_Services::save(
	array(
		'name'        => 'Signature Massage',
		'category_id' => $category,
		'duration'    => 60,
		'buffer_time' => 15,
		'price'       => 1500,
		'status'      => 'active',
	)
);

$staff_a = QVB_Staff::save( array( 'name' => 'Aisha', 'status' => 'active', 'use_business_hours' => 1 ) );
$staff_b = QVB_Staff::save( array( 'name' => 'Nina', 'status' => 'active', 'use_business_hours' => 1 ) );

QVB_Staff::set_services( $staff_a, array( $service ) );
QVB_Staff::set_services( $staff_b, array( $service ) );

qvb_assert( ! is_wp_error( $service ) && $service > 0, 'service created', qvb_error_text( $service ) );
qvb_assert( 1 === count( QVB_Staff::service_ids( $staff_a ) ) && (int) QVB_Staff::service_ids( $staff_a )[0] === (int) $service, 'staff service assignment stored' );

$date  = QVB_Utils::now()->modify( 'next monday' )->format( 'Y-m-d' );
$slots = QVB_Availability::get_slots( $service, 0, $date );

qvb_assert( 16 === count( $slots ), 'slot grid generated (16 slots)', count( $slots ) );
qvb_assert( isset( $slots['10:00'] ), '10:00 offered' );
qvb_assert( ! isset( $slots['13:00'] ) && ! isset( $slots['13:30'] ), 'break excluded from the grid' );
qvb_assert( ! isset( $slots['18:30'] ), 'last slot respects duration plus buffer' );

echo "\n== Booking engine\n";

$emails = array();
add_filter(
	'pre_wp_mail',
	function ( $return, $atts ) use ( &$emails ) {
		$emails[] = $atts['to'];
		return true;
	},
	10,
	2
);

$first = QVB_Bookings::create(
	array(
		'service_id' => $service,
		'staff_id'   => $staff_a,
		'date'       => $date,
		'time'       => '10:00',
		'name'       => 'Priya Sharma',
		'email'      => 'priya@example.com',
		'phone'      => '+91 98765 43210',
		'notes'      => 'First visit',
		'consent'    => 1,
	),
	'frontend'
);

qvb_assert( ! is_wp_error( $first ), 'booking created', qvb_error_text( $first ) );
qvb_assert( is_array( $first ) && 'confirmed' === $first['status'], 'auto-approval confirms instantly' );
qvb_assert( count( $emails ) >= 2, 'customer and staff emails dispatched', count( $emails ) );
qvb_assert( ! empty( $first['manage_url'] ), 'manage URL returned' );

$blocked = QVB_Availability::get_slots( $service, $staff_a, $date );
qvb_assert( ! isset( $blocked['10:00'] ), 'booked slot disappears for that staff member' );

$any = QVB_Availability::get_slots( $service, 0, $date );
qvb_assert( isset( $any['10:00'] ) && in_array( $staff_b, $any['10:00']['staff_ids'], true ), 'slot still free for the other staff member' );

$conflict = QVB_Bookings::create(
	array(
		'service_id' => $service,
		'staff_id'   => $staff_a,
		'date'       => $date,
		'time'       => '10:00',
		'name'       => 'Someone Else',
		'email'      => 'other@example.com',
		'phone'      => '9999999999',
		'consent'    => 1,
	),
	'frontend'
);

qvb_assert( is_wp_error( $conflict ), 'double booking rejected', qvb_error_text( $conflict ) );

$overlap = QVB_Bookings::create(
	array(
		'service_id' => $service,
		'staff_id'   => $staff_a,
		'date'       => $date,
		'time'       => '10:30',
		'name'       => 'Overlap',
		'email'      => 'overlap@example.com',
		'phone'      => '9999999998',
		'consent'    => 1,
	),
	'frontend'
);

qvb_assert( is_wp_error( $overlap ), 'overlapping start time rejected' );

$past = QVB_Bookings::create(
	array(
		'service_id' => $service,
		'staff_id'   => $staff_a,
		'date'       => QVB_Utils::date_from_now( -2 ),
		'time'       => '10:00',
		'name'       => 'Past',
		'email'      => 'past@example.com',
		'consent'    => 1,
	),
	'frontend'
);

qvb_assert( is_wp_error( $past ), 'past bookings rejected', qvb_error_text( $past ) );

$any_staff = QVB_Bookings::create(
	array(
		'service_id' => $service,
		'staff_id'   => 0,
		'date'       => $date,
		'time'       => '10:00',
		'name'       => 'Neha',
		'email'      => 'neha@example.com',
		'phone'      => '8888888888',
		'consent'    => 1,
	),
	'frontend'
);

qvb_assert( ! is_wp_error( $any_staff ) && (int) $any_staff['booking']->staff_id === (int) $staff_b, 'any-available picks the free staff member', qvb_error_text( $any_staff ) );

$cancel = QVB_Bookings::cancel( $first['id'], array( 'context' => 'admin', 'reason' => 'Test cancel' ) );
qvb_assert( ! is_wp_error( $cancel ) && 'cancelled' === $cancel->status, 'admin can cancel', qvb_error_text( $cancel ) );

$freed = QVB_Availability::get_slots( $service, $staff_a, $date );
qvb_assert( isset( $freed['10:00'] ), 'cancelling releases the slot' );

$rebook = QVB_Bookings::create(
	array(
		'service_id' => $service,
		'staff_id'   => $staff_a,
		'date'       => $date,
		'time'       => '10:00',
		'name'       => 'Priya Again',
		'email'      => 'priya2@example.com',
		'phone'      => '+91 98765 43210',
		'consent'    => 1,
	),
	'frontend'
);

qvb_assert( ! is_wp_error( $rebook ), 'slot can be rebooked after cancellation', qvb_error_text( $rebook ) );

$rescheduled = QVB_Bookings::reschedule( $rebook['id'], $date, '11:30', $staff_a, array( 'context' => 'admin' ) );
qvb_assert( ! is_wp_error( $rescheduled ) && '11:30' === $rescheduled->start_time, 'reschedule moves the booking', qvb_error_text( $rescheduled ) );
qvb_assert( 1 === (int) $rescheduled->reschedule_count, 'reschedule counter increments' );

$completed = QVB_Bookings::update_status( $rebook['id'], 'completed', array( 'context' => 'admin' ) );
qvb_assert( ! is_wp_error( $completed ) && 'completed' === $completed->status, 'status can move confirmed -> completed', qvb_error_text( $completed ) );

$illegal = QVB_Bookings::update_status( $rebook['id'], 'pending', array( 'context' => 'admin' ) );
qvb_assert( is_wp_error( $illegal ), 'illegal status transition rejected', qvb_error_text( $illegal ) );

qvb_assert( count( $emails ) >= 4, 'notification pipeline active', count( $emails ) );

echo "\n== Admin screens\n";

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once QVB_DIR . 'admin/class-admin.php';
require_once QVB_DIR . 'admin/class-admin-actions.php';
require_once QVB_DIR . 'admin/class-bookings-table.php';
require_once QVB_DIR . 'admin/class-customers-table.php';

QVB_Admin::init();
set_current_screen( 'dashboard' );

// The staff/service/category forms open wp.media(). The admin script must pull
// in the media library so it can never be printed before media-views.js.
QVB_Admin::enqueue( 'toplevel_page_qevora-booking' );

$qvb_admin_deps = isset( wp_scripts()->registered['qvb-admin'] ) ? (array) wp_scripts()->registered['qvb-admin']->deps : array();

qvb_assert(
	did_action( 'wp_enqueue_media' ) > 0 && in_array( 'qvb-admin', (array) wp_scripts()->queue, true ),
	'admin assets enqueue with the media library',
	wp_json_encode( $qvb_admin_deps )
);

// The glass skin is a second stylesheet layered on top of admin.css. Both must
// be queued, and the body class that scopes the skin must be added on plugin
// screens only.
$qvb_glass_queued = in_array( 'qvb-admin-glass', (array) wp_styles()->queue, true );
$qvb_glass_file   = file_exists( QVB_DIR . 'admin/css/glass.css' ) && file_exists( QVB_DIR . 'public/css/glass.css' );

qvb_assert(
	$qvb_glass_queued && $qvb_glass_file,
	'glass stylesheet builds on admin.css and ships on both sides',
	wp_json_encode( array( $qvb_glass_queued, $qvb_glass_file ) )
);

$_GET['page'] = 'qevora-booking-settings';
$qvb_body_plugin = QVB_Admin::body_class( 'wp-admin' );

$_GET['page'] = 'edit';
$qvb_body_other = QVB_Admin::body_class( 'wp-admin' );

unset( $_GET['page'] );

qvb_assert(
	'wp-admin qvb-screen' === $qvb_body_plugin && 'wp-admin' === $qvb_body_other,
	'glass body class only applies to plugin screens',
	$qvb_body_plugin . ' | ' . $qvb_body_other
);

$booking_id  = (int) $rebook['id'];
$customer_id = (int) $rebook['booking']->customer_id;

$screens = array(
	'dashboard'    => array( 'qevora-booking' ),
	'calendar'     => array( 'qevora-booking-calendar' ),
	'calendar-week'=> array( 'qevora-booking-calendar', array( 'view' => 'week' ) ),
	'calendar-day' => array( 'qevora-booking-calendar', array( 'view' => 'day', 'date' => QVB_Utils::today() ) ),
	'bookings'     => array( 'qevora-booking-bookings' ),
	'booking-new'  => array( 'qevora-booking-bookings', array( 'action' => 'new' ) ),
	'booking-edit' => array( 'qevora-booking-bookings', array( 'action' => 'edit', 'booking' => $booking_id ) ),
	'services'     => array( 'qevora-booking-services' ),
	'service-edit' => array( 'qevora-booking-services', array( 'action' => 'edit', 'id' => (int) $service ) ),
	'categories'   => array( 'qevora-booking-categories' ),
	'staff'        => array( 'qevora-booking-staff' ),
	'staff-edit'   => array( 'qevora-booking-staff', array( 'action' => 'edit', 'id' => (int) $staff_a ) ),
	'availability' => array( 'qevora-booking-availability', array( 'staff_id' => (int) $staff_a ) ),
	'exceptions'   => array( 'qevora-booking-exceptions' ),
	'customers'    => array( 'qevora-booking-customers' ),
	'customer-view'=> array( 'qevora-booking-customers', array( 'action' => 'view', 'customer' => $customer_id ) ),
	'emails'       => array( 'qevora-booking-emails' ),
	'email-edit'   => array( 'qevora-booking-emails', array( 'template' => 'customer_confirmed' ) ),
	'settings'     => array( 'qevora-booking-settings' ),
	'help'         => array( 'qevora-booking-help' ),
);

$wrapped_screens = 0;

foreach ( $screens as $label => $definition ) {
	$_GET     = array( 'page' => $definition[0] );
	$_REQUEST = $_GET;

	if ( isset( $definition[1] ) ) {
		$_GET     = array_merge( $_GET, $definition[1] );
		$_REQUEST = $_GET;
	}

	$errors = array();

	set_error_handler(
		function ( $number, $message, $file, $line ) use ( &$errors ) {
			$errors[] = "{$message} ({$file}:{$line})";
			return true;
		}
	);

	ob_start();
	$fatal = null;

	try {
		QVB_Admin::render_page();
	} catch ( Throwable $throwable ) {
		$fatal = $throwable->getMessage();
	}

	$markup = ob_get_clean();
	restore_error_handler();

	if ( $fatal ) {
		qvb_assert( false, "screen {$label}", 'fatal: ' . $fatal );
		continue;
	}

	if ( $errors ) {
		qvb_assert( false, "screen {$label}", implode( ' | ', array_slice( $errors, 0, 2 ) ) );
		continue;
	}

	qvb_assert( strlen( $markup ) > 500, "screen {$label} renders", strlen( $markup ) . ' bytes' );

	if ( false !== strpos( $markup, 'class="wrap qvb-wrap"' ) ) {
		++$wrapped_screens;
	}
}

qvb_assert( count( $screens ) === $wrapped_screens, 'every admin screen uses the WordPress .wrap container', $wrapped_screens . '/' . count( $screens ) );

echo "\n== Public output\n";

wp_set_current_user( 0 );
$form = do_shortcode( '[qevora_booking]' );
qvb_assert( false !== strpos( $form, 'data-qvb-app' ) && false !== strpos( $form, 'data-qvb-indicators' ) && false !== strpos( $form, 'qvb-form' ), 'shortcode renders the wizard for guests', substr( wp_strip_all_tags( $form ), 0, 160 ) );

$booking_row = QVB_Bookings::get( $booking_id );
$manage      = QVB_Public::render_manage( $rebook['reference'], QVB_Bookings::token( $booking_row ) );
qvb_assert( false !== strpos( $manage, $rebook['reference'] ), 'manage page shows the booking with a valid token', strlen( $manage ) . ' bytes' );

$invalid_manage = QVB_Public::render_manage( $rebook['reference'], 'nope' );
qvb_assert( false === strpos( $invalid_manage, $rebook['reference'] ), 'manage page hides data for an invalid token' );

echo "\n== REST API\n";

$registry = WP_Block_Type_Registry::get_instance();
qvb_assert( $registry->is_registered( 'qevora/booking' ), 'Gutenberg block registered' );

$request  = new WP_REST_Request( 'GET', '/qevora/v1/services' );
$response = rest_do_request( $request );
qvb_assert( 200 === $response->get_status(), 'GET /services succeeds' );

$request = new WP_REST_Request( 'GET', '/qevora/v1/availability' );
$request->set_param( 'service_id', (int) $service );
$request->set_param( 'date', QVB_Utils::date_from_now( 3 ) );
$response = rest_do_request( $request );
$body     = $response->get_data();
qvb_assert( 200 === $response->get_status() && ! empty( $body['slots'] ), 'GET /availability returns slots' );

$guest_nonce = wp_create_nonce( 'wp_rest' );
$free_slot   = QVB_Utils::date_from_now( 4 );
$free_times  = QVB_Availability::get_slots( (int) $service, 0, $free_slot );

$request = new WP_REST_Request( 'POST', '/qevora/v1/bookings' );
$request->set_body_params(
	array(
		'service_id' => (int) $service,
		'date'       => $free_slot,
		'time'       => '10:00',
		'name'       => 'No Nonce',
		'email'      => 'nonce@example.com',
		'form_token' => QVB_Security::form_token(),
	)
);
$response = rest_do_request( $request );
qvb_assert( 403 === $response->get_status(), 'POST /bookings without a nonce is rejected', (string) $response->get_status() );

$request = new WP_REST_Request( 'POST', '/qevora/v1/bookings' );
$request->set_header( 'X-WP-Nonce', $guest_nonce );
$request->set_body_params(
	array(
		'service_id' => (int) $service,
		'date'       => $free_slot,
		'time'       => (string) array_key_first( $free_times ),
		'name'       => 'Rest Guest',
		'email'      => 'rest@example.com',
		'phone'      => '+91 90000 00000',
		'form_token' => QVB_Security::form_token(),
	)
);
$response = rest_do_request( $request );
$body     = $response->get_data();
qvb_assert( 201 === $response->get_status(), 'POST /bookings creates a booking', qvb_error_text( $body ) . ' ' . wp_json_encode( $body ) );

if ( 201 === $response->get_status() ) {
	parse_str( (string) wp_parse_url( $body['manage_url'], PHP_URL_QUERY ), $query );

	$request = new WP_REST_Request( 'GET', '/qevora/v1/booking' );
	$request->set_param( 'reference', $body['reference'] );
	$request->set_param( 'token', isset( $query['token'] ) ? $query['token'] : '' );
	$response = rest_do_request( $request );
	qvb_assert( 200 === $response->get_status(), 'GET /booking works with the signed token' );

	$request = new WP_REST_Request( 'GET', '/qevora/v1/admin/bookings' );
	$response = rest_do_request( $request );
	qvb_assert( 401 === $response->get_status() || 403 === $response->get_status(), 'admin REST routes require a capability', (string) $response->get_status() );
}

echo "\n== Emails\n";

$templates = QVB_Notifications::templates();
qvb_assert( count( $templates ) >= 7, 'seven email templates registered', count( $templates ) );

$preview = QVB_Notifications::preview( 'customer_confirmed' );
qvb_assert( false !== strpos( $preview, 'QVB-' ) && false === strpos( $preview, '{customer_name}' ), 'template preview resolves placeholders' );

qvb_assert( QVB_Notifications::send_test( 'owner@example.com' ), 'test email dispatches through wp_mail' );

echo "\nPASSES: {$GLOBALS['qvb_pass']}\nFAILS: {$GLOBALS['qvb_fail']}\n";

exit( $GLOBALS['qvb_fail'] > 0 ? 1 : 0 );
