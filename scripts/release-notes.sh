#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
    printf 'Usage: %s <tag> [output-file]\n' "$0" >&2
    exit 1
fi

tag="$1"
output_file="${2:-}"
version="${tag#v}"

notes="$(git tag -l --format='%(contents)' "$tag")"

if [ -z "$notes" ]; then
    changelog_file='CHANGELOG.md'

    if [ -f "$changelog_file" ]; then
        notes="$(awk -v version="$version" '
            $0 ~ "^##[[:space:]]+\\[?" version "\\]?([[:space:]]|$)" {capture=1; next}
            capture && $0 ~ "^##[[:space:]]+" {exit}
            capture {print}
        ' "$changelog_file")"
    fi
fi

if [ -z "$notes" ]; then
    notes="Release ${tag} for WebBlocks CMS."
fi

if [ -n "$output_file" ]; then
    printf '%s\n' "$notes" > "$output_file"
else
    printf '%s\n' "$notes"
fi
