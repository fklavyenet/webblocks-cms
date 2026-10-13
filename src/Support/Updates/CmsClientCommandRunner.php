<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner as CmsCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;

class CmsClientCommandRunner extends UpdateCommandRunner
{
  public function run(array $command, string $workingDirectory, array &$output): void
  {
    app(CmsCommandRunner::class)->run($command, $workingDirectory, $output);
  }
}
