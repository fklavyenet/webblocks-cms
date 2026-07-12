#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

if [ -z "${WEBBLOCKS_PUBLISHER_TOKEN:-}" ] && [ -f "${ROOT_DIR}/.env" ]; then
  WEBBLOCKS_PUBLISHER_TOKEN="$("${PHP_BIN}" -r '
    require $argv[1]."/vendor/autoload.php";
    $environment = Dotenv\Dotenv::parse((string) file_get_contents($argv[1]."/.env"));
    echo trim((string) ($environment["WEBBLOCKS_PUBLISHER_TOKEN"] ?? ""));
  ' "${ROOT_DIR}")"
  export WEBBLOCKS_PUBLISHER_TOKEN
fi

VERSION="$("${PHP_BIN}" -r '$source = file_get_contents($argv[1]); if (! preg_match("/VERSION = '\''([^'\'']+)'\''/", $source, $matches)) { fwrite(STDERR, "Unable to read WebBlocks CMS version.\n"); exit(1); } echo $matches[1];' "${ROOT_DIR}/src/Support/WebBlocks.php")"
RELEASE_ROOT="${WEBBLOCKS_CMS_RELEASE_ARTIFACT_DIR:-${ROOT_DIR}/storage/app/webblocks-cms-release/${VERSION}}"
ARGS=(
  "--release-version=${VERSION}"
  "--artifact=${RELEASE_ROOT}/webblocks-cms-${VERSION}.zip"
  "--payload=${RELEASE_ROOT}/webblocks-cms-${VERSION}-update-server-payload.json"
)

for arg in "$@"; do
  case "${arg}" in
    --version=*)
      ARGS[0]="--release-version=${arg#--version=}"
      ;;
    --version)
      printf '[webblocks-publish-update] Use --version=1.37.1 with the Composer alias. Testbench reserves bare --version for application version output.\n' >&2
      exit 1
      ;;
    *)
      ARGS+=("${arg}")
      ;;
  esac
done

cd "${ROOT_DIR}"
"${PHP_BIN}" vendor/bin/testbench webblocks:publish-update "${ARGS[@]}"
