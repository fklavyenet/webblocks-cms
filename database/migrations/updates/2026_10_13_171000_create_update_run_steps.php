<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_system_update_runs') && ! Schema::hasTable('wbcms_system_update_run_steps')) {
      Schema::create('wbcms_system_update_run_steps', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('system_update_run_id')->constrained('wbcms_system_update_runs')->cascadeOnDelete();
        $table->string('stage', 32);
        $table->timestamp('started_at', 3);
        $table->timestamp('finished_at', 3)->nullable();
        $table->unsignedBigInteger('duration_ms')->nullable();
        $table->index(['system_update_run_id', 'id'], 'update_steps_order');
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_system_update_run_steps');
  }
};
