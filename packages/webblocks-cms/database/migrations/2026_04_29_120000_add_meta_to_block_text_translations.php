<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_block_text_translations', function (Blueprint $table) {
      $table->text('meta')->nullable()->after('content');
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_block_text_translations', function (Blueprint $table) {
      $table->dropColumn('meta');
    });
  }
};
