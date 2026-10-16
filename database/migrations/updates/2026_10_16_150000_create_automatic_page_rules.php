<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_automatic_page_rules')) {
      Schema::create('wbcms_automatic_page_rules', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->string('name', 160);
        $table->string('source_handle', 160);
        $table->foreignId('template_page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
        $table->foreignId('catalogue_page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
        $table->string('base_path', 190);
        $table->string('status', 16)->default('draft');
        $table->unsignedInteger('revision')->default(1);
        $table->timestamps();
        $table->unique(['site_id', 'base_path'], 'automatic_page_site_path');
      });
    }
  }

  public function down(): void
  {
    // Preserve draft operator configuration during package rollback.
  }
};
