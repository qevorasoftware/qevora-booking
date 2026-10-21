<?php
/**
 * Customer detail and booking history.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$customer_id = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$customer    = QVB_Customers::get( $customer_id );

if ( ! $customer ) {
	echo '<div class="qvb-inline-block"><div class="notice notice-error"><p>' . esc_html__( 'That customer could not be found.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

if ( ! current_user_can( QVB_Capabilities::PII ) ) {
	echo '<div class="qvb-inline-block"><div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view customer contact details.', 'qevora-booking' ) . '</p></div></div>';
	return;
}

$bookings = QVB_Customers::bookings( $customer->id, 200 );
$stats    = array(
	'total'     => 0,
	'completed' => 0,
	'upcoming'  => 0,
	'cancelled' => 0,
	'spent'     => 0.0,
);

foreach ( $bookings as $booking ) {
	$stats['total']++;
	if ( 'completed' === $booking->status ) {
		$stats['completed']++;
		$stats['spent'] += (float) $booking->price;
	}
	if ( in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) {
		$stats['upcoming']++;
	}
	if ( in_array( $booking->status, array( 'cancelled', 'rejected' ), true ) ) {
		$stats['cancelled']++;
	}
}

$edit_url = QVB_Admin::url( 'customers', array( 'action' => 'view', 'customer' => (int) $customer->id ) );

QVB_Admin::page_header(
	$customer->name,
	sprintf(
		/* translators: %s: date. */
		__( 'Customer since %s', 'qevora-booking' ),
		QVB_Utils::format_admin_datetime( $customer->created_at )
	),
	array(
		array(
			'label' => __( 'All customers', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'customers' ),
			'class' => 'qvb-btn qvb-btn--ghost',
		),
		array(
			'label' => __( 'Add booking', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'bookings', array( 'action' => 'new', 'customer_id' => (int) $customer->id ) ),
			'class' => 'page-title-action',
		),
	)
);
?>

<div class="qvb-stat-grid qvb-stat-grid--compact">
	<div class="qvb-stat"><span class="qvb-stat__label"><?php esc_html_e( 'Bookings', 'qevora-booking' ); ?></span><strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong></div>
	<div class="qvb-stat"><span class="qvb-stat__label"><?php esc_html_e( 'Completed', 'qevora-booking' ); ?></span><strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $stats['completed'] ) ); ?></strong></div>
	<div class="qvb-stat"><span class="qvb-stat__label"><?php esc_html_e( 'Upcoming', 'qevora-booking' ); ?></span><strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $stats['upcoming'] ) ); ?></strong></div>
	<div class="qvb-stat"><span class="qvb-stat__label"><?php esc_html_e( 'Cancelled', 'qevora-booking' ); ?></span><strong class="qvb-stat__value"><?php echo esc_html( number_format_i18n( $stats['cancelled'] ) ); ?></strong></div>
	<div class="qvb-stat"><span class="qvb-stat__label"><?php esc_html_e( 'Completed value', 'qevora-booking' ); ?></span><strong class="qvb-stat__value qvb-stat__value--small"><?php echo esc_html( QVB_Utils::money( $stats['spent'] ) ); ?></strong></div>
</div>

<div class="qvb-card qvb-history-card">
	<div class="qvb-card__head">
		<h2><?php esc_html_e( 'Booking history', 'qevora-booking' ); ?></h2>
		<a href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 's' => $customer->email ) ) ); ?>"><?php esc_html_e( 'Open in bookings', 'qevora-booking' ); ?></a>
	</div>

	<?php if ( ! $bookings ) : ?>
		<p class="qvb-empty"><?php esc_html_e( 'No bookings yet.', 'qevora-booking' ); ?></p>
	<?php else : ?>
		<div class="qvb-table-wrap qvb-table-wrap--history">
			<table class="wp-list-table widefat striped qvb-table qvb-history-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Reference', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Service', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Price', 'qevora-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bookings as $booking ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( QVB_Admin::url( 'bookings', array( 'action' => 'edit', 'booking' => (int) $booking->id ) ) ); ?>"><code><?php echo esc_html( $booking->reference ); ?></code></a></td>
							<td>
								<?php echo esc_html( QVB_Utils::format_date( $booking->booking_date, 'M j, Y' ) ); ?>
								<span class="qvb-muted"><?php echo esc_html( QVB_Utils::format_time( $booking->start_time ) ); ?></span>
							</td>
							<td><?php echo esc_html( $booking->service_name ); ?></td>
							<td><?php echo $booking->staff_name ? esc_html( $booking->staff_name ) : '&mdash;'; ?></td>
							<td><?php echo QVB_Admin::status_badge( $booking->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo esc_html( QVB_Utils::money( $booking->price, $booking->currency ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<div class="qvb-columns qvb-columns--sidebar">
	<div class="qvb-columns__main">
		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Contact details', 'qevora-booking' ); ?></h2></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="qvb_save_customer" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $customer->id ); ?>" />
				<?php wp_nonce_field( 'qvb_save_customer_' . (int) $customer->id ); ?>

				<div class="qvb-form-grid qvb-form-grid--two">
					<p class="qvb-field">
						<label for="qvb-customer-name"><?php esc_html_e( 'Name', 'qevora-booking' ); ?></label>
						<input type="text" name="name" id="qvb-customer-name" value="<?php echo esc_attr( $customer->name ); ?>" required />
					</p>
					<p class="qvb-field">
						<label for="qvb-customer-email"><?php esc_html_e( 'Email', 'qevora-booking' ); ?></label>
						<input type="email" name="email" id="qvb-customer-email" value="<?php echo esc_attr( $customer->email ); ?>" required />
					</p>
					<p class="qvb-field">
						<label for="qvb-customer-phone"><?php esc_html_e( 'Phone', 'qevora-booking' ); ?></label>
						<input type="tel" name="phone" id="qvb-customer-phone" value="<?php echo esc_attr( $customer->phone ); ?>" />
					</p>
					<p class="qvb-field qvb-field--full">
						<label for="qvb-customer-notes"><?php esc_html_e( 'Private notes', 'qevora-booking' ); ?></label>
						<textarea name="notes" id="qvb-customer-notes" rows="3"><?php echo esc_textarea( (string) $customer->notes ); ?></textarea>
					</p>
				</div>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save customer', 'qevora-booking' ); ?></button></p>
			</form>
		</div>
	</div>

	<div class="qvb-columns__side">
		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Privacy', 'qevora-booking' ); ?></h2></div>
			<p class="qvb-help"><?php esc_html_e( 'Anonymising removes the name, email, phone, notes and IP hashes from this customer and their bookings. Booking dates, services and amounts remain for reporting. This cannot be undone.', 'qevora-booking' ); ?></p>
			<a class="button qvb-text-danger qvb-confirm" data-confirm="<?php esc_attr_e( 'Anonymise this customer? This cannot be undone.', 'qevora-booking' ); ?>"
				href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_anonymize_customer', array( 'id' => (int) $customer->id ) ), 'qvb_anonymize_customer_' . (int) $customer->id ) ); ?>">
				<?php esc_html_e( 'Anonymise customer', 'qevora-booking' ); ?>
			</a>
		</div>

		<div class="qvb-card">
			<div class="qvb-card__head"><h2><?php esc_html_e( 'Record', 'qevora-booking' ); ?></h2></div>
			<ul class="qvb-meta-list">
				<li><span><?php esc_html_e( 'Created', 'qevora-booking' ); ?></span><strong><?php echo esc_html( QVB_Utils::format_admin_datetime( $customer->created_at ) ); ?></strong></li>
				<li><span><?php esc_html_e( 'Updated', 'qevora-booking' ); ?></span><strong><?php echo esc_html( QVB_Utils::format_admin_datetime( $customer->updated_at ) ); ?></strong></li>
				<li><span><?php esc_html_e( 'WordPress user', 'qevora-booking' ); ?></span><strong><?php echo $customer->wp_user_id ? esc_html( get_the_author_meta( 'display_name', (int) $customer->wp_user_id ) ) : '—'; ?></strong></li>
			</ul>
			<p class="qvb-help"><?php printf( /* translators: %s: email. */ esc_html__( 'Email used for lookups: %s', 'qevora-booking' ), '<code>' . esc_html( $customer->email ) . '</code>' ); ?></p>
		</div>
	</div>
</div>
