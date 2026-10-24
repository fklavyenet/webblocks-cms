<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Throwable;
use WebBlocks\Cms\Support\System\SystemMaintenanceReports;

class RefreshSystemReportsCommand extends Command
{
  protected $signature = 'system:reports:refresh';

  protected $description = 'Refresh cached CMS maintenance and health reports outside panel requests';

  public function handle(SystemMaintenanceReports $reports): int
  {
    try {
      $reports->refresh();

      return self::SUCCESS;
    } catch (Throwable) {
      $this->error(__('webblocks-cms::maintenance_reports.refresh_failed'));

      return self::FAILURE;
    }
  }
}
