<?php

namespace WebBlocks\Cms\Support\System;

use Closure;

final class BackupTiming
{
  public static function measure(array &$output, string $stage, Closure $operation): mixed
  {
    $startedAt = hrtime(true);

    try {
      return $operation();
    } finally {
      self::record($output, $stage, $startedAt);
    }
  }

  public static function record(array &$output, string $stage, int|float $startedAt): void
  {
    $output[] = $stage.': '.(int) round((hrtime(true) - $startedAt) / 1_000_000).' ms';
  }
}
