<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\CmsComposerState;
use WebBlocks\Cms\Support\Updates\CmsUpdateMaintenance;
use WebBlocks\Cms\Support\WebBlocks;

/** Always called in a new PHP process while the step's recovery snapshot still exists. */
class VerifyUpdateChainCommand extends Command
{
  protected $signature = 'webblocks:updates:chain-verify {operation} {position} {--checkpoint}';

  protected $hidden = true;

  public function handle(): int
  {
    if (! Str::isUuid((string) $this->argument('operation')) || ! ctype_digit((string) $this->argument('position'))) {
      return self::FAILURE;
    }
    $operation = AdminUpdateOperation::find($this->argument('operation'));
    $step = UpdateChainStep::where('operation_id', $this->argument('operation'))->where('position', (int) $this->argument('position'))->first();
    $expected = $this->option('checkpoint') ? $step?->from_version : $step?->version;
    if (! $operation || ! $step || $operation->chain_protocol !== 1 || $operation->status !== 'running'
      || ! CmsUpdateMaintenance::active() || WebBlocks::version() !== $expected) {
      return self::FAILURE;
    }
    try {
      app(CmsComposerState::class)->assertCurrent();

      return self::SUCCESS;
    } catch (\Throwable) {
      return self::FAILURE;
    }
  }
}
