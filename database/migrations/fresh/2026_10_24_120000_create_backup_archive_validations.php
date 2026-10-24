<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\System\BackupArchiveValidation;

return new class extends Migration
{
  public function up(): void
  {
    BackupArchiveValidation::migrate();
  }

  public function down(): void
  {
    Schema::dropIfExists(BackupArchiveValidation::TABLE);
  }
};
