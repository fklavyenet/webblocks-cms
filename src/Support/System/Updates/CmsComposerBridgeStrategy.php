<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Support\Updates\Client\Apply\ComposerPackageApplyStrategy;

/** Bind crash leftovers to the legacy parent's existing SQL recovery receipt. */
class CmsComposerBridgeStrategy extends ComposerPackageApplyStrategy
{
  private int $runId = 0;

  public function forRun(int $id): void
  {
    $this->runId = $id;
  }

  protected function recoveryDirectory(): string
  {
    return base_path('.publisher-recovery-bridge-'.$this->runId.'-'.bin2hex(random_bytes(12)));
  }
}
