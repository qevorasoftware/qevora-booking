<?php
/**
 * Calendar view: month, week and day.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( QVB_Capabilities::MANAGE ) ) {
	wp_die( esc_html__( 'You do not have permission to view the calendar.', 'qevora-booking' ), 403 );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
$mode  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'month';
$mode  = in_array( $mode, array( 'month', 'week', 'day' ), true ) ? $mode : 'month';
$anchor = isset( $_GET['date'] ) ? QVB_Utils::sanitize_date( wp_unslash( $_GET['date'] ) ) : '';
$anchor = $anchor ? $anchor : QVB_Utils::today();

$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$status_filter = QVB_Utils::is_status( $status_filter ) ? $status_filter : '';

$staff_filter = isset( $_GET['staff'] ) ? absint( $_GET['staff'] ) : 0;

$statuses = $status_filter ? array( $status_filter ) : QVB_Utils::blocking_statuses();

$base_args = array(
	'view'   => $mode,
	'date'   => $anchor,
	'status' => $status_filter,
	'staff'  => $staff_filter ? $staff_filter : '',
);

$anchor_ts = strtotime( $anchor . ' 12:00:00' );
$weekdays  = QVB_Utils::weekdays();

switch ( $mode ) {
	case 'week':
		$from  = QVB_Utils::week_start( $anchor );
		$to    = gmdate( 'Y-m-d', strtotime( $from . ' +6 days' ) );
		$title = sprintf( '%s – %s', QVB_Utils::format_date( $from ), QVB_Utils::format_date( $to ) );
		$prev  = gmdate( 'Y-m-d', strtotime( $from . ' -7 days' ) );
		$next  = gmdate( 'Y-m-d', strtotime( $from . ' +7 days' ) );
		break;

	case 'day':
		$from  = $anchor;
		$to    = $anchor;
		$title = QVB_Utils::format_date_weekday( $anchor );
		$prev  = gmdate( 'Y-m-d', strtotime( $anchor . ' -1 day' ) );
		$next  = gmdate( 'Y-m-d', strtotime( $anchor . ' +1 day' ) );
		break;

	default:
		$month_start = gmdate( 'Y-m-01', $anchor_ts );
		$month_end   = gmdate( 'Y-m-t', $anchor_ts );
		$from        = QVB_Utils::week_start( $month_start );
		$to          = gmdate( 'Y-m-d', strtotime( $from . ' +41 days' ) );
		$title       = QVB_Utils::format_date( $month_start, 'F Y' );
		$prev        = gmdate( 'Y-m-01', strtotime( $month_start . ' -1 month' ) );
		$next        = gmdate( 'Y-m-01', strtotime( $month_start . ' +1 month' ) );
		break;
}

$bookings = QVB_Bookings::calendar(
	$from,
	$to,
	array(
		'statuses' => $statuses,
		'staff_id' => $staff_filter,
	)
);

$grouped = array();

foreach ( $bookings as $booking ) {
	$grouped[ $booking->booking_date ][] = $booking;
}

$colors = QVB_Admin::staff_colors();
$today  = QVB_Utils::today();

$nav_url = function ( $date, $view = null ) use ( $base_args ) {
	$args         = $base_args;
	$args['date'] = $date;

	if ( $view ) {
		$args['view'] = $view;
	}

	return QVB_Admin::url( 'calendar', array_filter( $args, 'strlen' ) );
};

QVB_Admin::page_header(
	__( 'Calendar', 'qevora-booking' ),
	__( 'See every appointment by month, week or day. Click a booking to manage it.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'New booking', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'bookings', array( 'action' => 'new', 'date' => $anchor ) ),
			'class' => 'button button-primary',
		),
	)
);
?>

<div class="qvb-card qvb-calendar-toolbar">
	<div class="qvb-calendar-nav">
		<a class="button qvb-btn-icon" href="<?php echo esc_url( $nav_url( $prev ) ); ?>" aria-label="<?php esc_attr_e( 'Previous period', 'qevora-booking' ); ?>">&larr;</a>
		<a class="button" href="<?php echo esc_url( $nav_url( $today ) ); ?>"><?php esc_html_e( 'Today', 'qevora-booking' ); ?></a>
		<a class="button qvb-btn-icon" href="<?php echo esc_url( $nav_url( $next ) ); ?>" aria-label="<?php esc_attr_e( 'Next period', 'qevora-booking' ); ?>">&rarr;</a>
		<strong class="qvb-calendar-title"><?php echo esc_html( $title ); ?></strong>
	</div>

	<div class="qvb-calendar-views" role="group" aria-label="<?php esc_attr_e( 'Calendar view', 'qevora-booking' ); ?>">
		<?php
		$views = array(
			'month' => __( 'Month', 'qevora-booking' ),
			'week'  => __( 'Week', 'qevora-booking' ),
			'day'   => __( 'Day', 'qevora-booking' ),
		);
		foreach ( $views as $key => $label ) :
			?>
			<a class="qvb-view-tab <?php echo $mode === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $nav_url( $anchor, $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>
</div>

<form class="qvb-card qvb-filters qvb-calendar-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="page" value="qevora-booking-calendar" />
	<input type="hidden" name="view" value="<?php echo esc_attr( $mode ); ?>" />
	<input type="hidden" name="date" value="<?php echo esc_attr( $anchor ); ?>" />

	<label class="screen-reader-text" for="qvb-calendar-status"><?php esc_html_e( 'Filter by status', 'qevora-booking' ); ?></label>
	<select name="status" id="qvb-calendar-status">
		<option value=""><?php esc_html_e( 'All active statuses', 'qevora-booking' ); ?></option>
		<?php foreach ( QVB_Utils::statuses() as $status ) : ?>
			<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status_filter, $status ); ?>><?php echo esc_html( QVB_Utils::status_label( $status ) ); ?></option>
		<?php endforeach; ?>
	</select>

	<label class="screen-reader-text" for="qvb-calendar-staff"><?php esc_html_e( 'Filter by staff', 'qevora-booking' ); ?></label>
	<select name="staff" id="qvb-calendar-staff">
		<?php echo QVB_Admin::options_html( QVB_Staff::options( true, false, __( 'All staff', 'qevora-booking' ) ), $staff_filter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</select>

	<button type="submit" class="button"><?php esc_html_e( 'Apply', 'qevora-booking' ); ?></button>

	<?php if ( $status_filter || $staff_filter ) : ?>
		<a class="qvb-link-reset" href="<?php echo esc_url( QVB_Admin::url( 'calendar', array( 'view' => $mode, 'date' => $anchor ) ) ); ?>"><?php esc_html_e( 'Clear filters', 'qevora-booking' ); ?></a>
	<?php endif; ?>
</form>

<?php if ( 'month' === $mode ) : ?>
	<div class="qvb-card qvb-calendar-month">
		<div class="qvb-month-head" aria-hidden="true">
			<?php foreach ( $weekdays as $label ) : ?>
				<span><?php echo esc_html( $label ); ?></span>
			<?php endforeach; ?>
		</div>

		<div class="qvb-month-grid" role="grid" aria-label="<?php echo esc_attr( $title ); ?>">
			<?php
			$grid_dates   = QVB_Utils::date_range( $from, $to );
			$current_month = gmdate( 'Y-m', strtotime( $month_start ) );

			foreach ( $grid_dates as $date ) :
				$day_bookings = isset( $grouped[ $date ] ) ? $grouped[ $date ] : array();
				$is_today     = $date === $today;
				$in_month     = gmdate( 'Y-m', strtotime( $date ) ) === $current_month;
				$is_weekend   = in_array( QVB_Utils::day_of_week( $date ), array( 0, 6 ), true );
				?>
				<div class="qvb-month-cell<?php echo $is_today ? ' is-today' : ''; ?><?php echo $in_month ? '' : ' is-outside'; ?><?php echo $is_weekend ? ' is-weekend' : ''; ?>" role="gridcell">
					<div class="qvb-month-cell__head">
						<a href="<?php echo esc_url( $nav_url( $date, 'day' ) ); ?>" class="qvb-month-day" aria-label="<?php echo esc_attr( QVB_Utils::format_date_weekday( $date ) ); ?>">
							<?php echo esc_html( QVB_Utils::format_date( $date, 'j' ) ); ?>
						</a>
						<?php if ( $day_bookings ) : ?>
							<span class="qvb-month-count"><?php echo esc_html( number_format_i18n( count( $day_bookings ) ) ); ?></span>
						<?php endif; ?>
					</div>

					<div class="qvb-month-events">
						<?php
						$shown = 0;

						foreach ( $day_bookings as $booking ) :
							if ( $shown >= 5 ) {
								break;
							}
							++$shown;

							$color = isset( $colors[ (int) $booking->staff_id ] ) ? $colors[ (int) $booking->staff_id ] : '#7c3aed';
							?>
							<a class="qvb-cal-event qvb-cal-event--<?php echo esc_attr( $booking->status ); ?>"
								style="--qvb-event-color: <?php echo esc_attr( $color ); ?>"
								href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => (int) $booking->id ) ) ); ?>"
								title="<?php echo esc_attr( sprintf( '%s · %s · %s', $booking->start_time, $booking->customer_name, $booking->service_name ) ); ?>">
								<span class="qvb-cal-event__time"><?php echo esc_html( QVB_Utils::format_time( $booking->start_time ) ); ?></span>
								<span class="qvb-cal-event__label"><?php echo esc_html( $booking->customer_name ); ?></span>
							</a>
						<?php endforeach; ?>

						<?php if ( count( $day_bookings ) > 5 ) : ?>
							<a class="qvb-cal-more" href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'date' => $date ) ) ); ?>">
								<?php
								printf(
									/* translators: %d: number of extra bookings. */
									esc_html__( '+%d more', 'qevora-booking' ),
									count( $day_bookings ) - 5
								);
								?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

<?php elseif ( 'week' === $mode ) : ?>
	<div class="qvb-card qvb-calendar-week">
		<div class="qvb-week-grid">
			<?php foreach ( QVB_Utils::date_range( $from, $to ) as $date ) : ?>
				<?php $day_bookings = isset( $grouped[ $date ] ) ? $grouped[ $date ] : array(); ?>
				<div class="qvb-week-col<?php echo $date === $today ? ' is-today' : ''; ?>">
					<div class="qvb-week-col__head">
						<a href="<?php echo esc_url( $nav_url( $date, 'day' ) ); ?>">
							<span class="qvb-week-dayname"><?php echo esc_html( QVB_Utils::format_date( $date, 'D' ) ); ?></span>
							<span class="qvb-week-daynum"><?php echo esc_html( QVB_Utils::format_date( $date, 'j' ) ); ?></span>
						</a>
						<a class="qvb-week-add" href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'action' => 'new', 'date' => $date ) ) ); ?>" title="<?php esc_attr_e( 'Add booking', 'qevora-booking' ); ?>">+</a>
					</div>

					<div class="qvb-week-col__body">
						<?php if ( ! $day_bookings ) : ?>
							<p class="qvb-week-empty"><?php esc_html_e( 'No appointments', 'qevora-booking' ); ?></p>
						<?php else : ?>
							<?php
							foreach ( $day_bookings as $booking ) :
								$color = isset( $colors[ (int) $booking->staff_id ] ) ? $colors[ (int) $booking->staff_id ] : '#7c3aed';
								?>
								<a class="qvb-cal-event qvb-cal-event--<?php echo esc_attr( $booking->status ); ?>"
									style="--qvb-event-color: <?php echo esc_attr( $color ); ?>"
									href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => (int) $booking->id ) ) ); ?>">
									<span class="qvb-cal-event__time"><?php echo esc_html( QVB_Utils::format_time( $booking->start_time ) ); ?></span>
									<span class="qvb-cal-event__label"><?php echo esc_html( $booking->customer_name ); ?></span>
									<span class="qvb-cal-event__meta"><?php echo esc_html( $booking->service_name ); ?></span>
								</a>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

<?php else : ?>
	<div class="qvb-card">
		<?php if ( empty( $grouped[ $anchor ] ) ) : ?>
			<div class="qvb-empty-state">
				<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No appointments on this day.', 'qevora-booking' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'action' => 'new', 'date' => $anchor ) ) ); ?>"><?php esc_html_e( 'Add a booking', 'qevora-booking' ); ?></a>
			</div>
		<?php else : ?>
			<table class="widefat striped qvb-table qvb-day-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Customer', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Service', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Price', 'qevora-booking' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'qevora-booking' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $grouped[ $anchor ] as $booking ) : ?>
						<tr>
							<td><strong><?php echo esc_html( QVB_Utils::format_time_range( $booking->start_time, $booking->end_time ) ); ?></strong></td>
							<td><?php echo esc_html( $booking->customer_name ); ?></td>
							<td><?php echo esc_html( $booking->service_name ); ?></td>
							<td><?php echo esc_html( $booking->staff_name ); ?></td>
							<td><?php echo QVB_Admin::status_badge( $booking->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( QVB_Utils::money( $booking->price, $booking->currency ) ); ?></td>
							<td class="qvb-row-actions">
								<a class="button button-small" href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => (int) $booking->id ) ) ); ?>"><?php esc_html_e( 'Manage', 'qevora-booking' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="qvb-card qvb-calendar-legend">
	<span class="qvb-legend-title"><?php esc_html_e( 'Legend', 'qevora-booking' ); ?></span>
	<?php foreach ( QVB_Utils::blocking_statuses() as $status ) : ?>
		<span class="qvb-legend-item"><span class="qvb-dot qvb-dot--<?php echo esc_attr( $status ); ?>"></span><?php echo esc_html( QVB_Utils::status_label( $status ) ); ?></span>
	<?php endforeach; ?>
	<?php if ( $staff_filter ) : ?>
		<span class="qvb-legend-note"><?php esc_html_e( 'Showing one staff member.', 'qevora-booking' ); ?></span>
	<?php else : ?>
		<span class="qvb-legend-note"><?php esc_html_e( 'Event colour shows the assigned staff member.', 'qevora-booking' ); ?></span>
	<?php endif; ?>
</div>
