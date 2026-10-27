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
    // Retain session revocation, forced enrollment and audit evidence.
  }
};
