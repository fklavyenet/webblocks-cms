<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunOutputSanitizer;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;

class SystemUpdateRunsCommand extends Command
{
  protected $signature = 'webblocks:updates:runs {--last : Show only the last retained update run} {--failed : Show retained failed update runs only}';

  protected $description = 'Show retained WebBlocks CMS update run summaries without sensitive output';

  public function __construct(
    private readonly SystemUpdateRunRetention $retention,
    private readonly SystemUpdateRunOutputSanitizer $sanitizer,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    if (! Schema::hasTable('system_update_runs')) {
      $this->warn('System update run logging is not ready.');

      return self::FAILURE;
    }

    $runs = $this->retention->retainedRuns();

    if ($this->option('last')) {
      $runs = $runs->take(1);
    }

    if ($this->option('failed')) {
      $runs = $runs->filter(fn (SystemUpdateRun $run): bool => $run->status === SystemUpdateRun::STATUS_FAILED)->values();
    }

    if ($runs->isEmpty()) {
      $this->info('No retained update runs matched.');

      return self::SUCCESS;
    }

    foreach ($runs as $run) {
      $this->line('#'.$run->id.' '.$this->versionLabel($run).' '.$run->statusLabel());
      $this->line('Started: '.($run->started_at?->format('Y-m-d H:i:s') ?? 'not available'));
      $this->line('Finished: '.($run->finished_at?->format('Y-m-d H:i:s') ?? 'not available'));
      $this->line('Duration: '.$run->durationLabel());
      $this->line('Summary: '.($this->sanitizer->sanitize($run->summary, 500) ?: 'No summary recorded.'));
      $this->newLine();
    }

    return self::SUCCESS;
  }

  private function versionLabel(SystemUpdateRun $run): string
  {
    return ($run->from_version ?: 'N/A').' -> '.($run->to_version ?: 'N/A');
  }
}
