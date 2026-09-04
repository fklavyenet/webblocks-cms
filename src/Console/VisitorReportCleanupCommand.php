<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Visitors\VisitorReportRetention;

class VisitorReportCleanupCommand extends Command
{
  protected $signature = 'visitors:cleanup {--dry-run : Preview without changing records}';

  protected $description = 'Roll up expired visitor records and apply visitor report retention';

  public function handle(VisitorReportRetention $retention): int
  {
    $policy = $retention->policy();
    if (! $policy['ready']) {
      $this->warn(__('webblocks-cms::visitor_insights.cleanup_not_ready'));

      return self::FAILURE;
    }
    if (! $policy['enabled']) {
      $this->info(__('webblocks-cms::visitor_insights.cleanup_disabled'));

      return self::SUCCESS;
    }
    $result = $retention->run((bool) $this->option('dry-run'));
    $this->info(__('webblocks-cms::visitor_insights.cleanup_result', $result));

    return self::SUCCESS;
  }
}
