<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_admin_update_operations', 'chain_protocol')) {
      Schema::table('wbcms_admin_update_operations', function (Blueprint $table): void {
        $table->unsignedTinyInteger('chain_protocol')->nullable();
        $table->string('product', 100)->nullable();
        $table->string('channel', 100)->nullable();
        $table->string('last_verified_version', 100)->nullable();
        $table->boolean('maintenance_owned')->default(false);
        $table->boolean('recovery_required')->default(false)->index();
        $table->unsignedBigInteger('user_id')->nullable()->change();
      });
    }
    if (! Schema::hasTable('wbcms_update_chain_steps')) {
      Schema::create('wbcms_update_chain_steps', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->uuid('operation_id')->index();
        $table->unsignedSmallInteger('position');
        $table->string('from_version', 100);
        $table->string('version', 100);
        $table->string('minimum_client_version', 100)->nullable();
        $table->string('checksum_sha256', 64);
        $table->string('signature', 100);
        $table->text('artifact_url');
        $table->string('source_reference', 255)->nullable();
        $table->string('status', 40)->default('queued')->index();
        $table->unsignedBigInteger('worker_pid')->nullable();
        $table->unsignedBigInteger('system_update_run_id')->nullable()->index();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->timestamps();
        $table->unique(['operation_id', 'position']);
      });
    }
  }

  public function down(): void {} // Keep recovery receipts available across a package rollback.
};
