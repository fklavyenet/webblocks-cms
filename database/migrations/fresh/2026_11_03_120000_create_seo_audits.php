<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Seo\SeoSchema;

return new class extends Migration
{
  public function up(): void
  {
    SeoSchema::up();
  }

  public function down(): void
  {
    SeoSchema::down();
  }
};
