<?php
/**
 * Staff list screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$staff   = QVB_Staff::all();
$schedule_map = array();

foreach ( $staff as $member ) {
	$rows = QVB_Staff::schedule( $member->id );
	$days = array();
	foreach ( $rows as $row ) {
		$days[] = (int) $row->day_of_week;
	}
	$schedule_map[ (int) $member->id ] = array(
		'custom' => $rows,
		'days'   => array_unique( $days ),
	);
}

QVB_Admin::page_header(
	__( 'Staff', 'qevora-booking' ),
	__( 'Team profiles, services and working hours.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Add staff member', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'staff', array( 'action' => 'edit' ) ),
			'class' => 'page-title-action',
		),
		array(
			'label' => __( 'Availability', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'availability' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<?php if ( ! $staff ) : ?>
	<div class="qvb-card qvb-empty-state">
		<h2><?php esc_html_e( 'Add your team', 'qevora-booking' ); ?></h2>
		<p><?php esc_html_e( 'Create a profile for each person who takes appointments, then assign the services they perform.', 'qevora-booking' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( QVB_Admin::url( 'staff', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a staff member', 'qevora-booking' ); ?></a>
	</div>
<?php else : ?>
	<div class="qvb-staff-grid">
		<?php foreach ( $staff as $member ) : ?>
			<?php
			$edit_url = QVB_Admin::url(
				'staff',
				array(
					'action' => 'edit',
					'id'     => (int) $member->id,
				)
			);
			$del_url   = wp_nonce_url(
				QVB_Admin::action_url( 'qvb_delete_staff', array( 'id' => (int) $member->id ) ),
				'qvb_delete_staff_' . (int) $member->id
			);
			$services  = QVB_Staff::services( $member->id );
			$color     = QVB_Utils::hex_color( $member->color, '#7c3aed' );
			$info      = $schedule_map[ (int) $member->id ];
			?>
			<article class="qvb-staff-card" style="--qvb-staff: <?php echo esc_attr( $color ); ?>">
				<div class="qvb-staff-card__top">
					<div class="qvb-avatar">
						<?php if ( $member->image_id ) : ?>
							<?php echo wp_get_attachment_image( (int) $member->image_id, array( 84, 84 ), false, array( 'class' => 'qvb-avatar__img' ) ); ?>
						<?php else : ?>
							<span class="qvb-avatar__initials"><?php echo esc_html( mb_substr( $member->name, 0, 1 ) ); ?></span>
						<?php endif; ?>
					</div>
					<div>
						<h2><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $member->name ); ?></a></h2>
						<?php if ( $member->job_title ) : ?>
							<p class="qvb-muted"><?php echo esc_html( $member->job_title ); ?></p>
						<?php endif; ?>
						<span class="qvb-badge qvb-badge--<?php echo 'active' === $member->status ? 'confirmed' : 'cancelled'; ?>">
							<?php echo 'active' === $member->status ? esc_html__( 'Active', 'qevora-booking' ) : esc_html__( 'Inactive', 'qevora-booking' ); ?>
						</span>
					</div>
				</div>

				<dl class="qvb-staff-card__meta">
					<div>
						<dt><?php esc_html_e( 'Services', 'qevora-booking' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( count( $services ) ) ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Hours', 'qevora-booking' ); ?></dt>
						<dd><?php echo $member->use_business_hours ? esc_html__( 'Business hours', 'qevora-booking' ) : esc_html__( 'Custom schedule', 'qevora-booking' ); ?></dd>
					</div>
				</dl>

				<?php if ( $member->email || $member->phone ) : ?>
					<p class="qvb-muted qvb-small">
						<?php echo $member->email ? esc_html( QVB_Admin::can_view_pii() ? $member->email : QVB_Utils::mask_email( $member->email ) ) : ''; ?>
						<?php echo $member->email && $member->phone ? ' · ' : ''; ?>
						<?php echo $member->phone ? esc_html( QVB_Admin::can_view_pii() ? $member->phone : QVB_Utils::mask_phone( $member->phone ) ) : ''; ?>
					</p>
				<?php endif; ?>

				<div class="qvb-staff-card__actions">
					<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit profile', 'qevora-booking' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( QVB_Admin::url( 'availability', array( 'staff_id' => (int) $member->id ) ) ); ?>"><?php esc_html_e( 'Hours', 'qevora-booking' ); ?></a>
					<a class="button-link qvb-text-danger qvb-confirm" data-confirm="<?php esc_attr_e( 'Delete this staff member? Historical bookings keep their saved details.', 'qevora-booking' ); ?>" href="<?php echo esc_url( $del_url ); ?>"><?php esc_html_e( 'Delete', 'qevora-booking' ); ?></a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
