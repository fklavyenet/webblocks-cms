<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use WebBlocks\Cms\Tests\TestCase;

class RenameHtmlClassesToCssClassesMigrationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[TestWith(['database/migrations/2026_07_31_140000_rename_html_classes_to_css_classes_on_page_layout_slots.php'])]
  #[TestWith(['database/migrations/updates/2026_07_31_140000_ensure_page_layout_slots_css_classes_column.php'])]
  #[Test]
  public function it_renames_the_column_on_a_pre_upgrade_schema_idempotently_and_reversibly(string $relativePath): void
  {
    // The fresh schema already ships css_classes; simulate a site still on
    // the pre-rename column, the real state this migration has to handle.
    Schema::table('wbcms_page_layout_slots', function ($table) {
      $table->renameColumn('css_classes', 'html_classes');
    });

    $this->assertTrue(Schema::hasColumn('wbcms_page_layout_slots', 'html_classes'));
    $this->assertFalse(Schema::hasColumn('wbcms_page_layout_slots', 'css_classes'));

    $migration = $this->loadMigration($relativePath);
    $migration->up();

    $this->assertFalse(Schema::hasColumn('wbcms_page_layout_slots', 'html_classes'));
    $this->assertTrue(Schema::hasColumn('wbcms_page_layout_slots', 'css_classes'));

    // Re-running must be a no-op, not an error -- this is what actually runs
    // whenever a site's next System Update replays package update migrations.
    $migration->up();

    $this->assertFalse(Schema::hasColumn('wbcms_page_layout_slots', 'html_classes'));
    $this->assertTrue(Schema::hasColumn('wbcms_page_layout_slots', 'css_classes'));

    $migration->down();

    $this->assertTrue(Schema::hasColumn('wbcms_page_layout_slots', 'html_classes'));
    $this->assertFalse(Schema::hasColumn('wbcms_page_layout_slots', 'css_classes'));
  }

  #[TestWith(['database/migrations/2026_07_31_140000_rename_html_classes_to_css_classes_on_page_layout_slots.php'])]
  #[TestWith(['database/migrations/updates/2026_07_31_140000_ensure_page_layout_slots_css_classes_column.php'])]
  #[Test]
  public function it_is_a_no_op_on_a_fresh_install_that_already_has_css_classes(string $relativePath): void
  {
    $this->assertTrue(Schema::hasColumn('wbcms_page_layout_slots', 'css_classes'));

    $migration = $this->loadMigration($relativePath);
    $migration->up();

    $this->assertTrue(Schema::hasColumn('wbcms_page_layout_slots', 'css_classes'));
    $this->assertFalse(Schema::hasColumn('wbcms_page_layout_slots', 'html_classes'));
  }

  private function loadMigration(string $relativePath): Migration
  {
    return require dirname(__DIR__, 2).'/'.$relativePath;
  }
}
