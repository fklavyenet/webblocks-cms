<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\System\MaintenanceCleanupState;

return new class extends Migration
{
  public function up(): void
  {
    MaintenanceCleanupState::migrate();
  }

  public function down(): void
  {
    Schema::dropIfExists(MaintenanceCleanupState::TABLE);
  }
};
