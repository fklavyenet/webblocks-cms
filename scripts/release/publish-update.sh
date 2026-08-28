#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

# Publisher credentials live outside the package. A .env at the repository root
# is a package boundary violation, so the default is a clearly named file in the
# project-family root; WEBBLOCKS_RELEASE_ENV_FILE overrides it, and a root .env
# still wins if one is present so existing checkouts keep working. This file
# never ships: scripts/ is export-ignore.
ENV_FILE="${WEBBLOCKS_RELEASE_ENV_FILE:-${ROOT_DIR}/../publish.env}"

if [ -f "${ROOT_DIR}/.env" ]; then
  ENV_FILE="${ROOT_DIR}/.env"
fi

if [ -f "${ENV_FILE}" ]; then
  for credential in WEBBLOCKS_PUBLISHER_TOKEN WEBBLOCKS_PUBLISHER_SIGNING_KEY; do
    if [ -z "$(eval "printf '%s' \"\${${credential}:-}\"")" ]; then
      value="$("${PHP_BIN}" -r '
        require $argv[1]."/vendor/autoload.php";
        $environment = Dotenv\Dotenv::parse((string) file_get_contents($argv[2]));
        echo trim((string) ($environment[$argv[3]] ?? ""));
      ' "${ROOT_DIR}" "${ENV_FILE}" "${credential}")"
      export "${credential}=${value}"
    fi
  done
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

DRY_RUN=false
for arg in "${ARGS[@]}"; do
  if [ "${arg}" = "--dry-run" ]; then
    DRY_RUN=true
  fi
done

# Check GitHub access before the irreversible Publisher write. This prevents a
# release from reaching the stable update channel while the repository sidebar
# remains pinned to an older GitHub Release.
if [ "${DRY_RUN}" = false ]; then
  if ! command -v gh >/dev/null 2>&1 || ! gh auth status >/dev/null 2>&1; then
    printf '[webblocks-publish-update] GitHub CLI authentication is required before publishing. Run gh auth login.\n' >&2
    exit 1
  fi
fi

"${PHP_BIN}" vendor/bin/testbench webblocks:publish-update "${ARGS[@]}"

if [ "${DRY_RUN}" = false ]; then
  "${ROOT_DIR}/scripts/release/publish-github.sh"
fi
