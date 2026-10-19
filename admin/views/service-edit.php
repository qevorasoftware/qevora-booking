<?php
/**
 * Add / edit a service.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$service_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$service    = $service_id ? QVB_Services::get( $service_id ) : null;

if ( $service_id && ! $service ) {
	echo '<div class="qvb-inline-block"><div class="notice notice-error"><p>' . esc_html__( 'That service could not be found.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

$defaults = array(
	'id'          => 0,
	'name'        => '',
	'slug'        => '',
	'category_id' => 0,
	'description' => '',
	'image_id'    => 0,
	'duration'    => 60,
	'buffer_time' => (int) QVB_Settings::get( 'default_buffer' ),
	'price'       => 0,
	'status'      => 'active',
	'sort_order'  => 0,
);

$data       = $service ? (array) $service : $defaults;
$categories = QVB_Categories::options( true );
$image_id   = (int) $data['image_id'];
$image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

QVB_Admin::page_header(
	$service ? __( 'Edit service', 'qevora-booking' ) : __( 'Add service', 'qevora-booking' ),
	$service ? $service->name : __( 'Create a new bookable treatment or appointment.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'All services', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'services' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-form-card">
	<input type="hidden" name="action" value="qvb_save_service" />
	<input type="hidden" name="id" value="<?php echo esc_attr( $data['id'] ); ?>" />
	<?php wp_nonce_field( 'qvb_save_service' ); ?>

	<div class="qvb-form-grid">
		<div class="qvb-field qvb-field--full">
			<label for="qvb-service-name"><?php esc_html_e( 'Service name', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="text" name="name" id="qvb-service-name" value="<?php echo esc_attr( $data['name'] ); ?>" maxlength="191" required />
			<p class="qvb-help"><?php esc_html_e( 'For example: Signature massage, Gel manicure, Bridal makeup.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-slug"><?php esc_html_e( 'URL slug', 'qevora-booking' ); ?></label>
			<input type="text" name="slug" id="qvb-service-slug" value="<?php echo esc_attr( $data['slug'] ); ?>" maxlength="191" />
			<p class="qvb-help"><?php esc_html_e( 'Leave blank to generate one from the name.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-category"><?php esc_html_e( 'Category', 'qevora-booking' ); ?></label>
			<select name="category_id" id="qvb-service-category">
				<?php echo QVB_Admin::options_html( $categories, (int) $data['category_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-duration"><?php esc_html_e( 'Duration (minutes)', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="number" name="duration" id="qvb-service-duration" value="<?php echo esc_attr( $data['duration'] ); ?>" min="5" max="1440" step="5" required />
			<p class="qvb-help"><?php esc_html_e( 'How long the appointment takes with the customer.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-buffer"><?php esc_html_e( 'Buffer after appointment (minutes)', 'qevora-booking' ); ?></label>
			<input type="number" name="buffer_time" id="qvb-service-buffer" value="<?php echo esc_attr( $data['buffer_time'] ); ?>" min="0" max="240" step="5" />
			<p class="qvb-help"><?php esc_html_e( 'Cleanup or reset time. The staff member is blocked for this long after the appointment.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-price"><?php esc_html_e( 'Price', 'qevora-booking' ); ?></label>
			<div class="qvb-input-prefix">
				<span><?php echo esc_html( QVB_Utils::currency_symbol() ); ?></span>
				<input type="number" name="price" id="qvb-service-price" value="<?php echo esc_attr( number_format( (float) $data['price'], 2, '.', '' ) ); ?>" min="0" step="0.01" />
			</div>
			<p class="qvb-help"><?php esc_html_e( 'Customers pay at the salon. No online payment is taken.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-status"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></label>
			<select name="status" id="qvb-service-status">
				<?php
				echo QVB_Admin::options_html(
					array(
						'active'   => __( 'Active — visible in the booking form', 'qevora-booking' ),
						'inactive' => __( 'Inactive — hidden from customers', 'qevora-booking' ),
					),
					$data['status']
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-service-sort"><?php esc_html_e( 'Sort order', 'qevora-booking' ); ?></label>
			<input type="number" name="sort_order" id="qvb-service-sort" value="<?php echo esc_attr( $data['sort_order'] ); ?>" step="1" />
			<p class="qvb-help"><?php esc_html_e( 'Lower numbers appear first.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field qvb-field--full">
			<label for="qvb-service-description"><?php esc_html_e( 'Description', 'qevora-booking' ); ?></label>
			<?php
			wp_editor(
				$data['description'],
				'qvb_service_description',
				array(
					'textarea_name' => 'description',
					'textarea_rows' => 6,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => true,
				)
			);
			?>
			<p class="qvb-help"><?php esc_html_e( 'Shown on the service card. Keep it short and helpful.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field qvb-field--full">
			<span class="qvb-label"><?php esc_html_e( 'Service image', 'qevora-booking' ); ?></span>
			<div class="qvb-media" data-qvb-media>
				<input type="hidden" name="image_id" value="<?php echo esc_attr( $image_id ); ?>" data-qvb-media-input />
				<div class="qvb-media__preview" data-qvb-media-preview>
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
					<?php endif; ?>
				</div>
				<div class="qvb-media__actions">
					<button type="button" class="button" data-qvb-media-select><?php esc_html_e( 'Choose image', 'qevora-booking' ); ?></button>
					<button type="button" class="button-link qvb-text-danger" data-qvb-media-remove <?php echo $image_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'qevora-booking' ); ?></button>
				</div>
				<p class="qvb-help"><?php esc_html_e( 'Optional. A square or landscape image works best.', 'qevora-booking' ); ?></p>
			</div>
		</div>
	</div>

	<?php if ( $service ) : ?>
		<?php $staff_ids = QVB_Services::staff_ids( $service->id ); ?>
		<div class="qvb-panel">
			<h3><?php esc_html_e( 'Staff who provide this service', 'qevora-booking' ); ?></h3>
			<?php if ( $staff_ids ) : ?>
				<ul class="qvb-chip-list">
					<?php foreach ( $staff_ids as $staff_id ) : ?>
						<?php $member = QVB_Staff::get( $staff_id ); ?>
						<?php if ( $member ) : ?>
							<li>
								<a class="qvb-chip" href="<?php echo esc_url( QVB_Admin::url( 'staff', array( 'action' => 'edit', 'id' => (int) $member->id ) ) ); ?>">
									<?php echo esc_html( $member->name ); ?>
								</a>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="qvb-muted"><?php esc_html_e( 'No staff assigned yet. Customers cannot book this service until at least one active staff member is assigned.', 'qevora-booking' ); ?></p>
			<?php endif; ?>
			<p><a class="button" href="<?php echo esc_url( QVB_Admin::url( 'staff' ) ); ?>"><?php esc_html_e( 'Manage staff assignments', 'qevora-booking' ); ?></a></p>
		</div>
	<?php endif; ?>

	<p class="qvb-form-actions">
		<button type="submit" class="button button-primary button-large"><?php echo $service ? esc_html__( 'Save service', 'qevora-booking' ) : esc_html__( 'Create service', 'qevora-booking' ); ?></button>
		<a class="button button-large" href="<?php echo esc_url( QVB_Admin::url( 'services' ) ); ?>"><?php esc_html_e( 'Cancel', 'qevora-booking' ); ?></a>

		<?php if ( $service ) : ?>
			<a class="button qvb-align-right qvb-confirm qvb-text-danger" data-confirm="<?php esc_attr_e( 'Delete this service? Historical bookings will keep their saved details.', 'qevora-booking' ); ?>"
				href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_delete_service', array( 'id' => (int) $service->id ) ), 'qvb_delete_service_' . (int) $service->id ) ); ?>">
				<?php esc_html_e( 'Delete service', 'qevora-booking' ); ?>
			</a>
		<?php endif; ?>
	</p>
</form>
