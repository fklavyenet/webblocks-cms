#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
PRIMARY_REMOTE="${WEBBLOCKS_RELEASE_PRIMARY_REMOTE:-origin}"
BACKUP_REMOTE="${WEBBLOCKS_RELEASE_BACKUP_REMOTE:-backup}"
VERSION="$("${PHP_BIN}" -r '$source = file_get_contents($argv[1]); if (! preg_match("/VERSION = '\''([^'\'']+)'\''/", $source, $matches)) { fwrite(STDERR, "Unable to read WebBlocks CMS version.\n"); exit(1); } echo $matches[1];' "${ROOT_DIR}/src/Support/WebBlocks.php")"
TAG_NAME="v${VERSION}"

cd "${ROOT_DIR}"

BRANCH="$(git symbolic-ref --quiet --short HEAD || true)"

if [ -z "${BRANCH}" ]; then
  printf '[webblocks-release-push] Releases cannot be pushed from a detached HEAD.\n' >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  printf '[webblocks-release-push] Commit release changes before pushing.\n' >&2
  exit 1
fi

if ! git rev-parse -q --verify "refs/tags/${TAG_NAME}" >/dev/null; then
  printf '[webblocks-release-push] Missing local tag %s.\n' "${TAG_NAME}" >&2
  exit 1
fi

HEAD_COMMIT="$(git rev-parse HEAD)"
TAG_COMMIT="$(git rev-list -n1 "refs/tags/${TAG_NAME}")"

if [ "${TAG_COMMIT}" != "${HEAD_COMMIT}" ]; then
  printf '[webblocks-release-push] %s does not point at HEAD.\n' "${TAG_NAME}" >&2
  exit 1
fi

# The primary push is the single verification boundary: branch and tag travel
# in one command, so the repository pre-push hook runs exactly once.
git push "${PRIMARY_REMOTE}" \
  "refs/heads/${BRANCH}:refs/heads/${BRANCH}" \
  "refs/tags/${TAG_NAME}:refs/tags/${TAG_NAME}"

# The exact refs that passed the primary push gates are mirrored without
# rerunning the same hook. Remote SHA verification below guards the mirror.
git push --no-verify "${BACKUP_REMOTE}" \
  "refs/heads/${BRANCH}:refs/heads/${BRANCH}" \
  "refs/tags/${TAG_NAME}:refs/tags/${TAG_NAME}"

remote_commit() {
  local remote="$1"
  local ref="$2"

  git ls-remote "${remote}" "${ref}" | awk -v wanted="${ref}" '$2 == wanted { print $1 }'
}

remote_tag_commit() {
  local remote="$1"
  local peeled="refs/tags/${TAG_NAME}^{}"
  local output

  output="$(git ls-remote "${remote}" "refs/tags/${TAG_NAME}" "${peeled}")"
  printf '%s\n' "${output}" | awk -v wanted="${peeled}" '$2 == wanted { print $1 }'
}

for remote in "${PRIMARY_REMOTE}" "${BACKUP_REMOTE}"; do
  REMOTE_BRANCH_COMMIT="$(remote_commit "${remote}" "refs/heads/${BRANCH}")"
  REMOTE_TAG_COMMIT="$(remote_tag_commit "${remote}")"

  if [ "${REMOTE_BRANCH_COMMIT}" != "${HEAD_COMMIT}" ] || [ "${REMOTE_TAG_COMMIT}" != "${HEAD_COMMIT}" ]; then
    printf '[webblocks-release-push] %s did not receive the expected branch and annotated tag refs.\n' "${remote}" >&2
    exit 1
  fi
done

printf '[webblocks-release-push] %s and %s now point %s and %s at %s; pre-push gates ran once.\n' \
  "${PRIMARY_REMOTE}" "${BACKUP_REMOTE}" "${BRANCH}" "${TAG_NAME}" "${HEAD_COMMIT}"
