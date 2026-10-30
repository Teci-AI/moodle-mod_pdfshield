#!/usr/bin/env bash
# Builds the plugin zip: build/mod_pdfshield-<version>.zip, with the plugin in a pdfshield/ folder (what Moodle's
# plugin installer and the moodle.org plugins directory expect).
#   bin/build.sh            release from version.php
#   bin/build.sh v2.0.1     release from a tag (GitHub Actions passes the tag)
# The tag only sets $plugin->release; $plugin->version (the YYYYMMDDXX number Moodle upgrades on) is raised in
# version.php by hand before tagging, and the build refuses a -dev release name for a tag.
# Needs bash, rsync, zip and php.
set -euo pipefail
cd "$(dirname "$0")/.."

NAME=pdfshield
COMPONENT=mod_pdfshield
RELEASE="${1:-$(sed -n "s/^[$]plugin->release *= *'\([^']*\)';.*/\1/p" version.php)}"
RELEASE="${RELEASE#v}"
if ! [[ "$RELEASE" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]]; then
  echo "Not a version: $RELEASE (expected 1.2.3 or 1.2.3-beta.1)" >&2
  exit 1
fi

OUT=build
STAGE="$OUT/$NAME"
rm -rf "$OUT"
mkdir -p "$STAGE"
rsync -a --exclude-from=.distignore ./ "$STAGE/"
# amd/build is made by Moodle's grunt from amd/src (see README, Development).
[ -s amd/build/viewer.min.js ] || { echo "amd/build/viewer.min.js is missing: build it with grunt amd" >&2; exit 1; }

sed -i -E "s/^([$]plugin->release *= *)'[^']*';/\1'$RELEASE';/" "$STAGE/version.php"
grep -q "^[$]plugin->release *= *'$RELEASE';" "$STAGE/version.php" || { echo "Release stamp failed" >&2; exit 1; }
# A release that is not a pre-release is stable.
if [[ "$RELEASE" != *-* ]]; then
  sed -i -E 's/^([$]plugin->maturity *= *)MATURITY_[A-Z]+;/\1MATURITY_STABLE;/' "$STAGE/version.php"
fi

find "$STAGE" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

(cd "$OUT" && zip -qr "$COMPONENT-$RELEASE.zip" "$NAME")
echo "$OUT/$COMPONENT-$RELEASE.zip"
