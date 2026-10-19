<?php
/**
 * Add / edit a staff member.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$staff_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$member   = $staff_id ? QVB_Staff::get( $staff_id ) : null;

if ( $staff_id && ! $member ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'That staff member could not be found.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

$defaults = array(
	'id'                 => 0,
	'name'               => '',
	'email'              => '',
	'phone'              => '',
	'job_title'          => '',
	'bio'                => '',
	'image_id'           => 0,
	'color'              => QVB_Staff::suggested_color( count( QVB_Staff::all() ) ),
	'use_business_hours' => 1,
	'status'             => 'active',
	'sort_order'         => 0,
);

$data        = $member ? (array) $member : $defaults;
$image_id    = (int) $data['image_id'];
$image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
$services    = QVB_Services::all();
$assigned    = $member ? QVB_Staff::services( $member->id ) : array();
$categories  = QVB_Categories::all();
$schedule    = $member ? QVB_Staff::schedule( $member->id ) : array();

QVB_Admin::page_header(
	$member ? __( 'Edit staff member', 'qevora-booking' ) : __( 'Add staff member', 'qevora-booking' ),
	$member ? $member->name : __( 'Create a profile and assign the services this person performs.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'All staff', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'staff' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-form-card">
	<input type="hidden" name="action" value="qvb_save_staff" />
	<input type="hidden" name="id" value="<?php echo esc_attr( $data['id'] ); ?>" />
	<?php wp_nonce_field( 'qvb_save_staff' ); ?>

	<div class="qvb-form-grid">
		<div class="qvb-field">
			<label for="qvb-staff-name"><?php esc_html_e( 'Full name', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="text" name="name" id="qvb-staff-name" value="<?php echo esc_attr( $data['name'] ); ?>" maxlength="191" required />
		</div>

		<div class="qvb-field">
			<label for="qvb-staff-title"><?php esc_html_e( 'Job title', 'qevora-booking' ); ?></label>
			<input type="text" name="job_title" id="qvb-staff-title" value="<?php echo esc_attr( $data['job_title'] ); ?>" maxlength="191" placeholder="<?php esc_attr_e( 'Senior therapist', 'qevora-booking' ); ?>" />
		</div>

		<div class="qvb-field">
			<label for="qvb-staff-email"><?php esc_html_e( 'Email', 'qevora-booking' ); ?></label>
			<input type="email" name="email" id="qvb-staff-email" value="<?php echo esc_attr( $data['email'] ); ?>" maxlength="191" />
			<p class="qvb-help"><?php esc_html_e( 'Optional. Used for internal reference only.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-staff-phone"><?php esc_html_e( 'Phone', 'qevora-booking' ); ?></label>
			<input type="tel" name="phone" id="qvb-staff-phone" value="<?php echo esc_attr( $data['phone'] ); ?>" maxlength="40" />
		</div>

		<div class="qvb-field">
			<label for="qvb-staff-color"><?php esc_html_e( 'Calendar colour', 'qevora-booking' ); ?></label>
			<input type="color" name="color" id="qvb-staff-color" value="<?php echo esc_attr( QVB_Utils::hex_color( $data['color'], '#7c3aed' ) ); ?>" />
			<p class="qvb-help"><?php esc_html_e( 'Used to identify this person on the calendar.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-staff-status"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></label>
			<select name="status" id="qvb-staff-status">
				<?php
				echo QVB_Admin::options_html(
					array(
						'active'   => __( 'Active — can be booked', 'qevora-booking' ),
						'inactive' => __( 'Inactive — hidden from customers', 'qevora-booking' ),
					),
					$data['status']
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-staff-sort"><?php esc_html_e( 'Sort order', 'qevora-booking' ); ?></label>
			<input type="number" name="sort_order" id="qvb-staff-sort" value="<?php echo esc_attr( $data['sort_order'] ); ?>" step="1" />
		</div>

		<div class="qvb-field qvb-field--full">
			<label class="qvb-inline">
				<input type="checkbox" name="use_business_hours" value="1" <?php checked( ! empty( $data['use_business_hours'] ) ); ?> />
				<?php esc_html_e( 'Follow the business hours set in Settings', 'qevora-booking' ); ?>
			</label>
			<p class="qvb-help"><?php esc_html_e( 'Uncheck to give this person a custom weekly schedule. You can edit shifts on the Availability screen.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field qvb-field--full">
			<label for="qvb-staff-bio"><?php esc_html_e( 'Short bio', 'qevora-booking' ); ?></label>
			<textarea name="bio" id="qvb-staff-bio" rows="4" maxlength="1000"><?php echo esc_textarea( $data['bio'] ); ?></textarea>
			<p class="qvb-help"><?php esc_html_e( 'Optional. Shown on the staff selection step.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field qvb-field--full">
			<span class="qvb-label"><?php esc_html_e( 'Profile photo', 'qevora-booking' ); ?></span>
			<div class="qvb-media" data-qvb-media>
				<input type="hidden" name="image_id" value="<?php echo esc_attr( $image_id ); ?>" data-qvb-media-input />
				<div class="qvb-media__preview" data-qvb-media-preview>
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
					<?php endif; ?>
				</div>
				<div class="qvb-media__actions">
					<button type="button" class="button" data-qvb-media-select><?php esc_html_e( 'Choose photo', 'qevora-booking' ); ?></button>
					<button type="button" class="button-link qvb-text-danger" data-qvb-media-remove <?php echo $image_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'qevora-booking' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<div class="qvb-panel">
		<h3><?php esc_html_e( 'Services performed', 'qevora-booking' ); ?></h3>
		<p class="qvb-help"><?php esc_html_e( 'Customers only see this person for services selected here. To offer "any available staff", leave at least one qualified person assigned to each service.', 'qevora-booking' ); ?></p>

		<?php if ( ! $services ) : ?>
			<p class="qvb-muted"><?php esc_html_e( 'No services exist yet.', 'qevora-booking' ); ?> <a href="<?php echo esc_url( QVB_Admin::url( 'services', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a service', 'qevora-booking' ); ?></a></p>
		<?php else : ?>
			<?php foreach ( $categories as $category ) : ?>
				<?php
				$in_category = array_filter(
					$services,
					static function ( $service ) use ( $category ) {
						return (int) $service->category_id === (int) $category->id;
					}
				);
				if ( ! $in_category ) {
					continue;
				}
				?>
				<fieldset class="qvb-checkbox-group">
					<legend><?php echo esc_html( $category->name ); ?></legend>
					<?php foreach ( $in_category as $service ) : ?>
						<label class="qvb-checkbox">
							<input type="checkbox" name="services[]" value="<?php echo esc_attr( $service->id ); ?>" <?php checked( in_array( (int) $service->id, $assigned, true ) ); ?> />
							<span><?php echo esc_html( $service->name ); ?> <span class="qvb-muted">(<?php echo esc_html( QVB_Utils::duration_label( $service->duration ) ); ?>)</span></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>

			<?php
			$uncategorised = array_filter( $services, static function ( $service ) { return ! (int) $service->category_id; } );
			?>
			<?php if ( $uncategorised ) : ?>
				<fieldset class="qvb-checkbox-group">
					<legend><?php esc_html_e( 'Uncategorised', 'qevora-booking' ); ?></legend>
					<?php foreach ( $uncategorised as $service ) : ?>
						<label class="qvb-checkbox">
							<input type="checkbox" name="services[]" value="<?php echo esc_attr( $service->id ); ?>" <?php checked( in_array( (int) $service->id, $assigned, true ) ); ?> />
							<span><?php echo esc_html( $service->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $member ) : ?>
		<div class="qvb-panel">
			<h3><?php esc_html_e( 'Working hours', 'qevora-booking' ); ?></h3>
			<p class="qvb-muted">
				<?php
				echo esc_html(
					! empty( $member->use_business_hours )
						? __( 'This person currently follows the business hours.', 'qevora-booking' )
						: __( 'This person has a custom weekly schedule.', 'qevora-booking' )
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( QVB_Admin::url( 'availability', array( 'staff_id' => (int) $member->id ) ) ); ?>"><?php esc_html_e( 'Edit weekly schedule', 'qevora-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( QVB_Admin::url( 'exceptions', array( 'action' => 'edit', 'staff_id' => (int) $member->id ) ) ); ?>"><?php esc_html_e( 'Add leave or holiday', 'qevora-booking' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<p class="qvb-form-actions">
		<button type="submit" class="button button-primary button-large"><?php echo $member ? esc_html__( 'Save staff member', 'qevora-booking' ) : esc_html__( 'Create staff member', 'qevora-booking' ); ?></button>
		<a class="button button-large" href="<?php echo esc_url( QVB_Admin::url( 'staff' ) ); ?>"><?php esc_html_e( 'Cancel', 'qevora-booking' ); ?></a>

		<?php if ( $member ) : ?>
			<a class="button qvb-align-right qvb-confirm qvb-text-danger" data-confirm="<?php esc_attr_e( 'Delete this staff member? Historical bookings keep their saved details.', 'qevora-booking' ); ?>"
				href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_delete_staff', array( 'id' => (int) $member->id ) ), 'qvb_delete_staff_' . (int) $member->id ) ); ?>">
				<?php esc_html_e( 'Delete staff member', 'qevora-booking' ); ?>
			</a>
		<?php endif; ?>
	</p>
</form>
