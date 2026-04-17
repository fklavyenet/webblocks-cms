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
            if (Schema::hasColumn('navigation_items', 'location')) {
                $table->renameColumn('location', 'menu_key');
            }

            if (Schema::hasColumn('navigation_items', 'label')) {
                $table->renameColumn('label', 'title');
            }

            if (Schema::hasColumn('navigation_items', 'sort_order')) {
                $table->renameColumn('sort_order', 'position');
            }
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('navigation_items', 'link_type')) {
                $table->string('link_type')->default('custom_url')->after('title');
            }

            if (! Schema::hasColumn('navigation_items', 'visibility')) {
                $table->string('visibility')->default('visible')->after('target');
            }

            if (! Schema::hasColumn('navigation_items', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('visibility');
            }
        });

        DB::table('navigation_items')->where('menu_key', 'header')->update(['menu_key' => 'primary']);
        DB::table('navigation_items')->where('menu_key', 'primary')->whereNull('position')->update(['position' => 0]);
        DB::table('navigation_items')->whereNull('menu_key')->update(['menu_key' => 'primary']);

        DB::table('navigation_items')
            ->whereNotNull('page_id')
            ->update(['link_type' => 'page']);

        DB::table('navigation_items')
            ->whereNull('page_id')
            ->whereNotNull('url')
            ->update(['link_type' => 'custom_url']);

        DB::table('navigation_items')
            ->whereNull('page_id')
            ->whereNull('url')
            ->update(['link_type' => 'group']);

        if (Schema::hasColumn('navigation_items', 'is_active')) {
            DB::table('navigation_items')->where('is_active', true)->update(['visibility' => 'visible']);
            DB::table('navigation_items')->where('is_active', false)->update(['visibility' => 'hidden']);

            Schema::table('navigation_items', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        DB::table('blocks')
            ->where('type', 'navigation-auto')
            ->orderBy('id')
            ->get(['id', 'subtitle', 'slot', 'settings'])
            ->each(function (object $block): void {
                $settings = json_decode((string) ($block->settings ?? ''), true);

                if (! is_array($settings)) {
                    $settings = [];
                }

                $configured = (string) ($settings['menu_key'] ?? $settings['location'] ?? '');
                $menuKey = match ($configured) {
                    'header' => 'primary',
                    'footer' => 'footer',
                    'mobile' => 'mobile',
                    'legal' => 'legal',
                    'primary' => 'primary',
                    default => ($block->subtitle === 'footer' || $block->slot === 'footer') ? 'footer' : 'primary',
                };

                DB::table('blocks')
                    ->where('id', $block->id)
                    ->update([
                        'settings' => json_encode(['menu_key' => $menuKey], JSON_UNESCAPED_SLASHES),
                    ]);
            });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->index(['menu_key', 'parent_id', 'position'], 'navigation_items_menu_parent_position_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('navigation_items', 'visibility')) {
            Schema::table('navigation_items', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('position');
            });

            DB::table('navigation_items')->where('visibility', 'visible')->update(['is_active' => true]);
            DB::table('navigation_items')->where('visibility', 'hidden')->update(['is_active' => false]);
        }

        Schema::table('navigation_items', function (Blueprint $table) {
            if (Schema::hasColumn('navigation_items', 'link_type')) {
                $table->dropColumn('link_type');
            }

            if (Schema::hasColumn('navigation_items', 'visibility')) {
                $table->dropColumn('visibility');
            }

            if (Schema::hasColumn('navigation_items', 'is_system')) {
                $table->dropColumn('is_system');
            }

            if (Schema::hasColumn('navigation_items', 'menu_key')) {
                $table->renameColumn('menu_key', 'location');
            }

            if (Schema::hasColumn('navigation_items', 'title')) {
                $table->renameColumn('title', 'label');
            }

            if (Schema::hasColumn('navigation_items', 'position')) {
                $table->renameColumn('position', 'sort_order');
            }
        });

        DB::table('navigation_items')->where('location', 'primary')->update(['location' => 'header']);

        DB::table('blocks')
            ->where('type', 'navigation-auto')
            ->orderBy('id')
            ->get(['id', 'subtitle', 'slot', 'settings'])
            ->each(function (object $block): void {
                $settings = json_decode((string) ($block->settings ?? ''), true);

                if (! is_array($settings)) {
                    $settings = [];
                }

                $configured = (string) ($settings['menu_key'] ?? '');
                $location = match ($configured) {
                    'primary' => 'header',
                    'footer' => 'footer',
                    default => ($block->subtitle === 'footer' || $block->slot === 'footer') ? 'footer' : 'header',
                };

                DB::table('blocks')
                    ->where('id', $block->id)
                    ->update([
                        'settings' => json_encode(['location' => $location], JSON_UNESCAPED_SLASHES),
                    ]);
            });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropIndex('navigation_items_menu_parent_position_index');
        });
    }
};
