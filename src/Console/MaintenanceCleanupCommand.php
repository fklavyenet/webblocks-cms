<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;

class MaintenanceCleanupCommand extends Command
{
  protected $signature = 'system:cleanup {category? : asset-revisions, media-variants, or temporary-workspaces} {--dry-run : Preview without deleting}';

  protected $description = 'Preview or run safe WebBlocks CMS maintenance cleanup categories';

  public function handle(MaintenanceCleanup $cleanup): int
  {
    $category = (string) ($this->argument('category') ?: '');
    if ($category === '') {
      foreach ($cleanup->overview() as $name => $result) {
        if (is_object($result)) {
          $this->line($name.': candidates='.$result->candidateCount.' bytes='.$result->candidateBytes);
        } else {
          $this->line($name.': retained='.$result);
        }
      }

      return self::SUCCESS;
    }
    if (! in_array($category, MaintenanceCleanup::RUNNABLE, true)) {
      $this->error('Unsupported cleanup category.');

      return self::INVALID;
    }

    $result = $this->option('dry-run') ? $cleanup->overview()[str_replace('-', '_', $category)] : $cleanup->run($category);
    $this->line('candidates='.$result->candidateCount.' bytes='.$result->candidateBytes.' deleted='.$result->deletedCount.' freed='.$result->deletedBytes.' failures='.count($result->failures));

    return count($result->failures) > 0 ? self::FAILURE : self::SUCCESS;
  }
}
