<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_page_translations', function (Blueprint $table): void {
      $table->string('seo_title')->nullable()->after('path');
      $table->text('seo_description')->nullable()->after('seo_title');
      $table->string('seo_keywords')->nullable()->after('seo_description');
      $table->string('og_title')->nullable()->after('seo_keywords');
      $table->text('og_description')->nullable()->after('og_title');
      $table->foreignId('og_image_asset_id')->nullable()->after('og_description')->constrained('wbcms_assets')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_page_translations', function (Blueprint $table): void {
      $table->dropConstrainedForeignId('og_image_asset_id');
      $table->dropColumn('og_description');
      $table->dropColumn('og_title');
      $table->dropColumn('seo_keywords');
      $table->dropColumn('seo_description');
      $table->dropColumn('seo_title');
    });
  }
};
