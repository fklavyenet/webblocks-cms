<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Media;

final class MediaUploadRules
{
  public const SHORT_TEXT_LIMIT = 255;

  public const BOUNDED_COPY_LIMIT = 2000;

  public const METADATA_LIMITS = [
    'title' => self::SHORT_TEXT_LIMIT,
    'alt_text' => self::SHORT_TEXT_LIMIT,
    'caption' => self::BOUNDED_COPY_LIMIT,
    'description' => self::BOUNDED_COPY_LIMIT,
  ];

  public static function file(): array
  {
    return ['required', 'file', 'max:51200', 'mimetypes:'.MediaMimeTypes::rule()];
  }

  public static function metadata(?Media $media = null, array $input = []): array
  {
    $rules = [];
    foreach (self::METADATA_LIMITS as $field => $limit) {
      // A full editor form may resubmit legacy copy verbatim. Excluding it
      // from validated data also preserves its original whitespace and lines.
      $unchanged = $media && array_key_exists($field, $input) && self::sameText($input[$field], $media->{$field}, $limit);
      $rules[$field] = $unchanged ? ['exclude'] : ['nullable', 'string', 'max:'.$limit];
    }

    return $rules;
  }

  public static function normalizeMetadataPatch(array $input): array
  {
    $fields = array_intersect_key($input, self::METADATA_LIMITS);
    foreach ($fields as $field => $value) {
      $value = trim((string) $value);
      $fields[$field] = $value === '' ? null : $value;
    }

    return $fields;
  }

  private static function sameText(mixed $submitted, ?string $stored, int $limit): bool
  {
    if ($submitted === $stored) {
      return true;
    }

    if (! is_string($submitted) || $stored === null || mb_strlen($stored) <= $limit) {
      return false;
    }

    // HTML form submissions normalize line endings and the host may trim input.
    $normalize = fn (string $value): string => Str::trim(str_replace(["\r\n", "\r"], "\n", $value));

    return $normalize($submitted) === $normalize($stored);
  }
}
