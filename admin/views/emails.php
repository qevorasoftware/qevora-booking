<?php
/**
 * Email notifications templates.
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( QVB_Capabilities::SETTINGS ) ) {
	wp_die( esc_html__( 'You do not have permission to manage email templates.', 'qevora-booking' ), 403 );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only selection.
$event     = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
$templates = QVB_Notifications::templates();
$editing   = ( $event && isset( $templates[ $event ] ) ) ? $templates[ $event ] : null;

if ( $event && ! $editing ) {
	$event = '';
}

$emails_enabled = (bool) QVB_Settings::get( 'enable_emails' );

QVB_Admin::page_header(
	__( 'Email Notifications', 'qevora-booking' ),
	__( 'Control the emails sent to customers and your team. Templates use placeholders that are replaced when a booking event happens.', 'qevora-booking' ),
	array(
		array(
			'label' => __( 'Notification settings', 'qevora-booking' ),
			'url'   => QVB_Admin::url( 'settings' ) . '#notifications',
			'class' => 'button',
		),
	)
);

if ( ! $emails_enabled ) :
	?>
	<div class="notice notice-warning inline qvb-inline-notice">
		<p>
			<?php esc_html_e( 'Email notifications are currently switched off. Booking emails will not be sent until you enable them in Settings → Notifications.', 'qevora-booking' ); ?>
			<a href="<?php echo esc_url( QVB_Admin::url( 'settings' ) . '#notifications' ); ?>"><?php esc_html_e( 'Enable emails', 'qevora-booking' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<?php if ( ! $editing ) : ?>

	<div class="qvb-card">
		<table class="widefat striped qvb-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Email', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sent to', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'qevora-booking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Subject', 'qevora-booking' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'qevora-booking' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $templates as $key => $template ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $template['label'] ); ?></strong><br /><code><?php echo esc_html( $key ); ?></code></td>
						<td><?php echo esc_html( 'admin' === $template['recipient'] ? __( 'Business / staff', 'qevora-booking' ) : __( 'Customer', 'qevora-booking' ) ); ?></td>
						<td>
							<?php if ( ! empty( $template['enabled'] ) ) : ?>
								<span class="qvb-badge qvb-badge--confirmed"><?php esc_html_e( 'Enabled', 'qevora-booking' ); ?></span>
							<?php else : ?>
								<span class="qvb-badge qvb-badge--cancelled"><?php esc_html_e( 'Disabled', 'qevora-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="qvb-clamp"><?php echo esc_html( $template['subject'] ); ?></td>
						<td class="qvb-row-actions">
							<a class="button button-small" href="<?php echo esc_url( QVB_Admin::url( 'emails', array( 'template' => $key ) ) ); ?>"><?php esc_html_e( 'Edit', 'qevora-booking' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="qvb-columns">
		<div class="qvb-card">
			<h2><?php esc_html_e( 'Send a test email', 'qevora-booking' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The test uses the “Booking confirmed” template with sample data. If it does not arrive, configure SMTP or a mail plugin on your site.', 'qevora-booking' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-form-grid">
				<input type="hidden" name="action" value="qvb_test_email" />
				<?php wp_nonce_field( 'qvb_test_email' ); ?>
				<div class="qvb-field">
					<label for="qvb-test-email"><?php esc_html_e( 'Send to', 'qevora-booking' ); ?></label>
					<input type="email" class="regular-text" id="qvb-test-email" name="to" value="<?php echo esc_attr( QVB_Settings::get( 'admin_email' ) ); ?>" required />
				</div>
				<div class="qvb-field">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Send test email', 'qevora-booking' ); ?></button>
				</div>
			</form>
		</div>

		<div class="qvb-card qvb-meta-list">
			<h2><?php esc_html_e( 'Email delivery checklist', 'qevora-booking' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'WordPress uses wp_mail(); most hosts require an SMTP plugin for reliable delivery.', 'qevora-booking' ); ?></li>
				<li><?php esc_html_e( 'Set a From address on your own domain to improve deliverability.', 'qevora-booking' ); ?></li>
				<li><?php esc_html_e( 'Customer emails depend on a valid address being supplied at booking.', 'qevora-booking' ); ?></li>
				<li><?php esc_html_e( 'Use the Filters API (qvb_email_headers) if you need custom Reply-To behaviour.', 'qevora-booking' ); ?></li>
			</ul>
		</div>
	</div>

<?php else : ?>

	<p class="qvb-breadcrumb">
		<a href="<?php echo esc_url( QVB_Admin::url( 'emails' ) ); ?>">&larr; <?php esc_html_e( 'All email templates', 'qevora-booking' ); ?></a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qvb-card qvb-template-editor">
		<input type="hidden" name="action" value="qvb_save_email_template" />
		<input type="hidden" name="event" value="<?php echo esc_attr( $event ); ?>" />
		<?php wp_nonce_field( 'qvb_save_email_template' ); ?>

		<div class="qvb-template-head">
			<div>
				<h2><?php echo esc_html( $editing['label'] ); ?></h2>
				<p class="description">
					<?php
					echo esc_html(
						'admin' === $editing['recipient']
							? __( 'This email goes to the business / staff address in Settings.', 'qevora-booking' )
							: __( 'This email goes to the customer’s address stored with the booking.', 'qevora-booking' )
					);
					?>
				</p>
			</div>
			<label class="qvb-switch">
				<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $editing['enabled'] ) ); ?> />
				<span><?php esc_html_e( 'Enabled', 'qevora-booking' ); ?></span>
			</label>
		</div>

		<div class="qvb-field">
			<label for="qvb-email-subject"><?php esc_html_e( 'Subject', 'qevora-booking' ); ?></label>
			<input type="text" class="large-text" id="qvb-email-subject" name="subject" value="<?php echo esc_attr( $editing['subject'] ); ?>" required />
		</div>

		<div class="qvb-field">
			<label for="qvb-email-body"><?php esc_html_e( 'Body (HTML allowed)', 'qevora-booking' ); ?></label>
			<textarea class="large-text code qvb-code" id="qvb-email-body" name="body" rows="16" spellcheck="false"><?php echo esc_textarea( $editing['body'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Placeholders are replaced with booking data. Inline styles are recommended for email clients.', 'qevora-booking' ); ?></p>
		</div>

		<div class="qvb-template-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save template', 'qevora-booking' ); ?></button>
			<a class="button qvb-confirm" data-confirm="<?php esc_attr_e( 'Reset this template to the default copy?', 'qevora-booking' ); ?>"
				href="<?php echo esc_url( wp_nonce_url( QVB_Admin::action_url( 'qvb_reset_email_template', array( 'event' => $event ) ), 'qvb_reset_email_template_' . $event ) ); ?>">
				<?php esc_html_e( 'Reset to default', 'qevora-booking' ); ?>
			</a>
			<span class="qvb-save-hint"><?php esc_html_e( 'Saved templates override the plugin defaults.', 'qevora-booking' ); ?></span>
		</div>

		<details class="qvb-preview">
			<summary><?php esc_html_e( 'Preview with sample data', 'qevora-booking' ); ?></summary>
			<iframe class="qvb-email-preview" title="<?php esc_attr_e( 'Email preview', 'qevora-booking' ); ?>" sandbox srcdoc="<?php echo esc_attr( QVB_Notifications::preview( $event ) ); ?>"></iframe>
		</details>
	</form>

	<div class="qvb-card">
		<h3><?php esc_html_e( 'Available placeholders', 'qevora-booking' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Click a placeholder to copy it, then paste it into the subject or body.', 'qevora-booking' ); ?></p>
		<div class="qvb-chip-list qvb-placeholders">
			<?php foreach ( QVB_Notifications::placeholders() as $token => $label ) : ?>
				<button type="button" class="qvb-chip qvb-copy" data-copy="<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $label ); ?>">
					<code><?php echo esc_html( $token ); ?></code>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>
