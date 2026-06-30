<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EngagementUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function package_update_migration_creates_missing_engagement_tables(): void
  {
    Schema::dropIfExists('content_ratings');
    Schema::dropIfExists('comment_entries');

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_30_130000_ensure_engagement_comments_and_ratings_tables.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('comment_entries'));
    $this->assertTrue(Schema::hasTable('content_ratings'));

    foreach ([
      'id',
      'site_id',
      'page_id',
      'block_id',
      'author_name',
      'body',
      'status',
      'visitor_hash',
      'ip_hash',
      'spam_score',
      'spam_reasons',
      'approved_at',
      'approved_by_user_id',
      'created_at',
      'updated_at',
    ] as $column) {
      $this->assertTrue(Schema::hasColumn('comment_entries', $column), 'Missing comment_entries column: '.$column);
    }

    foreach ([
      'id',
      'site_id',
      'page_id',
      'block_id',
      'rating_value',
      'rating_max',
      'status',
      'visitor_hash',
      'ip_hash',
      'created_at',
      'updated_at',
    ] as $column) {
      $this->assertTrue(Schema::hasColumn('content_ratings', $column), 'Missing content_ratings column: '.$column);
    }
  }
}
