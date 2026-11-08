<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('wbcms_mcp_grants', 'drafts_created_only')) {
      Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->boolean('drafts_created_only')->default(false));
    }
    if (! Schema::hasColumn('wbcms_mcp_grants', 'can_edit_seo')) {
      Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->boolean('can_edit_seo')->default(false));
    }
    if (! Schema::hasTable('wbcms_mcp_grant_drafts')) {
      Schema::create('wbcms_mcp_grant_drafts', function (Blueprint $table): void {
        $table->foreignId('grant_id')->constrained('wbcms_mcp_grants')->cascadeOnDelete();
        $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
        $table->primary(['grant_id', 'page_id'], 'mcp_grant_draft');
      });
    }
  }

  public function down(): void
  {
    // Older code cannot enforce either new restriction. Require fresh consent.
    DB::table('wbcms_mcp_grants')->where('drafts_created_only', true)->orWhere('can_edit_seo', true)->delete();
    Schema::dropIfExists('wbcms_mcp_grant_drafts');
    Schema::table('wbcms_mcp_grants', fn (Blueprint $table) => $table->dropColumn(['drafts_created_only', 'can_edit_seo']));
  }
};
