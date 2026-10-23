<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\System\BackupRetentionSchema;

return new class extends Migration
{
  public function up(): void
  {
    BackupRetentionSchema::migrate();
  }

  public function down(): void
  {
    foreach (['is_protected', 'retention_mode', 'expires_at'] as $column) {
      if (Schema::hasColumn('wbcms_system_backups', $column)) {
        Schema::table('wbcms_system_backups', fn (Blueprint $table) => $table->dropColumn($column));
      }
    }
  }
};
