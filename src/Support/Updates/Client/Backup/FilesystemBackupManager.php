<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;

/**
 * Default backup: snapshots the apply strategy's target before an update and
 * restores it on failure. Strategy-aware, because the two strategies have very
 * different safety envelopes:
 *
 *  - package: the target is one package directory — snapshot it whole; restore
 *    replaces the directory (delete + copy back).
 *  - full-root: the target is the application root. The snapshot EXCLUDES the
 *    preserve list (storage, vendor, .env, …) — those paths are never touched
 *    by the apply, and the backups root itself lives under storage, so a whole-
 *    root copy would recurse into its own destination. Restore is an overlay
 *    copy back (never deletes the root): it rewrites exactly the files the
 *    apply could have overwritten, and only those.
 */
class FilesystemBackupManager implements BackupManager
{
  public function __construct(
    private readonly ApplyStrategy $strategy,
    private readonly ?RunHeartbeat $heartbeat = null,
  ) {
  }

  public function create(string $label, ?int $userId = null): ?string
  {
    $source = $this->strategy->targetRoot();

    if (! File::isDirectory($source)) {
      return null;
    }

    $handle = $this->backupsRoot().'/'.now()->format('Ymd-His').'-'.Str::uuid();

    File::ensureDirectoryExists($handle);

    $copied = $this->strategy->name() === 'full-root'
      ? $this->copyExcluding($source, $handle, $this->fullRootExclusions())
      : File::copyDirectory($source, $handle);

    if (! $copied) {
      File::deleteDirectory($handle);

      return null;
    }

    File::put($handle.'.label', $label);

    $this->prune();

    return $handle;
  }

  public function restore(string $handle): bool
  {
    if (! File::isDirectory($handle)) {
      return false;
    }

    $target = $this->strategy->targetRoot();

    if ($this->strategy->name() === 'full-root') {
      // Overlay the snapshot back over the root. Never delete the root: the
      // snapshot holds exactly the files the apply could have overwritten, and
      // preserved paths (storage, .env, vendor, …) were never touched.
      return $this->copyExcluding($handle, $target, []);
    }

    File::ensureDirectoryExists(dirname($target));
    File::deleteDirectory($target);

    return File::copyDirectory($handle, $target);
  }

  /**
   * Copy $source into $dest, skipping relative paths matching $excluded (same
   * prefix semantics as the full-root apply's preserve list). Does not follow
   * directory symlinks.
   *
   * Streamed on purpose: the previous File::allFiles() call materialised the
   * ENTIRE tree (excluded subtrees included) as an SplFileInfo array before
   * copying, which can exhaust the web process memory limit on large roots.
   * Here excluded subtrees are pruned at descent time — vendor/
   * storage are never even walked — and files are visited one at a time, so
   * memory stays flat regardless of tree size.
   *
   * @param  list<string>  $excluded
   */
  private function copyExcluding(string $source, string $dest, array $excluded): bool
  {
    $sourceRoot = rtrim($source, '/\\');

    $files = new \RecursiveDirectoryIterator(
      $sourceRoot,
      \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO,
    );

    $filtered = new \RecursiveCallbackFilterIterator(
      $files,
      function (\SplFileInfo $file) use ($sourceRoot, $excluded): bool {
        $relative = $this->relativePath($sourceRoot, $file->getPathname());

        foreach ($excluded as $exclude) {
          if ($relative === $exclude || str_starts_with($relative, $exclude.'/')) {
            return false;
          }
        }

        return true;
      },
    );

    foreach (new \RecursiveIteratorIterator($filtered, \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      // Large roots take minutes to snapshot; keep the run-lock heartbeat
      // alive while the copy streams (beat() self-throttles).
      $this->heartbeat?->beat();

      if ($file->isDir()) {
        // A directory surfaced as a leaf (symlinked or unreadable — not
        // descended into). allFiles() skipped these too; keep parity.
        continue;
      }

      $relative = $this->relativePath($sourceRoot, $file->getPathname());

      if ($relative === '') {
        continue;
      }

      $targetPath = $dest.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

      File::ensureDirectoryExists(dirname($targetPath));

      if (! File::copy($file->getPathname(), $targetPath)) {
        return false;
      }
    }

    return true;
  }

  private function relativePath(string $root, string $path): string
  {
    return trim(str_replace('\\', '/', substr($path, strlen($root))), '/');
  }

  /**
   * What a full-root snapshot skips: the preserve list (never touched by the
   * apply) plus, defensively, `storage` — the backups root lives under it.
   *
   * @return list<string>
   */
  private function fullRootExclusions(): array
  {
    $configured = config('publisher-client.apply.preserve_paths', []);

    $paths = collect(is_array($configured) ? $configured : [])
      ->filter(fn ($value): bool => is_string($value) && $value !== '')
      ->map(fn (string $value): string => trim(str_replace('\\', '/', $value), '/'))
      ->push('storage')
      ->unique()
      ->values()
      ->all();

    return $paths;
  }

  private function prune(): void
  {
    $retention = max(1, (int) config('publisher-client.backup.retention', 3));

    $backups = collect(File::directories($this->backupsRoot()))
      ->sort()
      ->values();

    $backups->slice(0, max(0, $backups->count() - $retention))
      ->each(function (string $dir): void {
        File::deleteDirectory($dir);
        File::delete($dir.'.label');
      });
  }

  private function backupsRoot(): string
  {
    $root = storage_path(trim((string) config('publisher-client.apply.workspace_root', 'app/publisher-client'), '/').'/backups');

    File::ensureDirectoryExists($root);

    return $root;
  }
}
