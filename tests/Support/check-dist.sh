#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_DIR="$(mktemp -d)"
CONTRACT_CHECK_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "${TEMP_DIR}"
  rm -rf "${CONTRACT_CHECK_DIR}"
}

trap cleanup EXIT

TREE="$(git -C "${ROOT_DIR}" write-tree)"
git -C "${ROOT_DIR}" archive --format=tar "${TREE}" | tar -xf - -C "${TEMP_DIR}"

if git -C "${ROOT_DIR}" cat-file -e "${TREE}:tests/Support/check-inventory.php" 2>/dev/null; then
  for CHECK_FILE in InventoryReview.php check-inventory.php; do
    git -C "${ROOT_DIR}" show "${TREE}:tests/Support/${CHECK_FILE}" > "${CONTRACT_CHECK_DIR}/${CHECK_FILE}"
  done
  php "${CONTRACT_CHECK_DIR}/check-inventory.php" --root="${TEMP_DIR}"
  test -f "${TEMP_DIR}/resources/contracts/inventory-review.json"
fi

for path in resources/contracts/inventory.md composer.json src config database public resources routes stubs README.md LICENSE UPGRADING.md; do
  test -e "${TEMP_DIR}/${path}" || { printf 'Missing distribution path: %s\n' "${path}" >&2; exit 1; }
done

for path in docs storage AGENTS.md .DS_Store .claude .github .publisher-client.json tests scripts vendor composer.lock phpunit.xml.dist pint.json CONTRIBUTING.md CODE_OF_CONDUCT.md SUPPORT.md; do
  test ! -e "${TEMP_DIR}/${path}" || { printf 'Source-only path entered distribution: %s\n' "${path}" >&2; exit 1; }
done

test -z "$(find "${TEMP_DIR}" -type l -print -quit)" || { printf 'Distribution contains symlinks.\n' >&2; exit 1; }
test -z "$(find "${TEMP_DIR}" -type f -size +2M -print -quit)" || { printf 'Distribution contains an unexpected file over 2 MiB.\n' >&2; exit 1; }

composer validate --strict --working-dir="${TEMP_DIR}"

file_count="$(find "${TEMP_DIR}" -type f | wc -l | tr -d ' ')"
top_levels="$(find "${TEMP_DIR}" -mindepth 1 -maxdepth 1 -print | sed 's#.*/##' | sort | tr '\n' ' ')"
printf 'Distribution files: %s\nTop-level entries: %s\n' "${file_count}" "${top_levels}"
