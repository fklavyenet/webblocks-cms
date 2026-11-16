<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Media\GalleryLayout;
use WebBlocks\Cms\Support\Relational\PersistentSchema;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;

return new class extends Migration
{
  public function up(): void
  {
    foreach (GalleryLayout::DIMENSIONS as $column) {
      if (! Schema::hasColumn('wbcms_block_media', $column)) {
        Schema::table('wbcms_block_media', fn (Blueprint $table) => $table->unsignedBigInteger($column)->nullable());
      }
    }
    StructuredSettingsSchema::ensure();
    PersistentSchema::ensure();
  }

  public function down(): void
  {
    // Retain measurements and revision data through a package rollback.
  }
};
