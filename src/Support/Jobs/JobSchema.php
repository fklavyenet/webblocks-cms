<?php

namespace WebBlocks\Cms\Support\Jobs;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class JobSchema
{
  public static function up(): void
  {
    if (! Schema::hasTable('wbcms_job_runs')) {
      Schema::create('wbcms_job_runs', function (Blueprint $t): void {
        $t->id();
        $t->string('job_key', 120);
        $t->unsignedBigInteger('site_id')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->unsignedBigInteger('requested_by')->nullable();
        $t->string('source', 16);
        $t->string('state', 24)->default('queued');
        $t->string('reason', 40)->nullable();
        $t->string('requested_state', 24)->nullable();
        $t->char('active_key', 64)->nullable()->unique();
        $t->char('slot_key', 64)->nullable()->unique();
        $t->uuid('lease_token')->nullable();
        $t->timestamp('lease_until')->nullable();
        $t->unsignedBigInteger('cursor')->default(0);
        $t->unsignedBigInteger('processed')->default(0);
        $t->unsignedBigInteger('total')->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('progress_at')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->timestamps();
        $t->index(['state', 'updated_at'], 'wbcms_jobs_pending');
        $t->index(['site_id', 'id'], 'wbcms_jobs_site');
      });
    }
    if (! Schema::hasTable('wbcms_job_attempts')) {
      Schema::create('wbcms_job_attempts', function (Blueprint $t): void {
        $t->id();
        $t->foreignId('run_id')->constrained('wbcms_job_runs')->cascadeOnDelete();
        $t->uuid('token');
        $t->string('source', 16);
        $t->string('state', 24);
        $t->string('reason', 40)->nullable();
        $t->timestamp('started_at');
        $t->timestamp('completed_at')->nullable();
      });
    }
    if (! Schema::hasTable('wbcms_job_automation')) {
      Schema::create('wbcms_job_automation', function (Blueprint $t): void {
        $t->unsignedTinyInteger('id')->primary();
        $t->boolean('http_enabled')->default(false);
        $t->char('token_hash', 64)->nullable();
      });
    }
    DB::table('wbcms_job_automation')->insertOrIgnore(['id' => 1]);
    if (! Schema::hasTable('wbcms_job_settings')) {
      Schema::create('wbcms_job_settings', function (Blueprint $t): void {
        $t->string('job_key', 120)->primary();
        $t->boolean('automatic');
      });
    }
    if (! Schema::hasTable('wbcms_job_ticks')) {
      Schema::create('wbcms_job_ticks', function (Blueprint $t): void {
        $t->id();
        $t->string('source', 16);
        $t->timestamp('minute');
        $t->timestamp('completed_at')->nullable();
        $t->boolean('probe_completed')->default(false);
        $t->unique(['source', 'minute'], 'wbcms_job_tick_slot');
      });
    }
  }

  public static function ready(): bool
  {
    return Schema::hasTable('wbcms_job_runs') && Schema::hasTable('wbcms_job_attempts')
      && Schema::hasTable('wbcms_job_automation') && Schema::hasTable('wbcms_job_ticks') && Schema::hasTable('wbcms_job_settings');
  }

  public static function down(): void
  {
    foreach (['attempts', 'runs', 'automation', 'ticks', 'settings'] as $name) {
      Schema::dropIfExists('wbcms_job_'.$name);
    }
  }
}
