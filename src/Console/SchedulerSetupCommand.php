<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\SchedulerSetup;

class SchedulerSetupCommand extends Command
{
  protected $signature = 'webblocks:scheduler:setup {--install : Install one current-user cron entry after confirmation} {--yes : Confirm the installation and review of external schedulers} {--json : Print only the safe setup result}';

  protected $description = 'Inspect scheduler setup; optionally install one application-user cron entry';

  public function handle(SchedulerSetup $setup): int
  {
    try {
      $result = $setup->plan();
      if ($this->option('install') && $result['status'] === 'ready') {
        if (! $this->option('yes') && ($this->option('json') || ! $this->input->isInteractive() || ! $this->confirm(__('webblocks-cms::scheduler.confirm'), false))) {
          $result['status'] = 'confirmation_required';
        } else {
          $result = $setup->install();
        }
      }
    } catch (\Throwable) {
      $result = ['status' => 'unavailable', 'instructions' => []];
    }
    if ($this->option('json')) {
      $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } else {
      $this->info(__('webblocks-cms::scheduler.title'));
      $this->line(__('webblocks-cms::scheduler.result_'.$result['status']));
      foreach (['schedule', 'command', 'install_command', 'status_command'] as $key) {
        if (! empty($result['instructions'][$key])) {
          $this->line(__('webblocks-cms::scheduler.'.$key).': '.$result['instructions'][$key]);
        }
      }
      $this->line(__('webblocks-cms::scheduler.runtime_help'));
    }

    return in_array($result['status'], ['ready', 'running', 'existing', 'installed_unverified', 'external'], true) ? self::SUCCESS : self::FAILURE;
  }
}
