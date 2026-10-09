<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;

class SchedulerStatusCommand extends Command
{
  protected $signature = 'webblocks:scheduler:status {--json : Return recorded scheduler health as JSON}';

  protected $description = 'Read recorded scheduler and notification worker health without sending mail';

  public function handle(SchedulerHealth $health): int
  {
    $state = $health->snapshot();
    if ($this->option('json')) {
      $this->line(json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } else {
      $this->table([__('webblocks-cms::notifications.health_title'), __('webblocks-cms::notifications.health_value')], [
        [__('webblocks-cms::notifications.health_scheduler'), __('webblocks-cms::notifications.health_'.$state['scheduler_status'])],
        [__('webblocks-cms::notifications.health_worker'), __('webblocks-cms::notifications.health_'.$state['notification_status'])],
        [__('webblocks-cms::notifications.health_last_seen'), $state['last_seen_at'] ?? '-'],
        [__('webblocks-cms::notifications.health_last_completed'), $state['last_completed_at'] ?? '-'],
      ]);
      $this->line(__('webblocks-cms::notifications.health_setup'));
    }

    return $state['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
  }
}
