<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PublicSharedSlotResolver;
use WebBlocks\Cms\Support\Sites\SiteTokenRenderer;
use WebBlocks\Cms\Tests\TestCase;

class PublicBlockTreePerformanceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function shared_tree_translation_work_grows_once_per_node_not_with_ancestor_count(): void
  {
    $en = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $tr = Locale::query()->create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::query()->create(['name' => 'Synthetic studio', 'handle' => 'tree', 'is_primary' => true]);
    $visits = [];
    $queryCounts = [];
    foreach ([1, 4, 8] as $depth) {
      $slot = $this->slot($site, $en, $tr, $depth);
      $counter = new class(app(BlockTranslationRegistry::class), app(LocaleResolver::class), app(SiteTokenRenderer::class)) extends BlockTranslationResolver
      {
        public array $visited = [];

        public function resolve(Block $block, Locale|string|null $locale = null, ?Site $site = null): Block
        {
          $this->visited[] = $block->id;

          return parent::resolve($block, $locale, $site);
        }
      };
      app()->instance('request', Request::create('/tr/tree-'.$depth));
      DB::flushQueryLog();
      DB::enableQueryLog();
      $start = hrtime(true);
      try {
        $tree = (new PublicSharedSlotResolver($counter))->resolve($slot, $tr);
        $queries = DB::getQueryLog();
      } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
      }
      $visits[$depth] = count($counter->visited);
      $queryCounts[$depth] = count(array_filter($queries, fn ($query) => str_contains($query['query'], 'wbcms_locales')));
      $this->assertSame([1, $depth + 2, $depth + 1], $tree->pluck('id')->all());
      $node = $tree->first();
      for ($id = 1; $id <= $depth; $id++) {
        $this->assertSame($id, $node->id);
        $this->assertSame($id % 2 === 0 ? 'Türkçe '.$id : 'English '.$id, $node->title);
        $this->assertSame($id % 2 === 0 ? 'translated' : 'fallback', $node->translation_state);
        $this->assertSame('tr', $node->requested_locale_code);
        $node = $node->children->first();
      }
      $this->assertNull($node);
      // Resolver clones assignments rather than mutating source/other render contexts.
      $this->assertSame('Stored', $slot->sharedSlot->slotBlocks->first()->block->title);

      $report = getenv('CMS_PERFORMANCE_REPORT');
      if (is_string($report) && $report !== '') {
        file_put_contents($report, json_encode([
          'scenario' => 'shared-tree', 'depth' => $depth, 'nodes' => $depth + 2,
          'resolver_visits' => $visits[$depth], 'queries' => count($queries),
          'sql_ms' => round(array_sum(array_column($queries, 'time')), 3),
          'render_ms' => round((hrtime(true) - $start) / 1e6, 3),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
      }
    }
    foreach ($visits as $depth => $count) {
      $this->assertSame($depth + 2, $count);
      $this->assertSame($queryCounts[1], $queryCounts[$depth]);
    }
  }

  private function slot(Site $site, Locale $en, Locale $tr, int $depth): PageSlot
  {
    $page = new Page(['site_id' => $site->id]);
    $page->setRelation('site', $site);
    $shared = new SharedSlot(['site_id' => $site->id, 'is_active' => true, 'slot_name' => 'header']);
    $assignments = collect();
    for ($id = 1; $id <= $depth + 4; $id++) {
      $block = new Block(['type' => 'plain_text', 'title' => 'Stored', 'status' => $id === $depth + 3 ? 'draft' : 'published']);
      $block->id = $id;
      $block->setRelation('blockType', new BlockType(['slug' => 'plain_text']));
      $block->setRelation('textTranslations', collect([
        new BlockTextTranslation(['locale_id' => $en->id, 'title' => 'English '.$id]),
        ...($id % 2 === 0 ? [new BlockTextTranslation(['locale_id' => $tr->id, 'title' => 'Türkçe '.$id])] : []),
      ]));
      $assignment = new SharedSlotBlock(['parent_id' => $id <= $depth && $id > 1 ? $id - 1 : ($id === $depth + 4 ? $depth + 3 : null), 'sort_order' => $id === $depth + 2 ? 1 : $id]);
      $assignment->id = $id;
      $assignment->setRelation('block', $block);
      $assignments->push($assignment);
    }
    $shared->setRelation('slotBlocks', $assignments);
    $slot = new PageSlot(['source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT]);
    $slot->setRelation('page', $page);
    $slot->setRelation('sharedSlot', $shared);
    $slot->setRelation('slotType', new SlotType(['slug' => 'header']));

    return $slot;
  }
}
