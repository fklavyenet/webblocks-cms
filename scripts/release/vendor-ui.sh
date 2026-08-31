#!/usr/bin/env bash

# Vendors the complete, pinned WebBlocks UI browser runtime into the CMS
# package. Installing and updating a CMS must never need a CDN response to
# render the admin, guest, error, or public layouts.

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
UI_VERSION="$("${PHP_BIN}" -r '
  $source = file_get_contents($argv[1]);
  if (! preg_match("/UI_VERSION = '\''([^'\'']+)'\''/", $source, $matches)) {
    fwrite(STDERR, "Unable to read the pinned WebBlocks UI version.\n");
    exit(1);
  }
  echo $matches[1];
' "${ROOT_DIR}/src/Support/WebBlocks.php")"
SOURCE_BASE="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@${UI_VERSION}"
DIST_BASE="${SOURCE_BASE}/packages/webblocks/dist"
TARGET_ROOT="${ROOT_DIR}/public/cms/webblocks-ui"
TARGET_DIR="${TARGET_ROOT}/${UI_VERSION}"
ICON_TARGET_DIR="${ROOT_DIR}/database/content/icons"
ICON_TARGET_PATH="${ICON_TARGET_DIR}/webblocks-ui-${UI_VERSION}.json"
STAGING_DIR="$(mktemp -d)"
FILES=(webblocks-ui.css webblocks-icons.css webblocks-ui.js webblocks-icons.json)

cleanup() {
  rm -rf "${STAGING_DIR}"
}

trap cleanup EXIT

printf '[webblocks-vendor-ui] Pinned UI version: %s\n' "${UI_VERSION}"

for file in "${FILES[@]}"; do
  curl -fsS --max-time 60 "${DIST_BASE}/${file}" -o "${STAGING_DIR}/${file}"
done

curl -fsS --max-time 60 "${SOURCE_BASE}/LICENSE" -o "${STAGING_DIR}/LICENSE.txt"

"${PHP_BIN}" -r '
$directory = $argv[1];
$version = $argv[2];
$files = ["webblocks-ui.css", "webblocks-icons.css", "webblocks-ui.js", "webblocks-icons.json", "LICENSE.txt"];
$artifacts = [];

foreach ($files as $file) {
  $path = $directory."/".$file;

  if (! is_file($path) || filesize($path) < 1) {
    fwrite(STDERR, "Missing or empty WebBlocks UI artifact: ".$file."\n");
    exit(1);
  }

  $artifacts[$file] = [
    "sha256" => hash_file("sha256", $path),
    "bytes" => filesize($path),
  ];
}

$icons = json_decode((string) file_get_contents($directory."/webblocks-icons.json"), true);

if (! is_array($icons) || $icons === []) {
  fwrite(STDERR, "The WebBlocks UI icon manifest must be a non-empty JSON array.\n");
  exit(1);
}

foreach (["webblocks-ui.css", "webblocks-icons.css"] as $stylesheet) {
  $css = (string) file_get_contents($directory."/".$stylesheet);
  preg_match_all("/url\\(\\s*([\"'\'']?)(.*?)\\1\\s*\\)/i", $css, $matches);

  foreach ($matches[2] ?? [] as $reference) {
    $reference = trim((string) $reference);

    if ($reference !== "" && ! str_starts_with($reference, "data:") && ! str_starts_with($reference, "#")) {
      fwrite(STDERR, $stylesheet." contains an unvendored URL reference: ".$reference."\n");
      exit(1);
    }
  }
}

$manifest = [
  "product" => "webblocks-ui",
  "version" => $version,
  "source_reference" => "https://github.com/fklavyenet/webblocks-ui/tree/".$version,
  "artifacts" => $artifacts,
];

file_put_contents(
  $directory."/manifest.json",
  json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
);
' "${STAGING_DIR}" "${UI_VERSION}"

mkdir -p "${TARGET_ROOT}" "${ICON_TARGET_DIR}"

find "${TARGET_ROOT}" -mindepth 1 -maxdepth 1 -type d ! -name "${UI_VERSION}" -exec rm -rf {} +
rm -rf "${TARGET_DIR}"
mkdir -p "${TARGET_DIR}"
cp "${STAGING_DIR}"/* "${TARGET_DIR}/"

find "${ICON_TARGET_DIR}" -name 'webblocks-ui-*.json' ! -name "webblocks-ui-${UI_VERSION}.json" -delete
cp "${STAGING_DIR}/webblocks-icons.json" "${ICON_TARGET_PATH}"

printf '[webblocks-vendor-ui] Vendored runtime: %s\n' "${TARGET_DIR}"
printf '[webblocks-vendor-ui] Manifest: %s\n' "${TARGET_DIR}/manifest.json"
printf '[webblocks-vendor-ui] Icon catalog source: %s\n' "${ICON_TARGET_PATH}"
