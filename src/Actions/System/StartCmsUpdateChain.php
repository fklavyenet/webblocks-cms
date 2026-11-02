<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateLauncher;
use WebBlocks\Cms\Support\System\Updates\CmsChainAdmission;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Chains\UpdatePlanClient;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;

class StartCmsUpdateChain
{
  public function execute(?int $userId, bool $background = true): AdminUpdateOperation
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    if (! config('webblocks-updates.enabled', true) || ! config('publisher-client.enabled', true) || ! config('publisher-client.web_run_enabled', true)) {
      throw new UpdateException(__('webblocks-cms::admin.update_chain.unavailable'));
    }
    $lock = Cache::lock('system-updates:admission', 30);
    if (! $lock->get()) {
      throw new UpdateException(__('webblocks-cms::admin.update_chain.locked'));
    }
    try {
      if ($active = app(CmsChainAdmission::class)->active()) {
        throw new UpdateException(__('webblocks-cms::admin.update_chain.locked'));
      }
      if (SystemUpdateRun::whereIn('status', ['pending', 'running'])->exists()
        || AdminUpdateOperation::whereIn('status', ['queued', 'running'])->exists()
        || SystemUpdateRequest::where('status', 'running')->exists() || app(SystemUpdater::class)->isLocked()) {
        throw new UpdateException(__('webblocks-cms::admin.update_chain.locked'));
      }
      if (! Schema::hasTable('wbcms_update_chain_steps')) {
        throw new UpdateException(__('webblocks-cms::admin.update_chain.unavailable'));
      }
      $plan = app(UpdatePlanClient::class)->fetch(app(VersionResolver::class)->current());
      if ($plan->steps === []) {
        throw new UpdateException(__('webblocks-cms::admin.update_chain.unavailable'));
      }
      $operation = DB::transaction(function () use ($plan, $userId): AdminUpdateOperation {
        $operation = AdminUpdateOperation::create(['id' => (string) Str::uuid(), 'user_id' => $userId,
          'from_version' => $plan->installedVersion, 'target_version' => $plan->targetVersion,
          'checksum_sha256' => $plan->steps[array_key_last($plan->steps)]['checksum_sha256'],
          'chain_protocol' => 1, 'product' => $plan->product, 'channel' => $plan->channel,
          'last_verified_version' => $plan->installedVersion, 'status' => 'queued', 'started_at' => now()]);
        foreach ($plan->steps as $index => $step) {
          UpdateChainStep::create(['operation_id' => $operation->id, 'position' => $index + 1,
            'from_version' => $step['from_version'], 'version' => $step['version'],
            'minimum_client_version' => $step['minimum_client_version'] ?? null,
            'checksum_sha256' => $step['checksum_sha256'], 'signature' => $step['signature'],
            'artifact_url' => $step['artifact_url'], 'source_reference' => $step['source_reference'] ?? null]);
        }

        return $operation;
      });
      if ($background) {
        try {
          app(AdminUpdateLauncher::class)->launch($operation->id);
        } catch (\Throwable $exception) {
          $operation->update(['status' => 'failed', 'finished_at' => now()]);
          throw $exception;
        }
      }

      return $operation;
    } catch (UpdateException $exception) {
      throw $exception;
    } catch (\Throwable $exception) {
      throw new UpdateException(__('webblocks-cms::admin.update_chain.unavailable'), previous: $exception);
    } finally {
      $lock->release();
    }
  }
}
