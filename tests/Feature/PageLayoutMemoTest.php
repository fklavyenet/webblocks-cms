<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Tests\TestCase;

class PageLayoutMemoTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function repeated_graph_reads_do_not_repeat_queries_or_share_mutable_models(): void
  {
    app(CoreLayoutCatalogSyncer::class)->sync();
    $manager = app(PageLayoutManager::class);
    $layout = $manager->findByHandle('docs');
    $name = $layout->name;
    $slotName = $layout->layoutSlots->first()->label;
    $typeName = $layout->layoutSlots->first()->slotType->name;
    $layout->name = 'Unsaved name';
    $layout->layoutSlots->first()->label = 'Unsaved slot';
    $layout->layoutSlots->first()->slotType->name = 'Unsaved type';
    $this->assertSame([], $this->queries(function () use ($manager, $name, $slotName, $typeName): void {
      for ($index = 0; $index < 30; $index++) {
        // Include the legacy alias and resolve a new consumer each time.
        $copy = app(PageLayoutManager::class)->findByHandle($index % 2 ? 'dashboard' : 'docs');
        $this->assertSame($name, $copy->name);
        $this->assertSame($slotName, $copy->layoutSlots->first()->label);
        $this->assertSame($typeName, $copy->layoutSlots->first()->slotType->name);
        $this->assertTrue($manager->slotsTableExists());
      }
    }));
    $this->assertNull($manager->findByHandle('missing-layout'));
    $this->assertSame([], $this->queries(fn () => $this->assertNull($manager->findByHandle('missing-layout'))));
    app()->instance('request', Request::create('/next'));
    $this->assertNotEmpty($this->queries(fn () => $manager->findByHandle('docs')));
  }

  #[Test]
  public function inactive_flags_writes_rollback_and_slot_changes_remain_visible(): void
  {
    app(CoreLayoutCatalogSyncer::class)->sync();
    $layout = PageLayout::query()->where('handle', 'docs')->firstOrFail();
    $manager = app(PageLayoutManager::class);
    $this->assertNotNull($manager->findByHandle('docs', false));
    $layout->update(['is_active' => false]);
    $this->assertNull($manager->findByHandle('docs', false));
    $this->assertNotNull($manager->findByHandle('docs', true));
    DB::beginTransaction();
    $layout->update(['is_active' => true]);
    $this->assertNotNull($manager->findByHandle('docs', false));
    DB::rollBack();
    $this->assertNull($manager->findByHandle('docs', false));
    $slot = $layout->layoutSlots()->firstOrFail();
    $slot->update(['label' => 'Changed slot']);
    $this->assertSame('Changed slot', $manager->findByHandle('docs')->layoutSlots->first()->label);
    $this->assertNull($manager->findByHandle('new-layout'));
    PageLayout::query()->create(['handle' => 'new-layout', 'name' => 'New', 'is_active' => true, 'shell_type' => 'default']);
    $this->assertSame('New', $manager->findByHandle('new-layout')->name);
    $this->assertNotSame($manager->findByHandle('docs')->id, $manager->findByHandle('new-layout')->id);
  }

  #[Test]
  public function schema_availability_is_invalidated_by_ddl_and_missing_results_are_reused(): void
  {
    $manager = app(PageLayoutManager::class);
    $this->assertTrue($manager->slotsTableExists());
    Schema::drop('wbcms_page_layout_slots');
    $this->assertFalse($manager->slotsTableExists());
    $this->assertSame([], $this->queries(fn () => $this->assertFalse($manager->slotsTableExists())));
    $this->assertTrue($manager->tableExists());
  }

  private function queries(callable $work): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
  }
}
