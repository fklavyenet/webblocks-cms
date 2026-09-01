<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;
use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;

final class CmsClientBackupManager implements BackupManager
{
  public function __construct(
    private readonly SystemBackupManager $backups,
    private readonly SystemBackupRestoreManager $restores,
  ) {}

  public function create(string $label, ?int $userId = null): ?string
  {
    return (string) $this->backups->createPreUpdateBackup($userId, $label)->getKey();
  }

  public function restore(string $handle): bool
  {
    $backup = SystemBackup::query()->find($handle);

    if (! $backup) {
      return false;
    }

    $this->restores->restoreFromBackup($backup, $backup->triggered_by_user_id);

    return true;
  }
}
