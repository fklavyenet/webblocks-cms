<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

return new class extends Migration
{
  public function up(): void
  {
    PersistentSchema::migrateAndRetire();
  }

  public function down(): void
  {
    // Restore the full pre-update backup to return to a package needing legacy columns.
    // Recreating empty JSON columns would silently discard relational data.
  }
};
