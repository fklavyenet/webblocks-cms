<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_notes')) {
      Schema::create('wbcms_notes', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
        $table->boolean('is_private')->default(true);
        $table->string('title', 180);
        $table->text('body');
        $table->timestamps();
        $table->index(['created_at', 'id']);
        $table->index(['is_private', 'created_at', 'id']);
      });
    }

    if (! Schema::hasTable('wbcms_note_reads')) {
      Schema::create('wbcms_note_reads', function (Blueprint $table): void {
        $table->foreignId('note_id')->constrained('wbcms_notes')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->primary(['note_id', 'user_id']);
      });
    }

    foreach (['can_read_notes', 'can_write_notes', 'can_delete_notes'] as $column) {
      if (! Schema::hasColumn('wbcms_mcp_grants', $column)) {
        Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->boolean($column)->default(false));
      }
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_note_reads');
    Schema::dropIfExists('wbcms_notes');
    Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->dropColumn([
      'can_read_notes', 'can_write_notes', 'can_delete_notes',
    ]));
  }
};
