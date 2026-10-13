<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

return new class extends Migration
{
  public function up(): void
  {
    PersistentSchema::migrateAndRetire(false);
  }

  public function down(): void
  {
    // Preserve migrated SQL data on package rollback.
  }
};
