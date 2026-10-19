<?php
/**
 * Plugin settings.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( QVB_Capabilities::SETTINGS ) ) {
	wp_die( esc_html__( 'You do not have permission to manage settings.', 'qevora-booking' ), 403 );
}

$settings  = QVB_Settings::all();
$weekdays  = QVB_Utils::weekdays();
$hours     = isset( $settings['business_hours'] ) && is_array( $settings['business_hours'] ) ? $settings['business_hours'] : array();
$sample    = QVB_Utils::now()->format( 'Y-m-d' );
$currencies = array( 'INR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'AED', 'SGD', 'ZAR', 'NZD', 'CHF', 'JPY', 'MYR', 'PHP', 'LKR', 'BDT', 'PKR', 'KES', 'NGN' );

QVB_Admin::page_header(
	__( 'Settings', 'qevora-booking' ),
	__( 'Business hours, booking rules, currency and privacy. All times use the timezone selected below.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Email templates', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'emails' ),
			'class' => 'button',
		),
	)
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-settings-form">
	<input type="hidden" name="action" value="qvb_save_settings" />
	<?php wp_nonce_field( 'qvb_save_settings' ); ?>

	<nav class="qvb-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'qevora-booking' ); ?>">
		<a href="#general"><?php esc_html_e( 'General', 'qevora-booking' ); ?></a>
		<a href="#booking-rules"><?php esc_html_e( 'Booking rules', 'qevora-booking' ); ?></a>
		<a href="#cancellation"><?php esc_html_e( 'Cancellation &amp; rescheduling', 'qevora-booking' ); ?></a>
		<a href="#hours"><?php esc_html_e( 'Business hours', 'qevora-booking' ); ?></a>
		<a href="#notifications"><?php esc_html_e( 'Notifications', 'qevora-booking' ); ?></a>
		<a href="#customer"><?php esc_html_e( 'Customer form', 'qevora-booking' ); ?></a>
		<a href="#privacy"><?php esc_html_e( 'Privacy &amp; data', 'qevora-booking' ); ?></a>
	</nav>

	<div class="qvb-card qvb-settings-section" id="general">
		<h2><?php esc_html_e( 'General', 'qevora-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Shown on emails, the booking page and customer-facing messages.', 'qevora-booking' ); ?></p>

		<div class="qvb-form-grid qvb-form-grid--two">
			<div class="qvb-field">
				<label for="qvb-business-name"><?php esc_html_e( 'Business name', 'qevora-booking' ); ?></label>
				<input type="text" class="regular-text" id="qvb-business-name" name="qvb_settings[business_name]" value="<?php echo esc_attr( $settings['business_name'] ); ?>" />
			</div>
			<div class="qvb-field">
				<label for="qvb-business-email"><?php esc_html_e( 'Public business email', 'qevora-booking' ); ?></label>
				<input type="email" class="regular-text" id="qvb-business-email" name="qvb_settings[business_email]" value="<?php echo esc_attr( $settings['business_email'] ); ?>" />
			</div>
			<div class="qvb-field">
				<label for="qvb-business-phone"><?php esc_html_e( 'Business phone', 'qevora-booking' ); ?></label>
				<input type="text" class="regular-text" id="qvb-business-phone" name="qvb_settings[business_phone]" value="<?php echo esc_attr( $settings['business_phone'] ); ?>" />
			</div>
			<div class="qvb-field">
				<label for="qvb-business-address"><?php esc_html_e( 'Business address', 'qevora-booking' ); ?></label>
				<textarea class="regular-text" rows="2" id="qvb-business-address" name="qvb_settings[business_address]"><?php echo esc_textarea( $settings['business_address'] ); ?></textarea>
			</div>
		</div>

		<div class="qvb-form-grid qvb-form-grid--three">
			<div class="qvb-field">
				<label for="qvb-timezone"><?php esc_html_e( 'Timezone', 'qevora-booking' ); ?></label>
				<select class="regular-text" id="qvb-timezone" name="qvb_settings[timezone]">
					<?php echo QVB_Admin::options_html( QVB_Admin::timezone_choices(), $settings['timezone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: current time in the business timezone. */ __( 'Current business time: %s', 'qevora-booking' ), QVB_Utils::now()->format( 'Y-m-d H:i' ) ) ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-date-format"><?php esc_html_e( 'Date format', 'qevora-booking' ); ?></label>
				<input type="text" class="regular-text" id="qvb-date-format" name="qvb_settings[date_format]" value="<?php echo esc_attr( $settings['date_format'] ); ?>" />
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: formatted sample date. */ __( 'Preview: %s', 'qevora-booking' ), QVB_Utils::format_date( $sample, $settings['date_format'] ) ) ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-time-format"><?php esc_html_e( 'Time format', 'qevora-booking' ); ?></label>
				<input type="text" class="regular-text" id="qvb-time-format" name="qvb_settings[time_format]" value="<?php echo esc_attr( $settings['time_format'] ); ?>" />
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: formatted sample time. */ __( 'Preview: %s', 'qevora-booking' ), QVB_Utils::format_time( '14:30', $settings['time_format'] ) ) ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-week-start"><?php esc_html_e( 'Week starts on', 'qevora-booking' ); ?></label>
				<select id="qvb-week-start" name="qvb_settings[week_starts_on]">
					<option value="0" <?php selected( (int) $settings['week_starts_on'], 0 ); ?>><?php esc_html_e( 'Sunday', 'qevora-booking' ); ?></option>
					<option value="1" <?php selected( (int) $settings['week_starts_on'], 1 ); ?>><?php esc_html_e( 'Monday', 'qevora-booking' ); ?></option>
					<option value="6" <?php selected( (int) $settings['week_starts_on'], 6 ); ?>><?php esc_html_e( 'Saturday', 'qevora-booking' ); ?></option>
				</select>
			</div>
			<div class="qvb-field">
				<label for="qvb-currency"><?php esc_html_e( 'Currency', 'qevora-booking' ); ?></label>
				<input type="text" class="small-text" id="qvb-currency" name="qvb_settings[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" list="qvb-currency-list" maxlength="3" />
				<datalist id="qvb-currency-list">
					<?php foreach ( $currencies as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: sample price. */ __( 'Prices display like %s', 'qevora-booking' ), QVB_Utils::money( 1500, $settings['currency'] ) ) ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-currency-symbol"><?php esc_html_e( 'Currency symbol override', 'qevora-booking' ); ?></label>
				<input type="text" class="small-text" id="qvb-currency-symbol" name="qvb_settings[currency_symbol]" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to use the currency code’s default symbol.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-currency-position"><?php esc_html_e( 'Symbol position', 'qevora-booking' ); ?></label>
				<select id="qvb-currency-position" name="qvb_settings[currency_position]">
					<option value="before" <?php selected( $settings['currency_position'], 'before' ); ?>><?php esc_html_e( 'Before amount (₹1,500)', 'qevora-booking' ); ?></option>
					<option value="after" <?php selected( $settings['currency_position'], 'after' ); ?>><?php esc_html_e( 'After amount (1,500 ₹)', 'qevora-booking' ); ?></option>
				</select>
			</div>
			<div class="qvb-field">
				<label for="qvb-currency-decimals"><?php esc_html_e( 'Decimal places', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-currency-decimals" name="qvb_settings[currency_decimals]" value="<?php echo esc_attr( $settings['currency_decimals'] ); ?>" min="0" max="3" step="1" />
			</div>
			<div class="qvb-field">
				<label for="qvb-accent"><?php esc_html_e( 'Accent colour', 'qevora-booking' ); ?></label>
				<input type="color" id="qvb-accent" name="qvb_settings[accent_color]" value="<?php echo esc_attr( QVB_Utils::hex_color( $settings['accent_color'] ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Used by the booking form and email buttons.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-booking-page"><?php esc_html_e( 'Booking page', 'qevora-booking' ); ?></label>
				<select id="qvb-booking-page" name="qvb_settings[booking_page_id]">
					<?php echo QVB_Admin::options_html( QVB_Admin::page_choices(), (int) $settings['booking_page_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
				<p class="description"><?php esc_html_e( 'Used for “Book again” links in emails.', 'qevora-booking' ); ?></p>
			</div>
		</div>
	</div>

	<div class="qvb-card qvb-settings-section" id="booking-rules">
		<h2><?php esc_html_e( 'Booking rules', 'qevora-booking' ); ?></h2>

		<div class="qvb-form-grid qvb-form-grid--three">
			<div class="qvb-field">
				<label for="qvb-slot-interval"><?php esc_html_e( 'Slot interval (minutes)', 'qevora-booking' ); ?></label>
				<select id="qvb-slot-interval" name="qvb_settings[slot_interval]">
					<?php foreach ( array( 5, 10, 15, 20, 30, 45, 60, 90, 120 ) as $interval ) : ?>
						<option value="<?php echo esc_attr( $interval ); ?>" <?php selected( (int) $settings['slot_interval'], $interval ); ?>><?php echo esc_html( number_format_i18n( $interval ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'How often start times are offered.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-default-buffer"><?php esc_html_e( 'Default buffer (minutes)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-default-buffer" name="qvb_settings[default_buffer]" value="<?php echo esc_attr( $settings['default_buffer'] ); ?>" min="0" max="240" step="5" />
				<p class="description"><?php esc_html_e( 'Cleanup time after an appointment when a service has no buffer of its own.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-min-notice"><?php esc_html_e( 'Minimum notice (minutes)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-min-notice" name="qvb_settings[min_notice]" value="<?php echo esc_attr( $settings['min_notice'] ); ?>" min="0" max="10080" step="5" />
				<p class="description"><?php esc_html_e( 'How far ahead of now the earliest slot can start.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-max-advance"><?php esc_html_e( 'Maximum advance (days)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-max-advance" name="qvb_settings[max_advance]" value="<?php echo esc_attr( $settings['max_advance'] ); ?>" min="1" max="730" step="1" />
				<p class="description"><?php esc_html_e( 'How many days into the future customers may book.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-approval-mode"><?php esc_html_e( 'Approval mode', 'qevora-booking' ); ?></label>
				<select id="qvb-approval-mode" name="qvb_settings[approval_mode]">
					<option value="auto" <?php selected( $settings['approval_mode'], 'auto' ); ?>><?php esc_html_e( 'Confirm instantly', 'qevora-booking' ); ?></option>
					<option value="manual" <?php selected( $settings['approval_mode'], 'manual' ); ?>><?php esc_html_e( 'Hold as pending until approved', 'qevora-booking' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Manual approval works well for high-demand staff or salons that screen requests.', 'qevora-booking' ); ?></p>
			</div>
		</div>
	</div>

	<div class="qvb-card qvb-settings-section" id="cancellation">
		<h2><?php esc_html_e( 'Cancellation &amp; rescheduling', 'qevora-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Customers manage bookings from the secure link in their email. Staff can always override these rules in wp-admin.', 'qevora-booking' ); ?></p>

		<div class="qvb-form-grid qvb-form-grid--two">
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[allow_cancel]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[allow_cancel]" value="1" <?php checked( (int) $settings['allow_cancel'], 1 ); ?> />
					<span><?php esc_html_e( 'Allow customers to cancel', 'qevora-booking' ); ?></span>
				</label>
			</div>
			<div class="qvb-field">
				<label for="qvb-cancel-limit"><?php esc_html_e( 'Cancellation cut-off (hours before)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-cancel-limit" name="qvb_settings[cancel_limit_hours]" value="<?php echo esc_attr( $settings['cancel_limit_hours'] ); ?>" min="0" max="8760" step="1" />
			</div>
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[allow_reschedule]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[allow_reschedule]" value="1" <?php checked( (int) $settings['allow_reschedule'], 1 ); ?> />
					<span><?php esc_html_e( 'Allow customers to reschedule', 'qevora-booking' ); ?></span>
				</label>
			</div>
			<div class="qvb-field">
				<label for="qvb-reschedule-limit"><?php esc_html_e( 'Reschedule cut-off (hours before)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-reschedule-limit" name="qvb_settings[reschedule_limit_hours]" value="<?php echo esc_attr( $settings['reschedule_limit_hours'] ); ?>" min="0" max="8760" step="1" />
			</div>
			<div class="qvb-field">
				<label for="qvb-max-reschedules"><?php esc_html_e( 'Maximum reschedules per booking', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-max-reschedules" name="qvb_settings[max_reschedules]" value="<?php echo esc_attr( $settings['max_reschedules'] ); ?>" min="0" max="10" step="1" />
			</div>
			<div class="qvb-field">
				<label for="qvb-policy"><?php esc_html_e( 'Cancellation policy text', 'qevora-booking' ); ?></label>
				<textarea class="large-text" rows="3" id="qvb-policy" name="qvb_settings[cancellation_policy]"><?php echo esc_textarea( $settings['cancellation_policy'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Included in customer emails via the {cancellation_policy} placeholder.', 'qevora-booking' ); ?></p>
			</div>
		</div>
	</div>

	<div class="qvb-card qvb-settings-section" id="hours">
		<div class="qvb-section-head">
			<div>
				<h2><?php esc_html_e( 'Business hours', 'qevora-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These hours apply to staff who use the business schedule. Staff can override them on their profile.', 'qevora-booking' ); ?></p>
			</div>
			<button type="button" class="button" data-qvb-copy-hours><?php esc_html_e( 'Copy first day to all', 'qevora-booking' ); ?></button>
		</div>

		<table class="widefat qvb-hours-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Day', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Open', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Start', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'End', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Break start', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Break end', 'qevora-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $weekdays as $day => $label ) : ?>
					<?php $row = isset( $hours[ $day ] ) ? $hours[ $day ] : array( 'enabled' => 0, 'start' => '09:00', 'end' => '19:00', 'break_start' => '', 'break_end' => '' ); ?>
					<tr class="qvb-hours-row">
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<input type="hidden" name="qvb_settings[business_hours][<?php echo esc_attr( $day ); ?>][enabled]" value="0" />
							<input type="checkbox" class="qvb-hours-toggle" name="qvb_settings[business_hours][<?php echo esc_attr( $day ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: weekday name. */ __( 'Open on %s', 'qevora-booking' ), $label ) ); ?>" />
						</td>
						<td><input type="time" name="qvb_settings[business_hours][<?php echo esc_attr( $day ); ?>][start]" value="<?php echo esc_attr( $row['start'] ); ?>" /></td>
						<td><input type="time" name="qvb_settings[business_hours][<?php echo esc_attr( $day ); ?>][end]" value="<?php echo esc_attr( $row['end'] ); ?>" /></td>
						<td><input type="time" name="qvb_settings[business_hours][<?php echo esc_attr( $day ); ?>][break_start]" value="<?php echo esc_attr( $row['break_start'] ); ?>" /></td>
						<td><input type="time" name="qvb_settings[business_hours][<?php echo esc_attr( $day ); ?>][break_end]" value="<?php echo esc_attr( $row['break_end'] ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Leave break fields empty for no break. Holidays and special hours belong under Holidays / Exceptions.', 'qevora-booking' ); ?></p>
	</div>

	<div class="qvb-card qvb-settings-section" id="notifications">
		<h2><?php esc_html_e( 'Notifications', 'qevora-booking' ); ?></h2>

		<div class="qvb-form-grid qvb-form-grid--two">
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[enable_emails]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[enable_emails]" value="1" <?php checked( (int) $settings['enable_emails'], 1 ); ?> />
					<span><?php esc_html_e( 'Send booking emails', 'qevora-booking' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Master switch. Individual templates can also be disabled.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<label for="qvb-admin-email"><?php esc_html_e( 'Staff notification email', 'qevora-booking' ); ?></label>
				<input type="email" class="regular-text" id="qvb-admin-email" name="qvb_settings[admin_email]" value="<?php echo esc_attr( $settings['admin_email'] ); ?>" />
			</div>
			<div class="qvb-field">
				<label for="qvb-from-name"><?php esc_html_e( 'From name', 'qevora-booking' ); ?></label>
				<input type="text" class="regular-text" id="qvb-from-name" name="qvb_settings[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
			</div>
			<div class="qvb-field">
				<label for="qvb-from-email"><?php esc_html_e( 'From email', 'qevora-booking' ); ?></label>
				<input type="email" class="regular-text" id="qvb-from-email" name="qvb_settings[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Use an address on your own domain. Many hosts require SMTP for delivery.', 'qevora-booking' ); ?></p>
			</div>
		</div>
	</div>

	<div class="qvb-card qvb-settings-section" id="customer">
		<h2><?php esc_html_e( 'Customer form', 'qevora-booking' ); ?></h2>

		<div class="qvb-form-grid qvb-form-grid--two">
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[require_phone]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[require_phone]" value="1" <?php checked( (int) $settings['require_phone'], 1 ); ?> />
					<span><?php esc_html_e( 'Require a phone number', 'qevora-booking' ); ?></span>
				</label>
			</div>
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[require_consent]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[require_consent]" value="1" <?php checked( (int) $settings['require_consent'], 1 ); ?> />
					<span><?php esc_html_e( 'Require policy consent', 'qevora-booking' ); ?></span>
				</label>
			</div>
			<div class="qvb-field qvb-field--wide">
				<label for="qvb-consent-text"><?php esc_html_e( 'Consent label', 'qevora-booking' ); ?></label>
				<textarea class="large-text" rows="2" id="qvb-consent-text" name="qvb_settings[consent_text]"><?php echo esc_textarea( $settings['consent_text'] ); ?></textarea>
			</div>
		</div>

		<h3><?php esc_html_e( 'Spam &amp; abuse protection', 'qevora-booking' ); ?></h3>
		<div class="qvb-form-grid qvb-form-grid--three">
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[spam_protection]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[spam_protection]" value="1" <?php checked( (int) $settings['spam_protection'], 1 ); ?> />
					<span><?php esc_html_e( 'Enable honeypot and time trap', 'qevora-booking' ); ?></span>
				</label>
			</div>
			<div class="qvb-field">
				<label for="qvb-min-seconds"><?php esc_html_e( 'Minimum form fill time (seconds)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-min-seconds" name="qvb_settings[min_form_seconds]" value="<?php echo esc_attr( $settings['min_form_seconds'] ); ?>" min="0" max="120" step="1" />
			</div>
			<div class="qvb-field">
				<label for="qvb-rate-bookings"><?php esc_html_e( 'Bookings per IP per hour', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-rate-bookings" name="qvb_settings[rate_limit_bookings]" value="<?php echo esc_attr( $settings['rate_limit_bookings'] ); ?>" min="1" max="1000" step="1" />
			</div>
			<div class="qvb-field">
				<label for="qvb-rate-lookups"><?php esc_html_e( 'Booking lookups per IP per hour', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-rate-lookups" name="qvb_settings[rate_limit_lookups]" value="<?php echo esc_attr( $settings['rate_limit_lookups'] ); ?>" min="1" max="1000" step="1" />
			</div>
		</div>
	</div>

	<div class="qvb-card qvb-settings-section" id="privacy">
		<h2><?php esc_html_e( 'Privacy &amp; data', 'qevora-booking' ); ?></h2>

		<div class="qvb-form-grid qvb-form-grid--two">
			<div class="qvb-field">
				<label for="qvb-retention"><?php esc_html_e( 'Retention for cancelled/old bookings (days)', 'qevora-booking' ); ?></label>
				<input type="number" class="small-text" id="qvb-retention" name="qvb_settings[retention_days]" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" min="0" max="3650" step="1" />
				<p class="description"><?php esc_html_e( '0 keeps records indefinitely. When set, completed and cancelled bookings older than this are deleted by the daily maintenance task.', 'qevora-booking' ); ?></p>
			</div>
			<div class="qvb-field">
				<input type="hidden" name="qvb_settings[delete_data_on_uninstall]" value="0" />
				<label class="qvb-switch">
					<input type="checkbox" name="qvb_settings[delete_data_on_uninstall]" value="1" <?php checked( (int) $settings['delete_data_on_uninstall'], 1 ); ?> />
					<span><?php esc_html_e( 'Delete all plugin data when the plugin is deleted', 'qevora-booking' ); ?></span>
				</label>
				<p class="description qvb-warning"><?php esc_html_e( 'Off by default. Turning this on permanently removes services, staff, bookings and customers when the plugin is uninstalled.', 'qevora-booking' ); ?></p>
			</div>
		</div>
	</div>

	<p class="qvb-settings-submit">
		<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'qevora-booking' ); ?></button>
	</p>
</form>
