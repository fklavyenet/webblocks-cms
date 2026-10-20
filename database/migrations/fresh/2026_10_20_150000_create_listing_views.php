<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\ListingViews\ListingViewSchema;

return new class extends Migration
{
  public function up(): void
  {
    ListingViewSchema::migrate();
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_listing_view_columns');
    Schema::dropIfExists('wbcms_listing_view_filters');
    Schema::dropIfExists('wbcms_listing_views');
  }
};
