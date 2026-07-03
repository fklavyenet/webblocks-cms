<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Support\Database\CmsTable;

class PartialInstallState
{
  public const REPAIR_SUFFIX_PREFIX = '_before_cms_install_';

  private const CMS_TABLES = [
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
  ];

  private const REQUIRED_TABLES = [
    'page_types',
    'layout_types',
    'slot_types',
    'block_types',
    'sites',
    'locales',
    'system_settings',
  ];

  private const KNOWN_CONFLICTING_FOREIGN_KEYS = [
    'system_update_runs_triggered_by_user_id_foreign',
  ];

  public function isComplete(): bool
  {
    foreach (self::REQUIRED_TABLES as $table) {
      if (! Schema::hasTable(CmsTable::name($table))) {
        return false;
      }
    }

    return true;
  }

  public function hasPartialSchema(): bool
  {
    return $this->existingTables() !== [] && ! $this->isComplete();
  }

  public function report(): array
  {
    $tables = [];

    foreach ($this->existingTables() as $table) {
      $tables[] = [
        'table' => $table,
        'rows' => $this->rowCount($table),
      ];
    }

    return [
      'tables' => $tables,
      'migration_rows' => $this->migrationRows(),
      'foreign_keys' => $this->conflictingForeignKeys(),
    ];
  }

  public function hasNonEmptyTables(): bool
  {
    foreach ($this->existingTables() as $table) {
      if ($this->rowCount($table) > 0) {
        return true;
      }
    }

    return false;
  }

  public function repairEmptyPartialSchema(): array
  {
    if ($this->hasNonEmptyTables()) {
      throw new \RuntimeException('Partial CMS schema contains rows and cannot be repaired automatically.');
    }

    $this->dropKnownConflictingForeignKeys();

    $renamed = [];
    $suffix = self::REPAIR_SUFFIX_PREFIX.now()->format('YmdHis');

    foreach ($this->existingTables() as $table) {
      $target = $table.$suffix;

      if (Schema::hasTable($target)) {
        $target = $target.'_'.substr(str_replace('.', '', (string) microtime(true)), -6);
      }

      Schema::rename($table, $target);
      $renamed[$table] = $target;
    }

    return $renamed;
  }

  private function existingTables(): array
  {
    return array_values(array_filter(
      array_map(CmsTable::name(...), self::CMS_TABLES),
      fn (string $table): bool => Schema::hasTable($table),
    ));
  }

  private function rowCount(string $table): int
  {
    try {
      return (int) DB::table($table)->count();
    } catch (Throwable) {
      return -1;
    }
  }

  private function migrationRows(): array
  {
    if (! Schema::hasTable('migrations')) {
      return [];
    }

    try {
      return DB::table('migrations')
        ->where(function ($query): void {
          foreach ($this->migrationNameFragments() as $fragment) {
            $query->orWhere('migration', 'like', '%'.$fragment.'%');
          }
        })
        ->orderBy('migration')
        ->pluck('migration')
        ->all();
    } catch (Throwable) {
      return [];
    }
  }

  private function migrationNameFragments(): array
  {
    return self::CMS_TABLES;
  }

  private function conflictingForeignKeys(): array
  {
    if (DB::connection()->getDriverName() !== 'mysql') {
      return [];
    }

    try {
      return collect(DB::select(
        'select table_name, constraint_name
          from information_schema.key_column_usage
          where table_schema = database()
            and referenced_table_name is not null
            and constraint_name in ('.implode(',', array_fill(0, count(self::KNOWN_CONFLICTING_FOREIGN_KEYS), '?')).')
          order by table_name, constraint_name',
        self::KNOWN_CONFLICTING_FOREIGN_KEYS,
      ))->map(fn (object $row): array => [
        'table' => (string) $row->table_name,
        'constraint' => (string) $row->constraint_name,
      ])->all();
    } catch (Throwable) {
      return [];
    }
  }

  private function dropKnownConflictingForeignKeys(): void
  {
    foreach ($this->conflictingForeignKeys() as $foreignKey) {
      $table = $foreignKey['table'];

      if (in_array($table, array_map(CmsTable::name(...), self::CMS_TABLES), true)) {
        continue;
      }

      if (! str_contains($table, self::REPAIR_SUFFIX_PREFIX)) {
        continue;
      }

      if (! Schema::hasTable($table) || $this->rowCount($table) > 0) {
        continue;
      }

      Schema::table($table, function ($blueprint) use ($foreignKey): void {
        $blueprint->dropForeign($foreignKey['constraint']);
      });
    }
  }
}
