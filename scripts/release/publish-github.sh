#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
VERSION="$("${PHP_BIN}" -r '$source = file_get_contents($argv[1]); if (! preg_match("/VERSION = '\''([^'\'']+)'\''/", $source, $matches)) { fwrite(STDERR, "Unable to read WebBlocks CMS version.\n"); exit(1); } echo $matches[1];' "${ROOT_DIR}/src/Support/WebBlocks.php")"
TAG_NAME="v${VERSION}"
NOTES_FILE="$(mktemp)"

cleanup() {
  rm -f "${NOTES_FILE}"
}

trap cleanup EXIT

if ! command -v gh >/dev/null 2>&1; then
  printf '[webblocks-github-release] GitHub CLI is required before publishing.\n' >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  printf '[webblocks-github-release] GitHub CLI is not authenticated. Run gh auth login before publishing.\n' >&2
  exit 1
fi

if ! git -C "${ROOT_DIR}" rev-parse -q --verify "refs/tags/${TAG_NAME}" >/dev/null; then
  printf '[webblocks-github-release] Missing local tag %s.\n' "${TAG_NAME}" >&2
  exit 1
fi

awk -v heading="## ${VERSION}" '
  $0 == heading { found = 1; next }
  found && /^## / { exit }
  found { print }
' "${ROOT_DIR}/CHANGELOG.md" > "${NOTES_FILE}"

if [ ! -s "${NOTES_FILE}" ]; then
  printf '[webblocks-github-release] CHANGELOG.md has no notes for %s.\n' "${VERSION}" >&2
  exit 1
fi

if gh release view "${TAG_NAME}" --repo fklavyenet/webblocks-cms >/dev/null 2>&1; then
  gh release edit "${TAG_NAME}" \
    --repo fklavyenet/webblocks-cms \
    --title "WebBlocks CMS ${VERSION}" \
    --notes-file "${NOTES_FILE}" \
    --latest
else
  gh release create "${TAG_NAME}" \
    --repo fklavyenet/webblocks-cms \
    --verify-tag \
    --title "WebBlocks CMS ${VERSION}" \
    --notes-file "${NOTES_FILE}" \
    --latest
fi

printf '[webblocks-github-release] GitHub Release %s is published and marked latest.\n' "${TAG_NAME}"
