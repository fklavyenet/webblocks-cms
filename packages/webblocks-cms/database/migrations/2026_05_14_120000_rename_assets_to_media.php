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
        $this->renameConstrainedColumn('media', 'folder_id', 'media_folders', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('blocks', 'asset_id', 'media', 'media_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('block_media', 'asset_id', 'media', 'media_id', onDeleteAction: 'cascade');
        $this->renameConstrainedColumn('sites', 'favicon_asset_id', 'media', 'favicon_media_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('sites', 'social_image_asset_id', 'media', 'social_image_media_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('page_translations', 'og_image_asset_id', 'media', 'og_image_media_id', onDeleteAction: 'set null');
    }

    private function renameColumnsBack(): void
    {
        $this->renameConstrainedColumn('page_translations', 'og_image_media_id', 'assets', 'og_image_asset_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('sites', 'social_image_media_id', 'assets', 'social_image_asset_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('sites', 'favicon_media_id', 'assets', 'favicon_asset_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('block_media', 'media_id', 'assets', 'asset_id', onDeleteAction: 'cascade');
        $this->renameConstrainedColumn('blocks', 'media_id', 'assets', 'asset_id', onDeleteAction: 'set null');
        $this->renameConstrainedColumn('media', 'folder_id', 'asset_folders', onDeleteAction: 'set null');
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

        $this->renameConstrainedColumn('demo_media_references', 'asset_id', 'media', 'media_id', onDeleteAction: 'cascade');
    }

    private function renameDemoReferencesTableBack(): void
    {
        $this->renameConstrainedColumn('demo_media_references', 'media_id', 'assets', 'asset_id', onDeleteAction: 'cascade');

        if (Schema::hasTable('demo_media_references') && ! Schema::hasTable('demo_asset_references')) {
            Schema::rename('demo_media_references', 'demo_asset_references');
        }
    }

    private function renameConstrainedColumn(string $table, string $from, string $constrainedTable, ?string $to = null, string $onDeleteAction = 'set null'): void
    {
        $to ??= $from;

        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            $this->rebuildForeignKeyIfNeeded($table, $to, $constrainedTable, $onDeleteAction);

            return;
        }

        $this->dropForeignKeyIfExists($table, $from);
        $this->renameColumn($table, $from, $to);
        $this->rebuildForeignKeyIfNeeded($table, $to, $constrainedTable, $onDeleteAction);
    }

    private function rebuildForeignKeyIfNeeded(string $table, string $column, string $constrainedTable, string $onDeleteAction): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($constrainedTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropForeignKeyIfExists($table, $column);
        $this->ensureColumnNullabilityMatchesOnDeleteAction($table, $column, $onDeleteAction);

        if ($this->foreignKeyExists($table, $column, $constrainedTable, $onDeleteAction)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $constrainedTable, $onDeleteAction): void {
            $foreign = $blueprint->foreign($column)->references('id')->on($constrainedTable);

            if ($onDeleteAction === 'cascade') {
                $foreign->cascadeOnDelete();

                return;
            }

            $foreign->nullOnDelete();
        });
    }

    private function ensureColumnNullabilityMatchesOnDeleteAction(string $table, string $column, string $onDeleteAction): void
    {
        if ($onDeleteAction !== 'set null') {
            return;
        }

        $definition = $this->columnDefinition($table, $column);

        if (! $definition || $definition->is_nullable === 'YES') {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $defaultSql = $this->defaultSql($definition);
        $commentSql = $definition->column_comment !== '' ? ' COMMENT '.DB::getPdo()->quote((string) $definition->column_comment) : '';

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s NULL%s%s',
            $table,
            $column,
            $definition->column_type,
            $defaultSql,
            $commentSql,
        ));
    }

    private function foreignKeyExists(string $table, string $column, string $constrainedTable, string $onDeleteAction): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $constraint = DB::selectOne(
            'SELECT k.CONSTRAINT_NAME AS constraint_name, r.DELETE_RULE AS delete_rule '
            .'FROM information_schema.KEY_COLUMN_USAGE k '
            .'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
            .'ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA '
            .'AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME '
            .'AND k.TABLE_NAME = r.TABLE_NAME '
            .'WHERE k.CONSTRAINT_SCHEMA = DATABASE() '
            .'AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME = ? '
            .'LIMIT 1',
            [$table, $column, $constrainedTable],
        );

        if (! $constraint) {
            return false;
        }

        return strtolower((string) $constraint->delete_rule) === ($onDeleteAction === 'cascade' ? 'cascade' : 'set null');
    }

    private function columnDefinition(string $table, string $column): ?object
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        return DB::selectOne(
            'SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_COMMENT AS column_comment '
            .'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $definition = $this->columnDefinition($table, $from);

            if (! $definition) {
                return;
            }

            $nullSql = $definition->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
            $defaultSql = $this->defaultSql($definition);
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
        foreach ($this->possibleForeignKeyNames($table, $column) as $foreignKey) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
                    $blueprint->dropForeign($foreignKey);
                });
            } catch (Throwable) {
            }
        }
    }

    private function possibleForeignKeyNames(string $table, string $column): array
    {
        $names = [$table.'_'.$column.'_foreign'];

        if ($table === 'block_media' && $column === 'media_id') {
            $names[] = 'block_assets_asset_id_foreign';
        }

        if ($table === 'demo_media_references' && $column === 'media_id') {
            $names[] = 'demo_asset_references_asset_id_foreign';
        }

        if ($table === 'media' && $column === 'folder_id') {
            $names[] = 'assets_folder_id_foreign';
        }

        return array_values(array_unique($names));
    }

    private function defaultSql(object $definition): string
    {
        if ($definition->column_default === null) {
            return '';
        }

        $default = (string) $definition->column_default;
        $dataType = strtolower((string) ($definition->data_type ?? ''));

        if ($this->defaultShouldBeQuoted($dataType) && ! $this->isRawDefaultExpression($default)) {
            return ' DEFAULT '.DB::getPdo()->quote($default);
        }

        return ' DEFAULT '.$default;
    }

    private function defaultShouldBeQuoted(string $dataType): bool
    {
        return in_array($dataType, [
            'char',
            'varchar',
            'tinytext',
            'text',
            'mediumtext',
            'longtext',
            'enum',
            'set',
            'date',
            'datetime',
            'timestamp',
            'time',
            'json',
        ], true);
    }

    private function isRawDefaultExpression(string $default): bool
    {
        return preg_match('/^(current_timestamp(?:\(\d+\))?|current_date(?:\(\))?|current_time(?:\(\d+\))?|now\(\))$/i', $default) === 1;
    }
};
