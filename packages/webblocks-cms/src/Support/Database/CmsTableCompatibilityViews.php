<?php

namespace WebBlocks\Cms\Support\Database;

use Illuminate\Support\Facades\DB;

class CmsTableCompatibilityViews
{
  /**
   * @var array<int, string>
   */
  private const LEGACY_UPDATE_BRIDGE_VIEWS = [
    'system_settings',
    'system_update_runs',
  ];

  public function dropLegacyUpdateBridgeViews(): void
  {
    foreach (self::LEGACY_UPDATE_BRIDGE_VIEWS as $view) {
      if (! $this->isView($view)) {
        continue;
      }

      if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['insert', 'update', 'delete'] as $operation) {
          DB::statement('DROP TRIGGER IF EXISTS '.$this->wrapTable('wbcms_bridge_'.$view.'_'.$operation));
        }
      }

      DB::statement('DROP VIEW IF EXISTS '.$this->wrapTable($view));
    }
  }

  private function isView(string $name): bool
  {
    $connection = DB::connection();
    $driver = $connection->getDriverName();

    if ($driver === 'sqlite') {
      return $connection->table('sqlite_master')
        ->where('type', 'view')
        ->where('name', $name)
        ->exists();
    }

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
      return $connection->table('information_schema.views')
        ->where('table_schema', $connection->getDatabaseName())
        ->where('table_name', $name)
        ->exists();
    }

    if ($driver === 'pgsql') {
      return $connection->table('information_schema.views')
        ->where('table_schema', 'public')
        ->where('table_name', $name)
        ->exists();
    }

    return false;
  }

  private function wrapTable(string $table): string
  {
    return DB::connection()->getQueryGrammar()->wrapTable($table);
  }
}
