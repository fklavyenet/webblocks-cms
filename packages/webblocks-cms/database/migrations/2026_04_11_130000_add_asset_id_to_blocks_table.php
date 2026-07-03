<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_blocks', function (Blueprint $table) {
      $table->foreignId('asset_id')->nullable()->after('url')->constrained('wbcms_assets')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_blocks', function (Blueprint $table) {
      $table->dropConstrainedForeignId('asset_id');
    });
  }
};
