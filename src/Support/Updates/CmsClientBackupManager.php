<?php

namespace WebBlocks\Cms\Support\Updates;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;
use WebBlocks\Cms\Support\System\Updates\CmsPackageRollback;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;

final class CmsClientBackupManager implements BackupManager
{
  private array $tablesBeforeUpdate = [];

  public function __construct(
    private readonly SystemBackupManager $backups,
    private readonly SystemBackupRestoreManager $restores,
  ) {}

  public function create(string $label, ?int $userId = null): ?string
  {
    app(CmsUpdateProgress::class)->stage('backup');
    $this->tablesBeforeUpdate = Schema::getTableListing();

    return (string) $this->backups->createPreUpdateBackup($userId, $label)->getKey();
  }

  public function restore(string $handle): bool
  {
    $backup = SystemBackup::query()->find($handle);

    if (! $backup) {
      return false;
    }

    app(CmsUpdateProgress::class)->restorePreservingProgress(function () use ($backup): void {
      app(CmsPackageRollback::class)->restore();
      // A dump drops/recreates its own tables. Remove tables introduced by this
      // failed migration before importing it, including their conflicting indexes.
      if ($this->tablesBeforeUpdate !== []) {
        Schema::withoutForeignKeyConstraints(function (): void {
          foreach (array_diff(Schema::getTableListing(), $this->tablesBeforeUpdate) as $table) {
            Schema::drop($table);
          }
        });
      }
      $this->restores->restoreFromBackup($backup, $backup->triggered_by_user_id);
      $output = [];
      app(UpdateInstaller::class)->syncRuntimeAssets($output);
    });

    return true;
  }
}
