#!/usr/bin/env bash
#
# Runs tests/smoke.php against a real WordPress inside WordPress Playground
# (PHP 8.x + SQLite, no MySQL or PHP installation required on the host).
#
# Requirements:
#   * Node.js 20+
#   * @wp-playground/cli installed somewhere (npx or a local node_modules)
#   * A WordPress directory that Playground can mount
#
# Environment:
#   WP_PATH           WordPress directory to mount (default: /home/user/wp/wordpress)
#   PLAYGROUND_CLI    Path to @wp-playground/cli/cli.js
#   PLAYGROUND_PORT   Unused, kept for symmetry with the server helper
#
# Usage:
#   bash bin/test-playground.sh
#   WP_PATH=/srv/wordpress PLAYGROUND_CLI=/opt/tools/node_modules/@wp-playground/cli/cli.js \
#     bash bin/test-playground.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_PATH="${WP_PATH:-/home/user/wp/wordpress}"

if [ ! -f "$WP_PATH/wp-load.php" ]; then
  echo "WordPress not found at $WP_PATH (set WP_PATH)." >&2
  exit 1
fi

CLI_JS="${PLAYGROUND_CLI:-}"

if [ -z "$CLI_JS" ]; then
  for candidate in \
    "$ROOT/../tools/node_modules/@wp-playground/cli/cli.js" \
    "$ROOT/node_modules/@wp-playground/cli/cli.js" \
    "$PWD/node_modules/@wp-playground/cli/cli.js"; do
    if [ -f "$candidate" ]; then
      CLI_JS="$candidate"
      break
    fi
  done
fi

if [ -z "$CLI_JS" ]; then
  echo "Set PLAYGROUND_CLI to the @wp-playground/cli/cli.js path." >&2
  echo "Install it with: npm install @wp-playground/cli@3.1.59" >&2
  exit 1
fi

echo "WordPress:    $WP_PATH"
echo "Playground:   $CLI_JS"
echo "Smoke test:   $ROOT/tests/smoke.php"
echo

node "$CLI_JS" php \
  --auto-mount="$WP_PATH" \
  --mount="$ROOT:/wordpress/wp-content/plugins/qevora-booking" \
  --wordpress-install-mode=install-from-existing-files \
  -- /wordpress/wp-content/plugins/qevora-booking/tests/smoke.php
