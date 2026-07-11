<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_media')) {
      return;
    }

    Schema::table('wbcms_media', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_media', 'focal_point_x')) {
        $table->decimal('focal_point_x', 5, 4)->nullable();
      }
      if (! Schema::hasColumn('wbcms_media', 'focal_point_y')) {
        $table->decimal('focal_point_y', 5, 4)->nullable();
      }
    });
  }

  public function down(): void {}
};
