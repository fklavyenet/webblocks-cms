<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateLauncher;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class StartAdminSystemUpdate
{
  public function execute(int $userId): AdminUpdateOperation
  {
    $lock = Cache::lock('system-updates:admin-admission', 30);
    if (! $lock->get()) {
    throw new UpdateException('An update is already being started.');
    }
    try {
      $active = AdminUpdateOperation::whereIn('status', ['queued', 'running'])->latest('started_at')->first();
      if ($active) {
      return $active;
      }
      if (SystemUpdateRequest::where('status', 'running')->exists() || app(SystemUpdater::class)->isLocked()) {
      throw new UpdateException('An update is already running.');
      }
      $check = app(UpdateServerClient::class)->check();
      $target = $check->release['version'] ?? null;
      $checksum = strtolower((string) ($check->release['checksum_sha256'] ?? ''));
      if (! $check->updateAvailable || ($check->compatibility['status'] ?? '') !== 'compatible' || ! is_string($target) || ! preg_match('/^[a-f0-9]{64}$/', $checksum)) {
        throw new UpdateException('No compatible verified update is available right now.');
      }
      $operation = AdminUpdateOperation::create([
        'id' => (string) Str::uuid(), 'user_id' => $userId, 'from_version' => $check->installedVersion,
        'target_version' => $target, 'checksum_sha256' => $checksum, 'status' => 'queued', 'started_at' => now(),
      ]);
      try {
      app(AdminUpdateLauncher::class)->launch($operation->id);
      } catch (Throwable $exception) {
        $operation->update(['status' => 'failed', 'summary' => 'The update worker could not be started.', 'finished_at' => now()]);
        throw new UpdateException('The update worker could not be started.', previous: $exception);
      }

      return $operation;
    } finally {
    $lock->release();
    }
  }
}
