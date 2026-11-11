<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_theme_versions')) {
      Schema::create('wbcms_theme_versions', function (Blueprint $table): void {
        $table->id();
        $table->string('handle', 63);
        $table->string('version', 64);
        $table->string('name', 100);
        $table->string('license', 64);
        $table->string('cms', 100);
        $table->string('ui', 100);
        $table->char('checksum', 64);
        $table->boolean('button_corners');
        $table->timestamp('created_at');
        $table->unique(['handle', 'version']);
      });
    }

    if (! Schema::hasTable('wbcms_theme_layouts')) {
      Schema::create('wbcms_theme_layouts', function (Blueprint $table): void {
        $table->foreignId('theme_version_id')->constrained('wbcms_theme_versions')->cascadeOnDelete();
        $table->string('layout', 63);
        $table->primary(['theme_version_id', 'layout']);
      });
    }

    if (! Schema::hasTable('wbcms_theme_assets')) {
      Schema::create('wbcms_theme_assets', function (Blueprint $table): void {
        $table->foreignId('theme_version_id')->constrained('wbcms_theme_versions')->cascadeOnDelete();
        $table->string('path', 190);
        $table->string('mime', 32);
        $table->char('checksum', 64);
        $table->rawColumn('contents', Schema::getConnection()->getDriverName() === 'mysql' || Schema::getConnection()->getDriverName() === 'mariadb' ? 'mediumblob' : 'blob');
        $table->primary(['theme_version_id', 'path']);
      });
    }

    if (! Schema::hasTable('wbcms_site_theme_changes')) {
      Schema::create('wbcms_site_theme_changes', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->foreignId('theme_version_id')->nullable()->constrained('wbcms_theme_versions')->restrictOnDelete();
        $table->string('button_corners', 8)->default('default');
        $table->timestamp('created_at');
        $table->index(['site_id', 'id']);
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_site_theme_changes');
    Schema::dropIfExists('wbcms_theme_assets');
    Schema::dropIfExists('wbcms_theme_layouts');
    Schema::dropIfExists('wbcms_theme_versions');
  }
};
