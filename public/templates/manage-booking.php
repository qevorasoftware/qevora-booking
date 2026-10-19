<?php
/**
 * Customer manage-booking view.
 *
 * @var object $booking
 * @var string $token
 * @var bool   $can_cancel
 * @var bool   $can_reschedule
 * @var string $cancel_error
 * @var string $reschedule_error
 * @var string $policy
 * @var int    $cancel_limit_hours
 * @var int    $reschedule_limit_hours
 * @var string $business_name
 * @var string $business_phone
 * @var string $business_email
 * @var string $min_date
 * @var string $max_date
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$qvb_status       = sanitize_html_class( $booking->status );
$qvb_active       = in_array( $booking->status, array( 'pending', 'confirmed' ), true );
$qvb_booking_page = remove_query_arg( array( 'qvb_booking', 'token' ), QVB_Utils::booking_page_url() );
?>
<div
	class="qvb-app qvb-manage"
	data-qvb-manage
	data-reference="<?php echo esc_attr( $booking->reference ); ?>"
	data-token="<?php echo esc_attr( $token ); ?>"
	data-booking-id="<?php echo esc_attr( (int) $booking->id ); ?>"
	data-service-id="<?php echo esc_attr( (int) $booking->service_id ); ?>"
	data-staff-id="<?php echo esc_attr( (int) $booking->staff_id ); ?>"
	data-min-date="<?php echo esc_attr( $min_date ); ?>"
	data-max-date="<?php echo esc_attr( $max_date ); ?>"
	style="--qvb-accent: <?php echo esc_attr( QVB_Utils::hex_color( QVB_Settings::get( 'accent_color' ), '#7c3aed' ) ); ?>;"
>
	<header class="qvb-manage__header">
		<div>
			<p class="qvb-eyebrow"><?php echo esc_html( $business_name ); ?></p>
			<h2 class="qvb-title"><?php esc_html_e( 'Your appointment', 'qevora-booking' ); ?></h2>
		</div>
		<span class="qvb-status qvb-status--<?php echo esc_attr( $qvb_status ); ?>"><?php echo esc_html( QVB_Utils::status_label( $booking->status ) ); ?></span>
	</header>

	<div class="qvb-manage__summary">
		<div class="qvb-manage__date">
			<span class="qvb-manage__day"><?php echo esc_html( QVB_Utils::format_date( $booking->booking_date, 'j' ) ); ?></span>
			<span class="qvb-manage__month"><?php echo esc_html( QVB_Utils::format_date( $booking->booking_date, 'M Y' ) ); ?></span>
			<span class="qvb-manage__time"><?php echo esc_html( QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ) ); ?></span>
		</div>
		<div class="qvb-manage__details">
			<h3><?php echo esc_html( $booking->service_name ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: staff name. */
					esc_html__( 'with %s', 'qevora-booking' ),
					esc_html( $booking->staff_name )
				);
				?>
				· <?php echo esc_html( QVB_Utils::duration_label( (int) $booking->duration ) ); ?>
				· <?php echo esc_html( QVB_Utils::money( $booking->price, $booking->currency ) ); ?>
			</p>
			<p class="qvb-muted"><?php esc_html_e( 'Reference', 'qevora-booking' ); ?>: <strong><?php echo esc_html( $booking->reference ); ?></strong></p>
		</div>
	</div>

	<?php if ( 'pending' === $booking->status ) : ?>
		<div class="qvb-notice qvb-notice--info"><?php esc_html_e( 'This appointment is waiting for the salon to confirm it. We will email you as soon as it is confirmed.', 'qevora-booking' ); ?></div>
	<?php elseif ( 'confirmed' === $booking->status ) : ?>
		<div class="qvb-notice qvb-notice--success"><?php esc_html_e( 'Your appointment is confirmed. We look forward to seeing you.', 'qevora-booking' ); ?></div>
	<?php elseif ( 'cancelled' === $booking->status ) : ?>
		<div class="qvb-notice qvb-notice--warning"><?php esc_html_e( 'This appointment has been cancelled.', 'qevora-booking' ); ?></div>
	<?php elseif ( 'rejected' === $booking->status ) : ?>
		<div class="qvb-notice qvb-notice--warning"><?php esc_html_e( 'This booking request was not accepted. Please contact us if you would like to arrange another time.', 'qevora-booking' ); ?></div>
	<?php elseif ( 'completed' === $booking->status ) : ?>
		<div class="qvb-notice qvb-notice--info"><?php esc_html_e( 'This appointment is complete. Thank you for visiting.', 'qevora-booking' ); ?></div>
	<?php endif; ?>

	<dl class="qvb-summary__list">
		<div><dt><?php esc_html_e( 'Customer', 'qevora-booking' ); ?></dt><dd><?php echo esc_html( $booking->customer_name ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Email', 'qevora-booking' ); ?></dt><dd><?php echo esc_html( $booking->customer_email ); ?></dd></div>
		<?php if ( $booking->customer_phone ) : ?>
			<div><dt><?php esc_html_e( 'Phone', 'qevora-booking' ); ?></dt><dd><?php echo esc_html( $booking->customer_phone ); ?></dd></div>
		<?php endif; ?>
		<?php if ( $booking->notes ) : ?>
			<div><dt><?php esc_html_e( 'Your notes', 'qevora-booking' ); ?></dt><dd><?php echo esc_html( $booking->notes ); ?></dd></div>
		<?php endif; ?>
	</dl>

	<?php if ( $qvb_active ) : ?>
		<div class="qvb-manage__actions">
			<?php if ( $can_reschedule ) : ?>
				<button type="button" class="qvb-btn qvb-btn--primary" data-qvb-open-reschedule><?php esc_html_e( 'Reschedule', 'qevora-booking' ); ?></button>
			<?php endif; ?>
			<?php if ( $can_cancel ) : ?>
				<button type="button" class="qvb-btn qvb-btn--danger-ghost" data-qvb-open-cancel><?php esc_html_e( 'Cancel booking', 'qevora-booking' ); ?></button>
			<?php endif; ?>
			<a class="qvb-btn qvb-btn--ghost" href="<?php echo esc_url( $qvb_booking_page ); ?>"><?php esc_html_e( 'Book another appointment', 'qevora-booking' ); ?></a>
		</div>
	<?php else : ?>
		<p><a class="qvb-btn qvb-btn--primary" href="<?php echo esc_url( $qvb_booking_page ); ?>"><?php esc_html_e( 'Book another appointment', 'qevora-booking' ); ?></a></p>
	<?php endif; ?>

	<?php if ( $cancel_error && $qvb_active ) : ?>
		<p class="qvb-muted qvb-small"><?php echo esc_html( $cancel_error ); ?></p>
	<?php endif; ?>
	<?php if ( $reschedule_error && $qvb_active ) : ?>
		<p class="qvb-muted qvb-small"><?php echo esc_html( $reschedule_error ); ?></p>
	<?php endif; ?>

	<section class="qvb-manage-panel" data-qvb-cancel-panel hidden>
		<h3><?php esc_html_e( 'Cancel this appointment?', 'qevora-booking' ); ?></h3>
		<p><?php esc_html_e( 'This frees the slot for other customers and cannot be undone.', 'qevora-booking' ); ?></p>
		<label class="qvb-field">
			<span class="qvb-field__label"><?php esc_html_e( 'Reason (optional)', 'qevora-booking' ); ?></span>
			<textarea rows="2" maxlength="255" data-qvb-cancel-reason></textarea>
		</label>
		<div class="qvb-manage__buttons">
			<button type="button" class="qvb-btn qvb-btn--danger" data-qvb-cancel-submit><?php esc_html_e( 'Yes, cancel booking', 'qevora-booking' ); ?></button>
			<button type="button" class="qvb-btn qvb-btn--ghost" data-qvb-close-panel><?php esc_html_e( 'Keep booking', 'qevora-booking' ); ?></button>
		</div>
	</section>

	<section class="qvb-manage-panel" data-qvb-reschedule-panel hidden>
		<h3><?php esc_html_e( 'Choose a new time', 'qevora-booking' ); ?></h3>
		<div class="qvb-date-row">
			<label class="qvb-field qvb-field--date">
				<span class="qvb-field__label"><?php esc_html_e( 'New date', 'qevora-booking' ); ?></span>
				<input type="date" min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>" data-qvb-manage-date>
			</label>
			<div class="qvb-quick-dates" data-qvb-manage-days aria-live="polite"></div>
		</div>
		<div class="qvb-slots" data-qvb-manage-slots aria-live="polite">
			<p class="qvb-empty"><?php esc_html_e( 'Choose a date to see available times.', 'qevora-booking' ); ?></p>
		</div>
		<input type="hidden" value="" data-qvb-manage-time>
		<div class="qvb-manage__buttons">
			<button type="button" class="qvb-btn qvb-btn--primary" data-qvb-reschedule-submit disabled><?php esc_html_e( 'Move appointment', 'qevora-booking' ); ?></button>
			<button type="button" class="qvb-btn qvb-btn--ghost" data-qvb-close-panel><?php esc_html_e( 'Keep current time', 'qevora-booking' ); ?></button>
		</div>
	</section>

	<p class="qvb-form-error" data-qvb-manage-error role="alert" hidden></p>
	<p class="qvb-form-success" data-qvb-manage-success role="status" hidden></p>

	<p class="qvb-policy"><?php echo esc_html( $policy ); ?></p>

	<p class="qvb-muted qvb-small">
		<?php esc_html_e( 'Need help?', 'qevora-booking' ); ?>
		<?php if ( $business_phone ) : ?>
			<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $business_phone ) ); ?>"><?php echo esc_html( $business_phone ); ?></a>
		<?php endif; ?>
		<?php if ( $business_email ) : ?>
			<a href="mailto:<?php echo esc_attr( $business_email ); ?>"><?php echo esc_html( $business_email ); ?></a>
		<?php endif; ?>
	</p>
</div>
