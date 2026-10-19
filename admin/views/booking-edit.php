<?php
/**
 * Admin: view and manage a booking.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$booking_id = isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$booking    = QVB_Admin::get_booking_or_die( $booking_id );
$transitions = QVB_Bookings::allowed_transitions( $booking->status );
$can_cancel  = QVB_Bookings::can_cancel( $booking, 'admin' );

$manage_url = QVB_Bookings::manage_url( $booking );
$customer   = $booking->customer_id ? QVB_Customers::get( $booking->customer_id ) : null;
$customer_url = $customer ? QVB_Admin::url( 'customers', array( 'action' => 'view', 'customer' => (int) $customer->id ) ) : '';
$edit_url   = QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => (int) $booking->id ) );

$back = QVB_Admin::url( 'bookings' );

QVB_Admin::page_header(
	/* translators: %s: booking reference. */
	sprintf( __( 'Booking %s', 'qevora-booking' ), $booking->reference ),
	QVB_Utils::format_date_weekday( $booking->booking_date ) . ' · ' . QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ),
	array(
		array(
			'label' => __( 'All bookings', 'qevora-booking' ),
			'url'   => $back,
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<div class="qvb-booking-header">
	<div>
		<?php echo QVB_Admin::status_badge( $booking->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span class="qvb-muted">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: source, 2: date. */
					__( 'Source: %1$s · Created %2$s', 'qevora-booking' ),
					'admin' === $booking->source ? __( 'admin', 'qevora-booking' ) : __( 'website', 'qevora-booking' ),
					QVB_Utils::format_admin_datetime( $booking->created_at )
				)
			);
			?>
		</span>
	</div>
	<div class="qvb-booking-header__actions">
		<?php if ( $manage_url ) : ?>
			<button type="button" class="button qvb-copy" data-copy="<?php echo esc_attr( $manage_url ); ?>"><?php esc_html_e( 'Copy customer link', 'qevora-booking' ); ?></button>
		<?php endif; ?>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_resend_booking_email', array( 'id' => (int) $booking->id ) ), 'qvb_resend_booking_email_' . (int) $booking->id ) ); ?>">
			<?php esc_html_e( 'Resend status email', 'qevora-booking' ); ?>
		</a>
	</div>
</div>

<div class="qvb-columns qvb-columns--sidebar">
	<div class="qvb-columns__main">
		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Appointment', 'qevora-booking' ); ?></h2></div>
			<table class="qvb-detail-table">
				<tbody>
					<tr><th><?php esc_html_e( 'Reference', 'qevora-booking' ); ?></th><td><code><?php echo esc_html( $booking->reference ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Service', 'qevora-booking' ); ?></th><td><?php echo esc_html( $booking->service_name ); ?> <span class="qvb-muted">(<?php echo esc_html( QVB_Utils::duration_label( $booking->duration ) ); ?>)</span></td></tr>
					<tr><th><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></th><td><?php echo $booking->staff_name ? esc_html( $booking->staff_name ) : esc_html__( 'Any available', 'qevora-booking' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Date', 'qevora-booking' ); ?></th><td><?php echo esc_html( QVB_Utils::format_date_weekday( $booking->booking_date ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Time', 'qevora-booking' ); ?></th><td><?php echo esc_html( QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Price', 'qevora-booking' ); ?></th><td><?php echo esc_html( QVB_Utils::money( $booking->price, $booking->currency ) ); ?> <span class="qvb-muted"><?php esc_html_e( 'pay at salon', 'qevora-booking' ); ?></span></td></tr>
					<?php if ( $booking->cancelled_at ) : ?>
						<tr><th><?php esc_html_e( 'Cancelled', 'qevora-booking' ); ?></th><td><?php echo esc_html( QVB_Utils::format_admin_datetime( $booking->cancelled_at ) ); ?><?php echo $booking->cancellation_reason ? ' — ' . esc_html( $booking->cancellation_reason ) : ''; ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Customer', 'qevora-booking' ); ?></h2></div>
			<table class="qvb-detail-table">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Name', 'qevora-booking' ); ?></th>
						<td>
							<?php echo esc_html( $booking->customer_name ); ?>
							<?php if ( $customer_url ) : ?>
								<a href="<?php echo esc_url( $customer_url ); ?>"><?php esc_html_e( 'View history', 'qevora-booking' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr><th><?php esc_html_e( 'Email', 'qevora-booking' ); ?></th><td><a href="mailto:<?php echo esc_attr( $booking->customer_email ); ?>"><?php echo esc_html( QVB_Admin::can_view_pii() ? $booking->customer_email : QVB_Utils::mask_email( $booking->customer_email ) ); ?></a></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'qevora-booking' ); ?></th><td><?php echo $booking->customer_phone ? esc_html( QVB_Admin::can_view_pii() ? $booking->customer_phone : QVB_Utils::mask_phone( $booking->customer_phone ) ) : '—'; ?></td></tr>
					<?php if ( $booking->notes ) : ?>
						<tr><th><?php esc_html_e( 'Customer notes', 'qevora-booking' ); ?></th><td><?php echo nl2br( esc_html( $booking->notes ) ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Reschedule', 'qevora-booking' ); ?></h2></div>
			<?php if ( in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-booking-form" data-qvb-admin-booking data-exclude="<?php echo esc_attr( $booking->id ); ?>">
					<input type="hidden" name="action" value="qvb_reschedule_booking" />
					<input type="hidden" name="id" value="<?php echo esc_attr( $booking->id ); ?>" />
					<?php wp_nonce_field( 'qvb_reschedule_booking_' . (int) $booking->id ); ?>

					<div class="qvb-form-grid qvb-form-grid--three">
						<div class="qvb-field">
							<label for="qvb-resched-date"><?php esc_html_e( 'New date', 'qevora-booking' ); ?></label>
							<input type="date" name="date" id="qvb-resched-date" value="<?php echo esc_attr( $booking->booking_date ); ?>" min="<?php echo esc_attr( QVB_Utils::today() ); ?>" required />
						</div>
						<div class="qvb-field">
							<label for="qvb-resched-staff"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></label>
							<select name="staff_id" id="qvb-resched-staff">
								<option value="0"><?php esc_html_e( 'Any available staff', 'qevora-booking' ); ?></option>
								<?php foreach ( QVB_Staff::active( (int) $booking->service_id ) as $member ) : ?>
									<option value="<?php echo esc_attr( $member->id ); ?>" <?php selected( (int) $booking->staff_id, (int) $member->id ); ?>><?php echo esc_html( $member->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="qvb-field">
							<label for="qvb-resched-time"><?php esc_html_e( 'New time', 'qevora-booking' ); ?></label>
							<input type="time" name="time" id="qvb-resched-time" step="300" value="<?php echo esc_attr( $booking->start_time ); ?>" required />
						</div>
					</div>

					<input type="hidden" name="service_id" value="<?php echo esc_attr( $booking->service_id ); ?>" />

					<div class="qvb-slot-panel">
						<div class="qvb-slot-panel__head">
							<strong><?php esc_html_e( 'Available times', 'qevora-booking' ); ?></strong>
						</div>
						<div class="qvb-slot-results" data-qvb-slot-results aria-live="polite">
							<p class="qvb-muted"><?php esc_html_e( 'Change the date to load availability.', 'qevora-booking' ); ?></p>
						</div>
					</div>

					<p class="qvb-form-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Reschedule booking', 'qevora-booking' ); ?></button>
					</p>
				</form>
			<?php else : ?>
				<p class="qvb-muted"><?php esc_html_e( 'Only pending or confirmed bookings can be moved.', 'qevora-booking' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Private notes', 'qevora-booking' ); ?></h2></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="qvb_save_booking_notes" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $booking->id ); ?>" />
				<?php wp_nonce_field( 'qvb_save_booking_notes_' . (int) $booking->id ); ?>
				<label class="screen-reader-text" for="qvb-admin-notes"><?php esc_html_e( 'Private notes', 'qevora-booking' ); ?></label>
				<textarea name="admin_notes" id="qvb-admin-notes" rows="4" class="widefat" maxlength="2000"><?php echo esc_textarea( (string) $booking->admin_notes ); ?></textarea>
				<p class="qvb-help"><?php esc_html_e( 'Notes are only visible to the team and are never included in customer emails.', 'qevora-booking' ); ?></p>
				<p><button type="submit" class="button"><?php esc_html_e( 'Save notes', 'qevora-booking' ); ?></button></p>
			</form>
		</div>
	</div>

	<div class="qvb-columns__side">
		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Status', 'qevora-booking' ); ?></h2></div>
			<?php if ( $transitions ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="qvb_update_booking_status" />
					<input type="hidden" name="id" value="<?php echo esc_attr( $booking->id ); ?>" />
					<?php wp_nonce_field( 'qvb_update_booking_status_' . (int) $booking->id ); ?>

					<p class="qvb-field">
						<label for="qvb-new-status"><?php esc_html_e( 'Change to', 'qevora-booking' ); ?></label>
						<select name="status" id="qvb-new-status">
							<?php foreach ( $transitions as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( QVB_Utils::status_label( $status ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p class="qvb-field">
						<label for="qvb-status-reason"><?php esc_html_e( 'Reason (optional)', 'qevora-booking' ); ?></label>
						<input type="text" name="reason" id="qvb-status-reason" maxlength="180" />
						<span class="qvb-help"><?php esc_html_e( 'Included in the customer email for cancellations.', 'qevora-booking' ); ?></span>
					</p>

					<button type="submit" class="button button-primary widefat"><?php esc_html_e( 'Update status', 'qevora-booking' ); ?></button>
				</form>
			<?php else : ?>
				<p class="qvb-muted"><?php esc_html_e( 'This booking is in a final state.', 'qevora-booking' ); ?></p>
			<?php endif; ?>

			<?php if ( $can_cancel && in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) : ?>
				<hr />
				<a class="button button-link-delete qvb-confirm widefat" data-confirm="<?php esc_attr_e( 'Cancel this booking? The slot will be released.', 'qevora-booking' ); ?>"
					href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_update_booking_status', array( 'id' => (int) $booking->id, 'status' => 'cancelled' ) ), 'qvb_update_booking_status_' . (int) $booking->id ) ); ?>">
					<?php esc_html_e( 'Cancel booking', 'qevora-booking' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'At a glance', 'qevora-booking' ); ?></h2></div>
			<ul class="qvb-meta-list">
				<li><span><?php esc_html_e( 'Duration', 'qevora-booking' ); ?></span><strong><?php echo esc_html( QVB_Utils::duration_label( $booking->duration ) ); ?></strong></li>
				<li><span><?php esc_html_e( 'Buffer', 'qevora-booking' ); ?></span><strong><?php echo esc_html( QVB_Utils::duration_label( $booking->buffer_time ) ); ?></strong></li>
				<li><span><?php esc_html_e( 'Reschedules', 'qevora-booking' ); ?></span><strong><?php echo esc_html( number_format_i18n( $booking->reschedule_count ) ); ?></strong></li>
				<li><span><?php esc_html_e( 'Last updated', 'qevora-booking' ); ?></span><strong><?php echo esc_html( QVB_Utils::format_admin_datetime( $booking->updated_at ) ); ?></strong></li>
			</ul>
			<?php if ( QVB_Admin::can_view_pii() && $booking->ip_hash ) : ?>
				<p class="qvb-help"><?php esc_html_e( 'The customer IP address is stored only as a salted hash for abuse protection.', 'qevora-booking' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
