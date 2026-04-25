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

        Schema::table('pages', function (Blueprint $table) {
            $table->unique(['id', 'site_id']);
        });

        Schema::table('page_translations', function (Blueprint $table) {
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign(['page_id', 'site_id'])->references(['id', 'site_id'])->on('pages')->cascadeOnDelete();
            $table->unique(['site_id', 'locale_id', 'slug']);
            $table->unique(['site_id', 'locale_id', 'path']);
        });

        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropIndex(['locale_id', 'slug']);
            $table->dropIndex(['locale_id', 'path']);
            $table->index(['site_id', 'page_id']);
            $table->index(['locale_id', 'site_id']);
            $table->foreignId('site_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('page_translations', 'site_id')) {
            return;
        }

        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropForeign(['page_id', 'site_id']);
            $table->dropUnique(['site_id', 'locale_id', 'slug']);
            $table->dropUnique(['site_id', 'locale_id', 'path']);
            $table->dropIndex(['site_id', 'page_id']);
            $table->dropIndex(['locale_id', 'site_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['id', 'site_id']);
        });

        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropColumn('site_id');
            $table->index(['locale_id', 'slug']);
            $table->index(['locale_id', 'path']);
        });
    }
};
