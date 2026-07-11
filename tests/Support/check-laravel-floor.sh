#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "${TEMP_DIR}"
}

trap cleanup EXIT

composer init --working-dir="${TEMP_DIR}" --name=webblocks/laravel-floor-probe --type=project --require='php:^8.4' --no-interaction
composer config --working-dir="${TEMP_DIR}" --json repositories.webblocks "{\"type\":\"path\",\"url\":\"${ROOT_DIR}\",\"options\":{\"symlink\":false}}"

# This historical-floor probe validates declared dependency resolution only.
# Its scoped --no-audit does not weaken or replace the blocking audit of the
# current dependency graph.
if composer require --help | grep -q -- '--no-security-blocking'; then
  COMPOSER_MIRROR_PATH_REPOS=1 composer require --working-dir="${TEMP_DIR}" 'laravel/framework:13.0.*' 'fklavyenet/webblocks-cms:@dev' --no-interaction --prefer-dist --no-progress --no-audit --no-security-blocking -W
else
  COMPOSER_MIRROR_PATH_REPOS=1 composer require --working-dir="${TEMP_DIR}" 'laravel/framework:13.0.*' 'fklavyenet/webblocks-cms:@dev' --no-interaction --prefer-dist --no-progress --no-audit -W
fi
composer show --working-dir="${TEMP_DIR}" laravel/framework --locked
