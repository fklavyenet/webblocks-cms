<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Apply;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/**
 * Full-root apply (§7.1): overlays the staged release over the application root,
 * skipping the configured preserve list. Used by standalone Laravel apps.
 *
 * This is an OVERLAY copy, not a mirror: files present at the target but absent
 * from the release are left in place (never deleted), so user-created files are
 * safe even inside otherwise-updated directories. Combined with the preserve
 * list (.env, storage, public/storage, vendor, …), user data is shielded (§7.2).
 * A mandatory pre-update backup (orchestrator) covers rollback.
 *
 * The flip side of never deleting is that a file the product DROPS lives on
 * forever on every install, and under config/ that is eventually fatal. So the
 * apply reports what the release no longer ships (OrphanScanner) as a run
 * warning; it still deletes nothing.
 */
class FullRootApplyStrategy extends AbstractApplyStrategy
{
  public function name(): string
  {
    return 'full-root';
  }

  public function targetRoot(): string
  {
    $configured = trim((string) config('publisher-client.apply.target_path', ''));

    return $configured !== '' ? rtrim($configured, '/\\') : rtrim(base_path(), '/\\');
  }

  public function apply(string $stagedRoot): array
  {
    $target = $this->targetRoot();

    if (! File::isDirectory($stagedRoot)) {
      throw new UpdateException('The downloaded update package could not be applied.', 'Staged package root is missing: '.$stagedRoot);
    }

    if (! File::isDirectory($target)) {
      throw new UpdateException('The application root configured for updates does not exist.', 'Missing update target path: '.$target);
    }

    $this->assertSafeStagedContents($stagedRoot);

    $preserve = $this->preservePaths();
    $copied = 0;
    $skipped = 0;

    foreach (File::allFiles($stagedRoot, true) as $file) {
      $relativePath = trim(str_replace('\\', '/', str_replace($stagedRoot, '', $file->getPathname())), '/');

      if ($relativePath === '') {
        continue;
      }

      if ($this->isPreserved($relativePath, $preserve)) {
        $skipped++;

        continue;
      }

      $destination = rtrim($target, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

      $this->ensureDirectoryWithMode(dirname($destination));

      if (! File::copy($file->getPathname(), $destination)) {
        throw new UpdateException(
          'The update could not write application files. Check file permissions and try again.',
          'Failed to copy release file to '.$destination.'.',
        );
      }

      $copied++;
    }

    $output = ['Overlaid '.$copied.' release files onto '.$target.' ('.$skipped.' preserved-path entries skipped).'];
    $orphans = OrphanScanner::fromConfig($preserve)->scan($target, $stagedRoot);

    return [
      'strategy' => $this->name(),
      'target' => $target,
      'copied' => $copied,
      'skipped' => $skipped,
      'orphans' => $orphans,
      'warnings' => $orphans === [] ? 0 : 1,
      'output' => array_merge($output, $this->describeOrphans($orphans)),
    ];
  }

  /**
   * One warning for the whole set, not one per file: the operator's decision is
   * "go look at these", and a long run log buries it.
   *
   * @param  list<string>  $orphans
   * @return list<string>
   */
  private function describeOrphans(array $orphans): array
  {
    if ($orphans === []) {
      return [];
    }

    $limit = 25;
    $shown = array_slice($orphans, 0, $limit);
    $remaining = count($orphans) - count($shown);

    $lines = [
      'WARNING: '.count($orphans).' file(s) under the scanned release-owned paths exist on this install but are not part of this release:',
    ];

    foreach ($shown as $orphan) {
      $lines[] = '  - '.$orphan;
    }

    if ($remaining > 0) {
      $lines[] = '  … and '.$remaining.' more.';
    }

    // The consequence, not just the fact: a leftover under config/ is loaded on
    // every boot, so it fails the app the day it references code a release drops.
    $lines[] = 'Nothing was deleted — the updater never removes files it did not ship. Files under config/ are loaded on every boot, so a leftover that references removed code will break the app. Review these and remove the ones the product no longer owns.';

    return $lines;
  }

  /**
   * Create missing directories with apply.directory_mode, chmod'ing each NEWLY
   * created level explicitly (mkdir's mode is clamped by umask, and macOS drops
   * setgid). Pre-existing directories are never touched: panel/group users
   * over setgid+ACL trees must keep write access to updater-created directories;
   * a bare 0755 can clamp the ACL mask and lock the panel out.
   */
  private function ensureDirectoryWithMode(string $directory): void
  {
    if (is_dir($directory)) {
      return;
    }

    $mode = (int) config('publisher-client.apply.directory_mode', 02775);

    $missing = [];
    $cursor = $directory;

    while ($cursor !== dirname($cursor) && ! is_dir($cursor)) {
      $missing[] = $cursor;
      $cursor = dirname($cursor);
    }

    File::makeDirectory($directory, $mode, true);

    foreach ($missing as $created) {
      @chmod($created, $mode);
    }
  }

  /**
   * @return list<string>
   */
  private function preservePaths(): array
  {
    $configured = config('publisher-client.apply.preserve_paths', []);

    if (! is_array($configured)) {
      return [];
    }

    return collect($configured)
      ->filter(fn ($value): bool => is_string($value) && $value !== '')
      ->map(fn (string $value): string => trim(str_replace('\\', '/', $value), '/'))
      ->filter()
      ->values()
      ->all();
  }

  /**
   * @param  list<string>  $preserve
   */
  private function isPreserved(string $relativePath, array $preserve): bool
  {
    foreach ($preserve as $entry) {
      if ($relativePath === $entry || str_starts_with($relativePath, $entry.'/')) {
        return true;
      }
    }

    return false;
  }
}
