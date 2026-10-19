<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Access\AclSchema;

return new class extends Migration
{
  public function up(): void
  {
    (new AclSchema)->migrate();
  }

  public function down(): void
  {
    // Preserve authorization records; rollback requires a matching database backup.
  }
};
