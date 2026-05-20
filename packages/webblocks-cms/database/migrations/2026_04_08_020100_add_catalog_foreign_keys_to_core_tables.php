<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('page_type_id')->nullable()->after('page_type')->constrained('page_types')->nullOnDelete();
        });

        Schema::table('layouts', function (Blueprint $table) {
            $table->foreignId('layout_type_id')->nullable()->after('slug')->constrained('layout_types')->nullOnDelete();
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->foreignId('block_type_id')->nullable()->after('type')->constrained('block_types')->nullOnDelete();
            $table->foreignId('slot_type_id')->nullable()->after('slot')->constrained('slot_types')->nullOnDelete();
        });

        $this->backfillPageTypes();
        $this->backfillLayoutTypes();
        $this->backfillBlockTypes();
        $this->backfillSlotTypes();
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('slot_type_id');
            $table->dropConstrainedForeignId('block_type_id');
        });

        Schema::table('layouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_type_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_type_id');
        });
    }

    private function backfillPageTypes(): void
    {
        $slugs = DB::table('pages')
            ->whereNotNull('page_type')
            ->distinct()
            ->pluck('page_type')
            ->filter()
            ->values();

        foreach ($slugs as $slug) {
            $id = $this->upsertCatalogItem('page_types', [
                'name' => $this->titleFromSlug($slug),
                'slug' => $slug,
                'status' => 'published',
            ]);

            DB::table('pages')
                ->where('page_type', $slug)
                ->whereNull('page_type_id')
                ->update(['page_type_id' => $id]);
        }
    }

    private function backfillLayoutTypes(): void
    {
        if (! DB::table('layouts')->exists()) {
            return;
        }

        $knownLayoutTypes = [
            'default' => 'Default',
            'landing' => 'Landing',
            'sidebar-left' => 'Sidebar Left',
            'sidebar-right' => 'Sidebar Right',
            'full-width' => 'Full Width',
            'dashboard' => 'Dashboard',
            'system' => 'System',
        ];

        $defaultId = $this->upsertCatalogItem('layout_types', [
            'name' => 'Default',
            'slug' => 'default',
            'status' => 'published',
        ]);

        foreach ($knownLayoutTypes as $slug => $name) {
            $this->upsertCatalogItem('layout_types', [
                'name' => $name,
                'slug' => $slug,
                'status' => 'published',
            ]);
        }

        foreach (DB::table('layouts')->select('id', 'slug')->whereNull('layout_type_id')->get() as $layout) {
            $targetSlug = array_key_exists($layout->slug, $knownLayoutTypes) ? $layout->slug : 'default';
            $layoutTypeId = DB::table('layout_types')->where('slug', $targetSlug)->value('id') ?: $defaultId;

            DB::table('layouts')
                ->where('id', $layout->id)
                ->update(['layout_type_id' => $layoutTypeId]);
        }
    }

    private function backfillBlockTypes(): void
    {
        $slugs = DB::table('blocks')
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        foreach ($slugs as $slug) {
            $sourceType = DB::table('blocks')->where('type', $slug)->value('source_type');

            $id = $this->upsertCatalogItem('block_types', [
                'name' => $this->titleFromSlug($slug),
                'slug' => $slug,
                'source_type' => $sourceType ?: 'static',
                'status' => 'published',
            ]);

            DB::table('blocks')
                ->where('type', $slug)
                ->whereNull('block_type_id')
                ->update(['block_type_id' => $id]);
        }
    }

    private function backfillSlotTypes(): void
    {
        $slugs = DB::table('blocks')
            ->whereNotNull('slot')
            ->distinct()
            ->pluck('slot')
            ->filter()
            ->values();

        foreach ($slugs as $slug) {
            $id = $this->upsertCatalogItem('slot_types', [
                'name' => $this->titleFromSlug($slug),
                'slug' => $slug,
                'status' => 'published',
            ]);

            DB::table('blocks')
                ->where('slot', $slug)
                ->whereNull('slot_type_id')
                ->update(['slot_type_id' => $id]);
        }
    }

    private function upsertCatalogItem(string $table, array $attributes): int
    {
        $now = now();

        DB::table($table)->updateOrInsert(
            ['slug' => $attributes['slug']],
            [...$attributes, 'updated_at' => $now, 'created_at' => $now],
        );

        return (int) DB::table($table)->where('slug', $attributes['slug'])->value('id');
    }

    private function titleFromSlug(string $slug): string
    {
        return str($slug)->replace('-', ' ')->title()->toString();
    }
};
