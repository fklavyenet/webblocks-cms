<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_pages', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_pages', 'meta_title')) {
        $table->string('meta_title')->nullable()->after('slug');
      }

      if (! Schema::hasColumn('wbcms_pages', 'meta_description')) {
        $table->text('meta_description')->nullable()->after('meta_title');
      }
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_pages', function (Blueprint $table) {
      if (Schema::hasColumn('wbcms_pages', 'meta_description')) {
        $table->dropColumn('meta_description');
      }

      if (Schema::hasColumn('wbcms_pages', 'meta_title')) {
        $table->dropColumn('meta_title');
      }
    });
  }
};
