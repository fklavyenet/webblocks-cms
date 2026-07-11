#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "${TEMP_DIR}"
}

trap cleanup EXIT

git -C "${ROOT_DIR}" archive --format=tar --worktree-attributes HEAD | tar -xf - -C "${TEMP_DIR}"

for path in composer.json src config database public resources routes stubs README.md LICENSE UPGRADING.md; do
  test -e "${TEMP_DIR}/${path}" || { printf 'Missing distribution path: %s\n' "${path}" >&2; exit 1; }
done

for path in .github tests scripts vendor composer.lock phpunit.xml.dist pint.json CONTRIBUTING.md DEVELOPMENT.md CODE_OF_CONDUCT.md; do
  test ! -e "${TEMP_DIR}/${path}" || { printf 'Source-only path entered distribution: %s\n' "${path}" >&2; exit 1; }
done

test -z "$(find "${TEMP_DIR}" -type l -print -quit)" || { printf 'Distribution contains symlinks.\n' >&2; exit 1; }
test -z "$(find "${TEMP_DIR}" -type f -size +2M -print -quit)" || { printf 'Distribution contains an unexpected file over 2 MiB.\n' >&2; exit 1; }

composer validate --strict --working-dir="${TEMP_DIR}"

file_count="$(find "${TEMP_DIR}" -type f | wc -l | tr -d ' ')"
top_levels="$(find "${TEMP_DIR}" -mindepth 1 -maxdepth 1 -print | sed 's#.*/##' | sort | tr '\n' ' ')"
printf 'Distribution files: %s\nTop-level entries: %s\n' "${file_count}" "${top_levels}"
