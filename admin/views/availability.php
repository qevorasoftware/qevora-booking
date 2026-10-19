<?php
/**
 * Weekly availability screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$staff_all    = QVB_Staff::all();
$selected_id  = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$selected     = $selected_id ? QVB_Staff::get( $selected_id ) : null;

if ( ! $selected && $staff_all ) {
	$selected = $staff_all[0];
	$selected_id = (int) $selected->id;
}

if ( $selected_id && ! $selected ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'That staff member could not be found.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

$business_hours = QVB_Settings::business_hours();
$rows           = $selected ? QVB_Staff::schedule( $selected->id ) : array();
$rows_by_day    = array();
foreach ( $rows as $row ) {
	$rows_by_day[ (int) $row->day_of_week ][] = $row;
}
$mode = $selected && ! (int) $selected->use_business_hours ? 'custom' : 'business';
$weekdays = QVB_Utils::weekdays();
$exceptions = $selected ? QVB_Availability::exceptions( array( 'staff_id' => $selected->id, 'scope' => 'upcoming' ) ) : array();

QVB_Admin::page_header(
	__( 'Availability', 'qevora-booking' ),
	__( 'Weekly opening hours, breaks and individual staff schedules.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Holidays & exceptions', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'exceptions' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);

if ( ! $staff_all ) {
	echo '<div class="qvb-card qvb-empty-state"><h2>' . esc_html__( 'Add staff first', 'qevora-booking' ) . '</h2><p>' . esc_html__( 'Weekly schedules apply to staff members. Business hours can be edited in Settings.', 'qevora-booking' ) . '</p><a class="button button-primary" href="' . esc_url( QVB_Admin::url( 'staff', array( 'action' => 'edit' ) ) ) . '">' . esc_html__( 'Add staff member', 'qevora-booking' ) . '</a></div>';
	return;
}
?>

<div class="qvb-toolbar">
	<form method="get" class="qvb-inline-form">
		<input type="hidden" name="page" value="qevora-booking-availability" />
		<label for="qvb-availability-staff"><?php esc_html_e( 'Staff member', 'qevora-booking' ); ?></label>
		<select name="staff_id" id="qvb-availability-staff" onchange="this.form.submit()">
			<?php foreach ( $staff_all as $member ) : ?>
				<option value="<?php echo esc_attr( $member->id ); ?>" <?php selected( $selected_id, (int) $member->id ); ?>><?php echo esc_html( $member->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="button"><?php esc_html_e( 'View', 'qevora-booking' ); ?></button></noscript>
	</form>
</div>

<div class="qvb-columns">
	<div class="qvb-card qvb-columns__main">
		<div class="qvb-card__head">
			<h2><?php echo esc_html( sprintf( /* translators: %s: staff name. */ __( '%s’s schedule', 'qevora-booking' ), $selected->name ) ); ?></h2>
			<span class="qvb-badge qvb-badge--<?php echo 'custom' === $mode ? 'pending' : 'confirmed'; ?>">
				<?php echo 'custom' === $mode ? esc_html__( 'Custom schedule', 'qevora-booking' ) : esc_html__( 'Business hours', 'qevora-booking' ); ?>
			</span>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-availability-form" data-qvb-availability-form>
			<input type="hidden" name="action" value="qvb_save_availability" />
			<input type="hidden" name="staff_id" value="<?php echo esc_attr( $selected->id ); ?>" />
			<?php wp_nonce_field( 'qvb_save_availability' ); ?>

			<fieldset class="qvb-mode">
				<legend><?php esc_html_e( 'How should this person’s hours be calculated?', 'qevora-booking' ); ?></legend>
				<label class="qvb-radio">
					<input type="radio" name="mode" value="business" <?php checked( $mode, 'business' ); ?> data-qvb-mode="business" />
					<span><?php esc_html_e( 'Follow business hours', 'qevora-booking' ); ?></span>
				</label>
				<label class="qvb-radio">
					<input type="radio" name="mode" value="custom" <?php checked( $mode, 'custom' ); ?> data-qvb-mode="custom" />
					<span><?php esc_html_e( 'Use a custom weekly schedule', 'qevora-booking' ); ?></span>
				</label>
			</fieldset>

			<div class="qvb-custom-schedule" data-qvb-schedule data-qvb-business-hours="<?php echo esc_attr( wp_json_encode( $business_hours ) ); ?>" <?php echo 'custom' === $mode ? '' : 'hidden'; ?>>
				<div class="qvb-schedule-rows">
					<?php if ( $rows ) : ?>
						<?php foreach ( array_values( $rows ) as $index => $row ) : ?>
							<div class="qvb-schedule-row" data-qvb-schedule-row>
								<label>
									<span class="screen-reader-text"><?php esc_html_e( 'Day', 'qevora-booking' ); ?></span>
									<select name="rows[<?php echo esc_attr( $index ); ?>][day_of_week]">
										<?php foreach ( $weekdays as $day_index => $day_name ) : ?>
											<option value="<?php echo esc_attr( $day_index ); ?>" <?php selected( (int) $row->day_of_week, (int) $day_index ); ?>><?php echo esc_html( $day_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Start', 'qevora-booking' ); ?></span>
									<input type="time" name="rows[<?php echo esc_attr( $index ); ?>][start]" value="<?php echo esc_attr( $row->start_time ); ?>" required />
								</label>
								<label>
									<span><?php esc_html_e( 'End', 'qevora-booking' ); ?></span>
									<input type="time" name="rows[<?php echo esc_attr( $index ); ?>][end]" value="<?php echo esc_attr( $row->end_time ); ?>" required />
								</label>
								<label>
									<span><?php esc_html_e( 'Break start', 'qevora-booking' ); ?></span>
									<input type="time" name="rows[<?php echo esc_attr( $index ); ?>][break_start]" value="<?php echo esc_attr( $row->break_start ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'Break end', 'qevora-booking' ); ?></span>
									<input type="time" name="rows[<?php echo esc_attr( $index ); ?>][break_end]" value="<?php echo esc_attr( $row->break_end ); ?>" />
								</label>
								<button type="button" class="button-link qvb-text-danger" data-qvb-remove-row><?php esc_html_e( 'Remove', 'qevora-booking' ); ?></button>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<p class="qvb-schedule-actions">
					<button type="button" class="button" data-qvb-add-row><?php esc_html_e( 'Add shift', 'qevora-booking' ); ?></button>
					<button type="button" class="button-link" data-qvb-copy-hours><?php esc_html_e( 'Copy business hours', 'qevora-booking' ); ?></button>
				</p>

				<p class="qvb-help"><?php esc_html_e( 'Add one row per shift. Breaks are optional and must sit inside the shift. You can add several non-overlapping shifts per day.', 'qevora-booking' ); ?></p>

				<template data-qvb-row-template>
					<div class="qvb-schedule-row" data-qvb-schedule-row>
						<label>
							<span class="screen-reader-text"><?php esc_html_e( 'Day', 'qevora-booking' ); ?></span>
							<select name="rows[__i__][day_of_week]">
								<?php foreach ( $weekdays as $day_index => $day_name ) : ?>
									<option value="<?php echo esc_attr( $day_index ); ?>"><?php echo esc_html( $day_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Start', 'qevora-booking' ); ?></span>
							<input type="time" name="rows[__i__][start]" value="09:00" required />
						</label>
						<label>
							<span><?php esc_html_e( 'End', 'qevora-booking' ); ?></span>
							<input type="time" name="rows[__i__][end]" value="18:00" required />
						</label>
						<label>
							<span><?php esc_html_e( 'Break start', 'qevora-booking' ); ?></span>
							<input type="time" name="rows[__i__][break_start]" value="" />
						</label>
						<label>
							<span><?php esc_html_e( 'Break end', 'qevora-booking' ); ?></span>
							<input type="time" name="rows[__i__][break_end]" value="" />
						</label>
						<button type="button" class="button-link qvb-text-danger" data-qvb-remove-row><?php esc_html_e( 'Remove', 'qevora-booking' ); ?></button>
					</div>
				</template>

				<p class="qvb-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save schedule', 'qevora-booking' ); ?></button>
				</p>
			</div>
		</form>

		<?php if ( 'business' === $mode ) : ?>
			<p class="qvb-note"><?php esc_html_e( 'This person currently uses the business hours shown on the right. Switch to a custom schedule above if their hours differ.', 'qevora-booking' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="qvb-columns__side">
		<div class="qvb-card">
			<div class="qvb-card__head">
				<h2><?php esc_html_e( 'Business hours', 'qevora-booking' ); ?></h2>
				<a href="<?php echo esc_url( QVB_Admin::url( 'settings' ) . '#hours' ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a>
			</div>
			<ul class="qvb-hours-list">
				<?php foreach ( $weekdays as $day_index => $day_name ) : ?>
					<?php
					$day = isset( $business_hours[ $day_index ] ) ? $business_hours[ $day_index ] : array();
					$is_open = ! empty( $day['enabled'] );
					?>
					<li>
						<span><?php echo esc_html( $day_name ); ?></span>
						<?php if ( $is_open ) : ?>
							<strong>
								<?php echo esc_html( QVB_Utils::format_time( $day['start'] ) . ' – ' . QVB_Utils::format_time( $day['end'] ) ); ?>
								<?php if ( ! empty( $day['break_start'] ) && ! empty( $day['break_end'] ) ) : ?>
									<span class="qvb-muted">(<?php esc_html_e( 'break', 'qevora-booking' ); ?> <?php echo esc_html( QVB_Utils::format_time( $day['break_start'] ) . '–' . QVB_Utils::format_time( $day['break_end'] ) ); ?>)</span>
								<?php endif; ?>
							</strong>
						<?php else : ?>
							<span class="qvb-muted"><?php esc_html_e( 'Closed', 'qevora-booking' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="qvb-card">
			<div class="qvb-card__head">
				<h2><?php esc_html_e( 'Upcoming leave', 'qevora-booking' ); ?></h2>
				<a href="<?php echo esc_url( QVB_Admin::url( 'exceptions', array( 'action' => 'edit', 'staff_id' => (int) $selected->id ) ) ); ?>"><?php esc_html_e( 'Add', 'qevora-booking' ); ?></a>
			</div>
			<?php if ( ! $exceptions ) : ?>
				<p class="qvb-empty"><?php esc_html_e( 'No holidays or special hours for this person.', 'qevora-booking' ); ?></p>
			<?php else : ?>
				<ul class="qvb-list qvb-list--compact">
					<?php foreach ( array_slice( $exceptions, 0, 6 ) as $exception ) : ?>
						<li class="qvb-list__item">
							<div>
								<strong><?php echo esc_html( QVB_Utils::format_date( $exception->from_date, 'M j' ) ); ?><?php echo $exception->to_date !== $exception->from_date ? ' – ' . esc_html( QVB_Utils::format_date( $exception->to_date, 'M j' ) ) : ''; ?></strong>
								<span class="qvb-muted">
									<?php
									if ( 'custom' === $exception->type ) {
										echo esc_html( QVB_Utils::format_time( $exception->start_time ) . '–' . QVB_Utils::format_time( $exception->end_time ) );
									} else {
										esc_html_e( 'Closed', 'qevora-booking' );
									}
									?>
								</span>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p><a class="button button-small" href="<?php echo esc_url( QVB_Admin::url( 'exceptions', array( 'staff_id' => (int) $selected->id ) ) ); ?>"><?php esc_html_e( 'Manage exceptions', 'qevora-booking' ); ?></a></p>
		</div>
	</div>
</div>
