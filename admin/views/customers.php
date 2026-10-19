<?php
/**
 * Customers list screen.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$table = new QVB_Customers_Table();
$table->prepare_items();

QVB_Admin::page_header(
	__( 'Customers', 'qevora-booking' ),
	__( 'Booking history and contact records. Access is limited by capability.', 'qevora-booking' )
);
?>

<?php if ( ! QVB_Admin::can_view_pii() ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'Your account cannot view customer contact details. You can still see booking volumes and anonymised contact information.', 'qevora-booking' ); ?></p>
	</div>
<?php endif; ?>

<form method="get" class="qvb-filters">
	<input type="hidden" name="page" value="qevora-booking-customers" />
	<div class="qvb-filters__row">
		<label class="screen-reader-text" for="qvb-customer-search"><?php esc_html_e( 'Search customers', 'qevora-booking' ); ?></label>
		<input type="search" id="qvb-customer-search" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" placeholder="<?php esc_attr_e( 'Name, email or phone', 'qevora-booking' ); ?>" />
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'qevora-booking' ); ?></button>
		<a class="button-link" href="<?php echo esc_url( QVB_Admin::url( 'customers' ) ); ?>"><?php esc_html_e( 'Reset', 'qevora-booking' ); ?></a>
	</div>
</form>

<div class="qvb-table-wrap qvb-table-wrap--list">
	<?php $table->display(); ?>
</div>

<div class="qvb-card">
	<h2><?php esc_html_e( 'Privacy and data', 'qevora-booking' ); ?></h2>
	<p><?php esc_html_e( 'Customer records are created automatically when someone books. Contact information is only visible to roles with the “View customer data” capability. You can anonymise a customer from their detail screen; their booking history is retained without personal contact details.', 'qevora-booking' ); ?></p>
	<p><a href="<?php echo esc_url( QVB_Admin::url( 'settings' ) . '#data' ); ?>"><?php esc_html_e( 'Review data retention settings', 'qevora-booking' ); ?></a></p>
</div>
