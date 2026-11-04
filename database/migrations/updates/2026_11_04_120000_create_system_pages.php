<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\SystemPages\SystemPageSchema;

return new class extends Migration
{
  public function up(): void
  {
    SystemPageSchema::up();
  }

  public function down(): void
  {
    SystemPageSchema::down();
  }
};
