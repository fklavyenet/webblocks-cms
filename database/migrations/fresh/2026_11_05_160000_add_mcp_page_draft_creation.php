<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_mcp_grants', 'can_create_draft')) {
      Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
        $table->boolean('can_create_draft')->default(false);
      });
    }

    if (! Schema::hasTable('wbcms_mcp_page_drafts')) {
      Schema::create('wbcms_mcp_page_drafts', function (Blueprint $table): void {
        $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
        $table->unsignedBigInteger('user_id');
        $table->char('request_hash', 64);
        $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
        $table->primary(['site_id', 'user_id', 'request_hash'], 'mcp_page_draft_request');
      });
    }
  }

  public function down(): void
  {
    // Keep request receipts so reverting and reapplying code cannot duplicate drafts.
    Schema::table('wbcms_mcp_grants', function (Blueprint $table): void {
      $table->dropColumn('can_create_draft');
    });
  }
};
