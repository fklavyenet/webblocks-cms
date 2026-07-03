<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CmsApiTokensUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  public function test_update_migration_creates_missing_cms_api_tokens_table(): void
  {
    Schema::dropIfExists('wbcms_cms_api_token_activity_logs');
    Schema::dropIfExists('wbcms_cms_api_tokens');

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_23_121000_ensure_cms_api_tokens_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_cms_api_tokens'));

    foreach (['id', 'name', 'token_hash', 'token_preview', 'created_by_user_id', 'last_used_at', 'last_used_ip', 'revoked_at', 'created_at', 'updated_at'] as $column) {
      $this->assertTrue(Schema::hasColumn('wbcms_cms_api_tokens', $column), 'Missing cms_api_tokens column: '.$column);
    }
  }

  #[Test]
  public function test_update_migration_creates_missing_cms_api_token_activity_logs_table(): void
  {
    Schema::dropIfExists('wbcms_cms_api_token_activity_logs');

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_30_120000_ensure_cms_api_token_activity_logs_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_cms_api_token_activity_logs'));

    foreach (['id', 'cms_api_token_id', 'occurred_at', 'status', 'method', 'path', 'route_name', 'required_capability', 'ip', 'user_agent', 'created_at', 'updated_at'] as $column) {
      $this->assertTrue(Schema::hasColumn('wbcms_cms_api_token_activity_logs', $column), 'Missing cms_api_token_activity_logs column: '.$column);
    }
  }

  #[Test]
  public function test_update_migration_adds_capability_columns_to_existing_cms_api_tokens_table(): void
  {
    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_24_100000_add_capabilities_to_cms_api_tokens_table.php');

    $migration->up();

    $this->assertTrue(Schema::hasColumn('wbcms_cms_api_tokens', 'capabilities'));
    $this->assertTrue(Schema::hasColumn('wbcms_cms_api_tokens', 'last_used_user_agent'));
  }

  public function test_update_migration_keeps_existing_cms_api_tokens_table(): void
  {
    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_23_121000_ensure_cms_api_tokens_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_cms_api_tokens'));
    $this->assertTrue(Schema::hasColumn('wbcms_cms_api_tokens', 'token_preview'));
  }

  #[Test]
  public function sites_public_theme_update_migration_adds_missing_column_to_existing_sites_table(): void
  {
    Schema::table('wbcms_sites', function ($table): void {
      $table->dropColumn('public_theme_preset');
    });

    $this->assertFalse(Schema::hasColumn('wbcms_sites', 'public_theme_preset'));

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_27_120000_ensure_sites_public_theme_preset.php');
    $migration->up();

    $this->assertTrue(Schema::hasColumn('wbcms_sites', 'public_theme_preset'));

    DB::table('wbcms_sites')->where('handle', 'default')->update(['public_theme_preset' => 'atlas']);

    $this->assertSame('atlas', DB::table('wbcms_sites')->where('handle', 'default')->value('public_theme_preset'));
  }
}
