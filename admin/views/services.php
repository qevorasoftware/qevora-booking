<?php
/**
 * Services list screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$category_filter = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status_filter   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$args = array();
if ( $category_filter ) {
	$args['category_id'] = $category_filter;
}
if ( $status_filter ) {
	$args['status'] = $status_filter;
}
if ( $search ) {
	$args['search'] = $search;
}

$services   = QVB_Services::all( $args );
$categories = QVB_Categories::options( true );
$counts     = QVB_Categories::counts();

QVB_Admin::page_header(
	__( 'Services', 'qevora-booking' ),
	__( 'What customers can book, how long it takes and what it costs.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Add service', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'services', array( 'action' => 'edit' ) ),
			'class' => 'page-title-action',
		),
	)
);
?>

<form method="get" class="qvb-filters">
	<input type="hidden" name="page" value="qevora-booking-services" />
	<div class="qvb-filters__row">
		<label class="screen-reader-text" for="qvb-service-search"><?php esc_html_e( 'Search services', 'qevora-booking' ); ?></label>
		<input type="search" id="qvb-service-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search services', 'qevora-booking' ); ?>" />

		<label class="screen-reader-text" for="qvb-category-filter"><?php esc_html_e( 'Category', 'qevora-booking' ); ?></label>
		<select id="qvb-category-filter" name="category_id">
			<?php echo QVB_Admin::options_html( $categories, $category_filter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</select>

		<label class="screen-reader-text" for="qvb-status-filter"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></label>
		<select id="qvb-status-filter" name="status">
			<?php
			echo QVB_Admin::options_html(
				array(
					''         => __( 'All statuses', 'qevora-booking' ),
					'active'   => __( 'Active', 'qevora-booking' ),
					'inactive' => __( 'Inactive', 'qevora-booking' ),
				),
				$status_filter
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'qevora-booking' ); ?></button>
		<a class="button-link" href="<?php echo esc_url( QVB_Admin::url( 'services' ) ); ?>"><?php esc_html_e( 'Reset', 'qevora-booking' ); ?></a>
	</div>
</form>

<?php if ( ! $services ) : ?>
	<div class="qvb-card qvb-empty-state">
		<h2><?php esc_html_e( 'No services yet', 'qevora-booking' ); ?></h2>
		<p><?php esc_html_e( 'Add your first treatment or appointment type. You can group services into categories and assign them to staff members.', 'qevora-booking' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( QVB_Admin::url( 'services', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a service', 'qevora-booking' ); ?></a>
	</div>
<?php else : ?>
	<div class="qvb-table-wrap">
		<table class="wp-list-table widefat fixed striped qvb-table">
			<thead>
				<tr>
					<th scope="col" class="qvb-col-media"><?php esc_html_e( 'Image', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Service', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Duration', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Buffer', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></th>
					<th scope="col" class="qvb-col-actions"><?php esc_html_e( 'Actions', 'qevora-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $services as $service ) : ?>
					<?php
					$edit_url = QVB_Admin::url(
						'services',
						array(
							'action' => 'edit',
							'id'     => (int) $service->id,
						)
					);
					$del_url  = wp_nonce_url(
						QVB_Admin::action_url( 'qvb_delete_service', array( 'id' => (int) $service->id ) ),
						'qvb_delete_service_' . (int) $service->id
					);
					$staff_count = count( QVB_Services::staff_ids( $service->id ) );
					$cat_name    = isset( $categories[ (int) $service->category_id ] ) && $service->category_id ? $categories[ (int) $service->category_id ] : __( 'Uncategorised', 'qevora-booking' );
					?>
					<tr>
						<td>
							<?php if ( $service->image_id ) : ?>
								<?php echo wp_get_attachment_image( (int) $service->image_id, array( 48, 48 ), false, array( 'class' => 'qvb-thumb' ) ); ?>
							<?php else : ?>
								<span class="qvb-thumb qvb-thumb--empty" aria-hidden="true"><?php echo esc_html( mb_substr( $service->name, 0, 1 ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><a class="row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $service->name ); ?></a></strong>
							<?php if ( $service->description ) : ?>
								<div class="qvb-muted qvb-clamp"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $service->description ), 14 ) ); ?></div>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a> | </span>
								<span class="trash"><a class="qvb-confirm" data-confirm="<?php esc_attr_e( 'Delete this service? Historical bookings will keep their saved details.', 'qevora-booking' ); ?>" href="<?php echo esc_url( $del_url ); ?>"><?php esc_html_e( 'Delete', 'qevora-booking' ); ?></a></span>
							</div>
						</td>
						<td><?php echo esc_html( $cat_name ); ?></td>
						<td><?php echo esc_html( QVB_Utils::duration_label( $service->duration ) ); ?></td>
						<td><?php echo $service->buffer_time ? esc_html( QVB_Utils::duration_label( $service->buffer_time ) ) : '—'; ?></td>
						<td><?php echo esc_html( QVB_Utils::money( $service->price ) ); ?></td>
						<td>
							<?php
							printf(
								/* translators: %d: number of staff. */
								esc_html( _n( '%d member', '%d members', $staff_count, 'qevora-booking' ) ),
								esc_html( number_format_i18n( $staff_count ) )
							);
							?>
						</td>
						<td>
							<span class="qvb-badge qvb-badge--<?php echo 'active' === $service->status ? 'confirmed' : 'cancelled'; ?>">
								<?php echo 'active' === $service->status ? esc_html__( 'Active', 'qevora-booking' ) : esc_html__( 'Inactive', 'qevora-booking' ); ?>
							</span>
						</td>
						<td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="qvb-muted">
		<?php
		printf(
			/* translators: %s: category count. */
			esc_html__( 'Categories: %s', 'qevora-booking' ),
			esc_html( number_format_i18n( count( array_filter( array_keys( $counts ) ) ) ) )
		);
		?>
		<a href="<?php echo esc_url( QVB_Admin::url( 'categories' ) ); ?>"><?php esc_html_e( 'Manage categories', 'qevora-booking' ); ?></a>
	</p>
<?php endif; ?>
