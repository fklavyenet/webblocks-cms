<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameTables();
        $this->renameColumns();
        $this->renameDemoReferencesTable();
    }

    public function down(): void
    {
        $this->renameDemoReferencesTableBack();
        $this->renameColumnsBack();
        $this->renameTablesBack();
    }

    private function renameTables(): void
    {
        if (Schema::hasTable('asset_folders') && ! Schema::hasTable('media_folders')) {
            Schema::rename('asset_folders', 'media_folders');
        }

        if (Schema::hasTable('assets') && ! Schema::hasTable('media')) {
            Schema::rename('assets', 'media');
        }

        if (Schema::hasTable('block_assets') && ! Schema::hasTable('block_media')) {
            Schema::rename('block_assets', 'block_media');
        }
    }

    private function renameColumns(): void
    {
        $this->renameConstrainedColumn('media', 'folder_id', 'media_folders');
        $this->renameConstrainedColumn('blocks', 'asset_id', 'media', 'media_id');
        $this->renameConstrainedColumn('block_media', 'asset_id', 'media', 'media_id');
        $this->renameConstrainedColumn('sites', 'favicon_asset_id', 'media', 'favicon_media_id');
        $this->renameConstrainedColumn('sites', 'social_image_asset_id', 'media', 'social_image_media_id');
        $this->renameConstrainedColumn('page_translations', 'og_image_asset_id', 'media', 'og_image_media_id');
    }

    private function renameColumnsBack(): void
    {
        $this->renameConstrainedColumn('page_translations', 'og_image_media_id', 'assets', 'og_image_asset_id');
        $this->renameConstrainedColumn('sites', 'social_image_media_id', 'assets', 'social_image_asset_id');
        $this->renameConstrainedColumn('sites', 'favicon_media_id', 'assets', 'favicon_asset_id');
        $this->renameConstrainedColumn('block_media', 'media_id', 'assets', 'asset_id');
        $this->renameConstrainedColumn('blocks', 'media_id', 'assets', 'asset_id');
        $this->renameConstrainedColumn('media', 'folder_id', 'asset_folders');
    }

    private function renameTablesBack(): void
    {
        if (Schema::hasTable('block_media') && ! Schema::hasTable('block_assets')) {
            Schema::rename('block_media', 'block_assets');
        }

        if (Schema::hasTable('media') && ! Schema::hasTable('assets')) {
            Schema::rename('media', 'assets');
        }

        if (Schema::hasTable('media_folders') && ! Schema::hasTable('asset_folders')) {
            Schema::rename('media_folders', 'asset_folders');
        }
    }

    private function renameDemoReferencesTable(): void
    {
        if (Schema::hasTable('demo_asset_references') && ! Schema::hasTable('demo_media_references')) {
            Schema::rename('demo_asset_references', 'demo_media_references');
        }

        $this->renameConstrainedColumn('demo_media_references', 'asset_id', 'media', 'media_id', cascadeOnDelete: true);
    }

    private function renameDemoReferencesTableBack(): void
    {
        $this->renameConstrainedColumn('demo_media_references', 'media_id', 'assets', 'asset_id', cascadeOnDelete: true);

        if (Schema::hasTable('demo_media_references') && ! Schema::hasTable('demo_asset_references')) {
            Schema::rename('demo_media_references', 'demo_asset_references');
        }
    }

    private function renameConstrainedColumn(string $table, string $from, string $constrainedTable, ?string $to = null, bool $cascadeOnDelete = false): void
    {
        $to ??= $from;

        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            $this->rebuildForeignKeyIfNeeded($table, $to, $constrainedTable, $cascadeOnDelete);

            return;
        }

        $this->dropForeignKeyIfExists($table, $from);
        $this->renameColumn($table, $from, $to);
        $this->rebuildForeignKeyIfNeeded($table, $to, $constrainedTable, $cascadeOnDelete);
    }

    private function rebuildForeignKeyIfNeeded(string $table, string $column, string $constrainedTable, bool $cascadeOnDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropForeignKeyIfExists($table, $column);

        Schema::table($table, function (Blueprint $blueprint) use ($column, $constrainedTable, $cascadeOnDelete): void {
            $foreign = $blueprint->foreign($column)->references('id')->on($constrainedTable);

            if ($cascadeOnDelete) {
                $foreign->cascadeOnDelete();

                return;
            }

            $foreign->nullOnDelete();
        });
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $definition = DB::selectOne(
                'SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_COMMENT AS column_comment '
                .'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $from],
            );

            if (! $definition) {
                return;
            }

            $nullSql = $definition->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
            $defaultSql = $definition->column_default === null ? '' : ' DEFAULT '.DB::getPdo()->quote((string) $definition->column_default);
            $commentSql = $definition->column_comment !== '' ? ' COMMENT '.DB::getPdo()->quote((string) $definition->column_comment) : '';

            DB::statement(sprintf(
                'ALTER TABLE `%s` CHANGE `%s` `%s` %s %s%s%s',
                $table,
                $from,
                $to,
                $definition->column_type,
                $nullSql,
                $defaultSql,
                $commentSql,
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
            $blueprint->renameColumn($from, $to);
        });
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $foreignKey = $this->foreignKeyName($table, $column);

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
                $blueprint->dropForeign($foreignKey);
            });
        } catch (Throwable) {
        }
    }

    private function foreignKeyName(string $table, string $column): string
    {
        return $table.'_'.$column.'_foreign';
    }
};
