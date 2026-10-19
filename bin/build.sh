#!/usr/bin/env bash
#
# Builds the distributable packages:
#   releases/qevora-booking.zip            production package (WordPress.org layout)
#   releases/qevora-booking-developer.zip  full source, docs and build tooling
#
# Usage: bash bin/build.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="qevora-booking"
STAGE="$ROOT/.build-stage"
RELEASES="$ROOT/releases"
VERSION="$(grep -m1 "Version:" "$ROOT/$SLUG.php" | awk '{print $3}')"

echo "Building $SLUG $VERSION"

rm -rf "$STAGE"
mkdir -p "$STAGE/$SLUG" "$RELEASES"

# Runtime files only. Anything not listed here never reaches production.
RUNTIME=(
  "$SLUG.php"
  "uninstall.php"
  "readme.txt"
  "LICENSE"
  "includes"
  "admin"
  "public"
  "integrations"
  "languages"
  "assets"
)

for item in "${RUNTIME[@]}"; do
  if [ ! -e "$ROOT/$item" ]; then
    echo "Missing runtime file: $item" >&2
    exit 1
  fi
  cp -R "$ROOT/$item" "$STAGE/$SLUG/"
done

# Housekeeping inside the package.
find "$STAGE/$SLUG" -name ".DS_Store" -delete
find "$STAGE/$SLUG" -name "*.map" -delete
find "$STAGE/$SLUG" -name "Thumbs.db" -delete

# Fail loudly if dev-only paths slipped in.
for forbidden in ".git" "node_modules" "bin" "docs" "tests" "releases" ".build-stage"; do
  if [ -e "$STAGE/$SLUG/$forbidden" ]; then
    echo "Refusing to package dev path: $forbidden" >&2
    exit 1
  fi
done

rm -f "$RELEASES/$SLUG.zip" "$RELEASES/$SLUG-developer.zip"

( cd "$STAGE" && zip -qr -9 "$RELEASES/$SLUG.zip" "$SLUG" -x "*.DS_Store" )

# Developer package: the full source tree (docs, tests and tooling included),
# still wrapped in a single top-level folder for a predictable unzip.
DEV_STAGE="$ROOT/.build-stage-dev/$SLUG"
mkdir -p "$DEV_STAGE"
( cd "$ROOT" && tar -cf - \
    --exclude=".git" \
    --exclude="node_modules" \
    --exclude="releases" \
    --exclude=".build-stage" \
    --exclude=".build-stage-dev" \
    --exclude="*.DS_Store" \
    --exclude="*.zip" . ) | ( cd "$DEV_STAGE" && tar -xf - )
( cd "$ROOT/.build-stage-dev" && zip -qr -9 "$RELEASES/$SLUG-developer.zip" "$SLUG" )
rm -rf "$ROOT/.build-stage-dev"
rm -rf "$STAGE"

ls -lh "$RELEASES"

echo
echo "Production contents:"
unzip -l "$RELEASES/$SLUG.zip" | tail -3
echo "Developer contents:"
unzip -l "$RELEASES/$SLUG-developer.zip" | tail -3
