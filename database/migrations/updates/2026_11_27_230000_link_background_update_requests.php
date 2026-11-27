<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_system_update_requests') && ! Schema::hasColumn('wbcms_system_update_requests', 'admin_update_operation_id')) {
      Schema::table('wbcms_system_update_requests', function (Blueprint $table): void {
        $table->uuid('admin_update_operation_id')->nullable()->index();
      });
    }
  }

  public function down(): void {} // Preserve durable receipt links across rollback.
};
