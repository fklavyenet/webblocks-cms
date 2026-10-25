<?php

namespace WebBlocks\Cms\Support\System;

use Closure;
use Illuminate\Support\Facades\File;

/** A process-held lease survives database restoration and releases on process exit. */
final class BackupOperations
{
  public const LOCK_FILE = '.backup-operations.lock';

  private bool $active = false;

  /** Probe the process-held lease without creating a disk root or lock file. */
  public function isBusy(): bool
  {
    if ($this->active) {
      return true;
    }
    $root = (string) config('filesystems.disks.backups.root', storage_path('app/backups'));
    $path = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::LOCK_FILE;
    clearstatcache(true, $path);
    if (is_link($path)) {
      return true;
    }
    if (! file_exists($path)) {
      return false;
    }
    if (! is_file($path)) {
      return true;
    }
    $handle = @fopen($path, 'r');
    if ($handle === false) {
      return true;
    }
    try {
      return ! flock($handle, LOCK_EX | LOCK_NB);
    } finally {
      fclose($handle);
    }
  }

  public function run(Closure $operation): mixed
  {
    if ($this->active) {
      return $operation(); // Restore creates its safety archive under this same lease.
    }
    $root = app(SystemBackupArchiveResolver::class)->archiveDisk()->path('');
    File::ensureDirectoryExists($root);
    $handle = @fopen(rtrim($root, DIRECTORY_SEPARATOR).'/'.self::LOCK_FILE, 'c');
    if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
      if (is_resource($handle)) {
        fclose($handle);
      }
      throw new BackupOperationException(__('webblocks-cms::backup-retention.busy'));
    }
    $this->active = true;
    try {
      return $operation();
    } finally {
      $this->active = false;
      flock($handle, LOCK_UN);
      fclose($handle);
    }
  }
}
