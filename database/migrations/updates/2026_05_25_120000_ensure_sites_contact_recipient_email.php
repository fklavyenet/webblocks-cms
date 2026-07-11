<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_sites') || Schema::hasColumn('wbcms_sites', 'contact_recipient_email')) {
      return;
    }

    $afterColumn = Schema::hasColumn('wbcms_sites', 'social_image_media_id') ? 'social_image_media_id' : null;

    Schema::table('wbcms_sites', function (Blueprint $table) use ($afterColumn): void {
      $column = $table->string('contact_recipient_email')->nullable();

      if ($afterColumn !== null) {
        $column->after($afterColumn);
      }
    });
  }

  public function down(): void
  {
    // Existing-install repair migrations are intentionally not destructive on rollback.
  }
};
