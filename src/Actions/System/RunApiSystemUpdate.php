<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateLauncher;
use WebBlocks\Cms\Support\System\Updates\ApiUpdateRunRecorder;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateServerClient;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateTargetChanged;
use WebBlocks\Cms\Support\System\Updates\CmsChainAdmission;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateAdmissionContext;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunReconciler;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkerEvidence;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class RunApiSystemUpdate
{
  /** Legacy synchronous execution and opt-in native background execution share durable receipts. */
  public function execute(CmsApiToken $token, array $input): array
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $input['checksum_sha256'] = strtolower($input['checksum_sha256']);
    $existing = $this->existing($token, $input);

    if ($existing) {
      return $this->replay($existing, $input);
    }

    $ttl = ($input['execution'] ?? 'synchronous') === 'background' ? 30 : max(900, (int) config('publisher-client.lock.ttl_seconds', 900));
    $lock = Cache::lock('system-updates:admission', $ttl);

    if (! $lock->get()) {
      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_in_progress'];
    }

    try {
      // Recheck under the admission lock, before any update side effects.
      if ($existing = $this->existing($token, $input)) {
        return $this->replay($existing, $input);
      }

      foreach (SystemUpdateRequest::query()->where('status', 'running')->get() as $pending) {
        app(SystemUpdateRunReconciler::class)->reconcileReceipt($pending);
      }

      if (SystemUpdateRequest::query()->whereIn('status', ['queued', 'running'])->exists()) {
        return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_requires_attention'];
      }

      if (Schema::hasTable('wbcms_admin_update_operations')
        && AdminUpdateOperation::whereIn('status', ['queued', 'running'])->exists()) {
        return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_in_progress'];
      }
      $engine = app(SystemUpdater::class);

      if ($engine->isLocked()) {
        return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_in_progress'];
      }

      if (($input['execution'] ?? 'synchronous') === 'background') {
        return $this->background($token, $input, $lock);
      }

      $receipt = SystemUpdateRequest::query()->create([
        'id' => (string) Str::uuid(),
        'cms_api_token_id' => $token->getKey(),
        'idempotency_key' => $input['idempotency_key'],
        'expected_current_version' => $input['expected_current_version'],
        'target_version' => $input['target_version'],
        'checksum_sha256' => $input['checksum_sha256'],
        'status' => 'running',
      ] + app(UpdateWorkerEvidence::class)->capture('wbcms_system_update_requests'));
      $engine = app()->makeWith(SystemUpdater::class, [
        'client' => new ApprovedUpdateServerClient(app(UpdateServerClient::class), $receipt),
        'runRecorder' => new ApiUpdateRunRecorder(app(RunRecorder::class), $receipt),
      ]);

      app(CmsUpdateAdmissionContext::class)->requestId = $receipt->id;
      try {
        $engine->run((int) $token->created_by_user_id);
      } catch (Throwable $exception) {
        $receipt->refresh();
        // The shared engine records rollback outcomes; do not overwrite restored.
        if ($receipt->status === 'running' && $receipt->system_update_run_id === null) {
          $receipt->forceFill(['status' => 'failed', 'finished_at' => now()]);
        }
        $receipt->code = $exception instanceof ApprovedUpdateTargetChanged ? 'system_update_target_changed' : 'system_update_failed';
        if ($receipt->status === 'running') {
          $receipt->code = 'system_update_requires_attention';
        }
        $receipt->save();
      } finally {
        app(CmsUpdateAdmissionContext::class)->requestId = null;
      }

      app(SystemUpdateRunReconciler::class)->reconcileReceipt($receipt);

      return ['http_status' => $receipt->code ? 409 : 200, 'ok' => $receipt->code === null, 'operation' => $receipt->fresh()];
    } finally {
      $lock->release();
    }
  }

  private function background(CmsApiToken $token, array $input, Lock $admission): array
  {
    if (! Schema::hasColumn('wbcms_system_update_requests', 'admin_update_operation_id')) {
      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_schema_required'];
    }
    if (app(CmsChainAdmission::class)->blocked()) {
      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_requires_attention'];
    }
    try {
      (new ApprovedUpdateServerClient(app(UpdateServerClient::class), new SystemUpdateRequest($input)))->check();
    } catch (ApprovedUpdateTargetChanged) {
      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_target_changed'];
    } catch (Throwable $exception) {
      report($exception);

      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_check_failed'];
    }
    if (! $admission->isOwnedByCurrentProcess()) {
      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_in_progress'];
    }
    [$receipt, $operation] = DB::transaction(function () use ($token, $input): array {
      $operation = AdminUpdateOperation::create([
        'id' => (string) Str::uuid(), 'user_id' => $token->created_by_user_id,
        'from_version' => $input['expected_current_version'], 'target_version' => $input['target_version'],
        'checksum_sha256' => $input['checksum_sha256'], 'status' => 'queued', 'started_at' => now(),
      ]);
      $receipt = SystemUpdateRequest::create([
        'id' => (string) Str::uuid(), 'cms_api_token_id' => $token->getKey(),
        'idempotency_key' => $input['idempotency_key'], 'expected_current_version' => $input['expected_current_version'],
        'target_version' => $input['target_version'], 'checksum_sha256' => $input['checksum_sha256'],
        'status' => 'queued', 'admin_update_operation_id' => $operation->id,
      ]);

      return [$receipt, $operation];
    });
    try {
      app(AdminUpdateLauncher::class)->launch($operation->id);
    } catch (Throwable $exception) {
      $operation->update(['status' => 'failed', 'summary' => 'The update worker could not be started.', 'finished_at' => now()]);
      $receipt->update(['status' => 'failed', 'code' => 'system_update_worker_start_failed', 'finished_at' => now()]);
      report($exception);

      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_worker_start_failed', 'operation' => $receipt->fresh()];
    }

    return ['http_status' => 202, 'ok' => true, 'operation' => $receipt->fresh()];
  }

  private function existing(CmsApiToken $token, array $input): ?SystemUpdateRequest
  {
    return SystemUpdateRequest::query()->where('cms_api_token_id', $token->getKey())->where('idempotency_key', $input['idempotency_key'])->first();
  }

  private function replay(SystemUpdateRequest $receipt, array $input): array
  {
    foreach (['expected_current_version', 'target_version', 'checksum_sha256'] as $field) {
      if ($input[$field] !== $receipt->$field) {
        return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_idempotency_conflict'];
      }
    }

    if (($input['execution'] ?? 'synchronous') !== ($receipt->admin_update_operation_id ? 'background' : 'synchronous')) {
      return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_idempotency_conflict'];
    }

    return ['http_status' => 200, 'ok' => true, 'replayed' => true, 'operation' => $receipt];
  }
}
