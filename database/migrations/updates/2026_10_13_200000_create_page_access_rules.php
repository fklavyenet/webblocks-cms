<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_page_access_rules')) {
      Schema::create('wbcms_page_access_rules', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('page_id')->unique()->constrained('wbcms_pages')->cascadeOnDelete();
        // The optional plugin owns packages; no cascading cross-plugin foreign key.
        $table->unsignedBigInteger('package_id')->nullable();
        $table->timestamps();
      });
    }
  }

  public function down(): void
  {
    // Removing protection during rollback would publish member content.
  }
};
