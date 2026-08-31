#!/usr/bin/env bash

# Backward-compatible alias for the complete WebBlocks UI runtime vendor step.
#
# The icon catalog is seeded from this file at install and by the catalog
# repair a System Update runs, so it has to travel with the package rather
# than be fetched by the installing server. It cannot live only in the release
# artifact either: `composer require fklavyenet/webblocks-cms` installs from
# the GitHub tag, not from that zip, so the file has to be committed.
#
# Run after bumping UI_VERSION, then commit the result:
#   composer icons:vendor

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

exec "${ROOT_DIR}/scripts/release/vendor-ui.sh" "$@"
