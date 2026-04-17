<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['block_id', 'role', 'position']);
            $table->unique(['block_id', 'asset_id', 'role', 'position'], 'block_assets_unique_reference');
        });

        $this->migrateLegacyGalleryAssets();
    }

    public function down(): void
    {
        Schema::dropIfExists('block_assets');
    }

    private function migrateLegacyGalleryAssets(): void
    {
        $now = now();

        foreach (DB::table('blocks')->select('id', 'settings')->where('type', 'gallery')->get() as $block) {
            $settings = json_decode((string) $block->settings, true);

            if (! is_array($settings)) {
                continue;
            }

            $assetIds = collect($settings['asset_ids'] ?? $settings['gallery_asset_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            foreach ($assetIds as $position => $assetId) {
                DB::table('block_assets')->updateOrInsert(
                    [
                        'block_id' => $block->id,
                        'asset_id' => $assetId,
                        'role' => 'gallery_item',
                        'position' => $position,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
};
