<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Cache\Lock;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;
use WebBlocks\Cms\Support\System\BackupOperations;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\UpdateChainSchema;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;

final class RepairUpdateChainSchema
{
  public function __construct(private readonly UpdateChainSchema $schema) {}

  public function available(): bool
  {
    try {
      return $this->schema->canRepair()
        && glob(base_path('.publisher-recovery-*')) === []
        && app(UnfinishedSystemUpdates::class)->records()->isEmpty();
    } catch (Throwable) {
      return false;
    }
  }

  public function execute(int $actorId): ?SystemBackup
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $ttl = max(900, (int) config('publisher-client.lock.ttl_seconds', 7200));
    $admission = Cache::lock('system-updates:admission', $ttl);
    if (! $admission->get()) {
      throw new UpdateException(__('webblocks-cms::admin.update_chain.locked'));
    }
    try {
      $execution = Cache::lock((string) config('publisher-client.lock.name'), $ttl);
      if (! $execution->get()) {
        throw new UpdateException(__('webblocks-cms::admin.update_chain.locked'));
      }
      $heartbeat = app(RunHeartbeat::class);
      try {
        $heartbeat->beat();

        return $this->repair($actorId, $admission, $execution, $heartbeat);
      } finally {
        try {
          if ($execution->isOwnedByCurrentProcess()) {
            $heartbeat->clear();
          }
        } finally {
          $execution->release();
        }
      }
    } finally {
      $admission->release();
    }
  }

  private function repair(int $actorId, Lock $admission, Lock $execution, RunHeartbeat $heartbeat): ?SystemBackup
  {
    if ($this->schema->state() === 'ready') {
      return null;
    }
    if (! $this->available()) {
      throw new UpdateException(__('webblocks-cms::update_schema.support'));
    }

    $backup = null;
    try {
      return app(BackupOperations::class)->run(function () use ($actorId, &$backup, $admission, $execution, $heartbeat): SystemBackup {
        $backup = app(SystemBackupManager::class)->createPreUpdateBackup($actorId, __('webblocks-cms::update_schema.backup_label'));
        if (! $backup->isSuccessful()) {
          throw new UpdateException(__('webblocks-cms::update_schema.backup_failed'));
        }
        if (! $admission->isOwnedByCurrentProcess() || ! $execution->isOwnedByCurrentProcess() || ! $this->available()) {
          throw new UpdateException(__('webblocks-cms::update_schema.support'));
        }
        $heartbeat->beat();

        app(Migrator::class)->run([$this->schema->migrationPath()]);
        $this->schema->assertReady();

        return $backup;
      });
    } catch (Throwable $exception) {
      Log::warning('Update chain schema repair did not complete.', ['exception' => $exception::class, 'backup_id' => $backup?->id]);

      throw new UpdateException($backup?->isSuccessful()
        ? __('webblocks-cms::update_schema.repair_failed', ['backup' => $backup->id])
        : __('webblocks-cms::update_schema.backup_failed'));
    }
  }
}
