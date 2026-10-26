<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Users\CmsUserRoleSchema;

return new class extends Migration
{
  public function up(): void
  {
    (new CmsUserRoleSchema)->ensureNullableRole();
  }

  public function down(): void
  {
    // Keep role-free accounts valid; restoring NOT NULL would invalidate them.
  }
};
