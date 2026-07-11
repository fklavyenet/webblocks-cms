<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Database\CmsTable;

return new class extends Migration
{
  public function up(): void
  {
    foreach ($this->tables() as $table) {
      $prefixed = CmsTable::name($table);

      if (Schema::hasTable($table) && ! Schema::hasTable($prefixed)) {
        Schema::rename($table, $prefixed);
      }
    }

    $this->createLegacyUpdateBridgeViews();
  }

  public function down(): void
  {
    $this->dropLegacyUpdateBridgeViews();

    foreach (array_reverse($this->tables()) as $table) {
      $prefixed = CmsTable::name($table);

      if (Schema::hasTable($prefixed) && ! Schema::hasTable($table)) {
        Schema::rename($prefixed, $table);
      }
    }
  }

  /**
   * @return array<int, string>
   */
  private function tables(): array
  {
    return [
      'page_types',
      'layout_types',
      'slot_types',
      'block_types',
      'system_settings',
      'navigation_items',
      'media_folders',
      'media',
      'sites',
      'locales',
      'site_locales',
      'site_user',
      'site_domains',
      'site_variables',
      'layouts',
      'page_layouts',
      'page_layout_slots',
      'pages',
      'page_translations',
      'page_assets',
      'page_revisions',
      'shared_slots',
      'page_slots',
      'blocks',
      'block_media',
      'shared_slot_blocks',
      'shared_slot_revisions',
      'block_text_translations',
      'block_button_translations',
      'block_image_translations',
      'block_contact_form_translations',
      'block_gallery_item_translations',
      'public_search_index',
      'site_exports',
      'site_imports',
      'contact_messages',
      'visitor_events',
      'icon_catalog_items',
      'system_update_runs',
      'system_backups',
      'system_backup_restores',
      'cms_api_tokens',
      'cms_api_token_activity_logs',
      'comment_entries',
      'content_ratings',
      'demo_media_references',
    ];
  }

  /**
   * Keep the pre-update PHP request alive after the schema rename.
   *
   * During a package-native update from an older release, the request that
   * started the update still has the old SystemSetting/SystemUpdateRun model
   * metadata loaded in memory. These bridge views let that one request persist
   * the final version and run status; the new System Updates screen drops them
   * on the next request.
   */
  private function createLegacyUpdateBridgeViews(): void
  {
    foreach (['system_settings', 'system_update_runs'] as $table) {
      $prefixed = CmsTable::name($table);

      if (! Schema::hasTable($prefixed) || Schema::hasTable($table)) {
        continue;
      }

      DB::statement('CREATE VIEW '.$this->wrapTable($table).' AS SELECT * FROM '.$this->wrapTable($prefixed));

      if (DB::connection()->getDriverName() === 'sqlite') {
        $this->createSqliteWritableViewTriggers($table, $prefixed);
      }
    }
  }

  private function dropLegacyUpdateBridgeViews(): void
  {
    foreach (['system_settings', 'system_update_runs'] as $table) {
      if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['insert', 'update', 'delete'] as $operation) {
          DB::statement('DROP TRIGGER IF EXISTS '.$this->wrapTable($this->sqliteTriggerName($table, $operation)));
        }
      }

      if ($this->isView($table)) {
        DB::statement('DROP VIEW IF EXISTS '.$this->wrapTable($table));
      }
    }
  }

  private function createSqliteWritableViewTriggers(string $table, string $prefixed): void
  {
    $columns = Schema::getColumnListing($prefixed);

    if ($columns === []) {
      return;
    }

    $wrappedColumns = implode(', ', array_map($this->wrapColumn(...), $columns));
    $newColumns = implode(', ', array_map(fn (string $column): string => 'NEW.'.$this->wrapColumn($column), $columns));
    $updates = implode(', ', array_map(fn (string $column): string => $this->wrapColumn($column).' = NEW.'.$this->wrapColumn($column), array_filter($columns, fn (string $column): bool => $column !== 'id')));

    DB::statement('CREATE TRIGGER '.$this->wrapTable($this->sqliteTriggerName($table, 'insert')).' INSTEAD OF INSERT ON '.$this->wrapTable($table).' BEGIN INSERT INTO '.$this->wrapTable($prefixed).' ('.$wrappedColumns.') VALUES ('.$newColumns.'); END;');

    if ($updates !== '') {
      DB::statement('CREATE TRIGGER '.$this->wrapTable($this->sqliteTriggerName($table, 'update')).' INSTEAD OF UPDATE ON '.$this->wrapTable($table).' BEGIN UPDATE '.$this->wrapTable($prefixed).' SET '.$updates.' WHERE '.$this->wrapColumn('id').' = OLD.'.$this->wrapColumn('id').'; END;');
    }

    DB::statement('CREATE TRIGGER '.$this->wrapTable($this->sqliteTriggerName($table, 'delete')).' INSTEAD OF DELETE ON '.$this->wrapTable($table).' BEGIN DELETE FROM '.$this->wrapTable($prefixed).' WHERE '.$this->wrapColumn('id').' = OLD.'.$this->wrapColumn('id').'; END;');
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

  private function sqliteTriggerName(string $table, string $operation): string
  {
    return 'wbcms_bridge_'.$table.'_'.$operation;
  }

  private function wrapTable(string $table): string
  {
    return DB::connection()->getQueryGrammar()->wrapTable($table);
  }

  private function wrapColumn(string $column): string
  {
    return DB::connection()->getQueryGrammar()->wrap($column);
  }
};
