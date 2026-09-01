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

# Tagging is a step in the release flow that nothing used to enforce, so it was
# the step that got skipped: 19 published versions between 1.37.1 and 1.48.3 had
# no tag at all, and six more carry one pointing at some other commit. Publishing
# does not need the tag — that is exactly why remembering it was the only thing
# holding it in place. The artifact is built from HEAD, so requiring a matching
# tag here costs nothing when the flow is followed and stops the release when it
# is not.
TAG_NAME="v${VERSION}"

if ! git rev-parse -q --verify "refs/tags/${TAG_NAME}" >/dev/null; then
  printf '[webblocks-release-prepare] No %s tag for this release.\n' "${TAG_NAME}" >&2
  printf '[webblocks-release-prepare] Tag it before building the artifact:\n' >&2
  printf '[webblocks-release-prepare]   git tag -a %s -m "webblocks-cms %s"\n' "${TAG_NAME}" "${VERSION}" >&2
  exit 1
fi

if [ "$(git cat-file -t "refs/tags/${TAG_NAME}")" != "tag" ]; then
  printf '[webblocks-release-prepare] %s is a lightweight tag; the release flow uses annotated tags.\n' "${TAG_NAME}" >&2
  printf '[webblocks-release-prepare]   git tag -f -a %s -m "webblocks-cms %s"\n' "${TAG_NAME}" "${VERSION}" >&2
  exit 1
fi

# A published version's source reference is immutable, so moving a tag a remote
# already carries is not a fix — Packagist rejects the update and every consumer
# that resolved the old reference disagrees with the new one. It happened to
# v1.58.1: a correction landed after tagging, and moving the tag was the only
# route the two checks above left open. Bumping the version is the route.
# Unreachable remotes are not fatal here, for the same reason the icon manifest
# check tolerates them: a release must not hinge on the network being up.
TAG_COMMIT="$(git -C "${ROOT_DIR}" rev-list -1 "${TAG_NAME}")"

for REMOTE in $(git -C "${ROOT_DIR}" remote); do
  if ! REMOTE_TAG="$(git -C "${ROOT_DIR}" ls-remote "${REMOTE}" "refs/tags/${TAG_NAME}^{}" "refs/tags/${TAG_NAME}" 2>/dev/null)"; then
    printf '[webblocks-release-prepare] Could not reach %s to compare %s; moved-tag check skipped for that remote.\n' "${REMOTE}" "${TAG_NAME}" >&2

    continue
  fi

  # An annotated tag answers twice: the tag object, then the commit it peels to.
  # The peeled line is the one to compare, and the bare line is the fallback for
  # a lightweight tag on the remote.
  REMOTE_COMMIT="$(printf '%s\n' "${REMOTE_TAG}" | awk -v peeled="refs/tags/${TAG_NAME}^{}" '$2 == peeled { print $1 }')"

  if [ -z "${REMOTE_COMMIT}" ]; then
    REMOTE_COMMIT="$(printf '%s\n' "${REMOTE_TAG}" | awk -v bare="refs/tags/${TAG_NAME}" '$2 == bare { print $1 }')"
  fi

  if [ -n "${REMOTE_COMMIT}" ] && [ "${REMOTE_COMMIT}" != "${TAG_COMMIT}" ]; then
    printf '[webblocks-release-prepare] %s already exists on %s at a different commit.\n' "${TAG_NAME}" "${REMOTE}" >&2
    printf '[webblocks-release-prepare]   published: %s\n' "${REMOTE_COMMIT}" >&2
    printf '[webblocks-release-prepare]   local:     %s\n' "${TAG_COMMIT}" >&2
    printf '[webblocks-release-prepare] A released version cannot change its contents. Bump the version and tag the fix as a new release.\n' >&2
    exit 1
  fi
done

# The icon catalog ships with the package; a release that carries a manifest
# for some other UI version would install a catalog that does not match the CSS
# the browser loads. Missing is a hard error. Stale is a hard error. Unreachable
# is not: a transient network failure should not block a release.
UI_VERSION="$("${PHP_BIN}" -r '$source = file_get_contents($argv[1]); if (! preg_match("/UI_VERSION = '"'"'([^'"'"']+)'"'"'/", $source, $matches)) { fwrite(STDERR, "Unable to read the pinned UI version.\n"); exit(1); } echo $matches[1];' "${ROOT_DIR}/src/Support/WebBlocks.php")"
ICON_MANIFEST_PATH="${ROOT_DIR}/database/content/icons/webblocks-ui-${UI_VERSION}.json"

"${PHP_BIN}" "${ROOT_DIR}/scripts/release/verify-ui.php"

if [ ! -f "${ICON_MANIFEST_PATH}" ]; then
  printf '[webblocks-release-prepare] No bundled icon manifest for %s.\n' "${UI_VERSION}" >&2
  printf '[webblocks-release-prepare] Run "composer ui:vendor" and commit the result.\n' >&2
  exit 1
fi

ICON_MANIFEST_TEMP="$(mktemp)"

if curl -fsS --max-time 60 "https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@${UI_VERSION}/packages/webblocks/dist/webblocks-icons.json" -o "${ICON_MANIFEST_TEMP}"; then
  if ! cmp -s "${ICON_MANIFEST_TEMP}" "${ICON_MANIFEST_PATH}"; then
    rm -f "${ICON_MANIFEST_TEMP}"
    printf '[webblocks-release-prepare] Bundled icon manifest differs from the one published for %s.\n' "${UI_VERSION}" >&2
    printf '[webblocks-release-prepare] Run "composer ui:vendor" and commit the result.\n' >&2
    exit 1
  fi

  printf '[webblocks-release-prepare] Bundled icon manifest matches %s.\n' "${UI_VERSION}"
else
  printf '[webblocks-release-prepare] Could not reach the icon manifest for %s; shipping the bundled copy unverified.\n' "${UI_VERSION}" >&2
fi

rm -f "${ICON_MANIFEST_TEMP}"

TAG_COMMIT="$(git rev-list -n1 "refs/tags/${TAG_NAME}")"
HEAD_COMMIT="$(git rev-parse HEAD)"

if [ "${TAG_COMMIT}" != "${HEAD_COMMIT}" ]; then
  printf '[webblocks-release-prepare] %s points at %s, but the artifact is built from HEAD at %s.\n' "${TAG_NAME}" "${TAG_COMMIT}" "${HEAD_COMMIT}" >&2
  printf '[webblocks-release-prepare] Move the tag to the commit being released, or release the commit it names.\n' >&2
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
    -x '*/.*' \
    -x '.github/*' \
    -x 'CHANGELOG.md' \
    -x 'LICENSE' \
    -x 'README.md' \
    -x 'UPGRADING.md'
)

"${PHP_BIN}" -r '
$zip = new ZipArchive();
$path = $argv[1];
$allowed = ["composer.json", "src", "routes", "resources", "database", "config", "public", "docs", "stubs"];

if ($zip->open($path) !== true) {
  fwrite(STDERR, "[webblocks-release-prepare] Unable to inspect release ZIP.\n");
  exit(1);
}

for ($index = 0; $index < $zip->numFiles; $index++) {
  $entry = trim(str_replace("\\", "/", (string) $zip->getNameIndex($index)), "/");
  if ($entry === "") continue;
  $segments = explode("/", $entry);
  $root = $segments[0];

  $hasHiddenSegment = false;
  foreach ($segments as $segment) {
    if ($segment === "." || $segment === ".." || str_starts_with($segment, ".")) {
      $hasHiddenSegment = true;
      break;
    }
  }

  if ($hasHiddenSegment || ! in_array($root, $allowed, true)) {
    fwrite(STDERR, "[webblocks-release-prepare] Release ZIP path is outside the CMS package allowlist: {$entry}\n");
    exit(1);
  }
}

$zip->close();
' "${ARCHIVE_PATH}"

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
