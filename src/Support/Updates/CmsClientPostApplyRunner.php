<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;

final class CmsClientPostApplyRunner implements PostApplyRunner
{
  public function __construct(private readonly UpdateInstaller $installer) {}

  public function run(string $commandDir, array &$output): void
  {
    $this->installer->installDependencies($output);
    $this->installer->runPostInstallCommands($output);
  }
}
