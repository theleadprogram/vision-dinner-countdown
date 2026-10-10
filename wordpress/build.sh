#!/usr/bin/env bash
# Builds the uploadable plugin zip for one dinner.
#
#   wordpress/build.sh               # uses the newest dinners/*.json
#   wordpress/build.sh 2028-03       # uses dinners/2028-03.json
#
# Copies the dinner file into the plugin as dinner.json (board and column IDs,
# dinner date), then zips the plugin to wordpress/dist/lead-vision-dinner.zip.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(dirname "$here")"

if [[ $# -ge 1 ]]; then
  dinner="$repo/dinners/$1.json"
else
  dinner="$(ls "$repo"/dinners/*.json | sort | tail -n 1)"
fi
[[ -f "$dinner" ]] || { echo "No dinner file: $dinner" >&2; exit 1; }

php -r 'json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);' "$dinner" \
  || { echo "Invalid JSON: $dinner" >&2; exit 1; }

cp "$dinner" "$here/lead-vision-dinner/dinner.json"
mkdir -p "$here/dist"
rm -f "$here/dist/lead-vision-dinner.zip"
(cd "$here" && zip -qr dist/lead-vision-dinner.zip lead-vision-dinner -x '*.DS_Store')
echo "Built wordpress/dist/lead-vision-dinner.zip with $(basename "$dinner")"
