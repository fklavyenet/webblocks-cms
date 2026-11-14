<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Support\System\BackupOperationException;
use WebBlocks\Cms\Support\System\SystemBackupManager;

class WorkSystemBackupCommand extends Command
{
  protected $signature = 'system:backups:work {backup} {filename}';

  protected $description = 'Run one admitted panel backup outside the HTTP request';

  public function handle(SystemBackupManager $manager): int
  {
    $id = (string) $this->argument('backup');
    $filename = (string) $this->argument('filename');
    if (PHP_SAPI !== 'cli' || ! ctype_digit($id)) {
      return self::FAILURE;
    }

    $deadline = hrtime(true) + 10_000_000_000;
    do {
      try {
        return $manager->runReservedManualBackup((int) $id, $filename) ? self::SUCCESS : self::FAILURE;
      } catch (BackupOperationException) {
        // Another operation can take the shared lease between admission and worker startup.
        usleep(250_000);
      } catch (Throwable $exception) {
        Log::warning('Backup worker did not complete.', ['backup_id' => (int) $id, 'exception' => $exception::class]);

        return self::FAILURE;
      }
    } while (hrtime(true) < $deadline);

    return self::FAILURE;
  }
}
