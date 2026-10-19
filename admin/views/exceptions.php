<?php
/**
 * Holidays and exceptions list.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$staff_filter = isset( $_GET['staff_id'] ) ? absint( $_GET['staff_id'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$scope        = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'upcoming'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$scope        = in_array( $scope, array( 'upcoming', 'past', 'all' ), true ) ? $scope : 'upcoming';

$args = array();
if ( $staff_filter >= 0 ) {
	$args['staff_id'] = $staff_filter;
}
if ( 'all' !== $scope ) {
	$args['scope'] = $scope;
}

$exceptions = QVB_Availability::get_exceptions( $args );
$staff      = QVB_Staff::all();

QVB_Admin::page_header(
	__( 'Holidays & exceptions', 'qevora-booking' ),
	__( 'Close the salon, block leave or open special hours for a date range.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Add exception', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'exceptions', array( 'action' => 'edit' ) ),
			'class' => 'page-title-action',
		),
	)
);
?>

<form method="get" class="qvb-filters">
	<input type="hidden" name="page" value="qevora-booking-exceptions" />
	<div class="qvb-filters__row">
		<label class="screen-reader-text" for="qvb-exception-staff"><?php esc_html_e( 'Staff member', 'qevora-booking' ); ?></label>
		<select name="staff_id" id="qvb-exception-staff">
			<option value="-1" <?php selected( $staff_filter, -1 ); ?>><?php esc_html_e( 'All staff and business', 'qevora-booking' ); ?></option>
			<option value="0" <?php selected( $staff_filter, 0 ); ?>><?php esc_html_e( 'Business-wide only', 'qevora-booking' ); ?></option>
			<?php foreach ( $staff as $member ) : ?>
				<option value="<?php echo esc_attr( $member->id ); ?>" <?php selected( $staff_filter, (int) $member->id ); ?>><?php echo esc_html( $member->name ); ?></option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="qvb-exception-scope"><?php esc_html_e( 'Time range', 'qevora-booking' ); ?></label>
		<select name="scope" id="qvb-exception-scope">
			<?php
			echo QVB_Admin::options_html(
				array(
					'upcoming' => __( 'Upcoming', 'qevora-booking' ),
					'past'     => __( 'Past', 'qevora-booking' ),
					'all'      => __( 'All dates', 'qevora-booking' ),
				),
				$scope
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'qevora-booking' ); ?></button>
		<a class="button-link" href="<?php echo esc_url( QVB_Admin::url( 'exceptions' ) ); ?>"><?php esc_html_e( 'Reset', 'qevora-booking' ); ?></a>
	</div>
</form>

<?php if ( ! $exceptions ) : ?>
	<div class="qvb-card qvb-empty-state">
		<h2><?php esc_html_e( 'No exceptions found', 'qevora-booking' ); ?></h2>
		<p><?php esc_html_e( 'Use exceptions for public holidays, staff leave and one-off special opening hours. Weekly schedules are managed on the Availability screen.', 'qevora-booking' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( QVB_Admin::url( 'exceptions', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add an exception', 'qevora-booking' ); ?></a>
	</div>
<?php else : ?>
	<div class="qvb-table-wrap">
		<table class="wp-list-table widefat fixed striped qvb-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Dates', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Applies to', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Hours', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Note', 'qevora-booking' ); ?></th>
					<th scope="col" class="qvb-col-actions"><?php esc_html_e( 'Actions', 'qevora-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $exceptions as $exception ) : ?>
					<?php
					$edit_url = QVB_Admin::url(
						'exceptions',
						array(
							'action' => 'edit',
							'id'     => (int) $exception->id,
						)
					);
					$del_url  = wp_nonce_url(
						QVB_Admin::action_url( 'qvb_delete_exception', array( 'id' => (int) $exception->id ) ),
						'qvb_delete_exception_' . (int) $exception->id
					);
					$is_past  = $exception->to_date < QVB_Utils::today();
					?>
					<tr>
						<td>
							<strong><a class="row-title" href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( QVB_Utils::format_date( $exception->from_date, 'M j, Y' ) ); ?>
								<?php if ( $exception->to_date !== $exception->from_date ) : ?>
									– <?php echo esc_html( QVB_Utils::format_date( $exception->to_date, 'M j, Y' ) ); ?>
								<?php endif; ?>
							</a></strong>
							<?php if ( $is_past ) : ?>
								<span class="qvb-muted"><?php esc_html_e( '(past)', 'qevora-booking' ); ?></span>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a> | </span>
								<span class="trash"><a class="qvb-confirm" data-confirm="<?php esc_attr_e( 'Delete this exception?', 'qevora-booking' ); ?>" href="<?php echo esc_url( $del_url ); ?>"><?php esc_html_e( 'Delete', 'qevora-booking' ); ?></a></span>
							</div>
						</td>
						<td>
							<?php if ( (int) $exception->staff_id ) : ?>
								<?php echo esc_html( $exception->staff_name ? $exception->staff_name : __( 'Deleted staff member', 'qevora-booking' ) ); ?>
							<?php else : ?>
								<span class="qvb-badge qvb-badge--neutral"><?php esc_html_e( 'All staff', 'qevora-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'custom' === $exception->type ) : ?>
								<span class="qvb-badge qvb-badge--pending"><?php esc_html_e( 'Special hours', 'qevora-booking' ); ?></span>
							<?php else : ?>
								<span class="qvb-badge qvb-badge--cancelled"><?php esc_html_e( 'Closed', 'qevora-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'custom' === $exception->type ) : ?>
								<?php echo esc_html( QVB_Utils::format_time( $exception->start_time ) . ' – ' . QVB_Utils::format_time( $exception->end_time ) ); ?>
							<?php else : ?>
								<span class="qvb-muted"><?php esc_html_e( 'All day', 'qevora-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $exception->note ? esc_html( $exception->note ) : '<span class="qvb-muted">—</span>'; ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
