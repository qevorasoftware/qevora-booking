<?php
/**
 * Admin: create a booking.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$services = QVB_Services::all( array( 'status' => 'active' ) );
$staff    = QVB_Staff::all( array( 'status' => 'active' ) );

$preset_service = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$preset_staff   = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$preset_date    = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : QVB_Utils::today(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$preset_time    = isset( $_GET['time'] ) ? sanitize_text_field( wp_unslash( $_GET['time'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

QVB_Admin::page_header(
	__( 'Add booking', 'qevora-booking' ),
	__( 'Create an appointment for a walk-in or phone customer.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Back to bookings', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'bookings' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);

if ( ! $services ) {
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Create an active service before adding bookings.', 'qevora-booking' ) . '</p></div>';
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-form-card qvb-booking-form" data-qvb-admin-booking>
	<input type="hidden" name="action" value="qvb_create_booking" />
	<?php wp_nonce_field( 'qvb_create_booking' ); ?>

	<div class="qvb-form-grid">
		<div class="qvb-field">
			<label for="qvb-service"><?php esc_html_e( 'Service', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<select name="service_id" id="qvb-service" required>
				<option value=""><?php esc_html_e( 'Choose a service', 'qevora-booking' ); ?></option>
				<?php foreach ( $services as $service ) : ?>
					<option value="<?php echo esc_attr( $service->id ); ?>" data-duration="<?php echo esc_attr( $service->duration ); ?>" <?php selected( $preset_service, (int) $service->id ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: service name, 2: duration, 3: price. */
								__( '%1$s — %2$s — %3$s', 'qevora-booking' ),
								$service->name,
								QVB_Utils::duration_label( $service->duration ),
								QVB_Utils::money( $service->price )
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-staff"><?php esc_html_e( 'Staff member', 'qevora-booking' ); ?></label>
			<select name="staff_id" id="qvb-staff">
				<option value="0"><?php esc_html_e( 'Any available staff', 'qevora-booking' ); ?></option>
				<?php foreach ( $staff as $member ) : ?>
					<option value="<?php echo esc_attr( $member->id ); ?>" data-services="<?php echo esc_attr( implode( ',', QVB_Staff::service_ids( $member->id ) ) ); ?>" <?php selected( $preset_staff, (int) $member->id ); ?>>
						<?php echo esc_html( $member->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-date"><?php esc_html_e( 'Date', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="date" name="date" id="qvb-date" value="<?php echo esc_attr( $preset_date ); ?>" min="<?php echo esc_attr( QVB_Utils::today() ); ?>" required />
		</div>

		<div class="qvb-field">
			<label for="qvb-time"><?php esc_html_e( 'Time', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="time" name="time" id="qvb-time" value="<?php echo esc_attr( $preset_time ); ?>" step="300" required />
			<p class="qvb-help"><?php esc_html_e( 'Pick an available slot below, or type a time that matches the schedule.', 'qevora-booking' ); ?></p>
		</div>
	</div>

	<div class="qvb-slot-panel">
		<div class="qvb-slot-panel__head">
			<strong><?php esc_html_e( 'Available times', 'qevora-booking' ); ?></strong>
			<span class="qvb-muted"><?php esc_html_e( 'Times update when the service, staff member or date changes.', 'qevora-booking' ); ?></span>
		</div>
		<div class="qvb-slot-results" data-qvb-slot-results aria-live="polite">
			<p class="qvb-muted"><?php esc_html_e( 'Choose a service and date to load availability.', 'qevora-booking' ); ?></p>
		</div>
	</div>

	<h2 class="qvb-section-title"><?php esc_html_e( 'Customer', 'qevora-booking' ); ?></h2>

	<div class="qvb-form-grid">
		<div class="qvb-field">
			<label for="qvb-name"><?php esc_html_e( 'Full name', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="text" name="name" id="qvb-name" autocomplete="name" required />
		</div>
		<div class="qvb-field">
			<label for="qvb-email"><?php esc_html_e( 'Email address', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="email" name="email" id="qvb-email" autocomplete="email" required />
		</div>
		<div class="qvb-field">
			<label for="qvb-phone"><?php esc_html_e( 'Phone', 'qevora-booking' ); ?> <?php echo QVB_Settings::get( 'require_phone' ) ? '<span class="qvb-required">*</span>' : ''; ?></label>
			<input type="tel" name="phone" id="qvb-phone" autocomplete="tel" <?php echo QVB_Settings::get( 'require_phone' ) ? 'required' : ''; ?> />
		</div>
		<div class="qvb-field">
			<label for="qvb-status"><?php esc_html_e( 'Initial status', 'qevora-booking' ); ?></label>
			<select name="status" id="qvb-status">
				<option value="confirmed"><?php esc_html_e( 'Confirmed', 'qevora-booking' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Pending approval', 'qevora-booking' ); ?></option>
			</select>
		</div>
		<div class="qvb-field qvb-field--full">
			<label for="qvb-notes"><?php esc_html_e( 'Notes for the team', 'qevora-booking' ); ?></label>
			<textarea name="notes" id="qvb-notes" rows="3" maxlength="1000"></textarea>
		</div>
	</div>

	<p class="qvb-form-actions">
		<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Create booking', 'qevora-booking' ); ?></button>
		<a class="button button-large" href="<?php echo esc_url( QVB_Admin::url( 'bookings' ) ); ?>"><?php esc_html_e( 'Cancel', 'qevora-booking' ); ?></a>
	</p>
</form>
