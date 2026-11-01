<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Pages\PageLocaleRestoreSchema;

return new class extends Migration
{
  public function up(): void
  {
    PageLocaleRestoreSchema::ensure();
  }

  public function down(): void
  {
    // Additive: retain the reviewed scope and historical audit context.
  }
};
