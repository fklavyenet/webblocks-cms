<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Tests\TestCase;

class SubmissionDailyTotalsMigrationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public function test_fresh_schema_contains_content_free_daily_totals(): void
  {
    $this->assertSame([
      'id', 'site_id', 'date', 'surface', 'allowed', 'quarantined', 'spam', 'created_at', 'updated_at',
    ], Schema::getColumnListing('wbcms_submission_daily_totals'));
  }

  public function test_update_migration_is_idempotent(): void
  {
    Schema::drop('wbcms_submission_daily_totals');
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_10_020000_create_submission_daily_totals_table.php';
    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_submission_daily_totals'));
  }
}
