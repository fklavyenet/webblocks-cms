<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_page_layout_slots')) {
      return;
    }

    if (Schema::hasColumn('wbcms_page_layout_slots', 'html_classes') && ! Schema::hasColumn('wbcms_page_layout_slots', 'css_classes')) {
      Schema::table('wbcms_page_layout_slots', function (Blueprint $table) {
        $table->renameColumn('html_classes', 'css_classes');
      });
    }
  }

  public function down(): void
  {
    if (! Schema::hasTable('wbcms_page_layout_slots')) {
      return;
    }

    if (Schema::hasColumn('wbcms_page_layout_slots', 'css_classes') && ! Schema::hasColumn('wbcms_page_layout_slots', 'html_classes')) {
      Schema::table('wbcms_page_layout_slots', function (Blueprint $table) {
        $table->renameColumn('css_classes', 'html_classes');
      });
    }
  }
};
