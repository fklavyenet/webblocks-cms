<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Tests\Support\HtmlStructureAssertions;

abstract class TestCase extends BaseTestCase
{
  use HtmlStructureAssertions;

  protected array $trackedCreatedPublicSitePaths = [];

  protected function tearDown(): void
  {
    $this->cleanupTrackedPublicSitePaths();
    $this->restoreTestDatabaseEnvironment();

    parent::tearDown();
  }

  /**
   * Restore the suite's canonical file-based SQLite database.
   *
   * Some flows under test (the install wizard in particular) reconfigure the
   * live database connection and call putenv('DB_DATABASE=...'), which would
   * otherwise leak into every following test and cause "no such table"
   * cascades. Resetting the environment here keeps each test isolated.
   */
  private function restoreTestDatabaseEnvironment(): void
  {
    if (! defined('WEBBLOCKS_TEST_DATABASE')) {
      return;
    }

    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => WEBBLOCKS_TEST_DATABASE] as $key => $value) {
      putenv($key.'='.$value);
      $_ENV[$key] = $value;
      $_SERVER[$key] = $value;
    }
  }

  protected function putTrackedPublicSiteFile(string $relativePath, string $contents): string
  {
    $relativePath = ltrim($relativePath, '/');
    $path = public_path($relativePath);

    if (! str_starts_with($path, public_path('site').DIRECTORY_SEPARATOR)) {
      throw new \InvalidArgumentException('Tracked public site file must stay under public/site.');
    }

    $this->trackCreatedPublicSitePath($path);

    $directory = dirname($path);

    if (! is_dir($directory)) {
      File::ensureDirectoryExists($directory);
    }

    File::put($path, $contents);

    return $path;
  }

  private function trackCreatedPublicSitePath(string $path): void
  {
    $current = $path;

    while (str_starts_with($current, public_path('site').DIRECTORY_SEPARATOR)) {
      if (! file_exists($current)) {
        $this->trackedCreatedPublicSitePaths[$current] = true;
      }

      $parent = dirname($current);

      if ($parent === $current || $parent === public_path('site')) {
        break;
      }

      $current = $parent;
    }
  }

  private function cleanupTrackedPublicSitePaths(): void
  {
    $paths = array_keys($this->trackedCreatedPublicSitePaths);
    usort($paths, fn (string $left, string $right) => strlen($right) <=> strlen($left));

    foreach ($paths as $path) {
      if (is_file($path)) {
        @unlink($path);

        continue;
      }

      if (is_dir($path) && $this->isEmptyDirectory($path)) {
        @rmdir($path);
      }
    }

    $this->trackedCreatedPublicSitePaths = [];
  }

  private function isEmptyDirectory(string $path): bool
  {
    $entries = @scandir($path);

    return is_array($entries) && count(array_diff($entries, ['.', '..'])) === 0;
  }
}
