#!/usr/bin/env bash

set -euo pipefail

root="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$root"

declare -a candidates=()
declare -a php_files=()
declare -a php_like_files=()

add_candidate() {
  local path="$1"

  if [ -z "$path" ] || [ ! -f "$path" ]; then
    return
  fi

  if [ "${#candidates[@]}" -gt 0 ]; then
    local existing
    for existing in "${candidates[@]}"; do
      if [ "$existing" = "$path" ]; then
        return
      fi
    done
  fi

  candidates+=("$path")
}

collect_diff_paths() {
  local diff_ref="$1"

  while IFS= read -r -d '' path; do
    add_candidate "$path"
  done < <(git diff -z --name-only --diff-filter=ACMRT "$diff_ref")
}

if git rev-parse --verify --quiet origin/main >/dev/null; then
  collect_diff_paths "origin/main...HEAD"
elif git rev-parse --verify --quiet HEAD >/dev/null; then
  collect_diff_paths "HEAD"
fi

while IFS= read -r -d '' path; do
  add_candidate "$path"
done < <(git diff -z --cached --name-only --diff-filter=ACMRT)

while IFS= read -r -d '' path; do
  add_candidate "$path"
done < <(git diff -z --name-only --diff-filter=ACMRT)

if [ "${#candidates[@]}" -gt 0 ]; then
  for path in "${candidates[@]}"; do
    case "$path" in
      *.blade.php)
        php_like_files+=("$path")
        ;;
      *.php)
        php_files+=("$path")
        php_like_files+=("$path")
        ;;
    esac
  done
fi

if [ "${#php_like_files[@]}" -eq 0 ]; then
  printf 'No changed PHP/Blade files to format-check.\n'
  exit 0
fi

if [ "${#php_files[@]}" -gt 0 ]; then
  vendor/bin/pint --test "${php_files[@]}"
else
  printf 'No changed PHP files for Pint; skipping Pint.\n'
fi

php scripts/check-php-indentation.php "${php_like_files[@]}"
