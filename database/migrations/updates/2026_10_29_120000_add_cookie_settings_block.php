<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable((new BlockType)->getTable())) {
      return;
    }
    $definition = collect((new CoreBlockTypeCatalogSyncer)->definitions())->firstWhere('slug', 'cookie-settings');
    BlockType::query()->firstOrCreate(['slug' => 'cookie-settings'], $definition);
  }

  public function down(): void {}
};
