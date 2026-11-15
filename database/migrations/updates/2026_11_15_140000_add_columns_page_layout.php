<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;

return new class extends Migration
{
  public function up(): void
  {
    DB::transaction(function (): void {
      if (PageLayout::query()->where('handle', 'columns')->where('is_system', false)->exists()
        || SlotType::query()->whereIn('slug', PageLayoutCatalog::COLUMN_SIDEBARS)->where('is_system', false)->exists()) {
        throw new RuntimeException('The Columns layout or its Sidebar slot names conflict with an existing custom catalog entry.');
      }

      app(CoreLayoutCatalogSyncer::class)->sync(['columns']);
      PageLayout::query()->where('handle', 'article')->update(['is_active' => false]);
    });
  }

  public function down(): void
  {
    // Pages and their content may already reference the new layout and slots.
    PageLayout::query()->where('handle', 'columns')->update(['is_active' => false]);
  }
};
