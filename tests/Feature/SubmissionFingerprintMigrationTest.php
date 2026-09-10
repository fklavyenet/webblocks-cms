<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Tests\TestCase;

class SubmissionFingerprintMigrationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public function test_fresh_schema_contains_local_submission_reputation_fields(): void
  {
    $this->assertTrue(Schema::hasColumns('wbcms_submission_fingerprints', [
      'site_id',
      'exact_hash',
      'simhash',
      'occurrences',
      'spam_count',
      'ham_count',
      'last_seen_at',
    ]));
  }

  public function test_update_migration_creates_the_same_table(): void
  {
    Schema::drop('wbcms_submission_fingerprints');

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_10_010000_create_submission_fingerprints_table.php';
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_submission_fingerprints'));
  }
}
