<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\System\Updates\PackageLicensePromoter;

return new class extends Migration
{
  public function up(): void
  {
    app(PackageLicensePromoter::class)->promote(dirname(__DIR__, 3));
  }

  public function down(): void
  {
    // The MIT notice remains part of the installed package on rollback.
  }
};
