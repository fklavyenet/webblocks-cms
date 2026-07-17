<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site custom head HTML: raw operator-authored markup (verification meta tags, SEO
 * tags, analytics snippets) injected into the public <head>. Operator-only, written through
 * the internal content API under site-settings.write.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->text('custom_head_html')->nullable()->after('public_theme_preset');
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->dropColumn('custom_head_html');
    });
  }
};
