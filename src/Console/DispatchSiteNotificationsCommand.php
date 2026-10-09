<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;

class DispatchSiteNotificationsCommand extends Command
{
  protected $signature = 'webblocks:notifications:dispatch';

  protected $description = 'Send due site message batches and daily pending-message summaries';

  public function handle(SiteNotificationDispatcher $dispatcher): int
  {
    $dispatcher->run();

    return self::SUCCESS;
  }
}
