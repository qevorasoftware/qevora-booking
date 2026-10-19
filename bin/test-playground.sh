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
# The test DROPS and recreates every qvb_* table in the target WordPress, so use a
# disposable copy. The script refuses to run against the live Playground demo
# (/home/user/wp/wordpress) unless ALLOW_LIVE=1 is set.
#
# Environment:
#   WP_PATH           Disposable WordPress directory (default: /home/user/wp/test-wordpress)
#   PLAYGROUND_CLI    Path to @wp-playground/cli/cli.js
#   QVB_LIVE_WP_PATH  Path that must not be touched (default: /home/user/wp/wordpress)
#   ALLOW_LIVE        Set to 1 to explicitly allow the live demo path
#
# Usage:
#   bash bin/test-playground.sh
#   WP_PATH=/srv/wordpress PLAYGROUND_CLI=/opt/tools/node_modules/@wp-playground/cli/cli.js \
#     bash bin/test-playground.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_PATH="${WP_PATH:-/home/user/wp/test-wordpress}"

if [ ! -f "$WP_PATH/wp-load.php" ]; then
  echo "WordPress not found at $WP_PATH (set WP_PATH to a disposable WordPress copy)." >&2
  exit 1
fi

LIVE_PATH="${QVB_LIVE_WP_PATH:-/home/user/wp/wordpress}"

if [ -d "$LIVE_PATH" ] && [ "${ALLOW_LIVE:-0}" != "1" ]; then
  TARGET_REAL="$(cd "$WP_PATH" && pwd -P)"
  LIVE_REAL="$(cd "$LIVE_PATH" && pwd -P)"

  if [ "$TARGET_REAL" = "$LIVE_REAL" ]; then
    echo "Refusing to run: $WP_PATH is the live Playground demo." >&2
    echo "The test drops and recreates the Qevora tables. Set ALLOW_LIVE=1 to override." >&2
    exit 1
  fi
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
