<?php

namespace WebBlocks\Cms\Support\Media;

final class GalleryLayout
{
  public const VARIANTS = ['grid', 'weighted', 'equal-height', 'physical-scale', 'masonry', 'collage'];

  public const DIMENSIONS = ['physical_width_um', 'physical_height_um'];

  public const UNITS = ['mm' => 1000, 'cm' => 10000, 'in' => 25400];

  public const MAX_DIMENSION = 1000000000;

  public const ITEMS_PER_PHYSICAL_ROW = 3;

  public const MIN_WEIGHT = 0.01;

  public const MAX_WEIGHT = 1000;

  public static function dimensions(array $item): array
  {
    $pair = array_intersect_key($item, array_flip(self::DIMENSIONS));

    return array_map(static function ($value): ?int {
      if (! is_int($value) && ! is_string($value)) {
        return null;
      }

      return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => self::MAX_DIMENSION],
      ]) ?: null;
    }, $pair);
  }

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
