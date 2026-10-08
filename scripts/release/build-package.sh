#!/usr/bin/env bash

# Build the product artifact independently of tag/publish preflight. The normal
# release:prepare entry point still enforces immutable annotated release tags.
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
ARCHIVE_PATH="${1:?Usage: build-package.sh OUTPUT.zip [TREE]}"
TREE="${2:-HEAD}"
case "${ARCHIVE_PATH}" in
  /*) ;;
  *) ARCHIVE_PATH="${PWD}/${ARCHIVE_PATH}" ;;
esac
STAGING_DIR="$(mktemp -d)"
OUTPUT_PATH="${ARCHIVE_PATH}"
ARCHIVE_PATH="${STAGING_DIR}/package.zip"
PACKAGE_DIR="${STAGING_DIR}/webblocks-cms"
cleanup() {
  rm -rf "${STAGING_DIR}"
}
trap cleanup EXIT
mkdir -p "${PACKAGE_DIR}" "$(dirname "${OUTPUT_PATH}")"
cd "${ROOT_DIR}"

git archive --format=tar --worktree-attributes "${TREE}" | tar -xf - -C "${PACKAGE_DIR}"

# Validate the selected archive tree, not the caller's working copy. Historical
# trees predating the review gate keep their original distribution behavior.
if git cat-file -e "${TREE}:tests/Support/check-inventory.php" 2>/dev/null; then
  mkdir -p "${STAGING_DIR}/contract-check"
  for CHECK_FILE in InventoryReview.php check-inventory.php; do
    git show "${TREE}:tests/Support/${CHECK_FILE}" > "${STAGING_DIR}/contract-check/${CHECK_FILE}"
  done
  "${PHP_BIN}" "${STAGING_DIR}/contract-check/check-inventory.php" --root="${PACKAGE_DIR}"
fi

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
    -x 'README.md' \
    -x 'UPGRADING.md'
)

"${PHP_BIN}" -r '
$zip = new ZipArchive();
$path = $argv[1];
$allowed = ["composer.json", "src", "routes", "resources", "database", "config", "public", "stubs", "LICENSE"];
$required = ["composer.json" => false, "LICENSE" => false, "resources/contracts/inventory.md" => false];
if (is_file($argv[2]."/resources/contracts/inventory-review.json")) {
  $required["resources/contracts/inventory-review.json"] = false;
}

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

  if (array_key_exists($entry, $required)) {
    $required[$entry] = true;
  }
}

foreach ($required as $entry => $present) {
  if (! $present) {
    fwrite(STDERR, "[webblocks-release-prepare] Release ZIP is missing required package file: {$entry}\n");
    exit(1);
  }
}

$zip->close();
' "${ARCHIVE_PATH}" "${PACKAGE_DIR}"


mv "${ARCHIVE_PATH}" "${OUTPUT_PATH}"
printf '[webblocks-package] Built %s from %s.\n' "${OUTPUT_PATH}" "${TREE}"
