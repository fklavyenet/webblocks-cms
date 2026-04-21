<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropPageUniqueIfPresent();

        if (! Schema::hasColumn('page_translations', 'site_id')) {
            return;
        }

        $this->dropPageTranslationForeignIfPresent('page_translations_page_site_foreign', 'page_translations');

        if ($this->hasIndex('page_translations', 'page_translations_page_id_site_id_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropIndex('page_translations_page_id_site_id_index');
            });
        }

        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropUnique('page_translations_site_id_locale_id_slug_unique');
            $table->dropUnique('page_translations_site_id_locale_id_path_unique');
            $table->dropColumn('site_id');
        });

        Schema::table('page_translations', function (Blueprint $table) {
            $table->index(['locale_id', 'slug']);
            $table->index(['locale_id', 'path']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('page_translations', 'site_id')) {
            return;
        }

        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropIndex(['locale_id', 'slug']);
            $table->dropIndex(['locale_id', 'path']);
            $table->foreignId('site_id')->nullable()->after('page_id')->constrained('sites')->cascadeOnDelete();
        });

        DB::table('page_translations')
            ->join('pages', 'pages.id', '=', 'page_translations.page_id')
            ->update(['page_translations.site_id' => DB::raw('pages.site_id')]);

        Schema::table('page_translations', function (Blueprint $table) {
            $table->unique(['site_id', 'locale_id', 'slug']);
            $table->unique(['site_id', 'locale_id', 'path']);
        });
    }

    private function dropPageUniqueIfPresent(): void
    {
        if (! $this->hasIndex('pages', 'pages_id_site_id_unique')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique('pages_id_site_id_unique');
        });
    }

    private function dropPageTranslationForeignIfPresent(string $foreignKey, string $table): void
    {
        if (! $this->hasForeignKey($table, $foreignKey)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($foreignKey) {
            $table->dropForeign($foreignKey);
        });
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.table_constraints')
                ->where('table_schema', DB::raw('database()'))
                ->where('table_name', $table)
                ->where('constraint_name', $foreignKey)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists();
        }

        return false;
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::raw('database()'))
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("pragma index_list('{$table}')"))
                ->contains(fn (object $row) => ($row->name ?? null) === $index);
        }

        return false;
    }
};
