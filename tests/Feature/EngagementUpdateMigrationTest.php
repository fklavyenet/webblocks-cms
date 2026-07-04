<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EngagementUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function package_update_migration_creates_missing_engagement_tables(): void
  {
    Schema::dropIfExists('wbcms_content_ratings');
    Schema::dropIfExists('wbcms_comment_entries');
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

  #[Test]
  public function package_update_migrations_repair_partially_created_prefixed_engagement_tables(): void
  {
    Schema::dropIfExists('wbcms_content_ratings');
    Schema::dropIfExists('wbcms_comment_entries');
    Schema::dropIfExists('content_ratings');
    Schema::dropIfExists('comment_entries');

    Schema::create('content_ratings', function (Blueprint $table): void {
      $table->id();
      $table->unsignedBigInteger('site_id')->nullable();
      $table->unsignedBigInteger('page_id')->nullable();
      $table->unsignedBigInteger('block_id')->nullable();
      $table->unsignedTinyInteger('rating_value');
      $table->unsignedTinyInteger('rating_max')->default(5);
      $table->string('status')->default('active');
      $table->text('source_url')->nullable();
      $table->string('visitor_hash', 64)->nullable();
      $table->string('ip_hash', 64)->nullable();
      $table->text('user_agent')->nullable();
      $table->timestamps();
    });

    $engagementMigration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_30_130000_ensure_engagement_comments_and_ratings_tables.php');
    $prefixMigration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_03_120000_prefix_webblocks_cms_tables.php');
    $repairMigration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_04_090000_repair_engagement_table_indexes.php');

    $engagementMigration->up();
    $prefixMigration->up();
    $repairMigration->up();
    $repairMigration->up();

    $this->assertFalse(Schema::hasTable('content_ratings'));
    $this->assertFalse(Schema::hasTable('comment_entries'));
    $this->assertTrue(Schema::hasTable('wbcms_content_ratings'));
    $this->assertTrue(Schema::hasTable('wbcms_comment_entries'));

    $this->assertTrue($this->hasIndex('wbcms_content_ratings', 'content_ratings_block_visitor_unique'));
    $this->assertTrue($this->hasIndexOnColumns('wbcms_content_ratings', ['site_id', 'created_at']));
    $this->assertTrue($this->hasIndexOnColumns('wbcms_content_ratings', ['block_id', 'status', 'rating_value']));
    $this->assertTrue($this->hasIndexOnColumns('wbcms_comment_entries', ['site_id', 'created_at']));
    $this->assertTrue($this->hasIndexOnColumns('wbcms_comment_entries', ['block_id', 'status', 'created_at']));
  }

  private function hasIndex(string $table, string $index): bool
  {
    $driver = DB::getDriverName();

    return match ($driver) {
      'mysql', 'mariadb' => DB::table('information_schema.statistics')
        ->where('table_schema', DB::raw('database()'))
        ->where('table_name', $table)
        ->where('index_name', $index)
        ->exists(),
      'sqlite' => collect(DB::select("pragma index_list('{$table}')"))
        ->contains(fn (object $row): bool => ($row->name ?? null) === $index),
      default => false,
    };
  }

  /**
   * @param  array<int, string>  $columns
   */
  private function hasIndexOnColumns(string $table, array $columns): bool
  {
    if (DB::getDriverName() !== 'sqlite') {
      return true;
    }

    foreach (DB::select("pragma index_list('{$table}')") as $index) {
      $name = (string) ($index->name ?? '');

      if ($name === '') {
        continue;
      }

      $indexColumns = collect(DB::select("pragma index_info('{$name}')"))
        ->sortBy('seqno')
        ->map(fn (object $row): string => (string) $row->name)
        ->values()
        ->all();

      if ($indexColumns === $columns) {
        return true;
      }
    }

    return false;
  }
}
