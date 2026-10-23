<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;

class MaintenanceCleanupCommand extends Command
{
  protected $signature = 'system:cleanup {category? : asset-revisions, media-variants, temporary-workspaces, page-revisions, or shared-slot-revisions} {--dry-run : Preview without deleting} {--scheduled : Run only when this category has automatic maintenance enabled}';

  protected $description = 'Preview or run safe WebBlocks CMS maintenance cleanup categories';

  public function handle(MaintenanceCleanup $cleanup): int
  {
    $category = (string) ($this->argument('category') ?: '');
    if ($this->option('scheduled') && $category === '') {
      $this->error(__('webblocks-cms::storage.category_required'));

      return self::INVALID;
    }
    if ($category === '') {
      $failed = false;
      foreach ($cleanup->overview() as $name => $result) {
        if (is_object($result)) {
          $failed = $failed || $result->failures !== [];
          $this->line($name.': candidates='.$result->candidateCount.' bytes='.$result->candidateBytes);
        } else {
          $this->line($name.': retained='.(is_array($result) ? json_encode($result, JSON_THROW_ON_ERROR) : $result));
        }
      }

      return $failed ? self::FAILURE : self::SUCCESS;
    }
    if (! in_array($category, MaintenanceCleanup::RUNNABLE, true)) {
      $this->error('Unsupported cleanup category.');

      return self::INVALID;
    }

    if ($this->option('scheduled') && ! (app(SystemSettings::class)->automaticMaintenanceSettings()[$category] ?? false)) {
      $this->line(__('webblocks-cms::storage.automatic_disabled'));

      return self::SUCCESS;
    }
    try {
      $result = $this->option('dry-run') ? $cleanup->preview($category)
        : ($this->option('scheduled') ? $cleanup->run($category, 'scheduled') : $cleanup->run($category));
    } catch (\Throwable) {
      $this->error(__('webblocks-cms::storage.execution_failed'));

      return self::FAILURE;
    }
    $this->line('candidates='.$result->candidateCount.' bytes='.$result->candidateBytes.' deleted='.$result->deletedCount.' freed='.$result->deletedBytes.' failures='.count($result->failures));

    return count($result->failures) > 0 ? self::FAILURE : self::SUCCESS;
  }
}
