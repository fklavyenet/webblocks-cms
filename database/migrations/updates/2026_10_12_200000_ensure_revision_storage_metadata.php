<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Revisions\RevisionStorageSchema;

return new class extends Migration
{
  public function up(): void
  {
    RevisionStorageSchema::ensure();
  }

  public function down(): void {}
};
