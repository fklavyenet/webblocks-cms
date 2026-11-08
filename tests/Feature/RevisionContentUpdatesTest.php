<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\Search\PublicSearchRebuildResult;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RevisionContentUpdatesTest extends TestCase
{
  private const VERSIONS = ['wbcms_seo_source_versions', 'wbcms_page_list_cache_versions', 'wbcms_navigation_cache_versions'];

  private bool $watch = false;

  private array $writes = [];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(RevisionContentUpdatesTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    DB::listen(function ($query): void {
      if ($this->watch && preg_match('/^(insert into|update|delete from) ["`]?([a-z_]+)/i', $query->sql, $match)) {
        $key = $match[2].' '.$match[1];
        $this->writes[$key] = ($this->writes[$key] ?? 0) + 1;
      }
    });
  }

  #[Test]
  public function direct_restores_keep_observers_and_refresh_each_derived_target_once(): void
  {
    [$page, $block, $actor] = $this->fixture();
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $block->textTranslations()->firstOrFail()->update(['content' => 'Current content']);
    $created = [];
    Block::created(function (Block $createdBlock) use (&$created): void {
      $created[] = $createdBlock->id;
    });
    $this->record(fn () => $manager->restore($page, $revision, $actor));
    $this->assertCount(1, $created);
    $this->assertDerivedWrites(1, 2);
    $this->assertStringContainsString('Saved content en', DB::table('wbcms_public_search_index')->where('page_id', $page->id)->where('locale_id', Locale::where('code', 'en')->value('id'))->value('content'));
    $this->assertSame(3, $page->revisions()->count());
    $this->assertDatabaseCount('wbcms_revision_capture_events', 3);
    $independent = $page->revisions()->latest('id')->firstOrFail();
    foreach ($page->revisions()->whereKeyNot($independent->id)->get() as $oldRevision) {
      $oldRevision->delete();
    }
    $page->blocks()->firstOrFail()->textTranslations()->firstOrFail()->update(['content' => 'After deleting old revisions']);
    $manager->restore($page->fresh(), $independent->fresh(), $actor);
    $this->assertStringContainsString('Saved content en', DB::table('wbcms_public_search_index')->where('page_id', $page->id)->where('locale_id', Locale::where('code', 'en')->value('id'))->value('content'));
  }

  #[Test]
  public function candidate_creation_and_apply_nest_with_the_request_indexing_scope(): void
  {
    [$page, , $actor] = $this->fixture();
    $revision = app(PageRevisionManager::class)->capture($page);
    $manager = app(PageRevisionCandidateManager::class);
    $candidate = $this->record(fn () => PublicSearchIndexer::coalescing(fn () => $manager->create($page, $revision, $actor)));
    $this->assertDerivedWrites(1, 0);
    $this->assertSame('draft', $candidate->candidatePage->status);
    $this->assertSame('published', $page->fresh()->status);
    $this->record(fn () => PublicSearchIndexer::coalescing(fn () => $manager->apply($candidate, $actor)));
    foreach (self::VERSIONS as $table) {
      $this->assertSame(1, $this->writes[$table.' update'] ?? 0, $table);
    }
    $this->assertSame(2, DB::table('wbcms_public_search_index')->where('page_id', $page->id)->count());
    $this->assertDatabaseMissing('wbcms_pages', ['id' => $candidate->candidate_page_id]);
  }

  #[Test]
  public function shared_slot_restore_refreshes_all_consumers_once(): void
  {
    [$page, , $actor, $locales, $slot] = $this->fixture();
    $shared = SharedSlot::create(['site_id' => $page->site_id, 'name' => 'Shared', 'handle' => 'shared', 'slot_name' => 'main', 'is_active' => true]);
    $source = app(SharedSlotSourcePageManager::class)->ensureFor($shared);
    PublicSearchIndexer::coalescing(function () use ($page, $source, $shared, $locales, $slot): void {
      $block = $source->blocks()->create(['type' => 'plain_text', 'slot_type_id' => $slot->id, 'slot' => 'main', 'status' => 'published']);
      foreach ($locales as $locale) {
        $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Shared saved '.$locale->code]);
      }
      $second = Page::create(['site_id' => $page->site_id, 'title' => 'Second', 'slug' => 'second', 'status' => 'published']);
      foreach ($locales as $locale) {
        $second->translations()->updateOrCreate(['locale_id' => $locale->id], ['name' => 'Second', 'slug' => 'second', 'path' => '/second']);
      }
      foreach ([$page, $second] as $consumer) {
        $consumer->slots()->updateOrCreate(['slot_type_id' => $slot->id], ['source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT, 'shared_slot_id' => $shared->id]);
      }
    });
    $manager = app(SharedSlotRevisionManager::class);
    $revision = $manager->capture($shared);
    $this->record(fn () => $manager->restore($shared, $revision, $actor));
    // The private source page is cleared once as well as both consumers.
    $this->assertDerivedWrites(3, 4);
    $this->assertSame(3, $shared->revisions()->count());
  }

  #[Test]
  public function model_validation_failure_rolls_back_content_revisions_versions_and_index(): void
  {
    [$page, , $actor] = $this->fixture();
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $snapshot = $revision->snapshot;
    $snapshot['blocks'][0]['text_translations'][0]['locale_id'] = 999999;
    $revision->forceFill(['snapshot' => $snapshot])->save();
    $before = $this->state();
    try {
      $manager->restore($page, $revision, $actor);
      $this->fail('Translation locale validation must still run.');
    } catch (RuntimeException $exception) {
      $this->assertStringContainsString('locale must be enabled', $exception->getMessage());
    }
    $this->assertSame($before, $this->state());
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
    $page->update(['title' => 'After failed restore']);
    $this->assertDatabaseHas('wbcms_public_search_index', ['page_id' => $page->id, 'title' => 'After failed restore']);
  }

  #[Test]
  public function an_index_flush_failure_rolls_back_the_restore_and_does_not_poison_the_next_call(): void
  {
    [$page, , $actor] = $this->fixture();
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $indexer = app(FailingRestorationIndexer::class);
    app()->instance(PublicSearchIndexer::class, $indexer);
    $before = $this->state();
    $indexer->fail = true;
    try {
      $manager->restore($page, $revision, $actor);
      $this->fail('The index failure must escape.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Synthetic indexing failure', $exception->getMessage());
    }
    $this->assertSame($before, $this->state());
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
    $indexer->fail = false;
    $this->record(fn () => $manager->restore($page, $revision, $actor));
    $this->assertDerivedWrites(1, 2);
  }

  #[Test]
  public function a_retried_transaction_and_a_failed_nested_scope_leave_only_committed_work(): void
  {
    [$page] = $this->fixture();
    $scope = app(RevisionContentUpdates::class);
    $attempts = 0;
    $scope->during(function () use ($scope, $page, &$attempts): void {
      $attempts++;
      $page->fresh()->update(['title' => 'Attempt '.$attempts]);
      if ($attempts === 1) {
        throw new DeadlockException('Deadlock found when trying to get lock');
      }
      try {
        $scope->during(function () use ($page): void {
          $page->fresh()->update(['title' => 'Rolled back child']);
          throw new RuntimeException('Synthetic child rollback');
        });
      } catch (RuntimeException) {
      }
    }, 2);
    $this->assertSame(2, $attempts);
    $this->assertSame('Attempt 2', $page->fresh()->title);
    $this->assertDatabaseHas('wbcms_public_search_index', ['page_id' => $page->id, 'title' => 'Attempt 2']);
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  private function record(callable $write): mixed
  {
    $this->watch = true;
    $this->writes = [];
    try {
      return $write();
    } finally {
      $this->watch = false;
    }
  }

  private function assertDerivedWrites(int $deletes, int $inserts): void
  {
    foreach (self::VERSIONS as $table) {
      $this->assertSame(1, $this->writes[$table.' update'] ?? 0, $table);
    }
    $this->assertSame($deletes, $this->writes['wbcms_public_search_index delete from'] ?? 0);
    $this->assertSame($inserts, $this->writes['wbcms_public_search_index insert into'] ?? 0);
  }

  private function state(): array
  {
    $tables = ['wbcms_pages', 'wbcms_page_translations', 'wbcms_blocks', 'wbcms_block_text_translations', 'wbcms_page_revisions', 'wbcms_revision_capture_events', 'wbcms_public_search_index', ...self::VERSIONS];

    return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
  }

  private function fixture(): array
  {
    return PublicSearchIndexer::coalescing(function (): array {
      $site = Site::create(['name' => 'Restore studio', 'handle' => 'restore', 'is_primary' => true]);
      $locales = collect(['en', 'tr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
      $page = Page::create(['site_id' => $site->id, 'title' => 'Saved title', 'slug' => 'saved', 'status' => 'published']);
      foreach ($locales as $locale) {
        $page->translations()->updateOrCreate(['locale_id' => $locale->id], ['name' => 'Saved title', 'slug' => 'saved', 'path' => '/saved']);
      }
      $page->slots()->create(['slot_type_id' => $slot->id, 'source_type' => 'page']);
      $block = $page->blocks()->create(['type' => 'plain_text', 'slot_type_id' => $slot->id, 'slot' => 'main', 'status' => 'published']);
      foreach ($locales as $locale) {
        $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Saved content '.$locale->code]);
      }
      $class = 'App\\Models\\User';
      $actor = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));

      return [$page, $block, $actor, $locales, $slot];
    });
  }
}

class FailingRestorationIndexer extends PublicSearchIndexer
{
  public bool $fail = false;

  public function rebuildPage(Page|int $page, ?Locale $locale = null): PublicSearchRebuildResult
  {
    if ($this->fail) {
      throw new RuntimeException('Synthetic indexing failure');
    }

    return parent::rebuildPage($page, $locale);
  }
}

class RevisionContentUpdatesTestUser extends CmsSqlTestUser
{
  protected $table = 'users';
}
