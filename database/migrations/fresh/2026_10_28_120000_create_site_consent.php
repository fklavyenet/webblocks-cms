<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Consent\ConsentSchema;

return new class extends Migration
{
  public function up(): void
  {
    ConsentSchema::migrate();
    foreach ((new CoreBlockTypeCatalogSyncer)->definitions() as $definition) {
      if ($definition['slug'] === 'external-content') {
        BlockType::query()->firstOrCreate(['slug' => 'external-content'], $definition);
      }
    }
  }

  public function down(): void
  {
    foreach (['cookies', 'translations', 'services', 'settings'] as $table) {
      Schema::dropIfExists('wbcms_consent_'.$table);
    }
  }
};
