<?php

namespace WebBlocks\Cms\Support\System;

use Closure;
use Illuminate\Support\Facades\File;

/** A process-held lease survives database restoration and releases on process exit. */
final class BackupOperations
{
  private bool $active = false;

  public function run(Closure $operation): mixed
  {
    if ($this->active) {
      return $operation(); // Restore creates its safety archive under this same lease.
    }
    $root = app(SystemBackupArchiveResolver::class)->archiveDisk()->path('');
    File::ensureDirectoryExists($root);
    $handle = @fopen(rtrim($root, DIRECTORY_SEPARATOR).'/.backup-operations.lock', 'c');
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
