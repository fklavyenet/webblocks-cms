<?php

namespace WebBlocks\Cms\Actions\SystemBackups;

use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\BackupOperationException;
use WebBlocks\Cms\Support\System\BackupOperations;
use WebBlocks\Cms\Support\System\BackupRetentionSchema;

final class UpdateBackupRetention
{
  public function execute(SystemBackup $backup, array $values): SystemBackup
  {
    return app(BackupOperations::class)->run(function () use ($backup, $values): SystemBackup {
      if (! BackupRetentionSchema::ready()) {
        throw new BackupOperationException(__('webblocks-cms::backup-retention.schema_missing'));
      }
      $backup->refresh();
      if ($backup->isRunning()) {
        throw new BackupOperationException(__('webblocks-cms::backup-retention.busy'));
      }
      $backup->fill([
        'is_protected' => (bool) $values['is_protected'],
        'retention_mode' => $values['retention_mode'],
        'expires_at' => $values['retention_mode'] === 'expires' ? $values['expires_at'] : null,
      ])->save();

      return $backup;
    });
  }
}
