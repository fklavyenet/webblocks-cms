<?php

namespace WebBlocks\Cms\Support\SystemPages;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class SystemPageSchema
{
  public static function up(): void
  {
    if (! Schema::hasTable('wbcms_system_pages')) {
      Schema::create('wbcms_system_pages', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->string('key', 150);
        $table->foreignId('page_id')->unique()->constrained('wbcms_pages')->cascadeOnDelete();
        $table->unique(['site_id', 'key'], 'wbcms_system_pages_site_key_unique');
        $table->timestamps();
      });
    }
  }

  public static function down(): void
  {
    Schema::dropIfExists('wbcms_system_pages');
  }
}
