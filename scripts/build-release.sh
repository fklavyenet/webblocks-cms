#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 2 ]; then
    printf 'Usage: %s <version> <output-dir>\n' "$0" >&2
    exit 1
fi

version="$1"
output_dir="$2"
archive_name="webblocks-cms-${version}.zip"
archive_path="${output_dir}/${archive_name}"
manifest_file="$(mktemp)"

cleanup() {
    rm -f "$manifest_file"
}

trap cleanup EXIT

mkdir -p "$output_dir"
rm -f "$archive_path"

while IFS= read -r path; do
    case "$path" in
        .github/*|.ddev/*|node_modules/*|vendor/*|dist/*|storage/logs/*|storage/app/backups/*|storage/app/temp/*|storage/app/test-releases/*|storage/app/test-release-command/*|storage/app/release-validation/*|storage/framework/cache/*|storage/framework/sessions/*|storage/framework/views/*|storage/framework/testing/*|storage/app/public/assets/*|storage/app/public/media/*|storage/app/public/releases/*|storage/app/releases/*|bootstrap/cache/*|public/build/*|public/hot|public/storage/*|release-notes.md|.env|.env.*|.phpunit.result.cache|*.log|.DS_Store|*/.DS_Store)
            continue
            ;;
    esac

    printf '%s\n' "$path" >> "$manifest_file"
done < <(git ls-files --cached --others --exclude-standard)

if [ ! -s "$manifest_file" ]; then
    printf 'No files selected for release archive.\n' >&2
    exit 1
fi

zip -q "$archive_path" -@ < "$manifest_file"

shasum -a 256 "$archive_path" | cut -d' ' -f1 > "${archive_path}.sha256"

printf 'archive=%s\n' "$archive_path"
printf 'checksum_file=%s\n' "${archive_path}.sha256"
