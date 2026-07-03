<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->string('display_name')->nullable()->after('name');
      $table->string('tagline')->nullable()->after('display_name');
      $table->foreignId('favicon_asset_id')->nullable()->after('is_primary')->constrained('wbcms_assets')->nullOnDelete();
      $table->string('seo_title')->nullable()->after('favicon_asset_id');
      $table->text('seo_description')->nullable()->after('seo_title');
      $table->string('seo_keywords')->nullable()->after('seo_description');
      $table->foreignId('social_image_asset_id')->nullable()->after('seo_keywords')->constrained('wbcms_assets')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->dropConstrainedForeignId('social_image_asset_id');
      $table->dropColumn('seo_keywords');
      $table->dropColumn('seo_description');
      $table->dropColumn('seo_title');
      $table->dropConstrainedForeignId('favicon_asset_id');
      $table->dropColumn('tagline');
      $table->dropColumn('display_name');
    });
  }
};
