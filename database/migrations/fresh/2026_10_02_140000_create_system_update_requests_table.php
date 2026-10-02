<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_system_update_requests')) {
      return;
    }

    Schema::create('wbcms_system_update_requests', function (Blueprint $table): void {
      $table->uuid('id')->primary();
      $table->unsignedBigInteger('cms_api_token_id');
      $table->string('idempotency_key', 128);
      $table->string('expected_current_version', 60);
      $table->string('target_version', 60);
      $table->string('checksum_sha256', 64);
      $table->unsignedBigInteger('system_update_run_id')->nullable();
      $table->string('status', 40)->default('running')->index();
      $table->string('code', 80)->nullable();
      $table->json('result')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->timestamps();
      $table->unique(['cms_api_token_id', 'idempotency_key'], 'wbcms_update_request_key_unique');
    });
  }

  public function down(): void
  {
    // Preserve idempotency receipts; dropping them can permit duplicate updates.
  }
};
