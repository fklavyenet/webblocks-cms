<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Catalog\CatalogRepairer;

return new class extends Migration
{
  public function up(): void
  {
    app(CatalogRepairer::class)->repair(['plugin-block-types'], dryRun: false);
  }

  public function down(): void
  {
    // Placed blocks can already reference the restored catalog rows.
  }
};
