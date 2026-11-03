<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Jobs\JobRunner;

final class WorkJobsCommand extends Command
{
  protected $signature = 'webblocks:jobs:work {--scheduled : Record an actual scheduler invocation}';

  protected $description = 'Advance due registered CMS jobs with shared admission and execution evidence';

  public function handle(JobRunner $runner): int
  {
    $runner->tick('cli', (bool) $this->option('scheduled'));

    return self::SUCCESS;
  }
}
