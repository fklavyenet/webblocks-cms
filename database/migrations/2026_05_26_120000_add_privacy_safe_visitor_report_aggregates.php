<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_visitor_events')) {
      return;
    }

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_visitor_events', 'referrer_host')) {
        $table->string('referrer_host')->nullable()->after('referrer');
      }

      if (! Schema::hasColumn('wbcms_visitor_events', 'referrer_type')) {
        $table->string('referrer_type', 24)->nullable()->after('referrer_host');
      }

      if (! Schema::hasColumn('wbcms_visitor_events', 'is_bot')) {
        $table->boolean('is_bot')->nullable()->after('os_family');
      }
    });

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      if (Schema::hasColumn('wbcms_visitor_events', 'referrer_host')) {
        $table->index(['referrer_host', 'visited_at']);
      }

      if (Schema::hasColumn('wbcms_visitor_events', 'referrer_type')) {
        $table->index(['referrer_type', 'visited_at']);
      }

      if (Schema::hasColumn('wbcms_visitor_events', 'is_bot')) {
        $table->index(['is_bot', 'visited_at']);
      }
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('wbcms_visitor_events')) {
      return;
    }

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      if (Schema::hasColumn('wbcms_visitor_events', 'referrer_host')) {
        $table->dropIndex(['referrer_host', 'visited_at']);
      }

      if (Schema::hasColumn('wbcms_visitor_events', 'referrer_type')) {
        $table->dropIndex(['referrer_type', 'visited_at']);
      }

      if (Schema::hasColumn('wbcms_visitor_events', 'is_bot')) {
        $table->dropIndex(['is_bot', 'visited_at']);
      }
    });

    Schema::table('wbcms_visitor_events', function (Blueprint $table) {
      $columns = array_values(array_filter([
        Schema::hasColumn('wbcms_visitor_events', 'referrer_host') ? 'referrer_host' : null,
        Schema::hasColumn('wbcms_visitor_events', 'referrer_type') ? 'referrer_type' : null,
        Schema::hasColumn('wbcms_visitor_events', 'is_bot') ? 'is_bot' : null,
      ]));

      if ($columns !== []) {
        $table->dropColumn($columns);
      }
    });
  }
};
