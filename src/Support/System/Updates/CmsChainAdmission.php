<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\Updates\Client\Contracts\UpdateAdmission;

class CmsChainAdmission implements UpdateAdmission
{
  public function active(): ?AdminUpdateOperation
  {
    if (! Schema::hasColumn('wbcms_admin_update_operations', 'chain_protocol')) {
      return null;
    }

    return AdminUpdateOperation::where('chain_protocol', 1)->where(function ($query): void {
      $query->whereIn('status', ['queued', 'running', 'interrupted'])->orWhere('recovery_required', true);
      if (Schema::hasTable('wbcms_update_chain_steps')) {
        $query->orWhereIn('id', UpdateChainStep::whereIn('status', ['running', 'interrupted'])->select('operation_id'));
      }
    })->first();
  }

  public function blocked(): bool
  {
    if (app(UnfinishedSystemUpdates::class)->requiresRecovery()
      || (Schema::hasTable('wbcms_system_update_runs') && SystemUpdateRun::whereIn('status', ['pending', 'running'])->exists())) {
      return true;
    }
    $context = app(CmsUpdateAdmissionContext::class);
    foreach ([[AdminUpdateOperation::class, ['queued', 'running'], $context->operationId],
      [SystemUpdateRequest::class, ['running'], $context->requestId]] as [$class, $statuses, $ownedId]) {
      if (Schema::hasTable((new $class)->getTable()) && $class::whereIn('status', $statuses)
        ->when($ownedId !== null, fn ($query) => $query->where('id', '!=', $ownedId))->exists()) {
        return true;
      }
    }
    $operation = $this->active();
    if (! $operation) {
      return false;
    }
    $step = app(CmsChainContext::class)->stepId ? UpdateChainStep::find(app(CmsChainContext::class)->stepId) : null;

    return ! ($step && $step->operation_id === $operation->id && $step->status === 'running'
      && (int) $step->worker_pid === getmypid() && $operation->status === 'running' && ! $operation->recovery_required);
  }

  public function assertAllowed(): void
  {
    if ($this->blocked()) {
      throw new UpdateException(__('webblocks-cms::admin.update_chain.locked'));
    }
  }
}
