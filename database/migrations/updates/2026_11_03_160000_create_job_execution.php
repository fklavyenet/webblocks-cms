<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Jobs\JobSchema;

return new class extends Migration
{
  public function up(): void
  {
    JobSchema::up();
  }

  public function down(): void
  {
    JobSchema::down();
  }
};
