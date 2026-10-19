# Qevora Booking

All-in-one appointment booking for spas, salons, beauty parlours and massage centres.
**Free online booking — customers pay at the salon.** No Stripe, no PayPal, no Razorpay, no subscriptions.

[![Version](https://img.shields.io/badge/version-1.0.0-7c3aed)](#)
[![WordPress](https://img.shields.io/badge/WordPress-6.2%20–%207.1-21759b)](#)
[![PHP](https://img.shields.io/badge/PHP-7.4%20–%208.3-777bb4)](#)
[![License](https://img.shields.io/badge/license-MIT-green)](#)

---

## What it does

| Area | Highlights |
|------|------------|
| **Services** | Categories, description, image, duration, price, optional buffer, active/inactive |
| **Staff** | Profiles, photos, colours, service assignment, weekly hours, multiple shifts, breaks, leave |
| **Availability** | Business-wide hours, per-staff custom schedules, holidays and special opening hours |
| **Booking flow** | Service → staff (or “any available”) → date → slot → details → review → confirmation with reference |
| **Safety** | Server-side re-validation, MySQL advisory lock plus post-insert race check, past/notice/advance rules |
| **Management** | Pending / confirmed / completed / cancelled / rejected / no-show with enforced transitions |
| **Admin** | Dashboard, month/week/day calendar, searchable + filterable list, bulk actions, private notes |
| **Customers** | Automatic records, history, lifetime value, PII masking, anonymisation, WP privacy tools |
| **Emails** | 7 editable templates, placeholders, live preview, test send, per-template switches |
| **Integrations** | `[qevora_booking]` shortcode, Gutenberg block, Elementor widget, REST API |
| **Settings** | Timezone, currency + formats, approval mode, booking window, buffers, cancellation rules, retention |

## Why “pay at the salon”?

Small salons lose bookings to prepayment friction and lose margin to gateway fees. Qevora Booking
takes the booking, holds the slot and sends the emails — money changes hands at the counter.
There is deliberately **no payment code path** in the plugin.

## Requirements

* WordPress 6.2 or newer (tested through 7.1)
* PHP 7.4 or newer (tested through 8.3)
* MySQL 5.7+ / MariaDB 10.3+ (SQLite works via the WordPress SQLite integration)
* No third-party services required

## Installation

```bash
# Production
wp plugin install qevora-booking.zip --activate

# Or copy the folder
cp -R qevora-booking /path/to/wp-content/plugins/
wp plugin activate qevora-booking
```

Then:

1. **Qevora Booking → Settings** — business name, timezone, currency, business hours.
2. **Services / Staff** — add what you sell and who delivers it.
3. **Availability / Holidays** — fine-tune hours and days off.
4. **Email Notifications** — review templates and send a test.
5. Publish `[qevora_booking]` on a page.

## Shortcode

```text
[qevora_booking]
[qevora_booking service="12"]
[qevora_booking staff="3" title="Book with Aisha"]
[qevora_booking category="2" show_title="0" class="my-salon"]
```

Attributes: `service`, `staff`, `category` (IDs), `title`, `show_title` (`1`/`0`), `class`.

## REST API

Namespace `qevora/v1`. Public reads are open; writes require the `X-WP-Nonce` header
(`wp_rest` nonce); admin routes require `qvb_manage_bookings`.

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/services` | Active services, optional `category_id` |
| GET | `/staff` | Active staff, optional `service_id` |
| GET | `/availability` | Slots for `service_id` + `date` (+ `staff_id`, `exclude`) |
| GET | `/availability/days` | Per-day availability for up to 31 days |
| GET | `/booking` | Booking by `reference` + `token` |
| POST | `/bookings` | Create a booking |
| POST | `/booking/cancel` | Cancel with `reference` + `token` |
| POST | `/booking/reschedule` | Reschedule with `reference` + `token` |
| GET | `/admin/bookings` | Booking feed for a date range |
| POST | `/admin/bookings/{id}/status` | Change status |
| POST | `/admin/bookings/{id}/reschedule` | Move a booking |

## Data model

Eight tables, created with the site’s real table prefix:

```text
{prefix}qvb_categories      {prefix}qvb_services     {prefix}qvb_staff
{prefix}qvb_staff_services  {prefix}qvb_availability {prefix}qvb_exceptions
{prefix}qvb_customers       {prefix}qvb_bookings
```

Bookings store **snapshots** (service name, staff name, price, currency, duration, buffer, customer
contact) so editing a service later never rewrites history. Dates are `Y-m-d`, times are `HH:MM`
wall-clock values interpreted in the business timezone; `QVB_Utils::local_datetime()` rejects
DST gaps.

## Concurrency

`QVB_Bookings::create()` wraps slot validation in a MySQL `GET_LOCK` when the driver is `mysqli`,
then re-reads overlapping rows after insert and rolls the booking back if a competitor won the race.
On drivers without advisory locks (for example SQLite) the post-insert verification alone prevents
double booking. Conflicts return HTTP 409 with a user-safe message.

## Developer notes

```text
qevora-booking.php          bootstrap, constants, requires
includes/                   domain layer (database, utils, settings, security, capabilities,
                            categories, services, staff, availability, customers, bookings,
                            notifications, cron, privacy, REST, shortcodes, activator)
admin/                      menus, router, actions, list tables, views, CSS/JS
public/                     front-end assets, templates and form helpers
integrations/gutenberg/     server-registered block
integrations/elementor/     optional widget
languages/                  qevora-booking.pot
assets/                     icon and banner artwork
bin/build.sh                produces both release ZIPs
bin/test-playground.sh      runs the smoke test inside WordPress Playground
tests/smoke.php             60-assertion automated suite
docs/TEST-CHECKLIST.md      manual QA matrix + automated suite instructions
docs/playground/            one-click Playground blueprint and demo seed
```

### Filters

| Filter | Purpose |
|--------|---------|
| `qvb_available_slots` | Adjust slots before they are returned |
| `qvb_allowed_transitions` | Change which status changes are legal |
| `qvb_default_email_templates` | Replace default email copy |
| `qvb_email_subject` / `qvb_email_body` / `qvb_email_headers` / `qvb_email_wrapper` | Modify outgoing mail |
| `qvb_capabilities` | Extend capability mapping |

### Actions

`qvb_activated`, `qvb_deactivated`, `qvb_daily_maintenance_complete`, `qvb_settings_saved`,
`qvb_schedule_saved`, `qvb_email_sent`, `qvb_email_failed`.

## Testing

Two ways to test without installing anything on a server:

| Route | What it gives you |
|-------|-------------------|
| **WordPress Playground demo** | A disposable salon in the browser — 5 services, 3 staff, 4 bookings, admin auto-login. One click, no setup. |
| **Automated smoke test** | 60 assertions against a real WordPress (PHP 8.3 + SQLite) covering the schema, booking engine, all 20 admin screens, shortcode, manage page, REST API and email pipeline. |

```bash
# Browser demo (same blueprint as docs/playground/)
open "https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/qevorasoftware/qevora-booking/v1.0.0/docs/playground/blueprint.json"

# Automated suite
bash bin/test-playground.sh
```

Latest run: **60 passed, 0 failed** on WordPress 7.1.3 / PHP 8.3 / SQLite.

Earlier development suites (activation, admin renders, REST, uninstall) all passed with zero
failures and are summarised in [`docs/TEST-CHECKLIST.md`](docs/TEST-CHECKLIST.md), which also
contains the full manual QA matrix.

## Build

```bash
bash bin/build.sh
# releases/qevora-booking.zip            production (runtime files only)
# releases/qevora-booking-developer.zip  full source + docs + build tooling
```

## Privacy & uninstall

Customer data lives only in your WordPress database. Public forms use a honeypot, a time trap and
salted IP rate limits; IPs are stored as hashes. Uninstalling keeps data by default — deletion
requires an explicit opt-in under **Settings → Privacy & data**.

## License

MIT © Qevora Software. See [LICENSE](LICENSE).
