<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class EngagementUpdateMigrationTest extends TestCase
{
  #[Test]
  public function update_migration_ensures_prefixed_engagement_tables_idempotently(): void
  {
    foreach (['wbcms_sites', 'wbcms_pages', 'wbcms_blocks', 'users'] as $tableName) {
      Schema::create($tableName, function (Blueprint $table): void {
        $table->id();
      });
    }

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_07_12_140000_ensure_prefixed_engagement_tables.php';

    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_comment_entries'));
    $this->assertTrue(Schema::hasTable('wbcms_content_ratings'));
    $this->assertTrue(Schema::hasColumns('wbcms_comment_entries', ['body', 'status', 'spam_score']));
    $this->assertTrue(Schema::hasColumns('wbcms_content_ratings', ['rating_value', 'rating_max', 'status']));
  }
}
