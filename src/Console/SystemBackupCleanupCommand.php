<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;

class SystemBackupCleanupCommand extends Command
{
  protected $signature = 'system:backups:cleanup {--dry-run : Preview eligible backups without deleting them} {--force : Run even when automatic cleanup is disabled}';

  protected $description = 'Preview or apply the configured WebBlocks CMS automatic backup cleanup policy';

  public function handle(SystemBackupCleanup $cleanup): int
  {
    $dryRun = (bool) $this->option('dry-run');
    $result = $dryRun ? $cleanup->preview() : $cleanup->run((bool) $this->option('force'));
    $count = $dryRun ? $result->candidateCount() : $result->deletedCount();
    $bytes = $dryRun ? $result->candidateBytes : $result->deletedBytes;

    $this->info(($dryRun ? 'Would remove ' : 'Removed ').$count.' backup(s), '.number_format($bytes).' byte(s).');

    return $result->failures === [] ? self::SUCCESS : self::FAILURE;
  }
}
