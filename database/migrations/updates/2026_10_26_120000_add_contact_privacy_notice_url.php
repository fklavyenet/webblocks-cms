<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Relational\PersistentSchema;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;

return new class extends Migration
{
  public function up(): void
  {
    StructuredSettingsSchema::ensure();
    PersistentSchema::ensure();
  }

  public function down(): void
  {
    // Additive: preserve saved form settings and independent revision snapshots.
  }
};
