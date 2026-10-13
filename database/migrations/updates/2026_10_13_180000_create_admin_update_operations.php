<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_admin_update_operations')) {
    return;
    }
    Schema::create('wbcms_admin_update_operations', function (Blueprint $table): void {
      $table->uuid('id')->primary();
      $table->unsignedBigInteger('user_id');
      $table->string('from_version', 60);
      $table->string('target_version', 60);
      $table->string('checksum_sha256', 64);
      $table->unsignedBigInteger('system_update_run_id')->nullable();
      $table->string('status', 40)->index();
      $table->unsignedBigInteger('worker_pid')->nullable();
      $table->string('summary', 500)->nullable();
      $table->timestamp('heartbeat_at')->nullable();
      $table->timestamp('started_at');
      $table->timestamp('finished_at')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void {}
};
