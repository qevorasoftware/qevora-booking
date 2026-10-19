# Qevora Booking — Release Test Checklist

Version: **1.0.3**
Target: WordPress **6.2 – 7.1**, PHP **7.4 – 8.3**, MySQL 5.7+/MariaDB 10.3+, SQLite via the WordPress SQLite integration.

Legend: `[ ]` not run · `[x]` passed · `[!]` failed (open an issue with the reference number).

---

## 1. Install, activate, upgrade, uninstall

| # | Test | Expected result |
|---|------|-----------------|
| 1.1 | Upload `qevora-booking.zip` via **Plugins → Add New → Upload Plugin** | Plugin installs with no PHP notices |
| 1.2 | Activate | 8 tables created (`…_qvb_categories`, `_services`, `_staff`, `_staff_services`, `_availability`, `_exceptions`, `_customers`, `_bookings`), admin redirected to the dashboard, `qvb_db_version` stored |
| 1.3 | Deactivate | Scheduled cron removed, no tables or options deleted, no fatal errors |
| 1.4 | Reactivate | No duplicate tables, settings preserved, cron re-scheduled |
| 1.5 | Upgrade from a modified `qvb_db_version` | `QVB_Database::maybe_upgrade()` re-runs `dbDelta` without data loss |
| 1.6 | Delete plugin with **Delete all plugin data** OFF | Tables and settings remain |
| 1.7 | Delete plugin with the option ON | Tables dropped, options removed, capabilities removed, cron cleared |
| 1.8 | Multisite: network activate, then network uninstall | Per-site tables created; uninstall respects each site’s own setting |
| 1.9 | Table prefix other than `wp_` (e.g. `salon_`) | All tables use the real prefix |
| 1.10 | **Plugins → Qevora Booking → Help / System Status** | All health checks OK, row counts accurate |

---

## 2. Services and categories

| # | Test | Expected result |
|---|------|-----------------|
| 2.1 | Create a service with name, category, description, image, duration, price, buffer | Saved, appears in list with thumbnail |
| 2.2 | Leave the name empty / enter duration 0 | Validation error, nothing saved |
| 2.3 | Set a service inactive | Hidden from the public form, still visible to staff |
| 2.4 | Edit a service used by an existing booking | Existing booking keeps the snapshotted name, price, duration |
| 2.5 | Delete a service with bookings | Bookings remain; staff-service links removed |
| 2.6 | Create/edit/delete a category | Service counts update; deleting a category detaches services without deleting them |
| 2.7 | Search and filter services | Results reflect the filter; pagination works |
| 2.8 | Category image and description | Rendered in the booking form group heading |

---

## 3. Staff

| # | Test | Expected result |
|---|------|-----------------|
| 3.1 | Create staff with photo, email, phone, bio, colour | Listed as a card with initials/photo |
| 3.2 | Assign services | Only assigned services can be booked for that person |
| 3.3 | Unassign a service with future bookings | Existing bookings remain valid |
| 3.4 | Deactivate staff | Removed from the public form; own bookings untouched |
| 3.5 | Delete staff | Availability and exceptions deleted; bookings keep the staff name snapshot |
| 3.6 | Two staff with the same name | Allowed, both bookable |

---

## 4. Availability, breaks, holidays

| # | Test | Expected result |
|---|------|-----------------|
| 4.1 | Staff on “business hours” | Slots follow **Settings → Business hours** |
| 4.2 | Custom weekly schedule with a break | Break window excluded from slots |
| 4.3 | Two non-overlapping shifts in one day | Slots generated for both shifts |
| 4.4 | Overlapping shifts or break outside the shift | Save is rejected with a message |
| 4.5 | Close a weekday | No slots that day, “Closed” in the quick-date strip |
| 4.6 | Business-wide holiday | No slots for every staff member |
| 4.7 | Staff-specific holiday | Only that person’s slots disappear |
| 4.8 | Special opening hours on a holiday | Slots appear only within the custom window |
| 4.9 | Switch a staff member back to business hours | Custom rows are ignored by the engine |
| 4.10 | Multiple shifts ending exactly at closing | Last slot still fits duration + buffer |

---

## 5. Public booking form

| # | Test | Expected result |
|---|------|-----------------|
| 5.1 | `[qevora_booking]` on a page | Multi-step form renders without Elementor |
| 5.2 | `[qevora_booking service="12"]` | Service preselected, other services hidden |
| 5.3 | `[qevora_booking staff="3"]` | Only that person’s services/staff shown |
| 5.4 | `[qevora_booking category="2"]` | Only that category’s services |
| 5.5 | Gutenberg block “Qevora Booking” | Same form, block preview works |
| 5.6 | Elementor widget | Appears under “Qevora”, renders the form |
| 5.7 | Step 1 → 2 → 3 → 4 → 5 | Progress indicator updates, back navigation preserves choices |
| 5.8 | “Any available staff” | Slot deduplication, assignment to the first free staff member |
| 5.9 | Date picker min/max | Bounded by minimum notice and maximum advance |
| 5.10 | Slot click | Time inserted in the review step, duration and price shown |
| 5.11 | Submit with approval mode auto | Review screen → success screen with reference number |
| 5.12 | Submit with approval mode manual | Success screen says pending confirmation |
| 5.13 | Browser refresh on success | No duplicate booking created |
| 5.14 | JS disabled | Clear notice with a contact fallback |
| 5.15 | Resize to 320 px, 768 px, 1440 px | Single column, tappable targets, no horizontal scroll |
| 5.16 | Keyboard only | All steps reachable, focus visible, slots are buttons |
| 5.17 | Screen reader (NVDA/VoiceOver) | Step headings, labels, live regions announced |

---

## 6. Validation and conflict handling

| # | Test | Expected result |
|---|------|-----------------|
| 6.1 | Submit a past date/time | Rejected server-side (`qvb_past_booking`) |
| 6.2 | Submit outside business hours | Rejected |
| 6.3 | Submit during a break or holiday | Rejected |
| 6.4 | Submit before minimum notice | Rejected |
| 6.5 | Submit beyond maximum advance | Rejected |
| 6.6 | Submit a slot taken while the form was open | Rejected with “no longer available” (409) |
| 6.7 | Two tabs submit the same slot simultaneously | Exactly one booking, the other receives a conflict error |
| 6.8 | Invalid email/phone | Validation error, no booking |
| 6.9 | Missing required phone when enabled | Validation error |
| 6.10 | Consent unchecked when required | Validation error |
| 6.11 | Honeypot filled | Silently rejected |
| 6.12 | Submit within `min_form_seconds` | Rejected with “please wait a moment” |
| 6.13 | More than the hourly booking limit from one IP | 429 rate-limit response |
| 6.14 | Duplicate booking for the same customer and overlapping time | Rejected |

---

## 7. Admin booking management

| # | Test | Expected result |
|---|------|-----------------|
| 7.1 | Dashboard | Today’s appointments, pending requests, revenue stats |
| 7.2 | Bookings list: search by reference/name/email | Correct results |
| 7.3 | Filters: status, staff, service, date range | Combined filters work, counts match |
| 7.4 | Bulk actions confirm/cancel/complete | Statuses updated, emails sent as configured |
| 7.5 | Open a booking | Detail, customer, notes, status, reschedule panels |
| 7.6 | Pending → confirmed | Slot remains blocked, customer email sent |
| 7.7 | Confirmed → completed | Allowed |
| 7.8 | Completed → confirmed | Rejected (invalid transition) |
| 7.9 | Cancel a booking | Reason stored, slot released, customer + admin emails |
| 7.10 | Cancel an already-cancelled booking | Rejected |
| 7.11 | Reschedule to a free slot | Old slot released, new slot blocked, `reschedule_count` incremented |
| 7.12 | Reschedule onto a taken slot | Rejected, original booking unchanged |
| 7.13 | Reschedule to a past time | Rejected |
| 7.14 | Save private notes | Visible only in wp-admin, never emailed to the customer |
| 7.15 | Resend status email | Email sent, success/error notice shown |
| 7.16 | Copy customer manage link | Clipboard contains `?qvb_booking=<ref>&token=…` |
| 7.17 | Create a booking from wp-admin | Availability picker lists real slots, conflict rules enforced |
| 7.18 | Calendar month/week/day | Bookings appear on the right day, staff colours applied, “Today” works |
| 7.19 | Calendar with a status filter | Only matching statuses displayed |
| 7.20 | Staff without PII capability opens bookings | Email/phone masked |

---

## 8. Customers

| # | Test | Expected result |
|---|------|-----------------|
| 8.1 | First booking creates a customer record | Name, email, phone stored, stats 1 booking |
| 8.2 | Second booking with the same email | Same customer, stats updated, no duplicate |
| 8.3 | Customer list search and sorting | Works for name, email, bookings, value, last visit |
| 8.4 | Customer profile | Booking history, totals, notes |
| 8.5 | Edit customer contact details | Saved, bookings unchanged |
| 8.6 | Anonymise customer | Name/email/phone replaced, history retained, cannot be undone without a backup |
| 8.7 | User without `qvb_view_customer_data` | Sees masked data and no edit controls |
| 8.8 | Duplicate email at booking time with different name | Existing record reused, snapshot updated |

---

## 9. Emails

| # | Test | Expected result |
|---|------|-----------------|
| 9.1 | **Email Notifications** list | 7 templates with enable switches |
| 9.2 | Edit subject/body, save, reload | Changes persisted |
| 9.3 | Reset to default | Original copy restored |
| 9.4 | Preview with sample data | Placeholders replaced, layout intact |
| 9.5 | Send test email | Arrives when site mail works; clear error when it does not |
| 9.6 | Disable a template | That email is not sent; other emails still send |
| 9.7 | Global email toggle OFF | No emails sent |
| 9.8 | Pending booking | Customer pending email + staff new-booking email |
| 9.9 | Confirmation | Customer confirmation email |
| 9.10 | Cancellation | Customer cancellation + staff cancellation emails |
| 9.11 | Reschedule | Customer reschedule email with previous date/time |
| 9.12 | Placeholder coverage | All documented placeholders render (no raw `{tag}` left) |
| 9.13 | Customer email missing | Staff emails still sent, no PHP notice |
| 9.14 | From name/address | Uses the configured values, valid `Reply-To` |

---

## 10. Settings

| # | Test | Expected result |
|---|------|-----------------|
| 10.1 | Business identity | Saved and reused in emails and the form |
| 10.2 | Timezone change | Displayed times and slot windows follow the new timezone |
| 10.3 | Date/time format change | Reflected in admin, emails and the front end |
| 10.4 | Currency + symbol position + decimals | Prices formatted accordingly |
| 10.5 | Slot interval and default buffer | Availability responds immediately |
| 10.6 | Minimum notice / maximum advance | Availability window respects both |
| 10.7 | Approval mode | New bookings pending or confirmed accordingly |
| 10.8 | Cancellation/reschedule toggles and cut-offs | Manage-booking page honours the rules |
| 10.9 | Max reschedules | Additional reschedules blocked |
| 10.10 | Require phone / require consent | Form validation updates |
| 10.11 | Retention days | Daily maintenance removes only old completed/cancelled bookings |
| 10.12 | Save with no changes | No data loss, no PHP notices |
| 10.13 | Settings form on a narrow screen | Fields stack, sticky save bar reachable |

---

## 11. Manage-booking page

| # | Test | Expected result |
|---|------|-----------------|
| 11.1 | Valid link from email | Booking details displayed |
| 11.2 | Tampered token | “Invalid link” screen, no data leaked |
| 11.3 | Unknown reference | Invalid-link screen |
| 11.4 | Cancellation inside the allowed window | Cancelled, confirmation shown, slot released |
| 11.5 | Cancellation inside the cut-off | Blocked with a clear message |
| 11.6 | Reschedule inside the allowed window | Slot list loads, new time saved |
| 11.7 | Reschedule when disabled | Action hidden |
| 11.8 | Lookup rate limit exceeded | Friendly 429 message |

---

## 12. Security

| # | Test | Expected result |
|---|------|-----------------|
| 12.1 | Admin actions without a nonce | Rejected (`check_admin_referer`) |
| 12.2 | Admin actions as a subscriber | 403 |
| 12.3 | Settings/email screens without `qvb_manage_settings` | 403 |
| 12.4 | REST write endpoints without `X-WP-Nonce` | 403 |
| 12.5 | REST admin routes without the capability | 401/403 |
| 12.6 | SQL injection attempts in search/filter params | Prepared statements hold, no errors |
| 12.7 | XSS payloads in service/staff/customer fields | Escaped on output in admin and front end |
| 12.8 | CSRF on public booking submit | Rejected without a valid form token |
| 12.9 | Direct access to plugin PHP files | No output, no fatal |
| 12.10 | Customer PII in page source for unprivileged users | Masked |
| 12.11 | IP addresses | Stored only as a salted hash |
| 12.12 | WordPress privacy exporter/eraser | Both complete without errors |
| 12.13 | File upload restrictions | Only images can be attached through the media picker |

---

## 13. Timezone, DST and concurrency

| # | Test | Expected result |
|---|------|-----------------|
| 13.1 | Site timezone Asia/Kolkata, staff hours 09:00–19:00 | Slots exactly 09:00–18:30 for a 30-min service |
| 13.2 | Site timezone America/New_York across the DST spring-forward gap | 02:00–03:00 times rejected for that date, no invalid DateTime |
| 13.3 | DST fall-back overlap | No duplicate or ambiguous slots |
| 13.4 | Booking created just before midnight | Stored under the business-local date |
| 13.5 | Two concurrent requests for the last slot | One wins, one receives 409 |
| 13.6 | MySQL advisory lock unavailable (non-mysqli driver, e.g. SQLite) | Post-insert verification still prevents double booking |
| 13.7 | Server clock vs site timezone mismatch | Past bookings still rejected |

---

## 14. Compatibility and code quality

| # | Test | Expected result |
|---|------|-----------------|
| 14.1 | PHP syntax check on all files | 0 failures (`php -l`) |
| 14.2 | WordPress 6.2, 6.6, 7.1 | No deprecations or fatals |
| 14.3 | PHP 7.4, 8.0, 8.2, 8.3 | No warnings |
| 14.4 | `WP_DEBUG` + `WP_DEBUG_DISPLAY` on | No notices on any screen |
| 14.5 | Twenty Twenty-Five and a classic theme | Form inherits theme, no layout breakage |
| 14.6 | Elementor inactive | Plugin loads, widget silently absent |
| 14.7 | Another plugin using `.qvb-` classes | No conflicts (all classes prefixed) |
| 14.8 | Translation template | `languages/qevora-booking.pot` loads, `loco`/Poedit can translate |
| 14.9 | `readme.txt` validator | No errors |

---

## 14b. Glass interface

| # | Test | Expected result |
|---|------|-----------------|
| 14b.1 | Every admin screen (Dashboard → Help) | Panes are translucent over the aurora canvas; no card touches or crosses the right edge |
| 14b.2 | Other plugins' admin screens | Unchanged — the skin is scoped to `body.qvb-screen` |
| 14b.3 | Booking form, steps 1 → 5 | Glass cards, step pills, day chips, slots, summary and buttons all styled; no whole-panel focus ring after Continue |
| 14b.4 | Booking form at 375 px / 480 px / 782 px | One card per row, slot grid stays even, nothing scrolls sideways |
| 14b.5 | Safari / Firefox / Chrome | Blur, saturation and specular edges render; hover lift is smooth |
| 14b.6 | Browser without `backdrop-filter` | Surfaces fall back to opaque white, all text remains readable |
| 14b.7 | OS “Reduce motion” | Aurora stops, no spring transitions, no layout shift |
| 14b.8 | OS “Increase contrast” | Opaque surfaces, stronger hairlines, aurora hidden |
| 14b.9 | Keyboard only | Visible focus ring on every control; tab order unchanged |
| 14b.10 | Settings screen scrolling | Section pill highlights the section in view |

---

## 15. Release packaging

| # | Test | Expected result |
|---|------|-----------------|
| 15.1 | Build script | Produces `qevora-booking.zip` and `qevora-booking-developer.zip` |
| 15.2 | Production ZIP contents | No `.git`, no `node_modules`, no tests, no build scripts, no dev docs |
| 15.3 | Developer ZIP contents | Includes `bin/`, `docs/`, test checklist and source files |
| 15.4 | Install the production ZIP into a clean WordPress | Activates, all screens render, one booking completes |
| 15.5 | ZIP size | Under 5 MB |
| 15.6 | Version headers | `1.0.3` in the plugin header, `QVB_VERSION` and `readme.txt` |

---

## Automated smoke test

`tests/smoke.php` runs against a real WordPress (PHP 8.x + SQLite) inside WordPress Playground.
One command, 64 assertions, exit code 0 only when everything passes:

```bash
bash bin/test-playground.sh
```

The script mounts the repository as `qevora-booking` and needs a **disposable** WordPress directory
to boot (`WP_PATH`, default `/home/user/wp/test-wordpress`) plus `@wp-playground/cli`
(`PLAYGROUND_CLI`, or a local `node_modules` copy).

> The suite drops and recreates every `qvb_*` table in the target WordPress. It refuses to run
> against the live Playground demo (`/home/user/wp/wordpress`) unless `ALLOW_LIVE=1` is set.

Coverage:

| Group | Assertions | What is checked |
|-------|-----------|-----------------|
| Bootstrap | 3 | classes load, admin capability exists, all 8 tables created |
| Services / staff / availability | 6 | CRUD, service assignment, 30-minute grid, break exclusion, duration + buffer at the end of day |
| Booking engine | 18 | create, auto-approval, customer + admin emails, manage URL, slot consumed, any-available routing, same-time and overlapping conflicts rejected, past dates rejected, cancel releases the slot, rebook after cancel, reschedule, reschedule counter, confirmed → completed, illegal transition rejected |
| Admin screens | 21 | dashboard, 3 calendar views, bookings list/new/edit, services list/edit, categories, staff list/edit, availability, exceptions, customers list/detail, emails list/edit, settings, help |
| Public output | 3 | shortcode wizard for guests, manage page with a valid token, invalid token reveals nothing |
| REST API | 7 | block registered, GET services, GET availability, POST bookings without a nonce → 403, POST with a nonce → 201, GET booking with the signed token, admin routes require a capability |
| Emails | 3 | 7 templates, placeholder preview, `wp_mail()` dispatch |

Latest run: **2026-10-19 — 61 passed, 0 failed** (WordPress 7.1.3, PHP 8.3, SQLite).

## Manual browser test on WordPress Playground

`docs/playground/blueprint.json` builds a disposable demo salon in the browser. Open the one-click
link in [`docs/playground/README.md`](playground/README.md), then walk the checklist sections above
in a real browser — no hosting required. Playground cannot send email and loses all data when the
tab closes, so treat it as a functional/UI pass and use staging for SMTP and MySQL checks.
