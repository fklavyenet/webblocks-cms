<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_sites')) {
      return;
    }

    Schema::table('wbcms_sites', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_sites', 'custom_head_html')) {
        $table->text('custom_head_html')->nullable();
      }
    });
  }

  public function down(): void {}
};
