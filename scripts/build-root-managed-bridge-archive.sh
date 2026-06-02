#!/usr/bin/env bash
set -euo pipefail

version="${1:-}"
output_dir="${2:-dist}"
ref="${3:-HEAD}"

if [ -z "$version" ]; then
  printf 'Usage: %s VERSION [OUTPUT_DIR] [GIT_REF]\n' "$0" >&2
  exit 1
fi

# Retired historical bridge helper. Keep for deliberate manual recovery audits
# only; package-native release gates validate package-rooted artifacts instead.
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

cat > "$staging_dir/app/Support/System/Updates/PackageUpdaterBridgeBootstrap.php" <<'PHP'
<?php

namespace WebBlocksCmsRootManagedBridge;

function require_update_class(string $class): void
{
  static $registered = false;

  if (! $registered) {
    spl_autoload_register(static function (string $className): void {
      $prefix = 'WebBlocks\\Cms\\Support\\System\\Updates\\';

      if (! str_starts_with($className, $prefix)) {
        return;
      }

      $relativeClass = substr($className, strlen($prefix));
      $path = __DIR__.'/../../../../packages/webblocks-cms/src/Support/System/Updates/'.str_replace('\\', '/', $relativeClass).'.php';

      if (is_file($path)) {
        require_once $path;
      }
    });

    $registered = true;
  }

  $fqcn = 'WebBlocks\\Cms\\Support\\System\\Updates\\'.$class;

  if (class_exists($fqcn, false)) {
    return;
  }

  $path = __DIR__.'/../../../../packages/webblocks-cms/src/Support/System/Updates/'.$class.'.php';

  if (is_file($path)) {
    require_once $path;
  }
}
PHP

for wrapper in "$staging_dir"/app/Support/System/Updates/*.php; do
  wrapper_name="$(basename "$wrapper" .php)"

  if [ "$wrapper_name" = "PackageUpdaterBridgeBootstrap" ]; then
    continue
  fi

  perl -0pi -e "s/(namespace App\\\\Support\\\\System\\\\Updates;\\n)/\$1\\nrequire_once __DIR__.'\\/PackageUpdaterBridgeBootstrap.php';\\n\\\\WebBlocksCmsRootManagedBridge\\\\require_update_class('$wrapper_name');\\n/" "$wrapper"
done

rm -rf \
  "$staging_dir/.git" \
  "$staging_dir/.github" \
  "$staging_dir/dist" \
  "$staging_dir/storage" \
  "$staging_dir/vendor" \
  "$staging_dir/project" \
  "$staging_dir/bootstrap/cache" \
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
