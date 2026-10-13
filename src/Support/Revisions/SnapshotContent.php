<?php

namespace WebBlocks\Cms\Support\Revisions;

final class SnapshotContent
{
  public static function hash(array $snapshot): string
  {
    unset($snapshot['captured_at']);

    return hash('sha256', json_encode(self::canonical($snapshot), JSON_THROW_ON_ERROR));
  }

  private static function canonical(array $value): array
  {
    if (! array_is_list($value)) {
      ksort($value);
    }
    foreach ($value as $key => &$item) {
      if (in_array($key, ['settings', 'meta'], true) && is_string($item)) {
        $decoded = json_decode($item, true);
        if (is_array($decoded)) {
          $item = $decoded;
        }
      }
      if (is_array($item)) {
        $item = self::canonical($item);
      }
    }

    return $value;
  }
}
