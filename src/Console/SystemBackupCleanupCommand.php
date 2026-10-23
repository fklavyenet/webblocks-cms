<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;

class SystemBackupCleanupCommand extends Command
{
  protected $signature = 'system:backups:cleanup {--dry-run : Preview eligible backups without deleting them} {--force : Run even when automatic cleanup is disabled} {--scheduled : Mark an automatic scheduler invocation}';

  protected $description = 'Preview or apply the configured WebBlocks CMS automatic backup cleanup policy';

  public function handle(SystemBackupCleanup $cleanup): int
  {
    $dryRun = (bool) $this->option('dry-run');
    $result = $dryRun ? $cleanup->preview() : $cleanup->run((bool) $this->option('force'), source: $this->option('scheduled') ? 'scheduled' : 'manual');
    $count = $dryRun ? $result->candidateCount() : $result->deletedCount();
    $bytes = $dryRun ? $result->candidateBytes : $result->deletedBytes;

    $this->info(($dryRun ? 'Would remove ' : 'Removed ').$count.' backup(s), '.number_format($bytes).' byte(s).');

    if ($dryRun && $this->output->isVerbose()) {
      foreach ($result->overview['items'] ?? [] as $item) {
        $this->line('#'.$item['id'].': '.__('webblocks-cms::backup-retention.reason_'.$item['reason']));
      }
    }
    if (isset($result->overview['storage'])) {
      $storage = $result->overview['storage'];
      $this->line(__('webblocks-cms::backup-retention.budget_'.$storage['budget_status']));
      foreach (['total_bytes', 'protected_bytes', 'eligible_bytes', 'after_cleanup_bytes'] as $metric) {
        $this->line(__('webblocks-cms::backup-retention.'.$metric).': '.(in_array($storage['status'], ['available', 'missing'], true) ? number_format($storage[$metric]).' B' : '—'));
      }
    }

    if ($result->failures !== []) {
      $this->error(__('webblocks-cms::storage.execution_result', ['count' => $result->deletedCount(), 'bytes' => number_format($result->deletedBytes), 'failures' => count($result->failures)]));
    }

    return $result->failures === [] ? self::SUCCESS : self::FAILURE;
  }
}
