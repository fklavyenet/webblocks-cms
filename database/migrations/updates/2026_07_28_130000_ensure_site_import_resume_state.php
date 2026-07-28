<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_site_imports')) {
      return;
    }

    Schema::table('wbcms_site_imports', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_site_imports', 'resume_phase')) {
        $table->string('resume_phase')->nullable();
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'resume_offset')) {
        $table->unsignedInteger('resume_offset')->default(0);
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'resume_state')) {
        $table->longText('resume_state')->nullable();
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'progress_done')) {
        $table->unsignedInteger('progress_done')->default(0);
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'progress_total')) {
        $table->unsignedInteger('progress_total')->default(0);
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'heartbeat_at')) {
        $table->timestamp('heartbeat_at')->nullable();
      }
    });
  }

  public function down(): void {}
};
