<?php

namespace WebBlocks\Cms\Support\Media;

final class GalleryLayout
{
  public const VARIANTS = ['grid', 'weighted', 'equal-height', 'masonry', 'collage'];

  public const MIN_WEIGHT = 0.01;

  public const MAX_WEIGHT = 1000;

  public static function variant(mixed $value): string
  {
    return match (true) {
      $value === 'masonary' => 'masonry',
      in_array($value, self::VARIANTS, true) => $value,
      default => 'grid',
    };
  }

  public static function validWeights(mixed $value): bool
  {
    return is_array($value) && array_is_list($value) && count($value) <= 5
      && array_all($value, fn ($weight) => is_numeric($weight) && $weight >= self::MIN_WEIGHT && $weight <= self::MAX_WEIGHT);
  }

  public static function weights(mixed $value, int $columns): array
  {
    $weights = self::validWeights($value) ? array_map(floatval(...), $value) : [];

    return array_pad(array_slice($weights, 0, $columns), $columns, 1);
  }
}
