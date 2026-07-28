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
      // An import used to be one transaction inside one request: nothing was
      // observable until it committed, and a dropped request lost all of it.
      // It now runs in committed steps, so it carries its own cursor.
      if (! Schema::hasColumn('wbcms_site_imports', 'resume_phase')) {
        $table->string('resume_phase')->nullable()->after('status');
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'resume_offset')) {
        $table->unsignedInteger('resume_offset')->default(0)->after('resume_phase');
      }

      // The id maps the phases hand to each other (locale, asset, page, block).
      // They used to be local variables; across steps they have to be state.
      if (! Schema::hasColumn('wbcms_site_imports', 'resume_state')) {
        $table->longText('resume_state')->nullable()->after('resume_offset');
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'progress_done')) {
        $table->unsignedInteger('progress_done')->default(0)->after('resume_state');
      }

      if (! Schema::hasColumn('wbcms_site_imports', 'progress_total')) {
        $table->unsignedInteger('progress_total')->default(0)->after('progress_done');
      }

      // Lets a second tab tell "another step is running" from "the last one
      // died", which is the difference between waiting and offering Resume.
      if (! Schema::hasColumn('wbcms_site_imports', 'heartbeat_at')) {
        $table->timestamp('heartbeat_at')->nullable()->after('progress_total');
      }
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('wbcms_site_imports')) {
      return;
    }

    Schema::table('wbcms_site_imports', function (Blueprint $table) {
      foreach ([
        'resume_phase',
        'resume_offset',
        'resume_state',
        'progress_done',
        'progress_total',
        'heartbeat_at',
      ] as $column) {
        if (Schema::hasColumn('wbcms_site_imports', $column)) {
          $table->dropColumn($column);
        }
      }
    });
  }
};
