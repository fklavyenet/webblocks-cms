<?php

namespace WebBlocks\Cms\Tests\Support;

/** Opt-in local timings; records identifiers and durations, never configuration. */
final class TestLifecycleProfile
{
  public static function measure(string $test, string $phase, callable $work, array $context = []): mixed
  {
    $path = getenv('WEBBLOCKS_CMS_TEST_PROFILE');
    if (! is_string($path) || $path === '') {
      return $work();
    }
    $start = hrtime(true);
    try {
      return $work();
    } finally {
      self::record($test, $phase, $start, $context);
    }
  }

  public static function record(string $test, string $phase, int $start, array $context = []): void
  {
    $path = getenv('WEBBLOCKS_CMS_TEST_PROFILE');
    if (is_string($path) && $path !== '') {
      file_put_contents($path, json_encode(['test' => $test, 'phase' => $phase, 'process_id' => getmypid(),
        'milliseconds' => (hrtime(true) - $start) / 1e6, ...$context], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    }
  }
}
