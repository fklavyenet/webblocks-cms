<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_media', function (Blueprint $table) {
      $table->decimal('focal_point_x', 5, 4)->nullable()->after('height');
      $table->decimal('focal_point_y', 5, 4)->nullable()->after('focal_point_x');
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_media', function (Blueprint $table) {
      $table->dropColumn(['focal_point_x', 'focal_point_y']);
    });
  }
};
