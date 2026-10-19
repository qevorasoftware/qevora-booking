<?php
/**
 * Invalid manage link.
 *
 * @var string $business_name
 * @var string $business_phone
 * @var string $business_email
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="qvb-app qvb-app--notice">
	<div class="qvb-notice qvb-notice--warning" role="status">
		<h2 class="qvb-title"><?php esc_html_e( 'This booking link is not valid', 'qevora-booking' ); ?></h2>
		<p><?php esc_html_e( 'The link may have expired, or the booking details may have changed. Please use the latest link in your confirmation email, or contact us and we will help.', 'qevora-booking' ); ?></p>
		<p>
			<?php echo esc_html( $business_name ); ?>
			<?php if ( $business_phone ) : ?>
				· <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $business_phone ) ); ?>"><?php echo esc_html( $business_phone ); ?></a>
			<?php endif; ?>
			<?php if ( $business_email ) : ?>
				· <a href="mailto:<?php echo esc_attr( $business_email ); ?>"><?php echo esc_html( $business_email ); ?></a>
			<?php endif; ?>
		</p>
		<p><a class="qvb-btn qvb-btn--primary" href="<?php echo esc_url( QVB_Utils::booking_page_url() ); ?>"><?php esc_html_e( 'Make a new booking', 'qevora-booking' ); ?></a></p>
	</div>
</div>
