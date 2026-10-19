<?php
/**
 * Categories list screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$categories = QVB_Categories::all();
$counts     = QVB_Categories::counts();

QVB_Admin::page_header(
	__( 'Categories', 'qevora-booking' ),
	__( 'Group services so customers can browse faster.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Add category', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'categories', array( 'action' => 'edit' ) ),
			'class' => 'page-title-action',
		),
	)
);
?>

<?php if ( ! $categories ) : ?>
	<div class="qvb-card qvb-empty-state">
		<h2><?php esc_html_e( 'No categories yet', 'qevora-booking' ); ?></h2>
		<p><?php esc_html_e( 'Categories are optional, but they make longer service lists easier to browse. For example: Massages, Facials, Hair.', 'qevora-booking' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( QVB_Admin::url( 'categories', array( 'action' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add a category', 'qevora-booking' ); ?></a>
	</div>
<?php else : ?>
	<div class="qvb-table-wrap">
		<table class="wp-list-table widefat fixed striped qvb-table">
			<thead>
				<tr>
					<th scope="col" class="qvb-col-media"><?php esc_html_e( 'Image', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Slug', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Services', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sort', 'qevora-booking' ); ?></th>
					<th scope="col" class="qvb-col-actions"><?php esc_html_e( 'Actions', 'qevora-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $categories as $category ) : ?>
					<?php
					$edit_url = QVB_Admin::url(
						'categories',
						array(
							'action' => 'edit',
							'id'     => (int) $category->id,
						)
					);
					$del_url  = wp_nonce_url(
						QVB_Admin::action_url( 'qvb_delete_category', array( 'id' => (int) $category->id ) ),
						'qvb_delete_category_' . (int) $category->id
					);
					$total    = isset( $counts[ (int) $category->id ] ) ? (int) $counts[ (int) $category->id ] : 0;
					?>
					<tr>
						<td>
							<?php if ( $category->image_id ) : ?>
								<?php echo wp_get_attachment_image( (int) $category->image_id, array( 48, 48 ), false, array( 'class' => 'qvb-thumb' ) ); ?>
							<?php else : ?>
								<span class="qvb-thumb qvb-thumb--empty" aria-hidden="true"><?php echo esc_html( mb_substr( $category->name, 0, 1 ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><a class="row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $category->name ); ?></a></strong>
							<?php if ( $category->description ) : ?>
								<div class="qvb-muted qvb-clamp"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $category->description ), 14 ) ); ?></div>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a> | </span>
								<span class="trash"><a class="qvb-confirm" data-confirm="<?php esc_attr_e( 'Delete this category? Services in it will become uncategorised.', 'qevora-booking' ); ?>" href="<?php echo esc_url( $del_url ); ?>"><?php esc_html_e( 'Delete', 'qevora-booking' ); ?></a></span>
							</div>
						</td>
						<td><code><?php echo esc_html( $category->slug ); ?></code></td>
						<td>
							<?php if ( $total ) : ?>
								<a href="<?php echo esc_url( QVB_Admin::url( 'services', array( 'category_id' => (int) $category->id ) ) ); ?>">
									<?php
									printf(
										/* translators: %d: number of services. */
										esc_html( _n( '%d service', '%d services', $total, 'qevora-booking' ) ),
										esc_html( number_format_i18n( $total ) )
									);
									?>
								</a>
							<?php else : ?>
								<span class="qvb-muted"><?php esc_html_e( 'None', 'qevora-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<span class="qvb-badge qvb-badge--<?php echo 'active' === $category->status ? 'confirmed' : 'cancelled'; ?>">
								<?php echo 'active' === $category->status ? esc_html__( 'Active', 'qevora-booking' ) : esc_html__( 'Inactive', 'qevora-booking' ); ?>
							</span>
						</td>
						<td><?php echo esc_html( (string) $category->sort_order ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
