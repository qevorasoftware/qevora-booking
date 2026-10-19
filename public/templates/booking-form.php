<?php
/**
 * Multi-step booking form.
 *
 * @var string $instance
 * @var array  $groups
 * @var array  $staff
 * @var int    $selected_service
 * @var int    $selected_staff
 * @var string $title
 * @var int    $show_title
 * @var string $extra_class
 * @var string $min_date
 * @var string $max_date
 * @var string $form_token
 * @var bool   $approval_manual
 * @var bool   $require_phone
 * @var bool   $require_consent
 * @var string $consent_text
 * @var string $policy
 * @var string $business_name
 * @var string $business_phone
 * @var string $business_email
 *
 * @package Qevora_Booking
 */

defined( 'ABSPATH' ) || exit;

$qvb_timezone = QVB_Settings::get( 'timezone' ) ? QVB_Settings::get( 'timezone' ) : wp_timezone_string();
$qvb_instance = sanitize_html_class( $instance );
$qvb_classes  = 'qvb-app';
if ( $extra_class ) {
	$qvb_classes .= ' ' . $extra_class;
}
?>
<div
	class="<?php echo esc_attr( $qvb_classes ); ?>"
	data-qvb-app
	data-instance="<?php echo esc_attr( $qvb_instance ); ?>"
	data-approval="<?php echo $approval_manual ? 'manual' : 'auto'; ?>"
	data-min-date="<?php echo esc_attr( $min_date ); ?>"
	data-max-date="<?php echo esc_attr( $max_date ); ?>"
	style="--qvb-accent: <?php echo esc_attr( QVB_Utils::hex_color( QVB_Settings::get( 'accent_color' ), '#7c3aed' ) ); ?>;"
>
	<noscript>
		<div class="qvb-notice qvb-notice--warning">
			<?php esc_html_e( 'The online booking form needs JavaScript. Please enable it, or contact us to book.', 'qevora-booking' ); ?>
			<?php if ( $business_phone ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $business_phone ) ); ?>"><?php echo esc_html( $business_phone ); ?></a>
			<?php endif; ?>
			<?php if ( $business_email ) : ?>
				<a href="mailto:<?php echo esc_attr( $business_email ); ?>"><?php echo esc_html( $business_email ); ?></a>
			<?php endif; ?>
		</div>
	</noscript>

	<?php if ( $show_title && $title ) : ?>
		<header class="qvb-app__header">
			<p class="qvb-eyebrow"><?php echo esc_html( $business_name ); ?></p>
			<h2 class="qvb-title"><?php echo esc_html( $title ); ?></h2>
		</header>
	<?php endif; ?>

	<ol class="qvb-steps" data-qvb-indicators aria-label="<?php esc_attr_e( 'Booking progress', 'qevora-booking' ); ?>">
		<li class="qvb-step-indicator is-current" data-qvb-indicator="1" aria-current="step">
			<span class="qvb-step-indicator__number">1</span>
			<span class="qvb-step-indicator__label"><?php esc_html_e( 'Service', 'qevora-booking' ); ?></span>
		</li>
		<li class="qvb-step-indicator" data-qvb-indicator="2">
			<span class="qvb-step-indicator__number">2</span>
			<span class="qvb-step-indicator__label"><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></span>
		</li>
		<li class="qvb-step-indicator" data-qvb-indicator="3">
			<span class="qvb-step-indicator__number">3</span>
			<span class="qvb-step-indicator__label"><?php esc_html_e( 'Date & time', 'qevora-booking' ); ?></span>
		</li>
		<li class="qvb-step-indicator" data-qvb-indicator="4">
			<span class="qvb-step-indicator__number">4</span>
			<span class="qvb-step-indicator__label"><?php esc_html_e( 'Your details', 'qevora-booking' ); ?></span>
		</li>
		<li class="qvb-step-indicator" data-qvb-indicator="5">
			<span class="qvb-step-indicator__number">5</span>
			<span class="qvb-step-indicator__label"><?php esc_html_e( 'Review', 'qevora-booking' ); ?></span>
		</li>
	</ol>

	<form class="qvb-form" data-qvb-form novalidate>
		<input type="hidden" name="form_token" value="<?php echo esc_attr( $form_token ); ?>" data-qvb-token>
		<input type="hidden" name="qvb_time" value="" data-qvb-time>

		<div class="qvb-hp" aria-hidden="true">
			<label>
				<?php esc_html_e( 'Website', 'qevora-booking' ); ?>
				<input type="text" name="qvb_hp" value="" tabindex="-1" autocomplete="off">
			</label>
		</div>

		<section class="qvb-step" data-qvb-step="1" aria-labelledby="qvb-step1-<?php echo esc_attr( $qvb_instance ); ?>">
			<div class="qvb-step__head">
				<h3 id="qvb-step1-<?php echo esc_attr( $qvb_instance ); ?>"><?php esc_html_e( 'Choose a service', 'qevora-booking' ); ?></h3>
				<p><?php esc_html_e( 'Pick the treatment you would like to book.', 'qevora-booking' ); ?></p>
			</div>

			<?php foreach ( $groups as $group ) : ?>
				<div class="qvb-service-group">
					<h4 class="qvb-group-title"><?php echo esc_html( $group['name'] ); ?></h4>
					<div class="qvb-choice-grid">
						<?php foreach ( $group['services'] as $service ) : ?>
							<?php
							$qvb_staff_ids = QVB_Services::staff_ids( (int) $service->id );
							$qvb_checked   = (int) $selected_service === (int) $service->id;
							$qvb_input_id  = 'qvb-service-' . $qvb_instance . '-' . (int) $service->id;
							?>
							<label class="qvb-choice qvb-service" for="<?php echo esc_attr( $qvb_input_id ); ?>"
								data-qvb-service
								data-service-id="<?php echo esc_attr( (int) $service->id ); ?>"
								data-duration="<?php echo esc_attr( QVB_Utils::duration_label( (int) $service->duration ) ); ?>"
								data-duration-minutes="<?php echo esc_attr( (int) $service->duration ); ?>"
								data-price="<?php echo esc_attr( (float) $service->price ); ?>"
								data-price-label="<?php echo esc_attr( QVB_Utils::money( $service->price ) ); ?>"
								data-staff-ids="<?php echo esc_attr( implode( ',', $qvb_staff_ids ) ); ?>">
								<input class="qvb-choice__input" id="<?php echo esc_attr( $qvb_input_id ); ?>" type="radio" name="qvb_service" value="<?php echo esc_attr( (int) $service->id ); ?>" data-qvb-service-input <?php checked( $qvb_checked ); ?>>
								<span class="qvb-choice__media" aria-hidden="true">
									<?php if ( $service->image_id ) : ?>
										<?php echo wp_get_attachment_image( (int) $service->image_id, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
									<?php else : ?>
										<span class="qvb-choice__initials"><?php echo esc_html( QVB_Utils::initials( $service->name ) ); ?></span>
									<?php endif; ?>
								</span>
								<span class="qvb-choice__body">
									<span class="qvb-choice__top">
										<span class="qvb-choice__title"><?php echo esc_html( $service->name ); ?></span>
										<span class="qvb-choice__price"><?php echo esc_html( QVB_Utils::money( $service->price ) ); ?></span>
									</span>
									<span class="qvb-choice__meta"><?php echo esc_html( QVB_Utils::duration_label( (int) $service->duration ) ); ?></span>
									<?php if ( $service->description ) : ?>
										<span class="qvb-choice__description"><?php echo esc_html( QVB_Utils::truncate( $service->description, 110 ) ); ?></span>
									<?php endif; ?>
								</span>
								<span class="qvb-choice__check" aria-hidden="true">
									<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12.5l4.2 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</section>

		<section class="qvb-step" data-qvb-step="2" hidden aria-labelledby="qvb-step2-<?php echo esc_attr( $qvb_instance ); ?>">
			<div class="qvb-step__head">
				<h3 id="qvb-step2-<?php echo esc_attr( $qvb_instance ); ?>"><?php esc_html_e( 'Choose your therapist', 'qevora-booking' ); ?></h3>
				<p><?php esc_html_e( 'Select a specific team member, or let us assign the first available person.', 'qevora-booking' ); ?></p>
			</div>

			<div class="qvb-choice-grid qvb-choice-grid--staff">
				<label class="qvb-choice qvb-staff qvb-staff--any" data-qvb-staff data-staff-id="0" data-service-ids="*">
					<input class="qvb-choice__input" type="radio" name="qvb_staff" value="0" data-qvb-staff-input <?php checked( 0 === $selected_staff ); ?>>
					<span class="qvb-choice__media" aria-hidden="true">
						<span class="qvb-choice__icon">
							<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.2"/><circle cx="16.5" cy="9.5" r="2.4"/><path d="M3.5 19c.4-3.2 2.6-5 5.5-5s5.1 1.8 5.5 5M14 14.4c2.6-.4 5 1.1 5.5 4.1" stroke-linecap="round"/></svg>
						</span>
					</span>
					<span class="qvb-choice__body">
						<span class="qvb-choice__title"><?php esc_html_e( 'Any available staff', 'qevora-booking' ); ?></span>
						<span class="qvb-choice__meta"><?php esc_html_e( 'We will assign the best available team member.', 'qevora-booking' ); ?></span>
					</span>
					<span class="qvb-choice__check" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12.5l4.2 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
				</label>

				<?php foreach ( $staff as $member ) : ?>
					<?php
					$qvb_service_ids = QVB_Staff::service_ids( (int) $member->id );
					$qvb_checked     = (int) $selected_staff === (int) $member->id;
					$qvb_staff_id    = 'qvb-staff-' . $qvb_instance . '-' . (int) $member->id;
					?>
					<label class="qvb-choice qvb-staff" for="<?php echo esc_attr( $qvb_staff_id ); ?>"
						data-qvb-staff
						data-staff-id="<?php echo esc_attr( (int) $member->id ); ?>"
						data-service-ids="<?php echo esc_attr( implode( ',', $qvb_service_ids ) ); ?>">
						<input class="qvb-choice__input" id="<?php echo esc_attr( $qvb_staff_id ); ?>" type="radio" name="qvb_staff" value="<?php echo esc_attr( (int) $member->id ); ?>" data-qvb-staff-input <?php checked( $qvb_checked ); ?>>
						<span class="qvb-choice__media qvb-choice__media--round" aria-hidden="true">
							<?php if ( $member->image_id ) : ?>
								<?php echo wp_get_attachment_image( (int) $member->image_id, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
							<?php else : ?>
								<span class="qvb-choice__initials"><?php echo esc_html( QVB_Utils::initials( $member->name ) ); ?></span>
							<?php endif; ?>
						</span>
						<span class="qvb-choice__body">
							<span class="qvb-choice__title"><?php echo esc_html( $member->name ); ?></span>
							<?php if ( $member->job_title ) : ?>
								<span class="qvb-choice__meta"><?php echo esc_html( $member->job_title ); ?></span>
							<?php endif; ?>
						</span>
						<span class="qvb-choice__check" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12.5l4.2 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="qvb-step" data-qvb-step="3" hidden aria-labelledby="qvb-step3-<?php echo esc_attr( $qvb_instance ); ?>">
			<div class="qvb-step__head">
				<h3 id="qvb-step3-<?php echo esc_attr( $qvb_instance ); ?>"><?php esc_html_e( 'Pick a date and time', 'qevora-booking' ); ?></h3>
				<p><?php esc_html_e( 'Available times update in real time.', 'qevora-booking' ); ?> <span class="qvb-muted"><?php printf( /* translators: %s: timezone name. */ esc_html__( 'Times are in %s.', 'qevora-booking' ), esc_html( $qvb_timezone ) ); ?></span></p>
			</div>

			<div class="qvb-date-row">
				<label class="qvb-field qvb-field--date" for="qvb-date-<?php echo esc_attr( $qvb_instance ); ?>">
					<span class="qvb-field__label"><?php esc_html_e( 'Date', 'qevora-booking' ); ?></span>
					<input id="qvb-date-<?php echo esc_attr( $qvb_instance ); ?>" type="date" name="qvb_date" value="" min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>" data-qvb-date>
				</label>
				<div class="qvb-quick-dates" data-qvb-days aria-live="polite"></div>
			</div>

			<div class="qvb-slots" data-qvb-slots aria-live="polite">
				<p class="qvb-empty"><?php esc_html_e( 'Choose a date to see available times.', 'qevora-booking' ); ?></p>
			</div>
			<p class="qvb-field__error" data-qvb-time-error hidden></p>
		</section>

		<section class="qvb-step" data-qvb-step="4" hidden aria-labelledby="qvb-step4-<?php echo esc_attr( $qvb_instance ); ?>">
			<div class="qvb-step__head">
				<h3 id="qvb-step4-<?php echo esc_attr( $qvb_instance ); ?>"><?php esc_html_e( 'Your details', 'qevora-booking' ); ?></h3>
				<p><?php esc_html_e( 'We use these details only to manage this appointment.', 'qevora-booking' ); ?></p>
			</div>

			<div class="qvb-grid-2">
				<label class="qvb-field">
					<span class="qvb-field__label"><?php esc_html_e( 'Full name', 'qevora-booking' ); ?> <span aria-hidden="true">*</span></span>
					<input type="text" name="qvb_name" autocomplete="name" maxlength="191" required data-qvb-name>
				</label>
				<label class="qvb-field">
					<span class="qvb-field__label"><?php esc_html_e( 'Email address', 'qevora-booking' ); ?> <span aria-hidden="true">*</span></span>
					<input type="email" name="qvb_email" autocomplete="email" maxlength="191" required data-qvb-email>
				</label>
				<label class="qvb-field">
					<span class="qvb-field__label"><?php esc_html_e( 'Phone number', 'qevora-booking' ); ?><?php echo $require_phone ? ' <span aria-hidden="true">*</span>' : ''; ?></span>
					<input type="tel" name="qvb_phone" autocomplete="tel" maxlength="40" <?php echo $require_phone ? 'required' : ''; ?> data-qvb-phone>
				</label>
				<label class="qvb-field qvb-field--full">
					<span class="qvb-field__label"><?php esc_html_e( 'Notes for the salon', 'qevora-booking' ); ?> <span class="qvb-muted"><?php esc_html_e( '(optional)', 'qevora-booking' ); ?></span></span>
					<textarea name="qvb_notes" rows="3" maxlength="2000" data-qvb-notes placeholder="<?php esc_attr_e( 'Allergies, preferences, or anything else we should know.', 'qevora-booking' ); ?>"></textarea>
				</label>
			</div>

			<?php if ( $require_consent ) : ?>
				<label class="qvb-checkbox">
					<input type="checkbox" name="qvb_consent" value="1" required data-qvb-consent>
					<span><?php echo esc_html( $consent_text ); ?></span>
				</label>
			<?php endif; ?>

			<p class="qvb-policy"><?php echo esc_html( $policy ); ?></p>
		</section>

		<section class="qvb-step" data-qvb-step="5" hidden aria-labelledby="qvb-step5-<?php echo esc_attr( $qvb_instance ); ?>">
			<div class="qvb-step__head">
				<h3 id="qvb-step5-<?php echo esc_attr( $qvb_instance ); ?>"><?php esc_html_e( 'Review your booking', 'qevora-booking' ); ?></h3>
				<p><?php esc_html_e( 'Check the details below, then confirm.', 'qevora-booking' ); ?></p>
			</div>

			<div class="qvb-summary">
				<dl class="qvb-summary__list">
					<div><dt><?php esc_html_e( 'Service', 'qevora-booking' ); ?></dt><dd data-qvb-summary="service">—</dd></div>
					<div><dt><?php esc_html_e( 'Staff', 'qevora-booking' ); ?></dt><dd data-qvb-summary="staff">—</dd></div>
					<div><dt><?php esc_html_e( 'Date', 'qevora-booking' ); ?></dt><dd data-qvb-summary="date">—</dd></div>
					<div><dt><?php esc_html_e( 'Time', 'qevora-booking' ); ?></dt><dd data-qvb-summary="time">—</dd></div>
					<div><dt><?php esc_html_e( 'Duration', 'qevora-booking' ); ?></dt><dd data-qvb-summary="duration">—</dd></div>
					<div><dt><?php esc_html_e( 'Price', 'qevora-booking' ); ?></dt><dd data-qvb-summary="price">—</dd></div>
					<div><dt><?php esc_html_e( 'Name', 'qevora-booking' ); ?></dt><dd data-qvb-summary="name">—</dd></div>
					<div><dt><?php esc_html_e( 'Email', 'qevora-booking' ); ?></dt><dd data-qvb-summary="email">—</dd></div>
					<div><dt><?php esc_html_e( 'Phone', 'qevora-booking' ); ?></dt><dd data-qvb-summary="phone">—</dd></div>
				</dl>
			</div>

			<div class="qvb-pay-note">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="2.5" y="6.5" width="19" height="12" rx="2.5"/><path d="M2.5 10.5h19"/></svg>
				<span><?php esc_html_e( 'No online payment. Pay at the salon after your appointment.', 'qevora-booking' ); ?></span>
			</div>

			<p class="qvb-policy"><?php echo esc_html( $policy ); ?></p>
			<p class="qvb-terms-note"><?php esc_html_e( 'By confirming, you agree to this booking and the cancellation policy above.', 'qevora-booking' ); ?></p>
		</section>

		<p class="qvb-form-error" data-qvb-error role="alert" hidden></p>

		<div class="qvb-nav">
			<button type="button" class="qvb-btn qvb-btn--ghost" data-qvb-back hidden><?php esc_html_e( 'Back', 'qevora-booking' ); ?></button>
			<button type="button" class="qvb-btn qvb-btn--primary" data-qvb-next><?php esc_html_e( 'Continue', 'qevora-booking' ); ?></button>
			<button type="submit" class="qvb-btn qvb-btn--primary qvb-btn--submit" data-qvb-submit hidden>
				<?php echo $approval_manual ? esc_html__( 'Request booking', 'qevora-booking' ) : esc_html__( 'Confirm booking', 'qevora-booking' ); ?>
			</button>
			<span class="qvb-nav__hint" data-qvb-step-label aria-live="polite"></span>
		</div>
	</form>

	<div class="qvb-confirmation" data-qvb-confirmation hidden tabindex="-1" aria-live="polite">
		<div class="qvb-confirmation__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.5l4.4 4.2L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</div>
		<h3 data-qvb-confirm-title><?php esc_html_e( 'Booking received', 'qevora-booking' ); ?></h3>
		<p data-qvb-confirm-message></p>
		<p class="qvb-confirmation__ref"><?php esc_html_e( 'Reference', 'qevora-booking' ); ?>: <strong data-qvb-confirm-reference></strong></p>
		<dl class="qvb-summary__list qvb-summary__list--compact">
			<div><dt><?php esc_html_e( 'Service', 'qevora-booking' ); ?></dt><dd data-qvb-confirm-service></dd></div>
			<div><dt><?php esc_html_e( 'When', 'qevora-booking' ); ?></dt><dd data-qvb-confirm-when></dd></div>
		</dl>
		<p><a class="qvb-btn qvb-btn--ghost" data-qvb-manage-link href="#"><?php esc_html_e( 'Manage this booking', 'qevora-booking' ); ?></a></p>
		<p class="qvb-muted"><?php esc_html_e( 'A confirmation email is on its way. No payment is needed online.', 'qevora-booking' ); ?></p>
	</div>
</div>
