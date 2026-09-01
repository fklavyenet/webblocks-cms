<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Apply;

use Illuminate\Support\Facades\File;

/**
 * Finds files an install still carries that the release no longer ships.
 *
 * Full-root apply is an OVERLAY (§7.1): it writes what the release contains and
 * never deletes anything else, so user-created files survive. The cost is that a
 * file DROPPED from the product stays on every install forever, and one class of
 * leftover is fatal — Laravel `require`s every file in `config/`, so a stale
 * config that references a class a later release deleted takes the app down
 * before it boots, with an empty 500 and a dead `artisan`.
 *
 * This scanner does not delete. Deleting on the basis of "absent from the
 * release" would be the same rule that makes mirroring unsafe: a directory the
 * product legitimately shares with the operator would be emptied. It reports,
 * the run is marked success-with-warnings, and a human decides.
 *
 * Scope is deliberately narrow — the configured scan paths, `config/` by
 * default — because that is where absence is load-bearing rather than merely
 * untidy.
 */
class OrphanScanner
{
  /**
   * @param  list<string>  $scanPaths  release-owned roots to compare, relative to the target
   * @param  list<string>  $preserve  paths the apply never writes, so their absence means nothing
   */
  public function __construct(
    private readonly array $scanPaths,
    private readonly array $preserve = [],
  ) {}

  /**
   * @param  list<string>  $preserve
   */
  public static function fromConfig(array $preserve = []): self
  {
    $configured = config('publisher-client.apply.orphan_scan_paths', ['config']);

    $scanPaths = is_array($configured)
      ? collect($configured)
        ->filter(fn ($value): bool => is_string($value) && $value !== '')
        ->map(fn (string $value): string => trim(str_replace('\\', '/', $value), '/'))
        ->filter()
        ->unique()
        ->values()
        ->all()
      : [];

    return new self($scanPaths, $preserve);
  }

  /**
   * Paths present under the target's scan roots but absent from the staged
   * release, relative to the target and sorted for a stable log line.
   *
   * @return list<string>
   */
  public function scan(string $target, string $stagedRoot): array
  {
    if ($this->scanPaths === []) {
      return [];
    }

    $target = rtrim(str_replace('\\', '/', $target), '/');
    $stagedRoot = rtrim(str_replace('\\', '/', $stagedRoot), '/');
    $orphans = [];

    foreach ($this->scanPaths as $scanPath) {
      $targetDir = $target.'/'.$scanPath;

      // A scan root the install does not have cannot hold leftovers. A scan
      // root the RELEASE does not have is skipped too: the product no longer
      // owns that directory, so everything in it would be reported at once.
      if (! File::isDirectory($targetDir) || ! File::isDirectory($stagedRoot.'/'.$scanPath)) {
        continue;
      }

      foreach (File::allFiles($targetDir, true) as $file) {
        $relativePath = $scanPath.'/'.trim(str_replace('\\', '/', str_replace($targetDir, '', $file->getPathname())), '/');

        if ($this->isPreserved($relativePath) || File::exists($stagedRoot.'/'.$relativePath)) {
          continue;
        }

        $orphans[] = $relativePath;
      }
    }

    sort($orphans);

    return $orphans;
  }

  private function isPreserved(string $relativePath): bool
  {
    foreach ($this->preserve as $entry) {
      if ($relativePath === $entry || str_starts_with($relativePath, $entry.'/')) {
        return true;
      }
    }

    return false;
  }
}
