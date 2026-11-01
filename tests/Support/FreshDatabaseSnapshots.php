<?php

namespace WebBlocks\Cms\Tests\Support;

/** Bounded process-local immutable fixtures; each restore still owns a new PDO. */
final class FreshDatabaseSnapshots
{
  private static array $snapshots = [];

  private static ?string $sourceFingerprint = null;

  public static function sourceFingerprint(): string
  {
    if (self::$sourceFingerprint === null) {
      $root = dirname(__DIR__, 2);
      $files = glob($root.'/database/migrations/fresh/*.php');
      // Migration helpers and provider code are part of the schema input too.
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/src', \FilesystemIterator::SKIP_DOTS));
      foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
          $files[] = $file->getPathname();
        }
      }
      $files[] = $root.'/composer.lock';
      sort($files);
      $hash = hash_init('sha256');
      foreach ($files as $file) {
        if (is_file($file)) {
          hash_update($hash, substr($file, strlen($root)).hash_file('sha256', $file));
        }
      }
      self::$sourceFingerprint = hash_final($hash);
    }

    return self::$sourceFingerprint;
  }

  public static function key(array $configuration, array $providers, string $source, ?string $boundary = null): string
  {
    return hash('sha256', serialize([$configuration, $providers, $source, $boundary]));
  }

  public static function get(string $key): ?SqliteDatabaseSnapshot
  {
    $snapshot = self::$snapshots[$key] ?? null;
    if ($snapshot !== null) {
      unset(self::$snapshots[$key]);
      self::$snapshots[$key] = $snapshot;
    }

    return $snapshot;
  }

  public static function put(string $key, SqliteDatabaseSnapshot $snapshot): void
  {
    unset(self::$snapshots[$key]);
    self::$snapshots[$key] = $snapshot;
    while (count(self::$snapshots) > 32) {
      array_shift(self::$snapshots);
    }
  }

  public static function forget(string $key): void
  {
    unset(self::$snapshots[$key]);
  }
}
