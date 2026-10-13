<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;

class AdminUpdateLauncher
{
  public function launch(string $id): void
  {
    if (! Str::isUuid($id)) {
      throw new UpdateException('Invalid update operation.');
    }
    if (PHP_OS_FAMILY === 'Windows') {
      throw new UpdateException('Background updates require a supported CLI worker.', 'Detached update workers require a POSIX host.');
    }
    $directory = storage_path('logs/system-updates');
    File::ensureDirectoryExists($directory, 0700);
    $command = [app(UpdateCommandRunner::class)->phpBinary(), 'artisan', 'webblocks:updates:work', $id];
    // Only a generated UUID and trusted executable/path enter this shell line.
    $shell = 'nohup '.implode(' ', array_map('escapeshellarg', $command)).' > '.escapeshellarg($directory.'/'.$id.'.log').' 2>&1 < /dev/null &';
    if (! Process::path(base_path())->timeout(10)->run(['sh', '-c', $shell])->successful()) {
      throw new UpdateException('The update worker could not be started.', 'Detached update worker launcher failed.');
    }
  }
}
