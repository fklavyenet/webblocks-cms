<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\EditorRecovery\RecoverySchema;

return new class extends Migration
{
  public function up(): void
  {
    RecoverySchema::migrate();
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_editor_recovery_nodes');
    Schema::dropIfExists('wbcms_editor_recovery_values');
    Schema::dropIfExists('wbcms_editor_recoveries');
  }
};
