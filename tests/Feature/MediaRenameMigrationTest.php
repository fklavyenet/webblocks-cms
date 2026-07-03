<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaRenameMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function migration_repairs_partial_media_rename_state_and_preserves_block_media_cascade_behavior(): void
  {
    $driver = Schema::getConnection()->getDriverName();
    $migration = require database_path('migrations/2026_05_14_120000_rename_assets_to_media.php');
    $migration->down();

    Schema::rename('wbcms_asset_folders', 'wbcms_media_folders');
    Schema::rename('wbcms_assets', 'wbcms_media');
    Schema::rename('wbcms_block_assets', 'wbcms_block_media');

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
      Schema::table('wbcms_media', function ($table): void {
        $table->dropForeign(['folder_id']);
      });

      Schema::table('wbcms_blocks', function ($table): void {
        $table->dropForeign(['asset_id']);
      });

      Schema::table('wbcms_block_media', function ($table): void {
        $table->dropForeign(['asset_id']);
        $table->dropForeign(['block_id']);
      });

      DB::statement('ALTER TABLE `wbcms_blocks` CHANGE `asset_id` `media_id` BIGINT UNSIGNED NULL');
      DB::statement('ALTER TABLE `wbcms_block_media` CHANGE `asset_id` `media_id` BIGINT UNSIGNED NOT NULL');
    } else {
      Schema::table('wbcms_blocks', function ($table): void {
        $table->renameColumn('asset_id', 'media_id');
      });

      Schema::table('wbcms_block_media', function ($table): void {
        $table->renameColumn('asset_id', 'media_id');
      });
    }

    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_media'));
    $this->assertTrue(Schema::hasTable('wbcms_media_folders'));
    $this->assertTrue(Schema::hasTable('wbcms_block_media'));
    $this->assertFalse(Schema::hasTable('wbcms_assets'));
    $this->assertFalse(Schema::hasTable('wbcms_asset_folders'));
    $this->assertFalse(Schema::hasTable('wbcms_block_assets'));
    $this->assertTrue(Schema::hasColumn('wbcms_block_media', 'media_id'));

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
      $blockMediaColumn = DB::selectOne(
        "SELECT IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wbcms_block_media' AND COLUMN_NAME = 'media_id'"
      );

      $blockMediaForeign = DB::selectOne(
        "SELECT r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME AND k.TABLE_NAME = r.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'wbcms_block_media' AND k.COLUMN_NAME = 'media_id' AND k.REFERENCED_TABLE_NAME = 'wbcms_media' LIMIT 1"
      );

      $blocksColumn = DB::selectOne(
        "SELECT IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wbcms_blocks' AND COLUMN_NAME = 'media_id'"
      );

      $blocksForeign = DB::selectOne(
        "SELECT r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME AND k.TABLE_NAME = r.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'wbcms_blocks' AND k.COLUMN_NAME = 'media_id' AND k.REFERENCED_TABLE_NAME = 'wbcms_media' LIMIT 1"
      );

      $this->assertSame('NO', $blockMediaColumn?->is_nullable);
      $this->assertSame('CASCADE', $blockMediaForeign?->delete_rule);
      $this->assertSame('YES', $blocksColumn?->is_nullable);
      $this->assertSame('SET NULL', $blocksForeign?->delete_rule);
    } else {
      $this->assertTrue(Schema::getColumnType('wbcms_blocks', 'media_id') === 'integer');
    }
  }

  #[Test]
  public function migration_default_sql_builder_omits_null_defaults_for_nullable_bigint_columns(): void
  {
    $migration = require database_path('migrations/2026_05_14_120000_rename_assets_to_media.php');
    $method = new \ReflectionMethod($migration, 'defaultSql');
    $method->setAccessible(true);

    $sql = $method->invoke($migration, (object) [
      'data_type' => 'bigint',
      'column_default' => null,
    ]);

    $this->assertSame('', $sql);
    $this->assertStringNotContainsString("DEFAULT 'NULL'", $sql);
  }

  #[Test]
  public function migration_renames_nullable_block_media_foreign_key_without_invalid_null_default_sql(): void
  {
    $driver = Schema::getConnection()->getDriverName();

    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
      $this->markTestSkipped('This regression only applies to MySQL/MariaDB raw ALTER TABLE rename SQL.');
    }

    $migration = require database_path('migrations/2026_05_14_120000_rename_assets_to_media.php');
    $migration->down();

    Schema::table('wbcms_blocks', function ($table): void {
      $table->dropForeign(['asset_id']);
    });

    DB::statement('ALTER TABLE `wbcms_blocks` CHANGE `asset_id` `media_id` BIGINT UNSIGNED NULL DEFAULT NULL');

    $migration->up();

    $this->assertTrue(Schema::hasColumn('wbcms_blocks', 'media_id'));

    $blocksColumn = DB::selectOne(
      "SELECT IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wbcms_blocks' AND COLUMN_NAME = 'media_id'"
    );

    $blocksForeign = DB::selectOne(
      "SELECT r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME AND k.TABLE_NAME = r.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'wbcms_blocks' AND k.COLUMN_NAME = 'media_id' AND k.REFERENCED_TABLE_NAME = 'wbcms_media' LIMIT 1"
    );

    $this->assertSame('YES', $blocksColumn?->is_nullable);
    $this->assertNull($blocksColumn?->column_default);
    $this->assertSame('SET NULL', $blocksForeign?->delete_rule);
  }
}
