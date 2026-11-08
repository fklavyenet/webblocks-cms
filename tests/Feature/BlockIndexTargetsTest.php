<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Revisions\RestoredIndexTargets;
use WebBlocks\Cms\Support\Search\BlockIndexTargets;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\TestCase;

class BlockIndexTargetsTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function copies_reuse_the_page_but_reread_the_block_and_isolate_returned_models(): void
  {
    [$block, $page] = $this->fixtures();
    $reads = 0;
    DB::listen(function ($query) use (&$reads): void {
      if (preg_match('/^select .*from ["`]wbcms_pages["`]/i', $query->sql)) {
      $reads++;
      }
    });
    $this->during(function () use ($block, $page): void {
      $targets = app(BlockIndexTargets::class);
      $first = $targets->freshForIndex($block);
      $this->assertSame($page->id, $first->page->id);
      $first->page->page_type = Page::TYPE_SHARED_SLOT_SOURCE;
      DB::table($block->getTable())->where('id', $block->id)->update(['title' => 'Raw title']);
      $next = $targets->freshForIndex($block);
      $this->assertSame('Raw title', $next->title);
      $this->assertFalse($next->page->isSharedSlotSourcePage());
      $this->assertSame($page->id, $targets->freshForIndex($block)->page->id);
    });
    $this->assertSame(1, $reads);
    app(BlockIndexTargets::class)->freshForIndex($block);
    $this->assertSame(2, $reads, 'Ordinary saves must use their original fresh-read path.');
  }

  #[Test]
  public function nontransactional_or_noncoalesced_copies_keep_the_original_read_path(): void
  {
    [$block] = $this->fixtures();
    $reads = 0;
    DB::listen(function ($query) use (&$reads): void {
      if (preg_match('/^select .*from ["`]wbcms_pages["`]/i', $query->sql)) {
      $reads++;
      }
    });
    $work = function () use ($block): void {
      app(BlockIndexTargets::class)->freshForIndex($block);
      app(BlockIndexTargets::class)->freshForIndex($block);
    };
    DB::transaction(fn () => app(BlockIndexTargets::class)->during($work));
    $this->assertSame(2, $reads);
    PublicSearchIndexer::coalescing(fn () => app(BlockIndexTargets::class)->during($work));
    $this->assertSame(4, $reads);
  }

  #[Test]
  public function saved_hooks_target_raw_moves_and_updated_shared_slot_runtime_data(): void
  {
    [$block, , $other] = $this->fixtures();
    $indexer = app(RecordingContentTargetIndexer::class);
    app()->instance(PublicSearchIndexer::class, $indexer);
    Block::updating(function (Block $saved) use ($other): void {
      if ($saved->title === 'Move in observer') {
        DB::table($saved->getTable())->where('id', $saved->id)->update(['page_id' => $other->id]);
      }
    });
    $this->during(function () use ($block, $other): void {
      $block->update(['title' => 'Move in observer']);
      DB::table($other->getTable())->where('id', $other->id)->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
      DB::table('wbcms_page_runtime_settings')->where('page_id', $other->id)->update(['shared_slot_reference' => 77, 'shared_slot_reference_kind' => 'integer']);
      $block->update(['title' => 'Shared target']);
      DB::table('wbcms_page_runtime_settings')->where('page_id', $other->id)->update(['shared_slot_reference' => 88]);
      $block->update(['title' => 'Runtime target']);
    });
    $this->assertSame([['page', $other->id], ['shared', 77], ['shared', 88]], $indexer->targets);
  }

  #[Test]
  public function persistent_page_edits_and_savepoint_rollbacks_invalidate_cached_pages(): void
  {
    [$block, $page] = $this->fixtures();
    $this->during(function () use ($block, $page): void {
      $targets = app(BlockIndexTargets::class);
      $this->assertSame('before', data_get($targets->freshForIndex($block)->page->settings, 'plugin.value'));
      $id = DB::table('wbcms_persistent_documents')->where('page_id', $page->id)->where('attribute', 'settings')->value('id');
      DB::table('wbcms_persistent_nodes')->where('document_id', $id)->where('key_text', 'value')->update(['value' => 'after']);
      $this->assertSame('after', data_get($targets->freshForIndex($block)->page->settings, 'plugin.value'));
      try {
        DB::transaction(function () use ($block, $page, $targets): void {
          DB::table($page->getTable())->where('id', $page->id)->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
          $this->assertTrue($targets->freshForIndex($block)->page->isSharedSlotSourcePage());
          throw new RuntimeException('Synthetic savepoint rollback');
        });
      } catch (RuntimeException) {
      }
      $this->assertFalse($targets->freshForIndex($block)->page->isSharedSlotSourcePage());
    });
  }

  #[Test]
  public function writes_during_page_hydration_and_global_scopes_cannot_leave_stale_targets(): void
  {
    [$block, $page] = $this->fixtures();
    $changed = false;
    Page::retrieved(function (Page $row) use ($page, &$changed): void {
      if ($row->id === $page->id && ! $changed) {
        $changed = true;
        DB::table($row->getTable())->where('id', $row->id)->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
      }
    });
    $this->during(function () use ($block): void {
      $targets = app(BlockIndexTargets::class);
      $this->assertFalse($targets->freshForIndex($block)->page->isSharedSlotSourcePage());
      $this->assertTrue($targets->freshForIndex($block)->page->isSharedSlotSourcePage());
    });
    $scopes = Page::getAllGlobalScopes();
    $visible = false;
    Page::addGlobalScope('target-visibility', function (Builder $query) use (&$visible): void {
      if (! $visible) {
      $query->whereRaw('1 = 0');
      }
    });
    try {
      $this->during(function () use ($block, &$visible): void {
        $this->assertNull(app(BlockIndexTargets::class)->freshForIndex($block)->page);
        $visible = true;
        $this->assertNotNull(app(BlockIndexTargets::class)->freshForIndex($block)->page);
      });
    } finally {
      Page::setAllGlobalScopes($scopes);
    }
  }

  #[Test]
  public function failed_scopes_do_not_reuse_pages_after_the_scope_has_ended(): void
  {
    [$block] = $this->fixtures();
    $reads = 0;
    DB::listen(function ($query) use (&$reads): void {
      if (preg_match('/^select .*from ["`]wbcms_pages["`]/i', $query->sql)) {
      $reads++;
      }
    });
    PublicSearchIndexer::coalescing(fn () => DB::transaction(function () use ($block): void {
      $targets = app(BlockIndexTargets::class);
      try {
        $targets->during(function () use ($targets, $block): void {
          $targets->freshForIndex($block);
          throw new RuntimeException('Synthetic copy failure');
        });
      } catch (RuntimeException) {
      }
      $targets->freshForIndex($block);
      $targets->freshForIndex($block);
    }));
    $this->assertSame(3, $reads);
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  #[Test]
  public function copied_block_creation_uses_the_actual_sql_owner_after_a_created_observer_move(): void
  {
    [, $page, $other] = $this->fixtures();
    $indexer = app(RecordingContentTargetIndexer::class);
    app()->instance(PublicSearchIndexer::class, $indexer);
    Block::created(function (Block $block) use ($other): void {
      DB::table($block->getTable())->where('id', $block->id)->update(['page_id' => $other->id]);
    });
    $this->during(function () use ($page): void {
      $block = new Block(['page_id' => $page->id, 'type' => 'plain_text']);
      $block->setRelation('page', $page);
      $this->assertTrue(app(RestoredIndexTargets::class)->creating($block, fn () => $block->createInTransaction()));
    });
    $this->assertSame([['page', $other->id]], $indexer->targets);
  }

  #[Test]
  public function copy_page_translations_are_independent_and_read_observers_retain_native_hydration(): void
  {
    [$block] = $this->fixtures();
    $this->during(function () use ($block): void {
      $targets = app(BlockIndexTargets::class);
      $first = $targets->freshForIndex($block);
      $first->page->translations->first()->name = 'Memory mutation';
      $this->assertSame('First', $targets->freshForIndex($block)->page->translations->first()->name);
      $reads = 0;
      PageTranslation::retrieved(function () use (&$reads): void {
        $reads++;
      });
      $targets->freshForIndex($block);
      $targets->freshForIndex($block);
      $this->assertSame(2, $reads);
    });
  }

  private function during(callable $work): mixed
  {
    return PublicSearchIndexer::coalescing(fn () => DB::transaction(fn () => app(BlockIndexTargets::class)->during($work)));
  }

  private function fixtures(): array
  {
    $site = Site::create(['name' => 'Synthetic targets', 'handle' => 'index-targets']);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'First', 'status' => 'draft', 'settings' => ['plugin' => ['value' => 'before']]]);
    $other = Page::create(['site_id' => $site->id, 'title' => 'Other', 'status' => 'draft', 'settings' => ['shared_slot_id' => 77]]);
    $block = Block::create(['page_id' => $page->id, 'type' => 'plain_text', 'title' => 'Original']);

    return [$block, $page, $other];
  }
}

class RecordingContentTargetIndexer extends PublicSearchIndexer
{
  public array $targets = [];

  public function refreshPage(Page|int $page, ?Locale $locale = null): void
  {
    $this->targets[] = ['page', $page instanceof Page ? (int) $page->id : $page];
    parent::refreshPage($page, $locale);
  }

  public function refreshSharedSlot(SharedSlot|int $sharedSlot): void
  {
    $this->targets[] = ['shared', $sharedSlot instanceof SharedSlot ? (int) $sharedSlot->id : $sharedSlot];
    parent::refreshSharedSlot($sharedSlot);
  }
}
