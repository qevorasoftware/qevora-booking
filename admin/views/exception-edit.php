<?php
/**
 * Add / edit a holiday or special-hours exception.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$exception_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$exception    = $exception_id ? QVB_Availability::get_exception( $exception_id ) : null;

if ( $exception_id && ! $exception ) {
	echo '<div class="qvb-inline-block"><div class="notice notice-error"><p>' . esc_html__( 'That exception could not be found.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

$preset_staff = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$data = $exception ? (array) $exception : array(
	'id'         => 0,
	'staff_id'   => $preset_staff,
	'from_date'  => QVB_Utils::today(),
	'to_date'    => QVB_Utils::today(),
	'type'       => 'closed',
	'start_time' => '',
	'end_time'   => '',
	'note'       => '',
);

QVB_Admin::page_header(
	$exception ? __( 'Edit exception', 'qevora-booking' ) : __( 'Add exception', 'qevora-booking' ),
	__( 'Block a date range or open special hours.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'All exceptions', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'exceptions' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-form-card" data-qvb-exception-form>
	<input type="hidden" name="action" value="qvb_save_exception" />
	<input type="hidden" name="id" value="<?php echo esc_attr( $data['id'] ); ?>" />
	<?php wp_nonce_field( 'qvb_save_exception' ); ?>

	<div class="qvb-form-grid">
		<div class="qvb-field">
			<label for="qvb-exception-staff"><?php esc_html_e( 'Applies to', 'qevora-booking' ); ?></label>
			<select name="staff_id" id="qvb-exception-staff">
				<option value="0" <?php selected( (int) $data['staff_id'], 0 ); ?>><?php esc_html_e( 'All staff / business-wide', 'qevora-booking' ); ?></option>
				<?php foreach ( QVB_Staff::all() as $member ) : ?>
					<option value="<?php echo esc_attr( $member->id ); ?>" <?php selected( (int) $data['staff_id'], (int) $member->id ); ?>><?php echo esc_html( $member->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="qvb-help"><?php esc_html_e( 'Business-wide closures apply to everyone. Staff exceptions override business-wide special hours for that person.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-exception-type"><?php esc_html_e( 'Type', 'qevora-booking' ); ?></label>
			<select name="type" id="qvb-exception-type" data-qvb-exception-type>
				<option value="closed" <?php selected( $data['type'], 'closed' ); ?>><?php esc_html_e( 'Closed (holiday or leave)', 'qevora-booking' ); ?></option>
				<option value="custom" <?php selected( $data['type'], 'custom' ); ?>><?php esc_html_e( 'Special opening hours', 'qevora-booking' ); ?></option>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-exception-from"><?php esc_html_e( 'From date', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="date" name="from_date" id="qvb-exception-from" value="<?php echo esc_attr( $data['from_date'] ); ?>" required />
		</div>

		<div class="qvb-field">
			<label for="qvb-exception-to"><?php esc_html_e( 'To date', 'qevora-booking' ); ?></label>
			<input type="date" name="to_date" id="qvb-exception-to" value="<?php echo esc_attr( $data['to_date'] ); ?>" />
			<p class="qvb-help"><?php esc_html_e( 'Leave the same as the start date for a single day.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field qvb-exception-hours" data-qvb-exception-hours <?php echo 'custom' === $data['type'] ? '' : 'hidden'; ?>>
			<label for="qvb-exception-start"><?php esc_html_e( 'Opens at', 'qevora-booking' ); ?></label>
			<input type="time" name="start_time" id="qvb-exception-start" value="<?php echo esc_attr( $data['start_time'] ); ?>" />
		</div>

		<div class="qvb-field qvb-exception-hours" data-qvb-exception-hours <?php echo 'custom' === $data['type'] ? '' : 'hidden'; ?>>
			<label for="qvb-exception-end"><?php esc_html_e( 'Closes at', 'qevora-booking' ); ?></label>
			<input type="time" name="end_time" id="qvb-exception-end" value="<?php echo esc_attr( $data['end_time'] ); ?>" />
		</div>

		<div class="qvb-field qvb-field--full">
			<label for="qvb-exception-note"><?php esc_html_e( 'Internal note', 'qevora-booking' ); ?></label>
			<input type="text" name="note" id="qvb-exception-note" value="<?php echo esc_attr( $data['note'] ); ?>" maxlength="255" placeholder="<?php esc_attr_e( 'Public holiday, training day, annual leave…', 'qevora-booking' ); ?>" />
			<p class="qvb-help"><?php esc_html_e( 'Only visible to the team. Existing bookings are not cancelled automatically — review the calendar for affected appointments.', 'qevora-booking' ); ?></p>
		</div>
	</div>

	<p class="qvb-form-actions">
		<button type="submit" class="button button-primary button-large"><?php echo $exception ? esc_html__( 'Save exception', 'qevora-booking' ) : esc_html__( 'Add exception', 'qevora-booking' ); ?></button>
		<a class="button button-large" href="<?php echo esc_url( QVB_Admin::url( 'exceptions' ) ); ?>"><?php esc_html_e( 'Cancel', 'qevora-booking' ); ?></a>

		<?php if ( $exception ) : ?>
			<a class="button qvb-align-right qvb-confirm qvb-text-danger" data-confirm="<?php esc_attr_e( 'Delete this exception?', 'qevora-booking' ); ?>"
				href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_delete_exception', array( 'id' => (int) $exception->id ) ), 'qvb_delete_exception_' . (int) $exception->id ) ); ?>">
				<?php esc_html_e( 'Delete exception', 'qevora-booking' ); ?>
			</a>
		<?php endif; ?>
	</p>
</form>
