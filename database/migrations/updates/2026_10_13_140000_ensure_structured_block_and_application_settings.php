<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;

return new class extends Migration
{
  public function up(): void
  {
    StructuredSettingsSchema::ensure();
  }

  public function down(): void
  {
    // Preserve canonical SQL data during a code-package rollback.
  }
};
