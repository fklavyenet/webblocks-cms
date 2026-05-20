<?php

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('page_translations', 'site_id')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->foreignId('site_id')->nullable()->after('page_id');
            });
        }

        Page::query()
            ->select(['id', 'site_id'])
            ->orderBy('id')
            ->get()
            ->each(fn (Page $page) => DB::table('page_translations')
                ->where('page_id', $page->id)
                ->update(['site_id' => $page->site_id]));

        $duplicateSlugGroups = PageTranslation::query()
            ->select(['site_id', 'locale_id', 'slug'])
            ->groupBy(['site_id', 'locale_id', 'slug'])
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicateSlugGroups > 0) {
            throw new RuntimeException('Cannot enforce site-scoped page translation slug uniqueness because duplicate rows already exist.');
        }

        $duplicatePathGroups = PageTranslation::query()
            ->select(['site_id', 'locale_id', 'path'])
            ->groupBy(['site_id', 'locale_id', 'path'])
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicatePathGroups > 0) {
            throw new RuntimeException('Cannot enforce site-scoped page translation path uniqueness because duplicate rows already exist.');
        }

        if (! $this->hasIndex('pages', 'pages_id_site_id_unique')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->unique(['id', 'site_id']);
            });
        }

        if (! $this->hasForeignKey('page_translations', 'page_translations_site_id_foreign')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            });
        }

        if (! $this->hasForeignKey('page_translations', 'page_translations_page_id_site_id_foreign')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->foreign(['page_id', 'site_id'])->references(['id', 'site_id'])->on('pages')->cascadeOnDelete();
            });
        }

        if (! $this->hasIndex('page_translations', 'page_translations_site_locale_slug_unique')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->unique(['site_id', 'locale_id', 'slug'], 'page_translations_site_locale_slug_unique');
            });
        }

        if (! $this->hasIndex('page_translations', 'page_translations_site_locale_path_unique')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->unique(['site_id', 'locale_id', 'path'], 'page_translations_site_locale_path_unique');
            });
        }

        if (! $this->hasIndex('page_translations', 'page_translations_site_id_page_id_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->index(['site_id', 'page_id']);
            });
        }

        if (! $this->hasIndex('page_translations', 'page_translations_locale_id_site_id_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->index(['locale_id', 'site_id']);
            });
        }

        if ($this->columnIsNullable('page_translations', 'site_id')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->foreignId('site_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('page_translations', 'site_id')) {
            return;
        }

        if ($this->hasForeignKey('page_translations', 'page_translations_site_id_foreign')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropForeign(['site_id']);
            });
        }

        if ($this->hasForeignKey('page_translations', 'page_translations_page_id_site_id_foreign')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropForeign(['page_id', 'site_id']);
            });
        }

        if ($this->hasIndex('page_translations', 'page_translations_site_locale_slug_unique')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropUnique('page_translations_site_locale_slug_unique');
            });
        }

        if ($this->hasIndex('page_translations', 'page_translations_site_locale_path_unique')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropUnique('page_translations_site_locale_path_unique');
            });
        }

        if ($this->hasIndex('page_translations', 'page_translations_site_id_page_id_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropIndex('page_translations_site_id_page_id_index');
            });
        }

        if ($this->hasIndex('page_translations', 'page_translations_locale_id_site_id_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->dropIndex('page_translations_locale_id_site_id_index');
            });
        }

        if ($this->hasIndex('pages', 'pages_id_site_id_unique')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropUnique('pages_id_site_id_unique');
            });
        }

        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropColumn('site_id');
        });

        if (! $this->hasIndex('page_translations', 'page_translations_locale_id_slug_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->index(['locale_id', 'slug']);
            });
        }

        if (! $this->hasIndex('page_translations', 'page_translations_locale_id_path_index')) {
            Schema::table('page_translations', function (Blueprint $table) {
                $table->index(['locale_id', 'path']);
            });
        }
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        if (! $this->usesMysqlInformationSchema()) {
            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('database()'))
            ->where('table_name', $table)
            ->where('constraint_name', $foreignKey)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => DB::table('information_schema.statistics')
                ->where('table_schema', DB::raw('database()'))
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists(),
            'sqlite' => collect(DB::select("pragma index_list('{$table}')"))
                ->contains(fn (object $row) => ($row->name ?? null) === $index),
            default => false,
        };
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => DB::table('information_schema.columns')
                ->where('table_schema', DB::raw('database()'))
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->value('is_nullable') === 'YES',
            'sqlite' => collect(DB::select("pragma table_info('{$table}')"))
                ->firstWhere('name', $column)?->notnull !== 1,
            default => false,
        };
    }

    private function usesMysqlInformationSchema(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
