<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/** Only named CMS scratch runs are disposable; measured storage is broader. */
final class TemporaryWorkspaceCleanup
{
  public function __construct(private readonly SystemSettings $settings, private readonly BackupOperations $backups) {}

  public function preview(): MaintenanceCleanupResult
  {
    if ($this->active() || $this->backups->isBusy() || ! $this->updateLeaseAvailable()) {
      return new MaintenanceCleanupResult(0, 0);
    }
    $candidates = $this->candidates();

    return new MaintenanceCleanupResult(count($candidates), array_sum(array_column($candidates, 'bytes')));
  }

  public function run(): MaintenanceCleanupResult
  {
    if ($this->active() || $this->backups->isBusy()) {
      return new MaintenanceCleanupResult(0, 0);
    }
    $lease = Cache::lock($this->lockName(), 7200);
    if (! $lease->get()) {
      return new MaintenanceCleanupResult(0, 0);
    }
    $heartbeat = false;
    try {
      if ($this->active()) {
        return new MaintenanceCleanupResult(0, 0);
      }
      // The updater can take over an abandoned cache lease. Keep our real
      // maintenance lease live without changing update history or progress.
      $heartbeat = true;
      $this->keepAlive($lease);

      return $this->backups->run(fn () => $this->deleteCandidates($lease));
    } finally {
      try {
        if ($heartbeat && $lease->isOwnedByCurrentProcess()) {
          Cache::forget($this->heartbeatKey());
        }
      } finally {
        $lease->release();
      }
    }
  }

  private function deleteCandidates(Lock $lease): MaintenanceCleanupResult
  {
    if ($this->liveRecords()) {
      return new MaintenanceCleanupResult(0, 0);
    }
    $candidates = $this->candidates();
    $deleted = 0;
    $bytes = 0;
    $failures = [];
    foreach ($candidates as $candidate) {
      try {
        if (! $lease->isOwnedByCurrentProcess()) {
          $failures[] = ['code' => 'busy'];
          break;
        }
        $this->keepAlive($lease);
        // A preview, including one from this run, never authorizes a changed tree.
        $current = $this->inspect($candidate['path'], $candidate['root']);
        if ($current === null || $current['signature'] !== $candidate['signature'] || $this->liveRecords()) {
          continue;
        }
        $removed = $candidate['file'] ? $this->deleteFile($candidate['path']) : File::deleteDirectory($candidate['path']);
        clearstatcache(true, $candidate['path']);
        if ($removed && ! file_exists($candidate['path']) && ! is_link($candidate['path'])) {
          $deleted++;
          $bytes += $candidate['bytes'];
        } else {
          $failures[] = ['path' => basename($candidate['path']), 'code' => 'delete_failed'];
        }
      } catch (\Throwable) {
        $failures[] = ['path' => basename($candidate['path']), 'code' => 'delete_failed'];
        break;
      }
    }

    return new MaintenanceCleanupResult(count($candidates), array_sum(array_column($candidates, 'bytes')), $deleted, $bytes, $failures);
  }

  private function candidates(): array
  {
    $paths = [];
    foreach ($this->roots() as [$root, $pattern, $files]) {
      if (! $this->safeRoot($root)) {
        continue;
      }
      foreach (new \DirectoryIterator($root) as $entry) {
        if ($entry->isDot() || $entry->isLink() || ! preg_match($pattern, $entry->getFilename())
          || ($files ? ! $entry->isFile() : ! $entry->isDir())) {
          continue;
        }
        $path = $entry->getPathname();
        $measurement = $this->inspect($path, $root);
        if ($measurement !== null && (! $files || $this->fileAvailable($path))) {
          $paths[] = ['path' => $path, 'root' => $root, 'file' => $files, ...$measurement];
        }
      }
    }

    return $paths;
  }

  private function roots(): array
  {
    return [
      [storage_path(trim((string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'), '/')), '/^\d{8}-\d{6}-[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', false],
      [storage_path('app/temp/system-backups'), '/^\d{14}-[a-z0-9]{8}$/D', false],
      [storage_path('app/temp/system-backup-restores'), '/^\d{14}-[a-z0-9]{8}$/D', false],
      [storage_path('app/webblocks/tmp/catalog-installs'), '/^catalog-plugin-/', true],
    ];
  }

  private function safeRoot(string $root): bool
  {
    if (! is_dir($root) || is_link($root)) {
      return false;
    }
    $storage = realpath(storage_path());
    $resolved = realpath($root);
    $relative = substr($root, strlen(rtrim(storage_path(), DIRECTORY_SEPARATOR)));
    if ($storage === false || $resolved === false || $resolved !== $storage.$relative
      || ! str_starts_with($resolved, $storage.DIRECTORY_SEPARATOR)) {
      return false; // Includes symlinked ancestors and traversal outside storage.
    }
    foreach (['backups', 'public'] as $disk) {
      $protected = config('filesystems.disks.'.$disk.'.root');
      $protected = is_string($protected) ? realpath($protected) : false;
      if ($protected !== false && ($resolved === $protected || str_starts_with($resolved, $protected.DIRECTORY_SEPARATOR)
        || str_starts_with($protected, $resolved.DIRECTORY_SEPARATOR))) {
        return false;
      }
    }

    return true;
  }

  private function inspect(string $path, string $root): ?array
  {
    clearstatcache();
    if (! $this->safeRoot($root) || is_link($path) || ! file_exists($path) || dirname($path) !== $root) {
      return null;
    }
    $entries = [new \SplFileInfo($path)];
    if (is_dir($path)) {
      foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $entries[] = $entry;
      }
    }
    usort($entries, fn ($a, $b) => strcmp($a->getPathname(), $b->getPathname()));
    $cutoff = now()->subHours($this->settings->maintenanceCleanupSettings()['temporary_workspace_hours'])->getTimestamp();
    $bytes = 0;
    $hash = hash_init('sha256');
    foreach ($entries as $entry) {
      $stat = lstat($entry->getPathname());
      if ($entry->isLink() || (! $entry->isFile() && ! $entry->isDir()) || $stat['mtime'] >= $cutoff) {
        return null;
      }
      hash_update($hash, $entry->getPathname().':'.implode(':', array_intersect_key($stat, array_flip(['dev', 'ino', 'mode', 'size', 'mtime', 'ctime']))));
      if ($entry->isFile()) {
        $bytes += $stat['size'];
      }
    }

    return ['bytes' => $bytes, 'signature' => hash_final($hash)];
  }

  private function active(): bool
  {
    $beat = Cache::get($this->heartbeatKey());

    return ($beat !== null && (! is_numeric($beat) || (int) $beat >= now()->timestamp - $this->staleSeconds())) || $this->liveRecords();
  }

  private function liveRecords(): bool
  {
    $cutoff = now()->subSeconds($this->staleSeconds());
    if (Schema::hasTable('wbcms_system_update_runs') && DB::table('wbcms_system_update_runs')
      ->whereIn('status', ['running', 'pending'])->where(function ($query) use ($cutoff) {
        $query->where('started_at', '>=', $cutoff)->orWhere(function ($query) use ($cutoff) {
          $query->whereNull('started_at')->where(function ($query) use ($cutoff) {
            $query->where('created_at', '>=', $cutoff)->orWhereNull('created_at');
          });
        });
      })->exists()) {
      return true;
    }
    if (Schema::hasTable('wbcms_admin_update_operations') && DB::table('wbcms_admin_update_operations')
      ->where(function ($query) use ($cutoff) {
        $query->where('status', 'queued')->orWhere(function ($query) use ($cutoff) {
          $query->where('status', 'running')->where(function ($query) use ($cutoff) {
            $query->where('heartbeat_at', '>=', $cutoff)->orWhere('started_at', '>=', $cutoff);
          });
        });
      })->exists()) {
      return true;
    }
    foreach (['wbcms_system_backups', 'wbcms_system_backup_restores'] as $table) {
      if (Schema::hasTable($table) && DB::table($table)->whereIn('status', ['running', 'pending'])->exists()) {
        return true;
      }
    }

    return false;
  }

  private function updateLeaseAvailable(): bool
  {
    $lease = Cache::lock($this->lockName(), 1);
    try {
      return $lease->get();
    } finally {
      $lease->release();
    }
  }

  private function staleSeconds(): int
  {
    return max(60, (int) config('publisher-client.lock.stale_after_seconds', 600));
  }

  private function lockName(): string
  {
    return (string) config('webblocks-updates.installer.lock_name', 'system-updates:run');
  }

  private function heartbeatKey(): string
  {
    return $this->lockName().':heartbeat';
  }

  private function keepAlive(Lock $lease): void
  {
    $beat = time();
    if (! Cache::put($this->heartbeatKey(), $beat, 7200)) {
      throw new \RuntimeException('Maintenance update lease heartbeat could not be saved.');
    }
    $stored = Cache::get($this->heartbeatKey());
    if ((! is_int($stored) && (! is_string($stored) || ! ctype_digit($stored)))
      || (int) $stored !== $beat || ! $lease->isOwnedByCurrentProcess()) {
      throw new \RuntimeException('Maintenance update lease heartbeat could not be verified.');
    }
  }

  private function fileAvailable(string $path): bool
  {
    $handle = @fopen($path, 'r');
    if ($handle === false) {
      return false;
    }
    try {
      return flock($handle, LOCK_EX | LOCK_NB);
    } finally {
      fclose($handle);
    }
  }

  private function deleteFile(string $path): bool
  {
    $handle = @fopen($path, 'r');
    if ($handle === false) {
      return false;
    }
    try {
      return flock($handle, LOCK_EX | LOCK_NB) && File::delete($path);
    } finally {
      fclose($handle);
    }
  }
}
