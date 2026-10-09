<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_sites')) {
      return;
    }
    if (! Schema::hasColumn('wbcms_sites', 'notification_settings')) {
      Schema::table('wbcms_sites', fn (Blueprint $table) => $table->json('notification_settings')->nullable());
      // Only installations predating the feature retain full/immediate mail.
      DB::table('wbcms_sites')->update(['notification_settings' => json_encode(['notification_mode' => 'full', 'notification_frequency' => 'immediate', 'batch_minutes' => 10, 'daily_summary' => false, 'summary_hour' => 9])]);
    }
    if (! Schema::hasTable('wbcms_site_notification_events')) {
      Schema::create('wbcms_site_notification_events', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('site_id')->index();
        $table->string('channel', 80);
        $table->unsignedBigInteger('source_id');
        $table->string('status', 30)->default('pending');
        $table->timestamps();
        $table->unique(['site_id', 'channel', 'source_id'], 'site_notification_source');
        $table->index(['site_id', 'channel', 'status'], 'site_notification_pending');
      });
    }
    if (! Schema::hasTable('wbcms_site_notification_states')) {
      Schema::create('wbcms_site_notification_states', function (Blueprint $table): void {
        $table->string('key', 64)->primary();
        $table->unsignedBigInteger('site_id')->index();
        $table->string('channel', 80);
        $table->timestamp('last_attempt_at')->nullable();
        $table->string('last_daily_date', 10)->nullable();
        $table->string('status', 30)->nullable();
      });
    }
  }

  public function down(): void
  {
    // Preserve policy and delivery history on rollback.
  }
};
