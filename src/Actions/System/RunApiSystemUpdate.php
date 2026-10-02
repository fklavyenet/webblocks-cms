<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\System\Updates\ApiUpdateRunRecorder;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateServerClient;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateTargetChanged;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunReconciler;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class RunApiSystemUpdate
{
  /** Execute synchronously. Receipts are never reused to execute a second time. */
  public function execute(CmsApiToken $token, array $input): array
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $input['checksum_sha256'] = strtolower($input['checksum_sha256']);
    $existing = $this->existing($token, $input);

    if ($existing) {
      return $this->replay($existing, $input);
    }

    $lock = Cache::lock('system-updates:api-admission', max(900, (int) config('publisher-client.lock.ttl_seconds', 900)));

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

      if (SystemUpdateRequest::query()->where('status', 'running')->exists()) {
        return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_requires_attention'];
      }

      $engine = app(SystemUpdater::class);

      if ($engine->isLocked()) {
        return ['http_status' => 409, 'ok' => false, 'code' => 'system_update_in_progress'];
      }

      $receipt = SystemUpdateRequest::query()->create([
        'id' => (string) Str::uuid(),
        'cms_api_token_id' => $token->getKey(),
        'idempotency_key' => $input['idempotency_key'],
        'expected_current_version' => $input['expected_current_version'],
        'target_version' => $input['target_version'],
        'checksum_sha256' => $input['checksum_sha256'],
        'status' => 'running',
      ]);
      $engine = app()->makeWith(SystemUpdater::class, [
        'client' => new ApprovedUpdateServerClient(app(UpdateServerClient::class), $receipt),
        'runRecorder' => new ApiUpdateRunRecorder(app(RunRecorder::class), $receipt),
      ]);

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
      }

      app(SystemUpdateRunReconciler::class)->reconcileReceipt($receipt);

      return ['http_status' => $receipt->code ? 409 : 200, 'ok' => $receipt->code === null, 'operation' => $receipt->fresh()];
    } finally {
      $lock->release();
    }
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

    return ['http_status' => 200, 'ok' => true, 'replayed' => true, 'operation' => $receipt];
  }
}
