<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Str;

final class ExtensionListSearch
{
  public static function term(mixed $input): string
  {
    return is_string($input) ? mb_substr(trim($input), 0, 120) : '';
  }

  public static function matches(string $search, string ...$values): bool
  {
    return $search === '' || array_any($values, fn (string $value): bool => Str::contains($value, $search, ignoreCase: true));
  }
}
