#!/usr/bin/env bash

# Vendors the WebBlocks UI icon manifest for the pinned UI version.
#
# The icon catalog is seeded from this file at install and by the catalog
# repair a System Update runs, so it has to travel with the package rather
# than be fetched by the installing server. It cannot live only in the release
# artifact either: `composer require fklavyenet/webblocks-cms` installs from
# the GitHub tag, not from that zip, so the file has to be committed.
#
# Run after bumping UI_VERSION, then commit the result:
#   composer icons:vendor

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

UI_VERSION="$("${PHP_BIN}" -r '
  $source = file_get_contents($argv[1]);
  if (! preg_match("/UI_VERSION = '\''([^'\'']+)'\''/", $source, $matches)) {
    fwrite(STDERR, "Unable to read the pinned UI version.\n");
    exit(1);
  }
  echo $matches[1];
' "${ROOT_DIR}/src/Support/WebBlocks.php")"

MANIFEST_URL="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@${UI_VERSION}/packages/webblocks/dist/webblocks-icons.json"
TARGET_DIR="${ROOT_DIR}/database/content/icons"
TARGET_PATH="${TARGET_DIR}/webblocks-ui-${UI_VERSION}.json"
TEMP_PATH="$(mktemp)"

cleanup() {
  rm -f "${TEMP_PATH}"
}

trap cleanup EXIT

printf '[webblocks-vendor-icons] Pinned UI version: %s\n' "${UI_VERSION}"

if ! curl -fsS --max-time 60 "${MANIFEST_URL}" -o "${TEMP_PATH}"; then
  printf '[webblocks-vendor-icons] Failed to download %s\n' "${MANIFEST_URL}" >&2
  exit 1
fi

COUNT="$("${PHP_BIN}" -r '
  $decoded = json_decode(file_get_contents($argv[1]), true);
  if (! is_array($decoded) || $decoded === []) {
    fwrite(STDERR, "The downloaded icon manifest is not a non-empty JSON array.\n");
    exit(1);
  }
  foreach ($decoded as $icon) {
    if (! is_array($icon) || ! isset($icon["slug"])) {
      fwrite(STDERR, "The downloaded icon manifest has an entry without a slug.\n");
      exit(1);
    }
  }
  echo count($decoded);
' "${TEMP_PATH}")"

mkdir -p "${TARGET_DIR}"

if [ -f "${TARGET_PATH}" ] && cmp -s "${TEMP_PATH}" "${TARGET_PATH}"; then
  printf '[webblocks-vendor-icons] Already current: %s (%s icons)\n' "${TARGET_PATH}" "${COUNT}"

  exit 0
fi

# Manifests for older pins are dead weight once the pin moves.
find "${TARGET_DIR}" -name 'webblocks-ui-*.json' ! -name "webblocks-ui-${UI_VERSION}.json" -delete

cp "${TEMP_PATH}" "${TARGET_PATH}"

printf '[webblocks-vendor-icons] Wrote %s (%s icons)\n' "${TARGET_PATH}" "${COUNT}"
printf '[webblocks-vendor-icons] Commit this file so package consumers install with a full icon catalog.\n'
