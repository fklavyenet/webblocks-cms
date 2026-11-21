<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    $operationColumns = collect(Schema::getColumns('wbcms_admin_update_operations'))->keyBy('name');
    Schema::table('wbcms_admin_update_operations', function (Blueprint $table) use ($operationColumns): void {
      foreach (['chain_protocol', 'product', 'channel', 'last_verified_version', 'maintenance_owned', 'recovery_required'] as $column) {
        if ($operationColumns->has($column)) {
          continue;
        }
        match ($column) {
          'chain_protocol' => $table->unsignedTinyInteger($column)->nullable(),
          'product', 'channel', 'last_verified_version' => $table->string($column, 100)->nullable(),
          'maintenance_owned' => $table->boolean($column)->default(false),
          'recovery_required' => $table->boolean($column)->default(false)->index(),
        };
      }
      if (! $operationColumns['user_id']['nullable']) {
        $table->unsignedBigInteger('user_id')->nullable()->change();
      }
    });
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
