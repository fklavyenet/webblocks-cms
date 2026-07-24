#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
VERSION="$("${PHP_BIN}" -r '$source = file_get_contents($argv[1]); if (! preg_match("/VERSION = '\''([^'\'']+)'\''/", $source, $matches)) { fwrite(STDERR, "Unable to read WebBlocks CMS version.\n"); exit(1); } echo $matches[1];' "${ROOT_DIR}/src/Support/WebBlocks.php")"
HEAD_VERSION="$(git -C "${ROOT_DIR}" show HEAD:src/Support/WebBlocks.php | "${PHP_BIN}" -r '$source = stream_get_contents(STDIN); if (! preg_match("/VERSION = '\''([^'\'']+)'\''/", $source, $matches)) { fwrite(STDERR, "Unable to read committed WebBlocks CMS version.\n"); exit(1); } echo $matches[1];')"
RELEASE_ROOT="${WEBBLOCKS_CMS_RELEASE_ARTIFACT_DIR:-${ROOT_DIR}/storage/app/webblocks-cms-release/${VERSION}}"
ARCHIVE_PATH="${RELEASE_ROOT}/webblocks-cms-${VERSION}.zip"
PAYLOAD_PATH="${RELEASE_ROOT}/webblocks-cms-${VERSION}-update-server-payload.json"
STAGING_DIR="$(mktemp -d)"
PACKAGE_DIR="${STAGING_DIR}/webblocks-cms"
CHANGELOG_PATH="${ROOT_DIR}/CHANGELOG.md"

cleanup() {
  rm -rf "${STAGING_DIR}"
}

trap cleanup EXIT

mkdir -p "${RELEASE_ROOT}" "${PACKAGE_DIR}"
rm -f "${ARCHIVE_PATH}" "${ARCHIVE_PATH}.sha256" "${PAYLOAD_PATH}"

cd "${ROOT_DIR}"

if [ "${VERSION}" != "${HEAD_VERSION}" ]; then
  printf '[webblocks-release-prepare] Working tree version %s does not match committed HEAD version %s.\n' "${VERSION}" "${HEAD_VERSION}" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  printf '[webblocks-release-prepare] Canonical package source has uncommitted changes.\n' >&2
  printf '[webblocks-release-prepare] Commit package changes before preparing a release artifact.\n' >&2
  exit 1
fi

git archive --format=tar --worktree-attributes HEAD | tar -xf - -C "${PACKAGE_DIR}"

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
function releaseNoteItemsForVersion(string $changelogPath, string $version): array
{
  if (! is_file($changelogPath)) {
    fwrite(STDERR, "[webblocks-release-prepare] CHANGELOG.md was not found.\n");
    exit(1);
  }

  $changelog = (string) file_get_contents($changelogPath);
  $pattern = "/^##\s+".preg_quote($version, "/")."\s*$(.*?)(?=^##\s+|\z)/ms";

  if (preg_match($pattern, $changelog, $matches) !== 1) {
    fwrite(STDERR, "[webblocks-release-prepare] CHANGELOG.md does not contain a release section for ".$version.".\n");
    exit(1);
  }

  $items = [];

  foreach (preg_split("/\r\n|\r|\n/", trim($matches[1])) ?: [] as $line) {
    if (preg_match("/^\s*-\s+(.+)$/", $line, $lineMatches) !== 1) {
      continue;
    }

    $item = trim($lineMatches[1]);

    if (preg_match("/^Bumped CMS to `?".preg_quote($version, "/")."`?\.$/", $item) === 1) {
      continue;
    }

    $item = (string) preg_replace("/`([^`]+)`/", "$1", $item);
    $item = (string) preg_replace("/\[(.*?)\]\((.*?)\)/", "$1", $item);
    $item = trim($item);

    if ($item !== "") {
      $items[] = $item;
    }
  }

  if ($items === []) {
    fwrite(STDERR, "[webblocks-release-prepare] CHANGELOG.md release section for ".$version." does not contain operator-facing notes.\n");
    exit(1);
  }

  return array_values(array_unique($items));
}

$version = $argv[1];
$archivePath = $argv[2];
$payloadPath = $argv[3];
$changelogPath = $argv[4];
$checksum = trim(file_get_contents($archivePath.".sha256"));
$minimumClientVersion = getenv("WEBBLOCKS_UPDATE_MINIMUM_CLIENT_VERSION") ?: "1.32.18";
$notes = releaseNoteItemsForVersion($changelogPath, $version);
// The first note is the headline (rendered as the summary/trigger); the rest
// are the supporting highlights. Splitting here keeps the summary out of the
// highlights list so a single-note release never repeats itself.
$summary = $notes[0] ?? "";
$highlights = array_slice($notes, 1);

$payload = [
  "product" => "webblocks-cms",
  "channel" => "stable",
  "version" => $version,
  "minimum_client_version" => $minimumClientVersion,
  "source_reference" => "v".$version,
  "artifact_filename" => basename($archivePath),
  "artifact_path" => $archivePath,
  "checksum_sha256" => $checksum,
  "release_notes" => "WebBlocks CMS ".$version.PHP_EOL.PHP_EOL."- ".implode(PHP_EOL."- ", $notes),
  "details" => [
    "title" => "WebBlocks CMS ".$version,
    "summary" => $summary,
    "highlights" => $highlights,
    "compatibility_notes" => [],
    "operator_notes" => [],
    "technical_notes" => [
      "Source reference: v".$version,
      "Artifact checksum: ".$checksum,
      "Minimum update client version: ".$minimumClientVersion,
    ],
  ],
];
file_put_contents($payloadPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
' "${VERSION}" "${ARCHIVE_PATH}" "${PAYLOAD_PATH}" "${CHANGELOG_PATH}"

printf '[webblocks-release-prepare] Retained artifact root: %s\n' "${RELEASE_ROOT}"
printf '[webblocks-release-prepare] Artifact: %s\n' "${ARCHIVE_PATH}"
printf '[webblocks-release-prepare] Payload: %s\n' "${PAYLOAD_PATH}"
printf '[webblocks-release-prepare] Publish with: composer release:publish-update\n'
