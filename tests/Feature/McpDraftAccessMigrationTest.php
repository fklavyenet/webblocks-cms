<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Tests\TestCase;

class McpDraftAccessMigrationTest extends TestCase
{
  protected bool $cacheFreshDatabaseSchema = false;

  public function test_upgrade_preserves_old_access_and_rollback_revokes_restricted_grants(): void
  {
    Schema::create('wbcms_sites', fn (Blueprint $table) => $table->id());
    Schema::create('wbcms_pages', fn (Blueprint $table) => $table->id());
    Schema::create('wbcms_mcp_grants', function (Blueprint $table): void {
      $table->id();
      $table->boolean('can_write')->default(false);
    });
    DB::table('wbcms_mcp_grants')->insert(['id' => 1, 'can_write' => true]);
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_07_130000_limit_mcp_draft_access.php';
    $migration->up();
    $migration->up();
    $this->assertDatabaseHas('wbcms_mcp_grants', ['id' => 1, 'can_write' => true, 'drafts_created_only' => false, 'can_edit_seo' => false]);
    DB::table('wbcms_mcp_grants')->insert([
      ['id' => 2, 'drafts_created_only' => true, 'can_edit_seo' => false],
      ['id' => 3, 'drafts_created_only' => false, 'can_edit_seo' => true],
    ]);
    DB::table('wbcms_pages')->insert(['id' => 1]);
    DB::table('wbcms_mcp_grant_drafts')->insert(['grant_id' => 2, 'page_id' => 1]);
    $migration->down();
    $this->assertDatabaseHas('wbcms_mcp_grants', ['id' => 1, 'can_write' => true]);
    $this->assertSame(1, DB::table('wbcms_mcp_grants')->count());
    $this->assertFalse(Schema::hasTable('wbcms_mcp_grant_drafts'));
    $migration->up();
    $this->assertDatabaseEmpty('wbcms_mcp_grant_drafts');
  }
}
