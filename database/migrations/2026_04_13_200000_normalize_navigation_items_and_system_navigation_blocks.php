<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            if (Schema::hasColumn('navigation_items', 'menu_name')) {
                $table->renameColumn('menu_name', 'location');
            }

            if (Schema::hasColumn('navigation_items', 'title')) {
                $table->renameColumn('title', 'label');
            }
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('navigation_items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        DB::table('navigation_items')->where('location', 'primary')->update(['location' => 'header']);
        DB::table('navigation_items')->whereNull('is_active')->update(['is_active' => true]);

        DB::table('block_types')
            ->whereIn('slug', ['section', 'container', 'columns', 'column_item', 'split', 'stack', 'grid', 'card-group'])
            ->update(['is_system' => false]);

        DB::table('block_types')
            ->where('slug', 'menu')
            ->update([
                'status' => 'draft',
                'description' => 'Legacy navigation block kept only for migrated data.',
                'is_system' => true,
            ]);

        DB::table('block_types')
            ->where('slug', 'navigation-auto')
            ->update([
                'description' => 'Renders navigation items assigned to a system location such as header or footer.',
                'status' => 'published',
                'is_system' => true,
            ]);

        $navigationAutoTypeId = DB::table('block_types')->where('slug', 'navigation-auto')->value('id');
        $legacyMenuTypeId = DB::table('block_types')->where('slug', 'menu')->value('id');

        DB::table('blocks')
            ->where('type', 'navigation-auto')
            ->orderBy('id')
            ->get(['id', 'subtitle', 'slot', 'settings'])
            ->each(function (object $block): void {
                $settings = json_decode((string) ($block->settings ?? ''), true);

                if (! is_array($settings)) {
                    $settings = [];
                }

                if (! isset($settings['location'])) {
                    $settings['location'] = ($block->subtitle === 'footer' || $block->slot === 'footer') ? 'footer' : 'header';
                }

                DB::table('blocks')
                    ->where('id', $block->id)
                    ->update([
                        'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
                        'is_system' => true,
                    ]);
            });

        $legacyMenuBlocks = DB::table('blocks')
            ->where('type', 'menu')
            ->when($legacyMenuTypeId, fn ($query) => $query->orWhere('block_type_id', $legacyMenuTypeId))
            ->orderBy('id')
            ->get(['id', 'subtitle', 'slot']);

        foreach ($legacyMenuBlocks as $block) {
            $location = ($block->subtitle === 'footer' || $block->slot === 'footer') ? 'footer' : 'header';

            DB::table('blocks')
                ->where('id', $block->id)
                ->update([
                    'type' => 'navigation-auto',
                    'block_type_id' => $navigationAutoTypeId,
                    'source_type' => 'navigation',
                    'settings' => json_encode(['location' => $location], JSON_UNESCAPED_SLASHES),
                    'subtitle' => null,
                    'content' => null,
                    'url' => null,
                    'variant' => null,
                    'meta' => null,
                    'is_system' => true,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            if (Schema::hasColumn('navigation_items', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            if (Schema::hasColumn('navigation_items', 'location')) {
                $table->renameColumn('location', 'menu_name');
            }

            if (Schema::hasColumn('navigation_items', 'label')) {
                $table->renameColumn('label', 'title');
            }
        });

        DB::table('navigation_items')->where('menu_name', 'header')->update(['menu_name' => 'primary']);
    }
};
