<?php

namespace WebBlocks\Cms\Support\ListingViews;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ListingViewSchema
{
  public static function migrate(): void
  {
    if (! Schema::hasTable('wbcms_listing_views')) {
      Schema::create('wbcms_listing_views', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('listing', 16);
        $table->string('name', 80);
        $table->timestamps();
        $table->unique(['user_id', 'listing', 'name'], 'listing_view_owner_name');
      });
    }
    if (! Schema::hasTable('wbcms_listing_view_filters')) {
      Schema::create('wbcms_listing_view_filters', function (Blueprint $table): void {
        $table->foreignId('view_id')->constrained('wbcms_listing_views')->cascadeOnDelete();
        $table->string('filter_name', 32);
        // Keep reference IDs after deletion so saved scopes become stale rather than broader.
        $table->string('filter_value', 255);
        $table->primary(['view_id', 'filter_name'], 'listing_view_filter_identity');
      });
    }
    if (! Schema::hasTable('wbcms_listing_view_columns')) {
      Schema::create('wbcms_listing_view_columns', function (Blueprint $table): void {
        $table->foreignId('view_id')->constrained('wbcms_listing_views')->cascadeOnDelete();
        $table->unsignedTinyInteger('position');
        $table->string('column_name', 32);
        $table->primary(['view_id', 'position'], 'listing_view_column_order');
        $table->unique(['view_id', 'column_name'], 'listing_view_column_identity');
      });
    }
  }
}
