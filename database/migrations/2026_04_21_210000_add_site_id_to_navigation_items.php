<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $primarySiteId = Site::query()->orderByDesc('is_primary')->orderBy('id')->value('id');

        Schema::table('navigation_items', function (Blueprint $table) use ($primarySiteId) {
            $table->foreignId('site_id')
                ->nullable()
                ->after('id')
                ->constrained('sites')
                ->cascadeOnDelete();
        });

        DB::table('navigation_items')
            ->whereNull('site_id')
            ->whereNotNull('page_id')
            ->orderBy('id')
            ->get(['id', 'page_id'])
            ->each(function (object $item): void {
                $siteId = DB::table('pages')->where('id', $item->page_id)->value('site_id');

                if ($siteId) {
                    DB::table('navigation_items')->where('id', $item->id)->update(['site_id' => $siteId]);
                }
            });

        if ($primarySiteId) {
            DB::table('navigation_items')->whereNull('site_id')->update(['site_id' => $primarySiteId]);
        }

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->index(['site_id', 'menu_key', 'parent_id', 'position'], 'navigation_items_site_menu_parent_position_index');
        });

        if (Schema::hasColumn('navigation_items', 'menu_key')) {
            Schema::table('navigation_items', function (Blueprint $table) {
                $table->dropIndex('navigation_items_menu_parent_position_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropIndex('navigation_items_site_menu_parent_position_index');
        });

        if (Schema::hasColumn('navigation_items', 'menu_key')) {
            Schema::table('navigation_items', function (Blueprint $table) {
                $table->index(['menu_key', 'parent_id', 'position'], 'navigation_items_menu_parent_position_index');
            });
        }

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
