<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_pages', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_pages', 'settings')) {
        $table->json('settings')->nullable()->after('status');
      }
    });

    Schema::table('wbcms_page_slots', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_page_slots', 'settings')) {
        $table->json('settings')->nullable()->after('sort_order');
      }
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_page_slots', function (Blueprint $table) {
      if (Schema::hasColumn('wbcms_page_slots', 'settings')) {
        $table->dropColumn('settings');
      }
    });

    Schema::table('wbcms_pages', function (Blueprint $table) {
      if (Schema::hasColumn('wbcms_pages', 'settings')) {
        $table->dropColumn('settings');
      }
    });
  }
};
