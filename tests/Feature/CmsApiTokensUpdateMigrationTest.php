<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsApiTokensUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  public function test_update_migration_creates_missing_cms_api_tokens_table(): void
  {
    Schema::dropIfExists('cms_api_tokens');

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_23_121000_ensure_cms_api_tokens_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('cms_api_tokens'));

    foreach (['id', 'name', 'token_hash', 'token_preview', 'created_by_user_id', 'last_used_at', 'last_used_ip', 'revoked_at', 'created_at', 'updated_at'] as $column) {
      $this->assertTrue(Schema::hasColumn('cms_api_tokens', $column), 'Missing cms_api_tokens column: '.$column);
    }
  }

  public function test_update_migration_keeps_existing_cms_api_tokens_table(): void
  {
    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_23_121000_ensure_cms_api_tokens_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('cms_api_tokens'));
    $this->assertTrue(Schema::hasColumn('cms_api_tokens', 'token_preview'));
  }
}
