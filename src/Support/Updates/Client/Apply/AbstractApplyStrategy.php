<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Apply;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/**
 * Shared helpers for the apply strategies. Concrete strategies implement the one
 * step that legitimately differs between product types (§7.1): where the staged,
 * already-verified release files land on disk.
 */
abstract class AbstractApplyStrategy implements ApplyStrategy
{
  /**
   * Reject a staged tree that tries to escape its root via traversal or an
   * absolute path.
   */
  protected function assertSafeStagedContents(string $stagedRoot): void
  {
    foreach (File::allFiles($stagedRoot, true) as $file) {
      $relativePath = trim(str_replace('\\', '/', str_replace($stagedRoot, '', $file->getPathname())), '/');

      if ($relativePath === '') {
        continue;
      }

      if (preg_match('/(^|\/)\.\.(\/|$)/', $relativePath) === 1 || str_starts_with($relativePath, '/')) {
        throw new UpdateException(
          'The downloaded update package contains invalid paths.',
          'Refusing to apply staged file outside the target boundary: '.$relativePath,
        );
      }
    }
  }

  protected function relativePath(string $targetPath, string $path): string
  {
    $normalizedTargetPath = rtrim(str_replace('\\', '/', $targetPath), '/').'/';
    $normalizedPath = str_replace('\\', '/', $path);

    if (str_starts_with($normalizedPath, $normalizedTargetPath)) {
      return substr($normalizedPath, strlen($normalizedTargetPath));
    }

    return $path;
  }

  /**
   * Read the `name` field from a directory's composer.json, or null.
   */
  protected function composerName(string $root): ?string
  {
    $composerPath = rtrim($root, '/\\').DIRECTORY_SEPARATOR.'composer.json';

    if (! File::isFile($composerPath)) {
      return null;
    }

    try {
      $composer = json_decode((string) File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      return null;
    }

    $name = is_array($composer) ? ($composer['name'] ?? null) : null;

    return is_string($name) && $name !== '' ? $name : null;
  }
}
