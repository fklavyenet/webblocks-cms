<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('sites') || Schema::hasColumn('sites', 'public_theme_preset')) {
      return;
    }

    $afterColumn = Schema::hasColumn('sites', 'contact_recipient_email') ? 'contact_recipient_email' : null;

    Schema::table('sites', function (Blueprint $table) use ($afterColumn): void {
      $column = $table->string('public_theme_preset')->nullable();

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
