<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ProgressReporter;

final class CmsProgressReporter implements ProgressReporter
{
  public function __construct(private readonly CmsUpdateProgress $progress) {}

  public function beginRun(): void {}

  public function phase(string $phase): void
  {
    if ($phase !== 'post_apply') {
    $this->progress->stage($phase === 'filesystem_backup' ? 'backup' : $phase);
    }
  }

  public function beat(): void
  {
  $this->progress->beat();
  }

  public function failStep(): void
  {
  $this->progress->finish('failed');
  }

  public function endRun(string $status): void {} // Existing SQL run/operation recorder owns terminal state.
}
