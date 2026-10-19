<?php
/**
 * Add / edit a category.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$category_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$category    = $category_id ? QVB_Categories::get( $category_id ) : null;

if ( $category_id && ! $category ) {
	echo '<div class="qvb-inline-block"><div class="notice notice-error"><p>' . esc_html__( 'That category could not be found.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

$data = $category ? (array) $category : array(
	'id'          => 0,
	'name'        => '',
	'slug'        => '',
	'description' => '',
	'image_id'    => 0,
	'status'      => 'active',
	'sort_order'  => 0,
);

$image_id  = (int) $data['image_id'];
$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

QVB_Admin::page_header(
	$category ? __( 'Edit category', 'qevora-booking' ) : __( 'Add category', 'qevora-booking' ),
	$category ? $category->name : __( 'Create a group for related services.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'All categories', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'categories' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
	)
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-form-card">
	<input type="hidden" name="action" value="qvb_save_category" />
	<input type="hidden" name="id" value="<?php echo esc_attr( $data['id'] ); ?>" />
	<?php wp_nonce_field( 'qvb_save_category' ); ?>

	<div class="qvb-form-grid">
		<div class="qvb-field">
			<label for="qvb-category-name"><?php esc_html_e( 'Name', 'qevora-booking' ); ?> <span class="qvb-required">*</span></label>
			<input type="text" name="name" id="qvb-category-name" value="<?php echo esc_attr( $data['name'] ); ?>" maxlength="191" required />
		</div>

		<div class="qvb-field">
			<label for="qvb-category-slug"><?php esc_html_e( 'URL slug', 'qevora-booking' ); ?></label>
			<input type="text" name="slug" id="qvb-category-slug" value="<?php echo esc_attr( $data['slug'] ); ?>" maxlength="191" />
			<p class="qvb-help"><?php esc_html_e( 'Leave blank to generate one from the name.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field">
			<label for="qvb-category-status"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></label>
			<select name="status" id="qvb-category-status">
				<?php
				echo QVB_Admin::options_html(
					array(
						'active'   => __( 'Active', 'qevora-booking' ),
						'inactive' => __( 'Inactive', 'qevora-booking' ),
					),
					$data['status']
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</select>
		</div>

		<div class="qvb-field">
			<label for="qvb-category-sort"><?php esc_html_e( 'Sort order', 'qevora-booking' ); ?></label>
			<input type="number" name="sort_order" id="qvb-category-sort" value="<?php echo esc_attr( $data['sort_order'] ); ?>" step="1" />
		</div>

		<div class="qvb-field qvb-field--full">
			<label for="qvb-category-description"><?php esc_html_e( 'Description', 'qevora-booking' ); ?></label>
			<textarea name="description" id="qvb-category-description" rows="4"><?php echo esc_textarea( $data['description'] ); ?></textarea>
			<p class="qvb-help"><?php esc_html_e( 'Optional. Displayed above the category in the booking form.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-field qvb-field--full">
			<span class="qvb-label"><?php esc_html_e( 'Category image', 'qevora-booking' ); ?></span>
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
			</div>
		</div>
	</div>

	<p class="qvb-form-actions">
		<button type="submit" class="button button-primary button-large"><?php echo $category ? esc_html__( 'Save category', 'qevora-booking' ) : esc_html__( 'Create category', 'qevora-booking' ); ?></button>
		<a class="button button-large" href="<?php echo esc_url( QVB_Admin::url( 'categories' ) ); ?>"><?php esc_html_e( 'Cancel', 'qevora-booking' ); ?></a>

		<?php if ( $category ) : ?>
			<a class="button qvb-align-right qvb-confirm qvb-text-danger" data-confirm="<?php esc_attr_e( 'Delete this category? Services in it will become uncategorised.', 'qevora-booking' ); ?>"
				href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_delete_category', array( 'id' => (int) $category->id ) ), 'qvb_delete_category_' . (int) $category->id ) ); ?>">
				<?php esc_html_e( 'Delete category', 'qevora-booking' ); ?>
			</a>
		<?php endif; ?>
	</p>
</form>
