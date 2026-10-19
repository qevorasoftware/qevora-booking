# WordPress Playground demo

[`blueprint.json`](blueprint.json) spins up a disposable WordPress in the browser with Qevora
Booking installed, activated and filled with a realistic demo salon:

* 5 services across Massage / Hair / Skin categories
* 3 staff members (one on a custom afternoon schedule)
* Business hours 10:00–20:00 with a 14:00–15:00 break, Sundays closed
* Two upcoming holidays
* Three confirmed bookings plus one pending request
* A published **Book an appointment** page with `[qevora_booking]`
* Admin auto-login, landing on the booking form

## Open it

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/qevorasoftware/qevora-booking/v1.0.3/docs/playground/blueprint.json
```

The blueprint pulls the production ZIP from the `v1.0.3` tag, so the demo always matches a
released build. Everything runs in the browser — the WordPress database is temporary and vanishes
when the tab is closed.

## Run it locally (same engine, no browser upload)

Playground also ships a Node CLI. With a local WordPress checkout and the plugin mounted as a
normal plugin directory:

```bash
npm install @wp-playground/cli@3.1.59

node node_modules/@wp-playground/cli/cli.js run-blueprint \
  --blueprint=docs/playground/blueprint.json \
  --auto-mount=/path/to/wordpress --verbosity=normal

node node_modules/@wp-playground/cli/cli.js server \
  --port=8899 --login \
  --site-url=https://<port>-<sandbox>.e2b.app \
  --mount-before-install=/path/to/wordpress:/wordpress \
  --wordpress-install-mode=install-from-existing-files
```

The first command installs the demo content (it is idempotent); the second serves the same site
over HTTP with admin auto-login. This is how the automated smoke test in
[`tests/smoke.php`](../../tests/smoke.php) is run — see `bin/test-playground.sh`.

## What to try

1. Book an appointment on the landing page (no payment step).
2. Open **wp-admin → Qevora Booking → Bookings** to see the new request.
3. Confirm / reschedule / cancel it and watch the status transitions.
4. Visit **Calendar** for the month, week and day views.
5. Open **Settings** and flip *Approval mode* to “Hold as pending”.
6. Edit an email template and preview it with sample data.

> Emails cannot leave Playground, so `wp_mail()` calls are accepted and discarded. Use a real
> staging site for SMTP testing.

## Regenerate

The demo content lives in `runPHP` steps inside `blueprint.json`. To change it, edit the PHP block,
run the JSON through a Blueprint validator (`validateBlueprint` from `@wp-playground/blueprints`)
and bump the tag referenced in the plugin URL.
