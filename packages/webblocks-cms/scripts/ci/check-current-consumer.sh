#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_DIR="$(mktemp -d)"
SOURCE_COPY="${TEMP_DIR}/package-source"
CONSUMER="${TEMP_DIR}/consumer"

cleanup() {
  rm -rf "${TEMP_DIR}"
}

trap cleanup EXIT

mkdir -p "${SOURCE_COPY}"
git -C "${ROOT_DIR}" archive --format=tar HEAD | tar -xf - -C "${SOURCE_COPY}"
composer create-project 'laravel/laravel:^13.0' "${CONSUMER}" --no-interaction --prefer-dist --no-progress
composer config --working-dir="${CONSUMER}" --json repositories.webblocks "{\"type\":\"path\",\"url\":\"${SOURCE_COPY}\",\"options\":{\"symlink\":false}}"
COMPOSER_MIRROR_PATH_REPOS=1 composer require --working-dir="${CONSUMER}" 'fklavyenet/webblocks-cms:@dev' --no-interaction --prefer-dist --no-progress -W

test ! -L "${CONSUMER}/vendor/fklavyenet/webblocks-cms"
php "${CONSUMER}/artisan" package:discover
php "${CONSUMER}/artisan" about --only=environment
php "${CONSUMER}/artisan" vendor:publish --tag=webblocks-cms-config
php "${CONSUMER}/artisan" vendor:publish --tag=webblocks-cms-assets
php "${CONSUMER}/artisan" vendor:publish --tag=webblocks-cms-stubs
php "${CONSUMER}/artisan" webblocks:install --name='CI Admin' --email='ci-admin@example.test' --password='CI-only-password!' --site-name='CI Site' --site-handle='ci-site' --no-interaction
php "${CONSUMER}/artisan" webblocks:install --name='CI Admin' --email='ci-admin@example.test' --password='CI-only-password!' --site-name='CI Site' --site-handle='ci-site' --no-interaction
php "${CONSUMER}/artisan" route:cache
php "${CONSUMER}/artisan" route:clear
php "${CONSUMER}/artisan" migrate:status

grep -q 'HasWebBlocksCmsAccess' "${CONSUMER}/app/Models/User.php"
test -f "${CONSUMER}/public/cms/package-boundary.json"
test -f "${CONSUMER}/config/webblocks-cms.php"
test -f "${CONSUMER}/stubs/vendor/webblocks-cms/README.md"

rm -rf "${SOURCE_COPY}"
composer config --working-dir="${CONSUMER}" --unset repositories.webblocks
composer dump-autoload --working-dir="${CONSUMER}" --no-interaction
php "${CONSUMER}/artisan" package:discover
php "${CONSUMER}/artisan" about --only=environment
php "${CONSUMER}/artisan" route:list > /dev/null
