<?php
/**
 * Bookings list screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$table = new QVB_Bookings_Table();
$table->prepare_items();

$new_url = QVB_Admin::url( 'bookings', array( 'action' => 'new' ) );

QVB_Admin::page_header(
	__( 'Bookings', 'qevora-booking' ),
	__( 'Search, filter and manage every appointment.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Add booking', 'qevora-booking' ),
			'url'   => $new_url,
			'class' => 'page-title-action',
		),
		array(
			'label' => __( 'Calendar view', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'calendar' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<ul class="subsubsub">
	<?php
	$views = $table->get_views();
	echo '<li>' . implode( ' | </li><li>', $views ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</ul>

<form method="get" class="qvb-filters">
	<input type="hidden" name="page" value="qevora-booking-bookings" />
	<div class="qvb-filters__row">
		<label class="screen-reader-text" for="qvb-search"><?php esc_html_e( 'Search bookings', 'qevora-booking' ); ?></label>
		<input type="search" id="qvb-search" name="s" placeholder="<?php esc_attr_e( 'Reference, name, email or phone', 'qevora-booking' ); ?>" value="<?php echo esc_attr( isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />

		<?php
		$service_filter = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$staff_filter   = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>

		<label class="screen-reader-text" for="qvb-service-filter"><?php esc_html_e( 'Service', 'qevora-booking' ); ?></label>
		<select id="qvb-service-filter" name="service_id">
			<?php echo QVB_Admin::options_html( QVB_Services::options( true, false ), $service_filter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</select>

		<label class="screen-reader-text" for="qvb-staff-filter"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></label>
		<select id="qvb-staff-filter" name="staff_id">
			<?php echo QVB_Admin::options_html( QVB_Staff::options( true, false, __( 'All staff', 'qevora-booking' ) ), $staff_filter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</select>

		<label class="screen-reader-text" for="qvb-date-from"><?php esc_html_e( 'From date', 'qevora-booking' ); ?></label>
		<input type="date" id="qvb-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />

		<label class="screen-reader-text" for="qvb-date-to"><?php esc_html_e( 'To date', 'qevora-booking' ); ?></label>
		<input type="date" id="qvb-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'qevora-booking' ); ?></button>
		<a class="button-link" href="<?php echo esc_url( QVB_Admin::url( 'bookings' ) ); ?>"><?php esc_html_e( 'Reset', 'qevora-booking' ); ?></a>
	</div>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-table-form">
	<input type="hidden" name="action" value="qvb_bulk_bookings" />
	<?php wp_nonce_field( 'qvb_bulk_bookings' ); ?>

	<div class="qvb-bulk">
		<label class="screen-reader-text" for="qvb-bulk-action"><?php esc_html_e( 'Bulk action', 'qevora-booking' ); ?></label>
		<select name="qvb_bulk_action" id="qvb-bulk-action">
			<option value=""><?php esc_html_e( 'Bulk actions', 'qevora-booking' ); ?></option>
			<option value="confirm"><?php esc_html_e( 'Change status to confirmed', 'qevora-booking' ); ?></option>
			<option value="complete"><?php esc_html_e( 'Change status to completed', 'qevora-booking' ); ?></option>
			<option value="cancel"><?php esc_html_e( 'Cancel bookings', 'qevora-booking' ); ?></option>
			<option value="reject"><?php esc_html_e( 'Reject requests', 'qevora-booking' ); ?></option>
			<option value="no_show"><?php esc_html_e( 'Mark as no-show', 'qevora-booking' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Apply', 'qevora-booking' ); ?></button>
		<span class="qvb-muted"><?php esc_html_e( 'Status changes email the customer when notifications are enabled.', 'qevora-booking' ); ?></span>
	</div>

	<?php $table->display(); ?>
</form>
