<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner as CmsCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;

class CmsClientCommandRunner extends UpdateCommandRunner
{
  public function composerCommand(array $arguments): array
  {
    return app(CmsCommandRunner::class)->composerCommand($arguments);
  }

  public function artisanCommand(array $arguments): array
  {
    // Customize the generated client's maintenance command at the CMS boundary.
    if ($arguments === ['down', '--render=errors::503']) {
      $arguments = CmsUpdateMaintenance::arguments();
    }

    return parent::artisanCommand($arguments);
  }

  public function run(array $command, string $workingDirectory, array &$output): void
  {
    app(CmsCommandRunner::class)->run($command, $workingDirectory, $output);
  }
}
