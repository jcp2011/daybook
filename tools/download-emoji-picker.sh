#!/usr/bin/env bash
# Downloads emoji-picker-element and emoji data from the npm registry.
# No npm, no Node.js required - uses curl and tar only.
#
# Usage (from project root):
#   bash tools/download-emoji-picker.sh
#
# What this produces under assets/emoji-picker/:
#   picker.js                       - emoji picker web component
#   database.js                     - IndexedDB cache layer (patched - see below)
#   emoji-picker-element.js         - entry-point re-export
#   <lang>/emojibase/data.json      - emoji dataset per language
#   i18n/<lang>.js                  - UI translations (non-English only)
#
# Patch applied automatically to database.js:
#   The jsonChecksum() function falls back to a djb2 hash when crypto.subtle is
#   unavailable. crypto.subtle is only available in secure contexts (HTTPS or
#   localhost); without this patch the picker fails on plain HTTP served from a
#   non-localhost IP with "Could not load emoji."
#
# Re-run at any time to update to the latest versions.
# Commit assets/emoji-picker/ afterwards to keep the repo air-gap deployable.

set -euo pipefail

# --- Configuration ---
LANGUAGES="en fr"   # space-separated list; add/remove language codes here

# --- Internal ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
OUTPUT_DIR="${SCRIPT_DIR}/../assets/emoji-picker"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

latest_version() {
    curl -sf "https://registry.npmjs.org/${1}/latest" \
        | grep -o '"version":"[^"]*"' \
        | head -1 \
        | cut -d'"' -f4
}

# Patches jsonChecksum() in database.js so the picker works on plain HTTP.
patch_database() {
    local file="$1"
    python3 - "$file" << 'PYEOF'
import sys

path = sys.argv[1]
with open(path) as f:
    src = f.read()

OLD = (
    "  // this does not need to be cryptographically secure, SHA-1 is fine\n"
    "  const outBuffer = await crypto.subtle.digest('SHA-1', inBuffer);\n"
    "  const outBinString = arrayBufferToBinaryString(outBuffer);\n"
    "  const res = btoa(outBinString);\n"
    "  return res\n"
)

NEW = (
    "  // crypto.subtle requires a secure context (HTTPS or localhost); fall back to a\n"
    "  // simple djb2 integer hash when it is unavailable (e.g. plain HTTP on a LAN IP).\n"
    "  if (typeof crypto !== 'undefined' && crypto.subtle) {\n"
    "    const outBuffer = await crypto.subtle.digest('SHA-1', inBuffer);\n"
    "    const outBinString = arrayBufferToBinaryString(outBuffer);\n"
    "    return btoa(outBinString)\n"
    "  }\n"
    "  let hash = 5381;\n"
    "  for (let i = 0; i < inString.length; i++) {\n"
    "    hash = Math.imul(hash, 33) ^ inString.charCodeAt(i);\n"
    "  }\n"
    "  return (hash >>> 0).toString(36)\n"
)

if OLD in src:
    with open(path, 'w') as f:
        f.write(src.replace(OLD, NEW, 1))
    print("  crypto.subtle fallback patch applied.")
else:
    print("  WARNING: patch could not be applied - jsonChecksum() changed upstream.")
    print("  Manually add a djb2 fallback for crypto.subtle in database.js.")
    print("  See the existing database.js in the repo for the expected patch shape.")
PYEOF
}

# --- emoji-picker-element ---
EPE_VER=$(latest_version emoji-picker-element)
printf 'Downloading emoji-picker-element v%s ...\n' "$EPE_VER"
mkdir "$TMP/epe"
curl -sL "https://registry.npmjs.org/emoji-picker-element/-/emoji-picker-element-${EPE_VER}.tgz" \
    | tar -xz -C "$TMP/epe"

rm -rf "${OUTPUT_DIR}"
mkdir -p "${OUTPUT_DIR}"
cp "$TMP/epe/package/picker.js"   "${OUTPUT_DIR}/picker.js"
cp "$TMP/epe/package/database.js" "${OUTPUT_DIR}/database.js"
cp "$TMP/epe/package/index.js"    "${OUTPUT_DIR}/emoji-picker-element.js"

patch_database "${OUTPUT_DIR}/database.js"

# --- emoji-picker-element-data ---
DATA_VER=$(latest_version emoji-picker-element-data)
printf 'Downloading emoji-picker-element-data v%s ...\n' "$DATA_VER"
mkdir "$TMP/data"
curl -sL "https://registry.npmjs.org/emoji-picker-element-data/-/emoji-picker-element-data-${DATA_VER}.tgz" \
    | tar -xz -C "$TMP/data"

for lang in $LANGUAGES; do
    printf '  - language: %s\n' "$lang"
    mkdir -p "${OUTPUT_DIR}/${lang}/emojibase"
    cp "$TMP/data/package/${lang}/emojibase/data.json" \
       "${OUTPUT_DIR}/${lang}/emojibase/data.json"
    if [ "$lang" != "en" ]; then
        mkdir -p "${OUTPUT_DIR}/i18n"
        src_i18n="$TMP/epe/package/i18n/${lang}.js"
        if [ -f "$src_i18n" ]; then
            cp "$src_i18n" "${OUTPUT_DIR}/i18n/${lang}.js"
        else
            printf '    Warning: no i18n file for "%s" in this package version.\n' "$lang"
        fi
    fi
done

printf '\nDone.\n'
printf '  emoji-picker-element       v%s\n' "$EPE_VER"
printf '  emoji-picker-element-data  v%s\n' "$DATA_VER"
printf '  Languages: %s\n' "$LANGUAGES"
printf '\nCommit assets/emoji-picker/ to keep the repository deployable on air-gapped machines.\n'
