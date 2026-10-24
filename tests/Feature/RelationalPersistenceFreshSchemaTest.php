<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Relational\PersistentFields;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Tests\TestCase;

class RelationalPersistenceFreshSchemaTest extends TestCase
{
  protected bool $cacheFreshDatabaseSchema = false;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function fresh_install_has_no_legacy_fields_and_new_records_use_sql_with_cleanup_metadata(): void
  {
    foreach (PersistentFields::MODELS as [$owner, $table, $attributes]) {
      foreach (array_keys($attributes) as $attribute) {
        $this->assertFalse(Schema::hasColumn($table, $attribute));
      }
    }
    foreach ([new Block, new EmbeddedApplication] as $model) {
      foreach (SettingDefinitions::attributes($model) as $attribute) {
        $this->assertFalse(Schema::hasColumn($model->getTable(), $attribute));
      }
    }
    foreach (Schema::getTables() as $table) {
      if (! str_starts_with($table['name'], 'wbcms_')) {
        continue;
      }
      foreach (Schema::getColumns($table['name']) as $column) {
        $this->assertNotSame('json', $column['type_name'], $table['name'].'.'.$column['name']);
      }
    }
    if (DB::getDriverName() === 'mysql') {
      $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND LOWER(CHECK_CLAUSE) LIKE '%json_valid%'");
      $this->assertSame([], $constraints);
    }
    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft', 'settings' => ['public_shell' => 'default', 'extension' => ['items' => [false, null, 1, '1']]]]);
    $block = Block::create(['page_id' => $page->id, 'type' => 'hero', 'settings' => '{"columns":"3","autoplay":false}', 'meta' => '{"source":"synthetic"}']);
    $revision = app(PageRevisionManager::class)->capture($page);
    $this->assertSame([false, null, 1, '1'], $page->fresh()->settings['extension']['items']);
    $this->assertSame('3', $block->fresh()->setting('columns'));
    $this->assertArrayNotHasKey('settings', $block->fresh()->makeHidden('settings')->toArray());
    $this->assertSame(['id' => $block->id], $block->fresh()->setVisible(['id'])->toArray());
    $this->assertSame('3', json_decode($revision->fresh()->snapshot['blocks'][0]['settings'], true)['columns']);
    $this->assertSame($revision->id, app(PageRevisionManager::class)->capture($page->fresh())->id);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $overview = app(MaintenanceCleanup::class)->overview();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertSame($revision->snapshot_bytes, $overview['site_growth'][0]['page_revision_bytes']);
    foreach ($queries as $query) {
      $this->assertStringNotContainsString('LENGTH(snapshot)', $query['query']);
      $this->assertStringNotContainsString('offset', strtolower($query['query']));
    }
  }
}
