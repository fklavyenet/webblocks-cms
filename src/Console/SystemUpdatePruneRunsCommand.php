<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;

class SystemUpdatePruneRunsCommand extends Command
{
  protected $signature = 'webblocks:updates:prune-runs {--keep=5 : Number of newest update runs to retain}';

  protected $description = 'Prune old WebBlocks CMS update run records using safe retention rules';

  public function __construct(
    private readonly SystemUpdateRunRetention $retention,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $keep = max(1, (int) $this->option('keep'));
    $deleted = $this->retention->prune($keep);

    $this->info('Pruned '.$deleted.' update run record(s). Retained at least '.$keep.' newest run(s).');

    return self::SUCCESS;
  }
}
