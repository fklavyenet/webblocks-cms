#!/usr/bin/env bash
set -euo pipefail

version="${1:-}"
output_dir="${2:-dist}"
ref="${3:-HEAD}"

if [ -z "$version" ]; then
  printf 'Usage: %s VERSION [OUTPUT_DIR] [GIT_REF]\n' "$0" >&2
  exit 1
fi

archive_path="${output_dir}/webblocks-cms-${version}-root-managed-bridge.zip"
checksum_path="${archive_path}.sha256"
staging_dir="$(mktemp -d)"

case "$archive_path" in
  /*) archive_absolute_path="$archive_path" ;;
  *) archive_absolute_path="$PWD/$archive_path" ;;
esac

cleanup() {
  rm -rf "$staging_dir"
}

trap cleanup EXIT

mkdir -p "$output_dir"
rm -f "$archive_path" "$checksum_path"

git archive --format=tar --worktree-attributes "$ref" | tar -xf - -C "$staging_dir"

if [ ! -f "$staging_dir/artisan" ] || [ ! -f "$staging_dir/composer.json" ]; then
  printf 'Bridge archive source must contain artisan and composer.json at the repository root.\n' >&2
  exit 1
fi

if [ ! -f "$staging_dir/app/Support/System/Updates/UpdatePackageExtractor.php" ]; then
  printf 'Bridge archive source must contain the legacy App\\Support updater wrappers.\n' >&2
  exit 1
fi

if [ ! -f "$staging_dir/packages/webblocks-cms/src/Support/System/Updates/UpdatePackageExtractor.php" ]; then
  printf 'Bridge archive source must contain the package-native updater implementation.\n' >&2
  exit 1
fi

if ! grep -q 'fklavyenet/webblocks-cms' "$staging_dir/packages/webblocks-cms/src/Support/System/Updates/UpdatePackageExtractor.php"; then
  printf 'Bridge archive source must contain a package-rooted WebBlocks CMS updater validator.\n' >&2
  exit 1
fi

rm -rf \
  "$staging_dir/.git" \
  "$staging_dir/.github" \
  "$staging_dir/.ddev" \
  "$staging_dir/dist" \
  "$staging_dir/node_modules" \
  "$staging_dir/storage" \
  "$staging_dir/vendor" \
  "$staging_dir/project" \
  "$staging_dir/bootstrap/cache" \
  "$staging_dir/public/build" \
  "$staging_dir/public/hot" \
  "$staging_dir/public/site" \
  "$staging_dir/public/storage"

find "$staging_dir" -maxdepth 1 \( -name '.env' -o -name '.env.*' \) -exec rm -f {} +

# Root config files are install-owned overrides. Keep package config in
# packages/webblocks-cms/config, but do not overwrite root config on bridge.
rm -rf "$staging_dir/config"

(
  cd "$staging_dir"
  zip -qr "$archive_absolute_path" .
)

shasum -a 256 "$archive_path" | cut -d' ' -f1 > "$checksum_path"

printf 'Bridge archive: %s\n' "$archive_path"
printf 'Checksum: %s\n' "$(cat "$checksum_path")"
