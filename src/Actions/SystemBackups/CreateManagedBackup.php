<?php

namespace WebBlocks\Cms\Actions\SystemBackups;

use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\BackupOperationException;
use WebBlocks\Cms\Support\System\BackupRetentionSchema;
use WebBlocks\Cms\Support\System\SystemBackupManager;

final class CreateManagedBackup
{
  public function execute(array $values, ?int $actorId = null): SystemBackup
  {
    $manager = app(SystemBackupManager::class);
    if (! isset($values['retention_mode'])) {
      return $manager->createManualBackup($actorId);
    }
    if (! BackupRetentionSchema::ready()) {
      throw new BackupOperationException(__('webblocks-cms::backup-retention.schema_missing'));
    }

    return $manager->createManagedManualBackup([
      'is_protected' => (bool) $values['is_protected'],
      'retention_mode' => $values['retention_mode'],
      'expires_at' => $values['retention_mode'] === 'expires' ? $values['expires_at'] : null,
    ], $actorId);
  }
}
