<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;

class DispatchSiteNotificationsCommand extends Command
{
  protected $signature = 'webblocks:notifications:dispatch';

  protected $description = 'Send due site message batches and daily pending-message summaries';

  public function handle(SiteNotificationDispatcher $dispatcher): int
  {
    $health = app(SchedulerHealth::class);
    $health->dispatchStarted();
    try {
      $dispatcher->run();
      $health->dispatchCompleted();

      return self::SUCCESS;
    } catch (\Throwable) {
      $health->dispatchFailed();
      $this->error(__('webblocks-cms::notifications.health_dispatch_failed'));

      return self::FAILURE;
    }
  }
}
