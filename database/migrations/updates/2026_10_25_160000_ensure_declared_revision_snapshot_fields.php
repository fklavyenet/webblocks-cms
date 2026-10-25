<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

return new class extends Migration
{
  public function up(): void
  {
    PersistentSchema::ensure();
  }

  public function down(): void
  {
    // Additive: existing revision values must remain independently restorable.
  }
};
