<?php

use Illuminate\Database\Migrations\Migration;
use WebBlocks\Cms\Support\Revisions\RevisionIdentitySchema;

return new class extends Migration
{
  public function up(): void
  {
    RevisionIdentitySchema::ensure();
  }

  public function down(): void
  {
    // Additive: retain identities and independent historical restoration data.
  }
};
