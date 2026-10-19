=== Qevora Booking ===
Contributors: qevorasoftware
Tags: appointment booking, salon booking, spa booking, massage booking, staff scheduling
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: MIT
License URI: https://opensource.org/licenses/MIT

All-in-one appointment booking for spas, salons, beauty parlours and massage centres. Free online booking, pay at the salon — no payment gateway required.

== Description ==

Qevora Booking is a complete, self-contained appointment booking system for service businesses. Customers choose a service, a staff member (or “any available staff”), a date and an available time, then submit their details. You confirm, reschedule, complete or cancel from a clean WordPress admin. Customers never pay online; the price is shown for transparency and payment happens at your salon.

**Built for real salons**

* Services with category, description, image, duration, price, optional buffer and active/inactive status.
* Staff profiles with service assignment, photo, bio, colour and individual weekly hours, breaks and leave.
* Weekly availability per staff member, or “use business hours” for everyone.
* Holidays, one-off closures and special opening hours with staff-specific or business-wide scope.
* Multi-step public booking form: service → staff → date → slot → details → review → confirmation with reference number.
* Server-side availability re-validation with a database lock, so a slot taken in another tab is rejected instead of double-booked.
* Booking snapshots: changing a service name, price or duration later never rewrites historical bookings.
* Pending, confirmed, completed, cancelled, rejected and no-show statuses with enforced transitions.
* Month, week and day calendar plus searchable, filterable booking list and bulk actions.
* Customer records with booking history and privacy-conscious masking for staff without the PII capability.
* Email notifications for customers and staff, editable templates and a one-click test email.
* `[qevora_booking]` shortcode, Gutenberg block and Elementor widget.
* Settings for timezone, currency, date/time formats, approval mode, booking window, buffers, slot interval, cancellation and reschedule rules, retention and uninstall behaviour.
* Accessible, responsive front end that inherits your theme.

**No payment gateway, by design**

Qevora Booking deliberately does not integrate Stripe, PayPal, Razorpay or any other gateway. There are no paid plans, no subscriptions and no external booking APIs. Prices are informational; customers pay at the salon.

**Privacy**

Customer data is stored only in your WordPress database. Public forms include honeypot, time-trap and salted IP rate limiting. Customer emails and phone numbers are masked in wp-admin for users without the `qvb_view_customer_data` capability. The plugin registers WordPress privacy exporter and eraser entries. Uninstalling does not delete data unless you explicitly opt in.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/qevora-booking`, or install the ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Open **Qevora Booking → Settings** and set your business name, timezone, currency and business hours.
4. Add categories, services and staff. Assign services to each staff member and set their hours.
5. Add the booking form to a page with `[qevora_booking]`, the “Qevora Booking” block, or the Elementor widget.
6. Send a test email from **Qevora Booking → Email Notifications**.

== Frequently Asked Questions ==

= Do customers pay online? =

No. Qevora Booking is a free-booking, pay-at-salon system. No payment gateway is loaded and no card data ever touches your site.

= Can I approve bookings manually? =

Yes. Set **Settings → Booking rules → Approval mode** to “Hold as pending until approved”. New bookings stay pending until you confirm them.

= How do I stop double bookings? =

Availability is enforced on the server at submit time. Each booking is checked against the service duration plus buffer and staff schedule inside a database lock. Conflicting requests are rejected with a clear error.

= Can different staff have different hours? =

Yes. Each staff profile can follow the business hours or use a custom weekly schedule with multiple shifts and breaks. Holidays and one-off hours live under **Holidays / Exceptions**.

= What happens when I edit a service price? =

Existing bookings keep the price, name, duration and staff name captured at booking time. Only new bookings use the updated values.

= How do customers manage their booking? =

Every email includes a secure, signed link to view, cancel or reschedule, limited by your cancellation policy and the reschedule cap. Links are HMAC-signed and do not expose sequential IDs.

= Are emails guaranteed to arrive? =

The plugin uses `wp_mail()`. Most hosts require an SMTP plugin for reliable delivery. Use the test email tool and check your From address.

= Does it work with block themes and Elementor? =

Yes. The shortcode works everywhere, the Gutenberg block is registered server-side and the Elementor widget appears in the “Qevora” category when Elementor is active. Elementor is optional.

= Is the plugin multisite compatible? =

Yes. Activation creates tables and capabilities per site, and a network uninstall respects each site’s data-retention setting.

= What data is removed on uninstall? =

By default nothing except scheduled tasks and caches. If you enable **Delete all plugin data when the plugin is deleted**, uninstall drops the plugin tables, deletes options and removes capabilities.

== Screenshots ==

1. Multi-step public booking form.
2. Admin dashboard with today’s appointments.
3. Month calendar with staff colours.
4. Service and staff management.
5. Weekly availability editor.
6. Email template editor with live preview.

== Changelog ==

= 1.0.1 =
* Fixed: every admin screen now renders inside the standard WordPress `.wrap` container, so cards no longer sit flush against (or run past) the right edge of the window on wide screens.
* Added: `tests/smoke.php` — a 61-assertion Playground suite covering schema, booking engine, all admin screens, shortcode, manage page, REST and email.
* Added: `bin/test-playground.sh` runner and the WordPress Playground demo blueprint.

= 1.0.0 =
* Initial release.
* Eight database tables with versioned schema and upgrade routine.
* Services, categories, staff, availability and exceptions management.
* Multi-step booking form, REST API, shortcode, Gutenberg block and Elementor widget.
* Server-side slot validation with MySQL advisory locking and post-insert race checks.
* Booking lifecycle with enforced transitions, cancellation and reschedule limits.
* Customer records, privacy masking, exporter and eraser integration.
* Editable email templates for customer and staff events.
* Settings for hours, timezone, currency, formats, rules, retention and uninstall.
* Month/week/day calendar, filters and bulk actions.
* Uninstall respects an explicit opt-in before deleting data.

== Upgrade Notice ==

= 1.0.1 =
Admin layout fix: screens now keep the standard WordPress gutter on the right.

= 1.0.0 =
First public release.
