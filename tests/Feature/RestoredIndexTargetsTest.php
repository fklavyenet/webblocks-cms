<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RestoredIndexTargetsTest extends TestCase
{
  private bool $watch = false;

  private int $blockReads = 0;

  private int $pageReads = 0;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(RestoredIndexTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    DB::listen(function ($query): void {
      if ($this->watch) {
        $this->blockReads += (int) preg_match('/^select .*from ["`]wbcms_blocks["`]/i', $query->sql);
        $this->pageReads += (int) preg_match('/^select .*from ["`]wbcms_pages["`]/i', $query->sql);
      }
    });
  }

  public static function batchSizes(): array
  {
    return [[6], [301]];
  }

  #[Test]
  #[DataProvider('batchSizes')]
  public function new_blocks_keep_every_write_event_without_reloading_blocks_or_sharing_mutable_page_models(int $count): void
  {
    [$page, , $indexer] = $this->fixture();
    $indexer->mutate = true;
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      Block::{$event}(function () use (&$events, $event): void {
        $events[$event] = ($events[$event] ?? 0) + 1;
      });
    }
    app(RevisionContentUpdates::class)->during(function () use ($page, $count): void {
      $this->watch = true;
      for ($i = 0; $i < $count; $i++) {
        $this->create($page);
      }
      $this->watch = false;
    });
    $this->assertSame(0, $this->blockReads);
    $this->assertSame(1, $this->pageReads);
    $this->assertSame(array_fill_keys(['saving', 'creating', 'created', 'saved'], $count), $events);
    $this->assertSame(array_fill(0, $count, ['page', $page->id]), $indexer->targets);
    $this->assertSame(array_fill(0, $count, 'First'), array_column($indexer->seen, 'name'));
    $this->assertSame(array_fill(0, $count, 'Before'), array_column($indexer->seen, 'marker'));
    $this->assertSame(Page::TYPE_DEFAULT, $page->fresh()->page_type);
    $this->assertSame($count, $page->blocks()->count());
  }

  #[Test]
  public function a_created_observer_raw_move_overrides_even_a_preloaded_page_relation(): void
  {
    [$page, $other, $indexer] = $this->fixture();
    Block::created(function (Block $block) use ($page, $other): void {
      $block->setRelation('page', $page);
      DB::table($block->getTable())->where('id', $block->id)->update(['page_id' => $other->id]);
    });
    app(RevisionContentUpdates::class)->during(function () use ($page): void {
      $this->watch = true;
      $this->create($page);
      $this->watch = false;
    });
    $this->assertSame([['page', $other->id]], $indexer->targets);
    $this->assertGreaterThan(0, $this->blockReads);
    $this->assertSame(1, $other->blocks()->count());
  }

  #[Test]
  public function an_in_memory_parent_change_cannot_replace_the_actual_insert_target(): void
  {
    [$page, $other, $indexer] = $this->fixture();
    Block::created(function (Block $block) use ($other): void {
      $block->page_id = $other->id;
    });
    app(RevisionContentUpdates::class)->during(fn () => $this->create($page));
    $this->assertSame([['page', $page->id]], $indexer->targets);
    $this->assertSame($page->id, DB::table('wbcms_blocks')->sole()->page_id);
  }

  #[Test]
  public function nested_created_callbacks_keep_each_insert_bound_to_its_actual_model_and_page(): void
  {
    [$page, $other, $indexer] = $this->fixture();
    $nested = false;
    Block::created(function () use ($other, &$nested): void {
      if (! $nested) {
        $nested = true;
        $this->create($other);
      }
    });
    app(RevisionContentUpdates::class)->during(fn () => $this->create($page));
    $this->assertSame([['page', $other->id], ['page', $page->id]], $indexer->targets);
    $this->assertSame(1, $page->blocks()->count());
    $this->assertSame(1, $other->blocks()->count());
  }

  #[Test]
  public function shared_slot_runtime_and_page_document_writes_refresh_cached_routes(): void
  {
    [$page, , $indexer] = $this->fixture();
    app(RevisionContentUpdates::class)->during(function () use ($page): void {
      $this->create($page);
      $document = DB::table('wbcms_persistent_documents')->where('page_id', $page->id)->where('attribute', 'settings')->value('id');
      DB::table('wbcms_persistent_nodes')->where('document_id', $document)->where('key_text', 'marker')->update(['value_text' => 'After']);
      $this->create($page);
      DB::table('wbcms_pages')->where('id', $page->id)->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
      DB::table('wbcms_page_runtime_settings')->where('page_id', $page->id)->update(['shared_slot_reference' => 77, 'shared_slot_reference_kind' => 'integer']);
      $this->create($page);
    });
    $this->assertSame([['page', $page->id], ['page', $page->id], ['shared', 77]], $indexer->targets);
    $this->assertSame(['Before', 'After'], array_column($indexer->seen, 'marker'));
  }

  #[Test]
  public function retrieved_observers_keep_the_native_block_and_page_hydration_path(): void
  {
    [$page] = $this->fixture();
    $blocks = $pages = 0;
    Block::retrieved(function () use (&$blocks): void {
      if ($this->watch) {
        $blocks++;
      }
    });
    Page::retrieved(function () use (&$pages): void {
      if ($this->watch) {
        $pages++;
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($page): void {
      $this->watch = true;
      for ($i = 0; $i < 3; $i++) {
        $this->create($page);
      }
      $this->watch = false;
    });
    $this->assertSame(3, $blocks);
    $this->assertSame(3, $pages);
    $this->assertSame(3, $this->blockReads);
  }

  #[Test]
  public function late_global_scopes_cannot_expose_a_cached_hidden_page(): void
  {
    [$page, , $indexer] = $this->fixture();
    $scopes = Page::getAllGlobalScopes();
    $visible = false;
    try {
      app(RevisionContentUpdates::class)->during(function () use ($page, &$visible): void {
        $this->create($page);
        Page::addGlobalScope('restore-route-visibility', function (Builder $query) use (&$visible): void {
          if (! $visible) {
            $query->whereRaw('1 = 0');
          }
        });
        $this->create($page);
        $visible = true;
        $this->create($page);
      });
    } finally {
      Page::setAllGlobalScopes($scopes);
    }
    $this->assertSame([['page', $page->id], ['page', $page->id]], $indexer->targets);
  }

  #[Test]
  public function page_writes_during_hydration_do_not_seed_a_stale_target(): void
  {
    [$page, , $indexer] = $this->fixture();
    $changed = false;
    DB::listen(function ($query) use ($page, &$changed): void {
      if (! $changed && $this->watch && str_starts_with($query->sql, 'select * from "wbcms_pages"')) {
        $changed = true;
        DB::table('wbcms_pages')->where('id', $page->id)->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
        DB::table('wbcms_page_runtime_settings')->where('page_id', $page->id)->update(['shared_slot_reference' => 77, 'shared_slot_reference_kind' => 'integer']);
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($page): void {
      $this->watch = true;
      $this->create($page);
      $this->watch = false;
    });
    $this->assertTrue($changed);
    $this->assertSame([['shared', 77]], $indexer->targets);
  }

  #[Test]
  public function rollback_and_failed_scopes_cannot_reuse_temporary_routes_or_affect_ordinary_saves(): void
  {
    [$page, , $indexer] = $this->fixture();
    app(RevisionContentUpdates::class)->during(function () use ($page): void {
      $this->create($page);
      try {
        DB::transaction(function () use ($page): void {
          DB::table('wbcms_pages')->where('id', $page->id)->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
          DB::table('wbcms_page_runtime_settings')->where('page_id', $page->id)->update(['shared_slot_reference' => 77, 'shared_slot_reference_kind' => 'integer']);
          $this->create($page);
          throw new RuntimeException('Synthetic routing rollback');
        });
      } catch (RuntimeException) {
      }
      $this->create($page);
    });
    $this->assertSame([['page', $page->id], ['shared', 77], ['page', $page->id]], $indexer->targets);
    try {
      app(RevisionContentUpdates::class)->during(function () use ($page): void {
        $this->create($page);
        throw new RuntimeException('Synthetic scope failure');
      });
    } catch (RuntimeException) {
    }
    $this->watch = true;
    Block::create(['page_id' => $page->id, 'type' => 'plain_text']);
    $this->watch = false;
    $this->assertGreaterThan(0, $this->blockReads, 'Ordinary saves keep their native index lookup.');
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  #[Test]
  public function historical_mutable_page_values_keep_independent_native_models(): void
  {
    [$page, , $indexer] = $this->fixture();
    app(PersistentDocuments::class)->write($page, 'settings', ['marker' => 'Before', 'historical' => (object) ['value' => 'Saved']]);
    $indexer->mutate = true;
    app(RevisionContentUpdates::class)->during(function () use ($page): void {
      $this->create($page);
      $this->create($page);
    });
    $this->assertSame(['Saved', 'Saved'], array_column($indexer->seen, 'historical'));
  }

  private function create(Page $page): Block
  {
    $block = new Block(['page_id' => $page->id, 'type' => 'plain_text', 'meta' => '{"source":"New block"}', 'settings' => '{"alignment":"left"}']);
    $this->assertTrue(app(PersistentDocuments::class)->createRestored($block));

    return $block;
  }

  private function fixture(): array
  {
    return PublicSearchIndexer::coalescing(function (): array {
      $site = Site::create(['name' => 'New index fixture', 'handle' => 'new-index-fixture']);
      $locales = collect(['en', 'tr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $page = Page::create(['site_id' => $site->id, 'title' => 'First', 'status' => 'draft', 'settings' => ['marker' => 'Before']]);
      $other = Page::create(['site_id' => $site->id, 'title' => 'Other', 'status' => 'draft']);
      $indexer = app(RestoredTargetRecordingIndexer::class);
      app()->instance(PublicSearchIndexer::class, $indexer);

      return [$page, $other, $indexer];
    });
  }
}

class RestoredTargetRecordingIndexer extends PublicSearchIndexer
{
  public array $targets = [];

  public array $seen = [];

  public bool $mutate = false;

  public function refreshPage(Page|int $page, ?Locale $locale = null): void
  {
    $this->targets[] = ['page', $page instanceof Page ? (int) $page->id : $page];
    if ($page instanceof Page) {
      $settings = $page->settings;
      $this->seen[] = ['marker' => $settings['marker'] ?? null, 'name' => $page->translations->first()?->name, 'historical' => $settings['historical']->value ?? null];
      if ($this->mutate) {
        $page->page_type = Page::TYPE_SHARED_SLOT_SOURCE;
        $page->translations->first()->name = 'Memory only';
        if (is_object($settings['historical'] ?? null)) {
          $settings['historical']->value = 'Memory only';
        }
        $settings['marker'] = 'Memory only';
        $page->settings = $settings;
      }
    }
    parent::refreshPage($page, $locale);
  }

  public function refreshSharedSlot(SharedSlot|int $sharedSlot): void
  {
    $this->targets[] = ['shared', $sharedSlot instanceof SharedSlot ? (int) $sharedSlot->id : $sharedSlot];
    parent::refreshSharedSlot($sharedSlot);
  }
}

class RestoredIndexTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey(): string
  {
    return 'user_id';
  }
}
