<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_visitor_events')) {
      foreach (['visitor_events_visited_at_index' => ['visited_at'], 'visitor_events_site_visited_at_index' => ['site_id', 'visited_at']] as $name => $columns) {
        if (! Schema::hasIndex('wbcms_visitor_events', $columns)) {
          Schema::table('wbcms_visitor_events', fn (Blueprint $table) => $table->index($columns, $name));
        }
      }
    }

    if (Schema::hasTable('wbcms_visitor_daily_totals')) {
      return;
    }

    Schema::create('wbcms_visitor_daily_totals', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      // Zero represents a missing locale; no visitor or page identifier is retained.
      $table->unsignedBigInteger('locale_id')->default(0);
      $table->date('day');
      $table->unsignedBigInteger('page_views');
      $table->unsignedBigInteger('bot_page_views')->default(0);
      $table->unique(['site_id', 'locale_id', 'day'], 'visitor_daily_dimension_unique');
      $table->index('day');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_visitor_daily_totals');
  }
};
