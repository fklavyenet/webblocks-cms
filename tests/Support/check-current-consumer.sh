#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LARAVEL_VERSION="${LARAVEL_VERSION:-13}"
TEMP_DIR="$(mktemp -d)"
SOURCE_COPY="${TEMP_DIR}/package-source"
CONSUMER="${TEMP_DIR}/consumer"

cleanup() {
  rm -rf "${TEMP_DIR}"
}

trap cleanup EXIT

mkdir -p "${SOURCE_COPY}"
tar -C "${ROOT_DIR}" -cf - \
  .editorconfig .gitattributes .github .gitignore CHANGELOG.md CODE_OF_CONDUCT.md \
  CONTRIBUTING.md LICENSE README.md SECURITY.md SUPPORT.md UPGRADING.md composer.json \
  config database phpunit.xml.dist pint.json public resources routes src stubs tests \
  | tar -xf - -C "${SOURCE_COPY}"
composer create-project "laravel/laravel:^${LARAVEL_VERSION}.0" "${CONSUMER}" --no-interaction --prefer-dist --no-progress
composer config --working-dir="${CONSUMER}" --json repositories.webblocks "{\"type\":\"path\",\"url\":\"${SOURCE_COPY}\",\"options\":{\"symlink\":false}}"
COMPOSER_MIRROR_PATH_REPOS=1 composer require --working-dir="${CONSUMER}" 'fklavyenet/webblocks-cms:@dev' --no-interaction --prefer-dist --no-progress -W

test ! -L "${CONSUMER}/vendor/fklavyenet/webblocks-cms"
php "${CONSUMER}/artisan" package:discover
php "${CONSUMER}/artisan" about --only=environment
php "${CONSUMER}/artisan" vendor:publish --tag=webblocks-cms-config
php "${CONSUMER}/artisan" vendor:publish --tag=webblocks-cms-assets
php "${CONSUMER}/artisan" vendor:publish --tag=webblocks-cms-stubs
WEBBLOCKS_CMS_PUBLIC_MOUNT=wb php "${CONSUMER}/artisan" webblocks:install --name='CI Admin' --email='ci-admin@example.test' --password='CI-only-password!' --site-name='CI Site' --site-handle='ci-site' --no-interaction
# Mounted installation must preserve the host's untouched welcome route.
grep -q "return view('welcome');" "${CONSUMER}/routes/web.php"
php "${CONSUMER}/artisan" webblocks:install --name='CI Admin' --email='ci-admin@example.test' --password='CI-only-password!' --site-name='CI Site' --site-handle='ci-site' --no-interaction
# Exercise real host/controller ownership through fresh cached and uncached boots.
cp "${ROOT_DIR}/tests/Support/Fixtures/RoutingConsumerController.php" "${CONSUMER}/app/Http/Controllers/RoutingConsumerController.php"
cp "${ROOT_DIR}/tests/Support/Fixtures/routing-consumer-routes.php" "${CONSUMER}/routes/webblocks-routing-probe.php"
cat >> "${CONSUMER}/routes/web.php" <<'PHP'

require __DIR__.'/webblocks-routing-probe.php';
PHP
APP_URL=http://localhost php "${ROOT_DIR}/tests/Support/check-consumer-routing.php" "${CONSUMER}" seed
for mount in '' wb; do
  APP_URL=http://localhost WEBBLOCKS_CMS_PUBLIC_MOUNT="${mount}" php "${CONSUMER}/artisan" route:clear
  APP_URL=http://localhost WEBBLOCKS_CMS_PUBLIC_MOUNT="${mount}" php "${ROOT_DIR}/tests/Support/check-consumer-routing.php" "${CONSUMER}" uncached
  APP_URL=http://localhost WEBBLOCKS_CMS_PUBLIC_MOUNT="${mount}" php "${CONSUMER}/artisan" route:cache
  APP_URL=http://localhost WEBBLOCKS_CMS_PUBLIC_MOUNT="${mount}" php "${ROOT_DIR}/tests/Support/check-consumer-routing.php" "${CONSUMER}" cached
done
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
php "${ROOT_DIR}/tests/Support/check-consumer-inventory.php" "${CONSUMER}"
