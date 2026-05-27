#!/bin/sh
#
# Bump the BLT M365 WP SSO plugin version.
#
# Usage:
#   bin/bump-version.sh [patch|minor|major]   (default: patch)
#
# Keeps the following in sync:
#   - the "Version:" plugin header in blt-m365-wp-sso.php
#   - the BLT_M365_SSO_VERSION constant in blt-m365-wp-sso.php
#   - the "Stable tag:" field in readme.txt
#
# Prints the new version to stdout.

set -eu

LEVEL="${1:-patch}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MAIN="$ROOT/blt-m365-wp-sso.php"
README="$ROOT/readme.txt"

if [ ! -f "$MAIN" ]; then
    echo "bump-version: main plugin file not found: $MAIN" >&2
    exit 1
fi

CURRENT="$(grep -iE '^[[:space:]]*\*[[:space:]]*Version:' "$MAIN" \
    | head -1 \
    | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"

if ! printf '%s' "$CURRENT" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo "bump-version: could not read a valid X.Y.Z version from $MAIN" >&2
    exit 1
fi

MAJOR="${CURRENT%%.*}"
REST="${CURRENT#*.}"
MINOR="${REST%%.*}"
PATCH="${REST#*.}"

case "$LEVEL" in
    major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
    minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
    patch) PATCH=$((PATCH + 1)) ;;
    *)
        echo "bump-version: unknown level '$LEVEL' (use patch, minor, or major)" >&2
        exit 1
        ;;
esac

NEW="$MAJOR.$MINOR.$PATCH"

# Update the plugin header version.
sed -i -E "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1$NEW/" "$MAIN"

# Update the version constant.
sed -i -E "s/(define\([[:space:]]*'BLT_M365_SSO_VERSION'[[:space:]]*,[[:space:]]*')[^']*('[[:space:]]*\)[[:space:]]*;)/\1$NEW\2/" "$MAIN"

# Update the readme stable tag.
if [ -f "$README" ]; then
    sed -i -E "s/^([[:space:]]*Stable tag:[[:space:]]*).*/\1$NEW/" "$README"
fi

echo "$NEW"
