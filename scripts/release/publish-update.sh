#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
ARGS=()

for arg in "$@"; do
  case "${arg}" in
    --version=*)
      ARGS+=("--release-version=${arg#--version=}")
      ;;
    --version)
      printf '[webblocks-publish-update] Use --version=1.32.90 with the Composer alias. Artisan reserves bare --version for application version output.\n' >&2
      exit 1
      ;;
    *)
      ARGS+=("${arg}")
      ;;
  esac
done

cd "${ROOT_DIR}"

if ((${#ARGS[@]} > 0)); then
  "${PHP_BIN}" artisan webblocks:publish-update "${ARGS[@]}"
else
  "${PHP_BIN}" artisan webblocks:publish-update
fi
