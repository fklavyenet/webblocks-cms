<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    $tableName = 'wbcms_system_update_runs';

    if (! Schema::hasTable($tableName)) {
      return;
    }

    Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
      if (! Schema::hasColumn($tableName, 'summary')) {
      $table->string('summary')->nullable()->after('status');
      }
      if (! Schema::hasColumn($tableName, 'warning_count')) {
      $table->unsignedInteger('warning_count')->default(0)->after('output');
      }
      if (! Schema::hasColumn($tableName, 'started_at')) {
      $table->timestamp('started_at')->nullable()->after('warning_count');
      }
      if (! Schema::hasColumn($tableName, 'finished_at')) {
      $table->timestamp('finished_at')->nullable()->after('started_at');
      }
      if (! Schema::hasColumn($tableName, 'duration_ms')) {
      $table->unsignedBigInteger('duration_ms')->nullable()->after('finished_at');
      }
      if (! Schema::hasColumn($tableName, 'triggered_by_user_id')) {
      $table->unsignedBigInteger('triggered_by_user_id')->nullable()->after('duration_ms');
      }
    });

    DB::table($tableName)->whereNull('started_at')->update(['started_at' => DB::raw('created_at')]);
  }

  public function down(): void {}
};
