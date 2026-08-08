<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Page List block described each listed page with its SEO description,
 * which is written for search results and social cards rather than for a card
 * in a listing. list_excerpt is the per-locale sentence an editor writes for
 * the listing itself; the SEO description stays as the fallback, so existing
 * pages keep the description they already show.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_page_translations')) {
      return;
    }

    if (Schema::hasColumn('wbcms_page_translations', 'list_excerpt')) {
      return;
    }

    Schema::table('wbcms_page_translations', function (Blueprint $table): void {
      $table->text('list_excerpt')->nullable()->after('seo_keywords');
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('wbcms_page_translations')) {
      return;
    }

    if (! Schema::hasColumn('wbcms_page_translations', 'list_excerpt')) {
      return;
    }

    Schema::table('wbcms_page_translations', function (Blueprint $table): void {
      $table->dropColumn('list_excerpt');
    });
  }
};
