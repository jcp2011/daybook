#!/usr/bin/env bash
# Downloads emoji-picker-element and its emoji data from the npm registry.
# No npm required - uses curl and tar only.
#
# Usage:
#   bash tools/download-emoji-picker.sh
#
# Output:
#   assets/emoji-picker-element/   - the web component (all JS modules)
#   assets/emoji-data.json         - full Unicode emoji dataset (en, compat)
#
# Re-run at any time to update to the latest versions.

set -euo pipefail

ASSETS_DIR="$(cd "$(dirname "$0")/../assets" && pwd)"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

latest_version() {
    curl -sf "https://registry.npmjs.org/${1}/latest" \
        | grep -o '"version":"[^"]*"' \
        | head -1 \
        | cut -d'"' -f4
}

# --- emoji-picker-element ---
EPE_VER=$(latest_version emoji-picker-element)
printf 'Downloading emoji-picker-element v%s ...\n' "$EPE_VER"
mkdir "$TMP/epe"
curl -sL "https://registry.npmjs.org/emoji-picker-element/-/emoji-picker-element-${EPE_VER}.tgz" \
    | tar -xz -C "$TMP/epe"
rm -rf "${ASSETS_DIR}/emoji-picker-element"
cp -r "$TMP/epe/package" "${ASSETS_DIR}/emoji-picker-element"

# --- emoji-picker-element-data ---
DATA_VER=$(latest_version emoji-picker-element-data)
printf 'Downloading emoji-picker-element-data v%s ...\n' "$DATA_VER"
mkdir "$TMP/data"
curl -sL "https://registry.npmjs.org/emoji-picker-element-data/-/emoji-picker-element-data-${DATA_VER}.tgz" \
    | tar -xz -C "$TMP/data"
cp "$TMP/data/package/en/emojibase/data.json" "${ASSETS_DIR}/emoji-data.json"

printf '\nDone.\n'
printf '  assets/emoji-picker-element/  (emoji-picker-element v%s)\n' "$EPE_VER"
printf '  assets/emoji-data.json         (emoji-picker-element-data v%s)\n' "$DATA_VER"
