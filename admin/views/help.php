<?php
/**
 * Help and system status.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( QVB_Capabilities::MANAGE ) ) {
	wp_die( esc_html__( 'You do not have permission to view this page.', 'qevora-booking' ), 403 );
}

global $wpdb, $wp_version;

$status      = QVB_Database::status();
$row_counts  = QVB_Database::row_counts();
$tables_ok   = ! in_array( false, $status, true );
$next_cron   = wp_next_scheduled( QVB_Cron::HOOK );
$booking_url = QVB_Utils::booking_page_url();
$emails_on   = (bool) QVB_Settings::get( 'enable_emails' );
$service_n   = (int) QVB_Services::count();
$staff_n     = (int) QVB_Staff::count();
$booking_n   = isset( $row_counts['bookings'] ) ? (int) $row_counts['bookings'] : 0;
$page_id     = (int) QVB_Settings::get( 'booking_page_id' );

$checks = array(
	array(
		'label' => __( 'Database tables', 'qevora-booking' ),
		'ok'    => $tables_ok,
		'note'  => $tables_ok ? __( 'All 8 tables exist.', 'qevora-booking' ) : __( 'One or more tables are missing. Use “Repair tables” below.', 'qevora-booking' ),
	),
	array(
		'label' => __( 'Booking page', 'qevora-booking' ),
		'ok'    => (bool) $booking_url,
		'note'  => $booking_url ? $booking_url : __( 'Add the [qevora_booking] shortcode to a published page.', 'qevora-booking' ),
	),
	array(
		'label' => __( 'Services', 'qevora-booking' ),
		'ok'    => $service_n > 0,
		/* translators: %d: number of services. */
		'note'  => sprintf( __( '%d active or inactive services.', 'qevora-booking' ), $service_n ),
	),
	array(
		'label' => __( 'Staff', 'qevora-booking' ),
		'ok'    => $staff_n > 0,
		/* translators: %d: number of staff. */
		'note'  => sprintf( __( '%d staff profiles.', 'qevora-booking' ), $staff_n ),
	),
	array(
		'label' => __( 'Email notifications', 'qevora-booking' ),
		'ok'    => $emails_on,
		'note'  => $emails_on ? __( 'Enabled. Send a test from Email Notifications.', 'qevora-booking' ) : __( 'Disabled — no booking emails will be sent.', 'qevora-booking' ),
	),
	array(
		'label' => __( 'WP-Cron', 'qevora-booking' ),
		'ok'    => (bool) $next_cron,
		'note'  => $next_cron ? sprintf( /* translators: %s: datetime. */ __( 'Next maintenance: %s', 'qevora-booking' ), wp_date( 'Y-m-d H:i', $next_cron ) ) : __( 'Not scheduled. Deactivate and reactivate the plugin.', 'qevora-booking' ),
	),
	array(
		'label' => __( 'HTTPS', 'qevora-booking' ),
		'ok'    => is_ssl(),
		'note'  => is_ssl() ? __( 'Secure connection detected.', 'qevora-booking' ) : __( 'Public booking forms should be served over HTTPS.', 'qevora-booking' ),
	),
);

$debug_lines = array(
	'Plugin: Qevora Booking ' . QVB_VERSION,
	'DB version: ' . get_option( QVB_Database::VERSION_OPTION, '—' ),
	'WordPress: ' . $wp_version,
	'PHP: ' . PHP_VERSION,
	'Database: ' . ( method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : '—' ),
	'Table prefix: ' . $wpdb->prefix,
	'Timezone: ' . QVB_Utils::timezone()->getName() . ' (site: ' . wp_timezone_string() . ')',
	'Locale: ' . get_locale(),
	'Permalinks: ' . ( get_option( 'permalink_structure' ) ? 'pretty' : 'plain' ),
	'REST: ' . rest_url( 'qevora/v1/' ),
	'Bookings: ' . $booking_n,
);

QVB_Admin::page_header(
	__( 'Help / System Status', 'qevora-booking' ),
	__( 'Quick-start guidance, integration snippets and a health check for this installation.', 'qevora-booking' )
);
?>

<div class="qvb-card">
	<h2><?php esc_html_e( 'System status', 'qevora-booking' ); ?></h2>
	<table class="widefat striped qvb-table qvb-health-table">
		<tbody>
			<?php foreach ( $checks as $check ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $check['label'] ); ?></th>
					<td>
						<span class="qvb-badge qvb-badge--<?php echo $check['ok'] ? 'confirmed' : 'pending'; ?>">
							<?php echo $check['ok'] ? esc_html__( 'OK', 'qevora-booking' ) : esc_html__( 'Needs attention', 'qevora-booking' ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $check['note'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( current_user_can( QVB_Capabilities::SETTINGS ) ) : ?>
		<div class="qvb-health-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="qvb_repair_tables" />
				<?php wp_nonce_field( 'qvb_repair_tables' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Repair tables', 'qevora-booking' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="qvb_run_maintenance" />
				<?php wp_nonce_field( 'qvb_run_maintenance' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Run maintenance now', 'qevora-booking' ); ?></button>
			</form>
		</div>
	<?php endif; ?>
</div>

<div class="qvb-columns">
	<div class="qvb-card">
		<h2><?php esc_html_e( 'Quick start', 'qevora-booking' ); ?></h2>
		<ol class="qvb-steps">
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'settings' ) ); ?>"><?php esc_html_e( 'Set your business name, timezone, currency and business hours.', 'qevora-booking' ); ?></a></li>
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'categories' ) ); ?>"><?php esc_html_e( 'Create service categories (optional, but great for grouping).', 'qevora-booking' ); ?></a></li>
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'services' ) ); ?>"><?php esc_html_e( 'Add services with duration, price and optional buffer.', 'qevora-booking' ); ?></a></li>
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'staff' ) ); ?>"><?php esc_html_e( 'Add staff, assign services and set working hours.', 'qevora-booking' ); ?></a></li>
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'availability' ) ); ?>"><?php esc_html_e( 'Fine-tune availability per staff member if needed.', 'qevora-booking' ); ?></a></li>
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'exceptions' ) ); ?>"><?php esc_html_e( 'Add holidays or special opening hours.', 'qevora-booking' ); ?></a></li>
			<li><a href="<?php echo esc_url( QVB_Admin::url( 'emails' ) ); ?>"><?php esc_html_e( 'Review email templates and send a test email.', 'qevora-booking' ); ?></a></li>
			<li><?php esc_html_e( 'Publish the shortcode on a page and test a real booking.', 'qevora-booking' ); ?></li>
		</ol>
	</div>

	<div class="qvb-card">
		<h2><?php esc_html_e( 'Add the booking form', 'qevora-booking' ); ?></h2>
		<p><?php esc_html_e( 'Shortcode:', 'qevora-booking' ); ?></p>
		<pre class="qvb-pre">[qevora_booking]</pre>
		<p><?php esc_html_e( 'Limit to a service, staff member or category:', 'qevora-booking' ); ?></p>
		<pre class="qvb-pre">[qevora_booking service="12"]

[qevora_booking staff="3" title="Book with Aisha"]

[qevora_booking category="2" show_title="0"]</pre>
		<p class="description"><?php esc_html_e( 'The same shortcode works with the Gutenberg block “Qevora Booking” and the Elementor widget of the same name.', 'qevora-booking' ); ?></p>
		<p>
			<?php if ( $page_id && get_post( $page_id ) ) : ?>
				<a class="button" href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Edit booking page', 'qevora-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View booking page', 'qevora-booking' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Create a booking page', 'qevora-booking' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>

<div class="qvb-card">
	<h2><?php esc_html_e( 'How bookings flow', 'qevora-booking' ); ?></h2>
	<div class="qvb-flow">
		<div class="qvb-flow-step"><span>1</span><?php esc_html_e( 'Customer picks a service, staff (or “any available”), date and time.', 'qevora-booking' ); ?></div>
		<div class="qvb-flow-step"><span>2</span><?php esc_html_e( 'Availability is re-checked on submit with a database lock, so double bookings are rejected.', 'qevora-booking' ); ?></div>
		<div class="qvb-flow-step"><span>3</span><?php esc_html_e( 'A pending or confirmed booking is created with snapshotted service and price data.', 'qevora-booking' ); ?></div>
		<div class="qvb-flow-step"><span>4</span><?php esc_html_e( 'Customer and staff receive emails with a secure manage link.', 'qevora-booking' ); ?></div>
		<div class="qvb-flow-step"><span>5</span><?php esc_html_e( 'Staff confirm, complete, cancel or reschedule from Bookings or the Calendar.', 'qevora-booking' ); ?></div>
		<div class="qvb-flow-step"><span>6</span><?php esc_html_e( 'Payment is collected at the salon — no gateway is involved.', 'qevora-booking' ); ?></div>
	</div>
</div>

<div class="qvb-columns">
	<div class="qvb-card">
		<h2><?php esc_html_e( 'Data stored', 'qevora-booking' ); ?></h2>
		<table class="widefat striped qvb-table">
			<tbody>
				<?php foreach ( $status as $table => $exists ) : ?>
					<tr>
						<th scope="row"><code><?php echo esc_html( QVB_Database::table( $table ) ); ?></code></th>
						<td><?php echo $exists ? esc_html( number_format_i18n( isset( $row_counts[ $table ] ) ? $row_counts[ $table ] : 0 ) ) : esc_html__( 'Missing', 'qevora-booking' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="qvb-card">
		<h2><?php esc_html_e( 'Debug information', 'qevora-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Copy this into a support request. It contains no customer data.', 'qevora-booking' ); ?></p>
		<textarea class="large-text code qvb-debug" rows="12" readonly id="qvb-debug-info"><?php echo esc_textarea( implode( "\n", $debug_lines ) ); ?></textarea>
		<p><button type="button" class="button qvb-copy" data-copy-target="#qvb-debug-info"><?php esc_html_e( 'Copy debug info', 'qevora-booking' ); ?></button></p>
	</div>
</div>

<div class="qvb-card">
	<h2><?php esc_html_e( 'How do I…?', 'qevora-booking' ); ?></h2>
	<div class="qvb-faq">
		<details>
			<summary><?php esc_html_e( 'Make my salon require approval for every booking', 'qevora-booking' ); ?></summary>
			<p><?php esc_html_e( 'Settings → Booking rules → Approval mode: “Hold as pending until approved”. You will get a staff email for each request; confirm it from Bookings.', 'qevora-booking' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Take a day off without deleting bookings', 'qevora-booking' ); ?></summary>
			<p><?php esc_html_e( 'Holidays / Exceptions → Add exception → Closed, then choose the date and scope. Existing bookings are not deleted; cancel them from Bookings if needed.', 'qevora-booking' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Give one staff member different hours', 'qevora-booking' ); ?></summary>
			<p><?php esc_html_e( 'Staff → Edit → set “Use business hours” to No, then fill in the weekly schedule. Breaks are optional.', 'qevora-booking' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Change how much a past booking cost', 'qevora-booking' ); ?></summary>
			<p><?php esc_html_e( 'Booking prices are snapshotted. Editing a service later never changes historical bookings; adjust the booking itself if you need to correct a price.', 'qevora-booking' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Stop emails from being sent', 'qevora-booking' ); ?></summary>
			<p><?php esc_html_e( 'Settings → Notifications → disable “Send booking emails”. Individual templates can be disabled too.', 'qevora-booking' ); ?></p>
		</details>
		<details>
			<summary><?php esc_html_e( 'Remove a customer’s personal data', 'qevora-booking' ); ?></summary>
			<p><?php esc_html_e( 'Customers → open the customer → “Anonymise customer”. Contact details are replaced and booking history is kept for reporting. The WordPress privacy tools also expose an eraser for this plugin.', 'qevora-booking' ); ?></p>
		</details>
	</div>
</div>

<div class="qvb-card qvb-credits">
	<p>
		<?php
		printf(
			/* translators: 1: plugin version, 2: WordPress version. */
			esc_html__( 'Qevora Booking %1$s · tested on WordPress %2$s · no online payments, ever.', 'qevora-booking' ),
			esc_html( QVB_VERSION ),
			esc_html( $wp_version )
		);
		?>
	</p>
</div>
