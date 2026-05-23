<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'pages_id_site_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        if ($this->hasExpectedIndex()) {
            return;
        }

        if ($this->hasIndexNamed(self::INDEX_NAME)) {
            throw new RuntimeException('Cannot repair pages site parent key because pages_id_site_id_unique exists with an unexpected definition.');
        }

        Schema::table('pages', function (Blueprint $table): void {
            $table->unique(['id', 'site_id'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        // Repair migrations are intentionally not destructive on rollback.
    }

    private function hasExpectedIndex(): bool
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlIndexColumns() === ['id', 'site_id'] && $this->mysqlIndexIsUnique(),
            'sqlite' => $this->sqliteIndexColumns() === ['id', 'site_id'] && $this->sqliteIndexIsUnique(),
            default => false,
        };
    }

    private function hasIndexNamed(string $index): bool
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => DB::table('information_schema.statistics')
                ->selectRaw('INDEX_NAME as index_name')
                ->where('table_schema', DB::raw('database()'))
                ->where('table_name', 'pages')
                ->where('index_name', $index)
                ->exists(),
            'sqlite' => collect(DB::select("pragma index_list('pages')"))
                ->contains(fn (object $row): bool => ($row->name ?? null) === $index),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    private function mysqlIndexColumns(): array
    {
        return DB::table('information_schema.statistics')
            ->selectRaw('COLUMN_NAME as column_name, SEQ_IN_INDEX as seq_in_index')
            ->where('table_schema', DB::raw('database()'))
            ->where('table_name', 'pages')
            ->where('index_name', self::INDEX_NAME)
            ->orderBy('seq_in_index')
            ->get()
            ->map(fn (object $row): string => $this->metadataValue($row, 'column_name', 'COLUMN_NAME'))
            ->all();
    }

    private function mysqlIndexIsUnique(): bool
    {
        return DB::table('information_schema.statistics')
            ->selectRaw('INDEX_NAME as index_name, NON_UNIQUE as non_unique')
            ->where('table_schema', DB::raw('database()'))
            ->where('table_name', 'pages')
            ->where('index_name', self::INDEX_NAME)
            ->where('non_unique', 0)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function sqliteIndexColumns(): array
    {
        if (! $this->hasIndexNamed(self::INDEX_NAME)) {
            return [];
        }

        return collect(DB::select("pragma index_info('".self::INDEX_NAME."')"))
            ->sortBy('seqno')
            ->pluck('name')
            ->map(fn (mixed $column): string => (string) $column)
            ->values()
            ->all();
    }

    private function sqliteIndexIsUnique(): bool
    {
        return collect(DB::select("pragma index_list('pages')"))
            ->contains(fn (object $row): bool => ($row->name ?? null) === self::INDEX_NAME && (int) ($row->unique ?? 0) === 1);
    }

    private function metadataValue(object $row, string $lowercaseKey, string $uppercaseKey): string
    {
        return (string) ($row->{$lowercaseKey} ?? $row->{$uppercaseKey} ?? '');
    }
};
