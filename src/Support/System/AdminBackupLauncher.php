<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Process;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;

class AdminBackupLauncher
{
  public function launch(int $id, string $filename): void
  {
    if (PHP_OS_FAMILY === 'Windows' || ! is_readable(base_path('artisan'))) {
      throw new BackupOperationException(__('webblocks-cms::backup-retention.worker_unavailable'));
    }

    $php = app(UpdateCommandRunner::class)->phpBinary();
    $command = [$php, '-d', 'max_execution_time=0', 'artisan', 'system:backups:work', (string) $id, $filename];
    $shell = 'command -v nohup >/dev/null && command -v '.escapeshellarg($php).' >/dev/null || exit 1; '
      .'nohup '.implode(' ', array_map('escapeshellarg', $command)).' >/dev/null 2>&1 </dev/null &';
    if (! Process::path(base_path())->timeout(5)->run(['sh', '-c', $shell])->successful()) {
      throw new BackupOperationException(__('webblocks-cms::backup-retention.worker_unavailable'));
    }
  }
}
