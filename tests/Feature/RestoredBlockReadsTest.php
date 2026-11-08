<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Revisions\RestoredIndexTargets;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RestoredBlockReadsTest extends TestCase
{
  private bool $watch = false;

  private int $blockReads = 0;

  private int $translationExists = 0;

  private int $pageReads = 0;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(RestoredReadTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    DB::listen(function ($query): void {
      if (! $this->watch) {
        return;
      }
      $this->blockReads += (int) preg_match('/^select .*from ["`]wbcms_blocks["`]/i', $query->sql);
      $this->pageReads += (int) preg_match('/^select .*from ["`]wbcms_pages["`]/i', $query->sql);
      $this->translationExists += (int) (str_starts_with($query->sql, 'select exists') && str_contains($query->sql, 'wbcms_block_text_translations'));
    });
  }

  #[Test]
  public function current_blocks_and_loaded_translations_avoid_duplicate_reads_but_keep_the_save_lock(): void
  {
    [, $block] = $this->fixture();
    $reads = app(RestoredBlockReads::class);
    $this->watch = true;
    DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $original) use ($reads): void {
      $target = $reads->forNormalization($original, false);
      $this->assertSame($original, $target);
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($target);
    }));
    $this->watch = false;
    $this->assertSame(2, $this->blockReads, 'Initial hydration and the normal model save lock both remain.');
    $this->assertSame(0, $this->translationExists);
    $this->assertNull($block->fresh()->getRawOriginal('content'));
    $this->assertSame('Saved en', $block->textTranslations()->where('locale_id', Locale::where('code', 'en')->value('id'))->value('content'));
  }

  #[Test]
  public function raw_metadata_changes_and_deleted_default_translations_are_read_from_current_sql(): void
  {
    [, $block, $locales] = $this->fixture();
    $reads = app(RestoredBlockReads::class);
    $created = 0;
    BlockTextTranslation::created(function () use (&$created): void {
      $created++;
    });
    DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $original) use ($reads, $locales): void {
      $document = DB::table('wbcms_persistent_documents')->where('block_id', $original->id)->value('id');
      DB::table('wbcms_persistent_metadata')->where('document_id', $document)->update(['source' => 'Raw metadata']);
      $target = $reads->forNormalization($original, false);
      $this->assertNotSame($original, $target);
      $this->assertJsonStringEqualsJsonString('{"source":"Raw metadata"}', $target->meta);
      $english = $locales->firstWhere('code', 'en')->id;
      $this->assertTrue($reads->defaultTranslationExists($target, 'textTranslations', $english));
      DB::table('wbcms_block_text_translations')->where('block_id', $original->id)->where('locale_id', $english)->delete();
      $this->assertNull($reads->defaultTranslationExists($target, 'textTranslations', $english));
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($target);
    }));
    $this->assertSame(1, $created, 'Fallback translation creation must still fire its model event.');
    $this->assertSame(2, $block->textTranslations()->count());
    $this->assertNull($block->fresh()->meta, 'Canonical text fields still move to the default translation.');
    $this->assertJsonStringEqualsJsonString('{"source":"Raw metadata"}', $block->textTranslations()->where('locale_id', $locales->firstWhere('code', 'en')->id)->firstOrFail()->meta);
  }

  #[Test]
  public function model_observer_mutations_survive_real_revision_restoration(): void
  {
    [$page, , $locales, $actor] = $this->fixture();
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $active = true;
    $created = 0;
    BlockTextTranslation::created(function (BlockTextTranslation $row) use (&$active, &$created, $locales): void {
      if (! $active) {
        return;
      }
      $created++;
      if ($row->locale_id === $locales->firstWhere('code', 'en')->id) {
        DB::table('wbcms_blocks')->where('id', $row->block_id)->update(['sort_order' => 17]);
        DB::table('wbcms_block_text_translations')->where('id', $row->id)->update(['content' => 'Observer English']);
        $document = DB::table('wbcms_persistent_documents')->where('block_id', $row->block_id)->value('id');
        DB::table('wbcms_persistent_metadata')->where('document_id', $document)->update(['source' => 'Observer metadata']);
      }
    });
    $manager->restore($page, $revision, $actor);
    $active = false;
    $this->assertSame(2, $created);
    $restored = $page->blocks()->sole();
    $this->assertSame('Observer English', $restored->textTranslations()->where('locale_id', $locales->firstWhere('code', 'en')->id)->value('content'));
    $this->assertSame(17, $restored->sort_order);
    $this->assertNull($restored->meta, 'Existing translations retain precedence over canonical text metadata.');
    $latest = $page->revisions()->latest('id')->firstOrFail();
    $this->assertSame('Observer English', $latest->snapshot['blocks'][0]['text_translations'][0]['content']);
    $this->assertCount(3, $page->revisions()->get());
  }

  #[Test]
  public function rollback_and_failed_scopes_do_not_reuse_temporary_values(): void
  {
    [, $block] = $this->fixture();
    $reads = app(RestoredBlockReads::class);
    DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $original) use ($reads): void {
      try {
        DB::transaction(function () use ($original, $reads): void {
          $original->fresh()->update(['meta' => '{"source":"Temporary"}']);
          $this->assertJsonStringEqualsJsonString('{"source":"Temporary"}', $reads->forNormalization($original, false)->meta);
          throw new RuntimeException('Synthetic rollback');
        });
      } catch (RuntimeException) {
      }
      $this->assertJsonStringEqualsJsonString('{"source":"Saved"}', $reads->forNormalization($original, false)->meta);
    }));
    $target = null;
    try {
      DB::transaction(function () use ($reads, $block, &$target): void {
        $reads->duringBlock($block->id, function (Block $original) use ($reads, &$target): void {
          $target = $reads->forNormalization($original, false);
          throw new RuntimeException('Synthetic scope failure');
        });
      });
    } catch (RuntimeException) {
    }
    $this->assertNull($reads->defaultTranslationExists($target, 'textTranslations', Locale::where('code', 'en')->value('id')));
  }

  #[Test]
  public function retrieved_observers_retain_the_original_hydration_path(): void
  {
    [, $block] = $this->fixture();
    $retrieved = 0;
    Block::retrieved(function () use (&$retrieved): void {
      $retrieved++;
    });
    $reads = app(RestoredBlockReads::class);
    $this->watch = true;
    DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $original) use ($reads): void {
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($reads->forNormalization($original, false));
    }));
    $this->watch = false;
    $this->assertSame(3, $this->blockReads);
    $this->assertSame(2, $retrieved);
    $this->assertSame(2, $this->translationExists);
  }

  #[Test]
  public function custom_global_scopes_keep_native_existence_checks(): void
  {
    [, $block] = $this->fixture();
    $scopes = BlockTextTranslation::getAllGlobalScopes();
    BlockTextTranslation::addGlobalScope('synthetic-visibility', fn (Builder $query) => $query->where('locale_id', '>', 0));
    try {
      $reads = app(RestoredBlockReads::class);
      $this->watch = true;
      DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $original) use ($reads): void {
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($reads->forNormalization($original, false));
      }));
      $this->watch = false;
      $this->assertSame(3, $this->blockReads);
      $this->assertSame(2, $this->translationExists);
    } finally {
      BlockTextTranslation::setAllGlobalScopes($scopes);
    }
  }

  #[Test]
  public function a_missing_block_cannot_silently_skip_required_normalization(): void
  {
    [, $block] = $this->fixture();
    $block->delete();
    $this->expectException(ModelNotFoundException::class);
    app(RestoredBlockReads::class)->forNormalization($block, true);
  }

  #[Test]
  public function translation_inverses_reuse_sql_but_do_not_share_mutable_models(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $reads = app(RestoredBlockReads::class);
    $this->watch = true;
    DB::transaction(fn () => $reads->duringBlock($block->id, function () use ($reads, $block, $page, $locales): void {
      for ($i = 0; $i < 6; $i++) {
        $row = new BlockTextTranslation(['block_id' => $block->id, 'locale_id' => $locales->first()->id]);
        $reads->primeTranslation($row);
        $this->assertTrue($row->relationLoaded('block'));
        $this->assertSame($page->site_id, $row->block->page->site_id);
        $this->assertSame('Saved page', $row->block->page->translations->first()->name);
        $row->block->page->site_id = 99999;
        $row->block->page->translations->first()->name = 'Observer memory only';
        $row->block->sort_order = 99999;
      }
    }));
    $this->watch = false;
    $this->assertSame(1, $this->blockReads);
  }

  #[Test]
  public function raw_parent_and_page_document_changes_and_rollback_refresh_translation_inverses(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $page->update(['settings' => ['marker' => 'Before']]);
    $otherSite = Site::create(['name' => 'Other site', 'handle' => 'other-restored-site']);
    $other = Page::create(['site_id' => $otherSite->id, 'title' => 'Other page', 'status' => 'draft']);
    $reads = app(RestoredBlockReads::class);
    DB::transaction(fn () => $reads->duringBlock($block->id, function () use ($reads, $block, $page, $other, $locales): void {
      $make = function () use ($reads, $block, $locales): BlockTextTranslation {
        $row = new BlockTextTranslation(['block_id' => $block->id, 'locale_id' => $locales->first()->id]);
        $reads->primeTranslation($row);

        return $row;
      };
      $this->assertSame('Before', $make()->block->page->settings['marker']);
      $document = DB::table('wbcms_persistent_documents')->where('page_id', $page->id)->where('attribute', 'settings')->value('id');
      try {
        DB::transaction(function () use ($document, $make): void {
          DB::table('wbcms_persistent_nodes')->where('document_id', $document)->where('key_text', 'marker')->update(['value_text' => 'After']);
          $this->assertSame('After', $make()->block->page->settings['marker']);
          throw new RuntimeException('Synthetic inverse rollback');
        });
      } catch (RuntimeException) {
      }
      $this->assertSame('Before', $make()->block->page->settings['marker']);
      DB::table('wbcms_blocks')->where('id', $block->id)->update(['page_id' => $other->id]);
      $row = $make();
      $this->assertSame($other->id, $row->block->page_id);
      $this->assertSame($other->site_id, $row->block->page->site_id);
    }));
  }

  #[Test]
  public function page_retrieved_observers_still_run_for_each_translation_inverse(): void
  {
    [, $block, $locales] = $this->fixture();
    $retrieved = 0;
    Page::retrieved(function () use (&$retrieved): void {
      $retrieved++;
    });
    $reads = app(RestoredBlockReads::class);
    DB::transaction(fn () => $reads->duringBlock($block->id, function () use ($reads, $block, $locales): void {
      for ($i = 0; $i < 2; $i++) {
        $row = new BlockTextTranslation(['block_id' => $block->id, 'locale_id' => $locales->first()->id]);
        $reads->primeTranslation($row);
        $this->assertFalse($row->relationLoaded('block'));
        $this->assertNotNull($row->block->page);
      }
    }));
    $this->assertSame(2, $retrieved);
  }

  #[Test]
  public function historical_mutable_page_objects_keep_independent_native_hydration(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $page->update(['settings' => []]);
    app(PersistentDocuments::class)->write($page, 'settings', ['historical' => (object) ['value' => 'Saved']]);
    $reads = app(RestoredBlockReads::class);
    DB::transaction(fn () => $reads->duringBlock($block->id, function () use ($reads, $block, $locales): void {
      for ($i = 0; $i < 2; $i++) {
        $row = new BlockTextTranslation(['block_id' => $block->id, 'locale_id' => $locales->first()->id]);
        $reads->primeTranslation($row);
        $this->assertFalse($row->relationLoaded('block'));
        $this->assertSame('Saved', $row->block->page->settings['historical']->value);
        $settings = $row->block->page->settings;
        $settings['historical']->value = 'Memory only';
      }
    }));
  }

  #[Test]
  public function a_locale_disabled_by_a_restore_observer_still_rejects_and_rolls_back_the_restore(): void
  {
    [$page, $block, $locales, $actor] = $this->fixture();
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $active = true;
    BlockTextTranslation::saved(function (BlockTextTranslation $row) use (&$active, $page, $locales): void {
      if ($active && $row->locale_id === $locales->firstWhere('code', 'en')->id) {
        DB::table('wbcms_site_locales')->where('site_id', $page->site_id)->where('locale_id', $locales->firstWhere('code', 'tr')->id)->update(['is_enabled' => false]);
      }
    });
    try {
      $manager->restore($page, $revision, $actor);
      $this->fail('Every restored row must validate current locale membership.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Block translation locale must be enabled for the block page site.', $exception->getMessage());
    }
    $active = false;
    $this->assertSame([$block->id], $page->blocks()->pluck('id')->all());
    $this->assertSame(1, $page->revisions()->count());
    $this->assertTrue($page->site->enabledLocales()->whereKey($locales->firstWhere('code', 'tr')->id)->exists());
    $manager->restore($page, $revision, $actor);
    $this->assertSame(2, $page->blocks()->sole()->textTranslations()->count());
  }

  public static function restorePageBatchSizes(): array
  {
    return [[6], [151]];
  }

  #[Test]
  #[DataProvider('restorePageBatchSizes')]
  public function six_locale_translation_targets_share_one_sql_page_across_blocks_and_metadata_flushes(int $count): void
  {
    [$page, $first, $locales] = $this->fixture();
    foreach (['de', 'es', 'fr', 'it'] as $code) {
      $locales->push(Locale::create(['code' => $code, 'name' => $code, 'is_enabled' => true]));
    }
    $page->site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
    DB::table('wbcms_block_text_translations')->delete();
    $blocks = collect([$first]);
    PublicSearchIndexer::coalescing(function () use ($first, $blocks, $count): void {
      for ($i = 1; $i < $count; $i++) {
        $blocks->push(Block::create(['page_id' => $first->page_id, 'type' => 'plain_text', 'block_type_id' => $first->block_type_id, 'settings' => '{"alignment":"left"}', 'meta' => '{"source":"Saved"}']));
      }
    });
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      BlockTextTranslation::{$event}(function () use (&$events, $event): void {
        $events[$event] = ($events[$event] ?? 0) + 1;
      });
    }
    $reads = app(RestoredBlockReads::class);
    app(RevisionContentUpdates::class)->during(function () use ($reads, $page, $blocks, $locales): void {
      $this->watch = true;
      foreach ($blocks as $block) {
        $reads->duringBlock($block->id, function (Block $target) use ($reads, $page, $locales): void {
          foreach ($locales as $locale) {
            $row = $target->textTranslations()->make(['locale_id' => $locale->id, 'content' => 'Restored '.$locale->code, 'meta' => '{"source":"Restored"}']);
            $this->assertTrue(app(PersistentDocuments::class)->createRestored($row));
            $this->assertSame($page->site_id, $row->block->page->site_id);
            $this->assertSame('Saved page', $row->block->page->translations->first()->name);
            $row->block->page->site_id = 999999;
            $row->block->page->translations->first()->name = 'Memory mutation';
          }
          $this->assertNotNull($reads->forNormalization($target, false));
        });
      }
      $this->watch = false;
    });
    $this->assertSame(1, $this->pageReads);
    $this->assertSame(array_fill_keys(['saving', 'creating', 'created', 'saved'], $count * 6), $events);
    $this->assertSame($count * 6, BlockTextTranslation::count());
    $this->assertSame($count * 6, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->count());
  }

  #[Test]
  public function shared_translation_routes_see_page_documents_translations_raw_moves_and_rollback(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $page->update(['settings' => ['marker' => 'Before']]);
    $page->translations()->where('locale_id', $locales->first()->id)->update(['name' => 'Before name']);
    $other = Page::create(['site_id' => $page->site_id, 'title' => 'Other page', 'status' => 'draft']);
    $second = PublicSearchIndexer::coalescing(fn () => Block::create(['page_id' => $page->id, 'type' => 'plain_text']));
    $make = fn (int $id) => $this->translationTarget($id, $locales->first()->id);
    app(RevisionContentUpdates::class)->during(function () use ($page, $block, $second, $other, $make): void {
      $this->assertSame('Before', $make($block->id)->block->page->settings['marker']);
      $document = DB::table('wbcms_persistent_documents')->where('page_id', $page->id)->where('attribute', 'settings')->value('id');
      try {
        DB::transaction(function () use ($page, $second, $document, $make): void {
          DB::table('wbcms_persistent_nodes')->where('document_id', $document)->where('key_text', 'marker')->update(['value_text' => 'Temporary']);
          DB::table('wbcms_page_translations')->where('page_id', $page->id)->update(['name' => 'Temporary name']);
          $target = $make($second->id)->block->page;
          $this->assertSame('Temporary', $target->settings['marker']);
          $this->assertSame('Temporary name', $target->translations->first()->name);
          throw new RuntimeException('Synthetic route rollback');
        });
      } catch (RuntimeException) {
      }
      $target = $make($block->id)->block->page;
      $this->assertSame('Before', $target->settings['marker']);
      $this->assertSame('Before name', $target->translations->first()->name);
      DB::table('wbcms_blocks')->where('id', $second->id)->update(['page_id' => $other->id]);
      $this->assertSame($other->id, $make($second->id)->block->page->id);
    });
    $this->assertNull(app(RestoredIndexTargets::class)->pageForTranslation($block));
    try {
      app(RevisionContentUpdates::class)->during(function () use ($page, $block, $make): void {
        DB::table('wbcms_page_translations')->where('page_id', $page->id)->update(['name' => 'Failed scope']);
        $this->assertSame('Failed scope', $make($block->id)->block->page->title);
        throw new RuntimeException('Synthetic failed restoration');
      });
    } catch (RuntimeException) {
    }
    app(RevisionContentUpdates::class)->during(fn () => $this->assertSame('Before name', $make($block->id)->block->page->title));
  }

  #[Test]
  public function a_site_change_between_blocks_keeps_locale_validation_and_atomic_rollback(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $second = PublicSearchIndexer::coalescing(fn () => Block::create(['page_id' => $page->id, 'type' => 'plain_text']));
    $otherSite = Site::create(['name' => 'Disabled locales', 'handle' => 'disabled-restore-locales']);
    $other = Page::create(['site_id' => $otherSite->id, 'title' => 'Other site page', 'status' => 'draft']);
    DB::table('wbcms_block_text_translations')->delete();
    $before = BlockTextTranslation::count();
    try {
      app(RevisionContentUpdates::class)->during(function () use ($block, $second, $locales, $other): void {
        $row = $this->translationTarget($block->id, $locales->first()->id);
        $this->assertTrue(app(PersistentDocuments::class)->createRestored($row));
        DB::table('wbcms_blocks')->where('id', $second->id)->update(['page_id' => $other->id]);
        app(RestoredBlockReads::class)->duringBlock($second->id, function (Block $target) use ($locales): void {
          $row = $target->textTranslations()->make(['locale_id' => $locales->last()->id, 'content' => 'Must fail']);
          app(PersistentDocuments::class)->createRestored($row);
        });
      });
      $this->fail('The newly loaded site must still reject a disabled locale.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Block translation locale must be enabled for the block page site.', $exception->getMessage());
    }
    $this->assertSame($page->site_id, $page->fresh()->site_id);
    $this->assertSame($page->id, $second->fresh()->page_id);
    $this->assertSame($before, BlockTextTranslation::count());
  }

  #[Test]
  public function late_page_read_observers_cannot_reuse_a_shared_translation_route(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $second = PublicSearchIndexer::coalescing(fn () => Block::create(['page_id' => $page->id, 'type' => 'plain_text']));
    app(RevisionContentUpdates::class)->during(function () use ($block, $second, $locales): void {
      $this->assertTrue($this->translationTarget($block->id, $locales->first()->id)->relationLoaded('block'));
      $retrieved = 0;
      PageTranslation::retrieved(function () use (&$retrieved): void {
        $retrieved++;
      });
      for ($i = 0; $i < 2; $i++) {
        $row = $this->translationTarget($second->id, $locales->first()->id);
        $this->assertFalse($row->relationLoaded('block'));
        $this->assertNotNull($row->block->page);
      }
      $this->assertSame(2, $retrieved);
      $pages = 0;
      Page::retrieved(function () use (&$pages): void {
        $pages++;
      });
      $this->assertNotNull($this->translationTarget($second->id, $locales->first()->id)->block->page);
      $this->assertSame(1, $pages);
    });
  }

  #[Test]
  public function a_late_page_scope_cannot_expose_a_cached_translation_route(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $second = PublicSearchIndexer::coalescing(fn () => Block::create(['page_id' => $page->id, 'type' => 'plain_text']));
    app(RevisionContentUpdates::class)->during(function () use ($block, $second, $locales): void {
      $this->assertNotNull($this->translationTarget($block->id, $locales->first()->id)->block->page);
      $scopes = Page::getAllGlobalScopes();
      Page::addGlobalScope('hidden-restore-page', fn (Builder $query) => $query->whereRaw('1 = 0'));
      try {
        $this->assertNull($this->translationTarget($second->id, $locales->first()->id)->block->page);
      } finally {
        Page::setAllGlobalScopes($scopes);
      }
    });
  }

  #[Test]
  public function page_writes_during_hydration_cannot_seed_a_stale_translation_route(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $changed = false;
    DB::listen(function ($query) use ($page, &$changed): void {
      if ($this->watch && ! $changed && preg_match('/^select .*from ["`]wbcms_pages["`]/i', $query->sql)) {
        $changed = true;
        DB::table('wbcms_page_translations')->where('page_id', $page->id)->update(['name' => 'During hydration']);
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($block, $locales): void {
      $this->watch = true;
      $row = $this->translationTarget($block->id, $locales->first()->id);
      $this->assertFalse($row->relationLoaded('block'));
      $this->assertSame('During hydration', $row->block->page->title);
      $this->assertSame('During hydration', $this->translationTarget($block->id, $locales->first()->id)->block->page->title);
      $this->watch = false;
    });
    $this->assertTrue($changed);
  }

  #[Test]
  public function historical_page_objects_remain_independent_across_restore_blocks(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $page->update(['settings' => []]);
    app(PersistentDocuments::class)->write($page, 'settings', ['historical' => (object) ['value' => 'Saved']]);
    $second = PublicSearchIndexer::coalescing(fn () => Block::create(['page_id' => $page->id, 'type' => 'plain_text']));
    app(RevisionContentUpdates::class)->during(function () use ($block, $second, $locales): void {
      foreach ([$block->id, $second->id] as $id) {
        $row = $this->translationTarget($id, $locales->first()->id);
        $this->assertFalse($row->relationLoaded('block'));
        $this->assertSame('Saved', $row->block->page->settings['historical']->value);
        $settings = $row->block->page->settings;
        $settings['historical']->value = 'Memory mutation';
      }
    });
  }

  private function translationTarget(int $blockId, int $localeId): BlockTextTranslation
  {
    return app(RestoredBlockReads::class)->duringBlock($blockId, function (Block $target) use ($localeId): BlockTextTranslation {
      $row = $target->textTranslations()->make(['locale_id' => $localeId]);
      app(RestoredBlockReads::class)->primeTranslation($row);

      return $row;
    });
  }

  private function fixture(): array
  {
    return PublicSearchIndexer::coalescing(function (): array {
      $site = Site::create(['name' => 'Restored reads', 'handle' => 'restored-reads']);
      $locales = collect(['en', 'tr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $type = BlockType::create(['name' => 'Plain text', 'slug' => 'plain_text', 'source_type' => 'static', 'status' => 'published']);
      $page = Page::create(['site_id' => $site->id, 'title' => 'Saved page', 'status' => 'draft']);
      $block = Block::create(['page_id' => $page->id, 'type' => 'plain_text', 'block_type_id' => $type->id, 'title' => 'Canonical title', 'content' => 'Canonical content', 'meta' => '{"source":"Saved"}', 'settings' => '{"alignment":"left"}']);
      foreach ($locales as $locale) {
        $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Saved '.$locale->code]);
      }
      $class = 'App\\Models\\User';
      $actor = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));

      return [$page, $block, $locales, $actor];
    });
  }
}

class RestoredReadTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey(): string
  {
    return 'user_id';
  }
}
