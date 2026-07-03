<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_visitor_events')) {
      return;
    }

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_visitor_events', 'tracking_mode')) {
        $table->string('tracking_mode', 16)->default('full')->after('path');
        $table->index(['tracking_mode', 'visited_at']);
      }
    });

    DB::table('wbcms_visitor_events')->update(['tracking_mode' => 'full']);

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      $table->string('session_key', 64)->nullable()->change();
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('wbcms_visitor_events')) {
      return;
    }

    DB::table('wbcms_visitor_events')->whereNull('session_key')->update(['session_key' => '']);

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      $table->string('session_key', 64)->nullable(false)->change();

      if (Schema::hasColumn('wbcms_visitor_events', 'tracking_mode')) {
        $table->dropIndex(['tracking_mode', 'visited_at']);
        $table->dropColumn('tracking_mode');
      }
    });
  }
};
