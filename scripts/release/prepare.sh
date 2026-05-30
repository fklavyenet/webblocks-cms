#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
VERSION="$("${PHP_BIN}" -r '$source = file_get_contents($argv[1]); if (! preg_match("/VERSION = '\''([^'\'']+)'\''/", $source, $matches)) { fwrite(STDERR, "Unable to read WebBlocks CMS version.\n"); exit(1); } echo $matches[1];' "${ROOT_DIR}/packages/webblocks-cms/src/Support/WebBlocks.php")"
RELEASE_ROOT="${WEBBLOCKS_CMS_RELEASE_ARTIFACT_DIR:-${ROOT_DIR}/storage/app/webblocks-cms-release/${VERSION}}"
ARCHIVE_PATH="${RELEASE_ROOT}/webblocks-cms-${VERSION}.zip"
PAYLOAD_PATH="${RELEASE_ROOT}/webblocks-cms-${VERSION}-update-server-payload.json"
STAGING_DIR="$(mktemp -d)"
PACKAGE_ROOT="packages/webblocks-cms"

cleanup() {
  rm -rf "${STAGING_DIR}"
}

trap cleanup EXIT

mkdir -p "${RELEASE_ROOT}"
rm -f "${ARCHIVE_PATH}" "${ARCHIVE_PATH}.sha256" "${PAYLOAD_PATH}"

cd "${ROOT_DIR}"

git archive --format=tar --worktree-attributes HEAD "${PACKAGE_ROOT}" | tar -xf - -C "${STAGING_DIR}"

PACKAGE_DIR="${STAGING_DIR}/${PACKAGE_ROOT}"

if [ ! -f "${PACKAGE_DIR}/composer.json" ]; then
  printf '[webblocks-release-prepare] Package composer.json not found at %s.\n' "${PACKAGE_DIR}" >&2
  exit 1
fi

(
  cd "${PACKAGE_DIR}"
  zip -qr "${ARCHIVE_PATH}" . \
    -x '.DS_Store' \
    -x '__MACOSX/*' \
    -x '._*' \
    -x '.git*' \
    -x '.github/*'
)

CHECKSUM="$(shasum -a 256 "${ARCHIVE_PATH}" | cut -d' ' -f1)"
printf '%s\n' "${CHECKSUM}" > "${ARCHIVE_PATH}.sha256"

"${PHP_BIN}" -r '
$payload = [
  "product" => "webblocks-cms",
  "channel" => getenv("WEBBLOCKS_UPDATE_PUBLISHER_CHANNEL") ?: getenv("WEBBLOCKS_UPDATES_CHANNEL") ?: "stable",
  "version" => $argv[1],
  "minimum_client_version" => getenv("WEBBLOCKS_UPDATE_MINIMUM_CLIENT_VERSION") ?: "1.32.18",
  "source_reference" => "v".$argv[1],
  "artifact_filename" => basename($argv[2]),
  "artifact_path" => $argv[2],
  "checksum_sha256" => trim(file_get_contents($argv[2].".sha256")),
  "release_notes" => "WebBlocks CMS ".$argv[1]." native update release.",
  "details" => [
    "title" => "WebBlocks CMS ".$argv[1],
    "summary" => "Native update-server package release for WebBlocks CMS.",
    "highlights" => ["Prepared locally through the native update-server publishing workflow."],
    "compatibility_notes" => ["System Updates continues to consume update-server package metadata."],
    "operator_notes" => ["Live installs update only through System Updates or supervised manual apply."],
    "technical_notes" => ["Package artifact checksum is verified before publishing."],
  ],
];
file_put_contents($argv[3], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
' "${VERSION}" "${ARCHIVE_PATH}" "${PAYLOAD_PATH}"

printf '[webblocks-release-prepare] Retained artifact root: %s\n' "${RELEASE_ROOT}"
printf '[webblocks-release-prepare] Artifact: %s\n' "${ARCHIVE_PATH}"
printf '[webblocks-release-prepare] Payload: %s\n' "${PAYLOAD_PATH}"
printf '[webblocks-release-prepare] Publish with: composer release:publish-update\n'
