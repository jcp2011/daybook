#!/usr/bin/env bash
# Downloads Noto Color Emoji woff2 subsets from Google Fonts.
# No npm, no Node.js required - uses curl only.
#
# Usage (from project root):
#   bash tools/download-fonts.sh
#
# What this produces under assets/fonts/:
#   NotoColorEmoji.0.woff2 ... NotoColorEmoji.9.woff2
#
# The font is split into unicode-range subsets so the browser only downloads
# the slices it needs. Total size is ~2 MB for all 10 subsets.
#
# Re-run at any time to update to the latest version served by Google Fonts.
# Commit assets/fonts/ afterwards to keep the repo air-gap deployable.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
OUTPUT_DIR="${SCRIPT_DIR}/../assets/fonts"
GOOGLE_FONTS_CSS="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji"
USER_AGENT="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

mkdir -p "$OUTPUT_DIR"

printf 'Fetching font URLs from Google Fonts...\n'
css=$(curl -sf "$GOOGLE_FONTS_CSS" -H "User-Agent: $USER_AGENT")

# Extract all woff2 URLs in order
urls=$(printf '%s' "$css" | grep -o 'https://fonts\.gstatic\.com[^)]*\.woff2')

i=0
while IFS= read -r url; do
    out="${OUTPUT_DIR}/NotoColorEmoji.${i}.woff2"
    printf '  Downloading subset %d...\n' "$i"
    curl -sf "$url" -o "$out"
    printf '    -> %d bytes\n' "$(wc -c < "$out")"
    i=$((i + 1))
done <<< "$urls"

printf '\nDone. %d subsets saved to assets/fonts/\n' "$i"
printf 'Commit assets/fonts/ to keep the repository deployable on air-gapped machines.\n'
