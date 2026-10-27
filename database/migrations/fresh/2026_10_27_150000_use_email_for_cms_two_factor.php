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
    // Security state is retained. Older TOTP-only binaries cannot read this schema.
  }
};
