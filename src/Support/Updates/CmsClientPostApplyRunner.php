<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\CmsChainContext;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;

final class CmsClientPostApplyRunner implements PostApplyRunner
{
  public function __construct(private readonly UpdateInstaller $installer) {}

  public function run(string $commandDir, array &$output): void
  {
    $this->installer->installDependencies($output);
    $this->installer->runPostInstallCommands($output);
    $this->installer->syncRuntimeAssets($output);
    $id = app(CmsChainContext::class)->stepId;
    if ($id !== null) {
      $step = UpdateChainStep::findOrFail($id);
      $runner = app(UpdateCommandRunner::class);
      $runner->run($runner->artisanCommand(['webblocks:updates:chain-verify', $step->operation_id, (string) $step->position]), $commandDir, $output);
    }
  }
}
