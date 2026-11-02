<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ChainStepExecutor;

class CmsChainStepExecutor implements ChainStepExecutor
{
  public function __construct(private readonly AdminUpdateOperation $operation) {}

  public function execute(int $position, array $approvedStep): string
  {
    if ($this->operation->fresh()->last_verified_version !== $approvedStep['from_version']) {
      throw new ApprovedUpdateTargetChanged;
    }
    $step = UpdateChainStep::where('operation_id', $this->operation->id)->where('position', $position)->firstOrFail();
    if ($step->status !== 'queued') {
      // A running/interrupted receipt is never authorization to replay an apply.
      throw new UpdateException(__('webblocks-cms::admin.update_chain.interrupted'));
    }
    $output = [];
    $runner = app(UpdateCommandRunner::class);
    $runner->run($runner->artisanCommand(['webblocks:updates:chain-step', $this->operation->id, (string) $position]), base_path(), $output);
    $step->refresh();
    if (! in_array($step->status, ['success', 'success_with_warnings'], true) || ! $step->system_update_run_id) {
      throw new UpdateException(__('webblocks-cms::admin.update_chain.failed'));
    }
    $this->operation->update(['last_verified_version' => $step->version, 'heartbeat_at' => now()]);

    return $step->version;
  }
}
