<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    foreach (['wbcms_system_update_runs', 'wbcms_admin_update_operations', 'wbcms_system_update_requests'] as $name) {
      if (! Schema::hasTable($name)) {
        continue;
      }
      foreach (['worker_pid', 'worker_host', 'heartbeat_at', 'recovery_required'] as $column) {
        if (! Schema::hasColumn($name, $column)) {
          Schema::table($name, function (Blueprint $table) use ($column): void {
            match ($column) {
              'worker_pid' => $table->unsignedBigInteger($column)->nullable(),
              'worker_host' => $table->string($column, 64)->nullable(),
              'heartbeat_at' => $table->timestamp($column)->nullable(),
              'recovery_required' => $table->boolean($column)->default(false)->index(),
            };
          });
        }
      }
    }
    if (! Schema::hasTable('wbcms_update_reconciliations')) {
      Schema::create('wbcms_update_reconciliations', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('record_type', 20);
        $table->string('record_id', 100);
        $table->unsignedBigInteger('actor_id')->nullable();
        $table->string('previous_status', 40);
        $table->string('status', 40);
        $table->string('reason', 500);
        $table->unsignedBigInteger('worker_pid');
        $table->string('worker_host', 64);
        $table->timestamp('last_heartbeat_at')->nullable();
        $table->string('worker_state', 20);
        $table->boolean('admission_lock_verified');
        $table->boolean('run_lock_verified');
        $table->boolean('recovery_required');
        $table->timestamp('checked_at');
        $table->index(['record_type', 'record_id']);
      });
    }
  }

  public function down(): void {} // Evidence must survive package rollback and history pruning.
};
