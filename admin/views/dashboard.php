<?php
/**
 * Dashboard screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$today      = QVB_Utils::today();
$tomorrow   = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
$week_start = QVB_Utils::week_start();
$week_end   = gmdate( 'Y-m-d', strtotime( $week_start . ' +6 days' ) );
$month_from = gmdate( 'Y-m-01' );
$month_to   = gmdate( 'Y-m-t' );

$today_bookings = QVB_Bookings::for_date( $today );
$today_counts   = QVB_Bookings::status_counts( array( 'date_from' => $today, 'date_to' => $today ) );
$week_stats     = QVB_Bookings::stats( $week_start, $week_end );
$month_stats    = QVB_Bookings::stats( $month_from, $month_to );
$upcoming       = QVB_Bookings::upcoming( 6 );
$pending_total  = QVB_Bookings::status_counts( array() );
$service_count  = count( QVB_Services::all() );
$staff_count    = count( QVB_Staff::all() );
$customer_count = QVB_Customers::count();
$colors         = QVB_Admin::staff_colors();
$table_status   = QVB_Database::status();
$tables_ok      = ! in_array( false, $table_status, true );

QVB_Admin::page_header(
	__( 'Dashboard', 'qevora-booking' ),
	sprintf(
		/* translators: %s: business name. */
		__( 'Today at %s — %s', 'qevora-booking' ),
		QVB_Settings::get( 'business_name' ),
		QVB_Utils::format_date_weekday( $today )
	),
	array(
		array(
			'label' => __( 'Add booking', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'bookings', array( 'action' => 'new' ) ),
			'class' => 'page-title-action',
		),
		array(
			'label' => __( 'Open calendar', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'calendar' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<?php if ( ! $tables_ok ) : ?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'One or more database tables are missing. Bookings will not work until they are repaired.', 'qevora-booking' ); ?>
			<a href="<?php echo esc_url( QVB_Admin::url( 'help' ) ); ?>"><?php esc_html_e( 'Open system status', 'qevora-booking' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<div class="qvb-stat-grid">
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'Today', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( count( $today_bookings ) ) ); ?></strong>
		<span class="qvb-stat__meta"><?php echo esc_html( sprintf( /* translators: 1: confirmed, 2: pending. */ __( '%1$d confirmed · %2$d pending', 'qevora-booking' ), $today_counts['confirmed'], $today_counts['pending'] ) ); ?></span>
	</div>
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'This week', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $week_stats['count'] ) ); ?></strong>
		<span class="qvb-stat__meta"><?php echo esc_html( QVB_Utils::money( $week_stats['revenue'] ) ); ?> <?php esc_html_e( 'expected', 'qevora-booking' ); ?></span>
	</div>
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'This month', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $month_stats['count'] ) ); ?></strong>
		<span class="qvb-stat__meta"><?php echo esc_html( QVB_Utils::money( $month_stats['revenue'] ) ); ?> <?php esc_html_e( 'expected', 'qevora-booking' ); ?></span>
	</div>
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'Awaiting approval', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $pending_total['pending'] ) ); ?></strong>
		<a class="qvb-stat__meta" href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'status' => 'pending' ) ) ); ?>"><?php esc_html_e( 'Review requests', 'qevora-booking' ); ?></a>
	</div>
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'Services', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $service_count ) ); ?></strong>
		<a class="qvb-stat__meta" href="<?php echo esc_url( QVB_Admin::url( 'services' ) ); ?>"><?php esc_html_e( 'Manage services', 'qevora-booking' ); ?></a>
	</div>
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $staff_count ) ); ?></strong>
		<a class="qvb-stat__meta" href="<?php echo esc_url( QVB_Admin::url( 'staff' ) ); ?>"><?php esc_html_e( 'Manage team', 'qevora-booking' ); ?></a>
	</div>
	<div class="qvb-stat">
		<span class="qvb-stat__label"><?php esc_html_e( 'Customers', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $customer_count ) ); ?></strong>
		<a class="qvb-stat__meta" href="<?php echo esc_url( QVB_Admin::url( 'customers' ) ); ?>"><?php esc_html_e( 'View records', 'qevora-booking' ); ?></a>
	</div>
	<div class="qvb-stat qvb-stat--accent">
		<span class="qvb-stat__label"><?php esc_html_e( 'Booking page', 'qevora-booking' ); ?></span>
		<strong class="qvb-stat__value qvb-stat__value--small"><?php echo esc_html( QVB_Utils::booking_page_id() ? __( 'Connected', 'qevora-booking' ) : __( 'Not set', 'qevora-booking' ) ); ?></strong>
		<a class="qvb-stat__meta" href="<?php echo esc_url( QVB_Utils::booking_page_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open booking form', 'qevora-booking' ); ?> &rarr;</a>
	</div>
</div>

<div class="qvb-columns">
	<div class="qvb-card">
		<div class="qvb-card__head">
			<h2><?php esc_html_e( 'Today’s schedule', 'qevora-booking' ); ?></h2>
			<a href="<?php echo esc_url( QVB_Admin::url( 'calendar', array( 'date' => $today ) ) ); ?>"><?php esc_html_e( 'Calendar', 'qevora-booking' ); ?></a>
		</div>

		<?php if ( ! $today_bookings ) : ?>
			<p class="qvb-empty"><?php esc_html_e( 'Nothing booked for today.', 'qevora-booking' ); ?></p>
		<?php else : ?>
			<ul class="qvb-timeline">
				<?php foreach ( $today_bookings as $booking ) : ?>
					<?php
					$color = isset( $colors[ (int) $booking->staff_id ] ) ? $colors[ (int) $booking->staff_id ] : '#7c3aed';
					$link  = QVB_Admin::url(
						'bookings',
						array(
							'action'  => 'edit',
							'booking' => (int) $booking->id,
						)
					);
					?>
					<li class="qvb-timeline__item" style="--qvb-item: <?php echo esc_attr( $color ); ?>">
						<span class="qvb-timeline__time"><?php echo esc_html( QVB_Utils::format_time( $booking->start_time ) ); ?></span>
						<div class="qvb-timeline__body">
							<a href="<?php echo esc_url( $link ); ?>"><strong><?php echo esc_html( $booking->customer_name ); ?></strong></a>
							<span><?php echo esc_html( $booking->service_name ); ?></span>
							<?php echo QVB_Admin::status_badge( $booking->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div class="qvb-card">
		<div class="qvb-card__head">
			<h2><?php esc_html_e( 'Upcoming appointments', 'qevora-booking' ); ?></h2>
			<a href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'status' => 'pending' ) ) ); ?>"><?php esc_html_e( 'All bookings', 'qevora-booking' ); ?></a>
		</div>

		<?php if ( ! $upcoming ) : ?>
			<p class="qvb-empty"><?php esc_html_e( 'No upcoming bookings yet.', 'qevora-booking' ); ?></p>
		<?php else : ?>
			<ul class="qvb-list">
				<?php foreach ( $upcoming as $booking ) : ?>
					<?php
					$link = QVB_Admin::url(
						'bookings',
						array(
							'action'  => 'edit',
							'booking' => (int) $booking->id,
						)
					);
					?>
					<li class="qvb-list__item">
						<div>
							<a href="<?php echo esc_url( $link ); ?>"><strong><?php echo esc_html( $booking->customer_name ); ?></strong></a>
							<span class="qvb-muted">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: date, 2: time. */
										__( '%1$s at %2$s', 'qevora-booking' ),
										QVB_Utils::format_date( $booking->booking_date, 'D, M j' ),
										QVB_Utils::format_time( $booking->start_time )
									)
								);
								?>
							</span>
						</div>
						<?php echo QVB_Admin::status_badge( $booking->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>

<div class="qvb-columns">
	<div class="qvb-card">
		<div class="qvb-card__head">
			<h2><?php esc_html_e( 'Quick actions', 'qevora-booking' ); ?></h2>
		</div>
		<div class="qvb-quick">
			<a class="qvb-quick__link" href="<?php echo esc_url( QVB_Admin::url( 'services', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a service', 'qevora-booking' ); ?></a>
			<a class="qvb-quick__link" href="<?php echo esc_url( QVB_Admin::url( 'staff', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a staff member', 'qevora-booking' ); ?></a>
			<a class="qvb-quick__link" href="<?php echo esc_url( QVB_Admin::url( 'availability' ) ); ?>"><?php esc_html_e( 'Set working hours', 'qevora-booking' ); ?></a>
			<a class="qvb-quick__link" href="<?php echo esc_url( QVB_Admin::url( 'exceptions', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a holiday', 'qevora-booking' ); ?></a>
			<a class="qvb-quick__link" href="<?php echo esc_url( QVB_Admin::url( 'emails' ) ); ?>"><?php esc_html_e( 'Edit email templates', 'qevora-booking' ); ?></a>
			<a class="qvb-quick__link" href="<?php echo esc_url( QVB_Admin::url( 'settings' ) ); ?>"><?php esc_html_e( 'Booking rules', 'qevora-booking' ); ?></a>
		</div>
	</div>

	<div class="qvb-card">
		<div class="qvb-card__head">
			<h2><?php esc_html_e( 'Setup checklist', 'qevora-booking' ); ?></h2>
		</div>
		<ul class="qvb-checklist">
			<li class="<?php echo $service_count ? 'is-done' : ''; ?>"><?php esc_html_e( 'Create at least one service', 'qevora-booking' ); ?></li>
			<li class="<?php echo $staff_count ? 'is-done' : ''; ?>"><?php esc_html_e( 'Add your team members', 'qevora-booking' ); ?></li>
			<li class="<?php echo QVB_Utils::booking_page_id() ? 'is-done' : ''; ?>"><?php esc_html_e( 'Publish the booking page with [qevora_booking]', 'qevora-booking' ); ?></li>
			<li class="<?php echo QVB_Settings::get( 'enable_emails' ) ? 'is-done' : ''; ?>"><?php esc_html_e( 'Turn on booking emails', 'qevora-booking' ); ?></li>
			<li class="<?php echo QVB_Settings::get( 'business_phone' ) ? 'is-done' : ''; ?>"><?php esc_html_e( 'Add business contact details', 'qevora-booking' ); ?></li>
		</ul>
		<p class="qvb-muted">
			<?php
			printf(
				/* translators: %s: shortcode. */
				esc_html__( 'Shortcode: %s', 'qevora-booking' ),
				'<code>[qevora_booking]</code>'
			);
			?>
		</p>
	</div>
</div>
