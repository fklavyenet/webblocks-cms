<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Existing installs get list_excerpt through System Update, not through the
 * fresh-install schema, so the update path is the one that decides whether a
 * live site can use the field at all.
 */
class PageTranslationListExcerptUpdateMigrationTest extends TestCase
{
  #[Test]
  public function update_migration_adds_the_column_idempotently(): void
  {
    Schema::create('wbcms_page_translations', function (Blueprint $table): void {
      $table->id();
      $table->text('seo_keywords')->nullable();
    });

    $migration = $this->migration();

    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasColumn('wbcms_page_translations', 'list_excerpt'));
  }

  #[Test]
  public function update_migration_is_a_no_op_without_the_table(): void
  {
    $this->migration()->up();

    $this->assertFalse(Schema::hasTable('wbcms_page_translations'));
  }

  #[Test]
  public function update_migration_rolls_back(): void
  {
    Schema::create('wbcms_page_translations', function (Blueprint $table): void {
      $table->id();
      $table->text('seo_keywords')->nullable();
    });

    $migration = $this->migration();
    $migration->up();
    $migration->down();
    $migration->down();

    $this->assertFalse(Schema::hasColumn('wbcms_page_translations', 'list_excerpt'));
  }

  private function migration(): object
  {
    return require dirname(__DIR__, 2).'/database/migrations/updates/2026_08_09_120000_ensure_page_translation_list_excerpt_column.php';
  }
}
