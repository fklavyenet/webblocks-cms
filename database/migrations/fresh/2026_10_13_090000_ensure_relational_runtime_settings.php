<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Relational\RelationalSchema;

return new class extends Migration
{
  public function up(): void
  {
    RelationalSchema::ensure();
  }

  public function down(): void
  {
    RelationalSchema::dropFreshTables();
  }
};
