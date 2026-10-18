<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_system_update_run_steps') && ! Schema::hasColumn('wbcms_system_update_run_steps', 'state')) {
      Schema::table('wbcms_system_update_run_steps', function (Blueprint $table): void {
      $table->string('state', 20)->nullable();
      });
    }
  }

  public function down(): void {} // Preserve update evidence during rollback.
};
