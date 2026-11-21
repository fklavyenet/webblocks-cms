<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\Updates\CmsUpdateMaintenance;

final class UpdateChainSchema
{
  public const MIGRATION = '2026_11_02_200000_add_update_chains';

  private const OPERATIONS = 'wbcms_admin_update_operations';

  private const STEPS = 'wbcms_update_chain_steps';

  private const OPERATION_COLUMNS = [
    'id', 'user_id', 'from_version', 'target_version', 'checksum_sha256', 'system_update_run_id',
    'status', 'worker_pid', 'summary', 'heartbeat_at', 'started_at', 'finished_at', 'created_at', 'updated_at',
  ];

  private const CHAIN_COLUMNS = ['chain_protocol', 'product', 'channel', 'last_verified_version', 'maintenance_owned', 'recovery_required'];

  public function required(): bool
  {
    return (bool) config('webblocks-updates.chains.enabled', true);
  }

  public function ready(): bool
  {
    return $this->hasOperationColumns([...self::OPERATION_COLUMNS, ...self::CHAIN_COLUMNS])
      && $this->nullableUserId() && $this->stepsReady()
      && Schema::hasIndex(self::STEPS, ['operation_id', 'position'], 'unique');
  }

  public function stepsReady(): bool
  {
    return Schema::hasColumns(self::STEPS, [
      'id', 'operation_id', 'position', 'from_version', 'version', 'minimum_client_version', 'checksum_sha256',
      'signature', 'artifact_url', 'source_reference', 'status', 'worker_pid', 'system_update_run_id',
      'started_at', 'finished_at', 'created_at', 'updated_at',
    ]);
  }

  public function assertReady(): void
  {
    if ($this->required() && ($state = $this->state()) !== 'ready') {
      throw new UpdateChainSchemaException($this->stateMessage($state));
    }
  }

  public function state(): string
  {
    try {
      return $this->ready() ? 'ready' : 'missing';
    } catch (Throwable) {
      return 'unavailable';
    }
  }

  public function stateMessage(string $state): string
  {
    return __('webblocks-cms::update_schema.'.$state);
  }

  /** The package migration can safely fill a missing group, including after an interrupted DDL run. */
  public function canRepair(): bool
  {
    try {
      $migrator = app(Migrator::class);
      if (! $this->required() || $this->ready() || ! $this->hasOperationColumns(self::OPERATION_COLUMNS)
        || ! Schema::hasTable((new SystemBackup)->getTable()) || CmsUpdateMaintenance::active()
        || ! is_file($this->migrationPath()) || ! $migrator->repositoryExists()
        || in_array(self::MIGRATION, $migrator->getRepository()->getRan(), true)) {
        return false;
      }

      return ! Schema::hasTable(self::STEPS)
        || ($this->stepsReady() && Schema::hasIndex(self::STEPS, ['operation_id', 'position'], 'unique'));
    } catch (Throwable) {
      return false;
    }
  }

  public function migrationPath(): string
  {
    return dirname(__DIR__, 4).'/database/migrations/updates/'.self::MIGRATION.'.php';
  }

  private function hasOperationColumns(array $columns): bool
  {
    return Schema::hasColumns(self::OPERATIONS, $columns);
  }

  private function nullableUserId(): bool
  {
    return (bool) (collect(Schema::getColumns(self::OPERATIONS))->firstWhere('name', 'user_id')['nullable'] ?? false);
  }
}
