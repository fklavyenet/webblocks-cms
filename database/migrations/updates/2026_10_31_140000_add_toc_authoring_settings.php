<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Relational\PersistentSchema;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;

return new class extends Migration
{
  public function up(): void
  {
    StructuredSettingsSchema::ensure();
    PersistentSchema::ensure();
    // Freeze historical automatic targets before staging/restoration can copy
    // these headings to new block identities. Keep all custom targets intact.
    Block::query()->where('type', 'header')->chunkById(100, function ($blocks): void {
      foreach ($blocks as $block) {
        if ($block->explicitHeaderAnchor() === null) {
          $block->settings = [...$block->decodedSettings(), 'anchor' => 'wb-heading-'.$block->id];
          $block->saveQuietly();
        }
      }
    });
  }

  public function down(): void
  {
    // Preserve editorial choices and independent revision snapshots.
  }
};
