<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Auth\TwoFactorSchema;

return new class extends Migration
{
  public function up(): void
  {
    (new TwoFactorSchema)->migrate();
  }

  public function down(): void
  {
    // Preserve enrolled credentials and enforcement policy; restore a matching backup to roll back.
  }
};
