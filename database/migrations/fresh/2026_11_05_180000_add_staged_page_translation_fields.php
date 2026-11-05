<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_mcp_grants', 'can_edit_translations')) {
      Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
        $table->boolean('can_edit_translations')->default(false);
      });
    }

    if (! Schema::hasTable('wbcms_staged_page_translation_fields')) {
      Schema::create('wbcms_staged_page_translation_fields', function (Blueprint $table): void {
        $table->foreignId('translation_id')->constrained('wbcms_page_translations')->cascadeOnDelete();
        $table->string('field', 30);
        $table->string('proposed_route_value')->nullable();
        $table->primary(['translation_id', 'field'], 'staged_translation_field');
      });
    }
  }

  public function down(): void
  {
    // Retain pending editorial changes across a rollback.
    Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
      $table->dropColumn('can_edit_translations');
    });
  }
};
