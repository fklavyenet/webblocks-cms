<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PageType;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageListCache;
use WebBlocks\Cms\Support\Pages\PageListItem;
use WebBlocks\Cms\Tests\TestCase;

class PageListCacheTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function repeat_lookups_and_new_requests_reuse_a_shared_serialized_listing(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $this->page($site, $en, $type, 'Guide', '/guides/a');
    $directory = sys_get_temp_dir().'/wbcms-page-list-'.bin2hex(random_bytes(8));
    config()->set('cache.stores.listing_test', ['driver' => 'file', 'path' => $directory]);
    config()->set('cache.default', 'listing_test');
    try {
      $this->assertSame(1, $this->listQueries(fn () => $this->assertSame(['Guide'], $block->pageListItems()->pluck('title')->all())));
      $this->assertSame(0, $this->listQueries(fn () => $block->pageListItems()));
      $this->nextRequest();
      Cache::purge('listing_test');
      $this->assertSame(0, $this->listQueries(fn () => $this->assertSame(['Guide'], $block->pageListItems()->pluck('title')->all())));
      $html = view('webblocks-cms::pages.partials.blocks.page-list', ['block' => $block])->render();
      $this->assertStringContainsString('href="/guides/a"', $html);
      $this->assertStringContainsString('Guide', $html);
    } finally {
      File::deleteDirectory($directory);
    }
  }

  #[Test]
  public function empty_results_expire_and_zero_disables_only_shared_caching(): void
  {
    [, , , , $block] = $this->fixture();
    config()->set('webblocks-cms.page_list.cache_seconds', 2);
    $this->assertSame(1, $this->listQueries(fn () => $this->assertCount(0, $block->pageListItems())));
    $this->nextRequest();
    $this->assertSame(0, $this->listQueries(fn () => $this->assertCount(0, $block->pageListItems())));
    $this->travel(3)->seconds();
    $this->nextRequest();
    $this->assertSame(1, $this->listQueries(fn () => $block->pageListItems()));
    config()->set('webblocks-cms.page_list.cache_seconds', 0);
    $this->assertSame(1, $this->listQueries(fn () => $block->pageListItems()));
    $this->assertSame(0, $this->listQueries(fn () => $block->pageListItems()));
    $this->nextRequest();
    $this->assertSame(1, $this->listQueries(fn () => $block->pageListItems()));
  }

  #[Test]
  public function site_locale_settings_current_page_and_mount_are_isolated(): void
  {
    [$site, $en, $tr, $type, $block] = $this->fixture();
    $a = $this->page($site, $en, $type, 'Alpha', '/guides/a');
    $this->page($site, $en, $type, 'Beta', '/guides/b');
    $this->translation($a, $tr, 'Alfa', '/kilavuz/a');
    $other = Site::create(['name' => 'Other synthetic studio', 'handle' => 'other']);
    $other->locales()->sync([$en->id => ['is_enabled' => true]]);
    $foreign = $this->page($other, $en, $type, 'Foreign', '/guides/a');
    $foreignBlock = $this->block($foreign);
    $hosted = $this->block($a);
    // Finish fixture writes before warming any cache.
    $this->assertSame(['Alpha', 'Beta'], $block->pageListItems()->pluck('title')->all());
    $this->nextRequest();
    $this->assertSame(0, $this->listQueries(fn () => $block->pageListItems()));
    $translated = clone $block;
    $translated->setAttribute('requested_locale_code', 'tr');
    $this->assertSame(['Alfa'], $translated->pageListItems()->pluck('title')->all());
    $this->assertSame('/tr/kilavuz/a', $translated->pageListItems()->first()->url);
    $this->assertSame([], $foreignBlock->pageListItems()->pluck('title')->all());
    $this->assertSame(['Beta'], $hosted->pageListItems()->pluck('title')->all());
    $changed = clone $block;
    $changed->setAttribute('settings', ['scope' => 'page_type', 'page_type' => 'guide', 'sort' => 'published_desc', 'limit' => 1, 'show_description' => false]);
    $this->assertSame(['Beta'], $changed->pageListItems()->pluck('title')->all());
    $this->assertNull($changed->pageListItems()->first()->description);
    $path = clone $block;
    $path->setAttribute('settings', ['scope' => 'path_prefix', 'path_prefix' => '/guides/b']);
    $this->assertSame(['Beta'], $path->pageListItems()->pluck('title')->all());
    config()->set('webblocks-cms.public.mount', '/content');
    $this->assertSame('/content/guides/a', $block->pageListItems()->first()->url);
    config()->set('webblocks-cms.public.mount', '');
    $this->assertSame('/guides/a', $block->pageListItems()->first()->url);
  }

  #[Test]
  public function model_and_bulk_writes_refresh_titles_paths_excerpts_publication_and_deletion(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $page = $this->page($site, $en, $type, 'Before', '/guides/a');
    $this->assertSame('Before', $block->pageListItems()->first()->title);
    $page->translations()->where('locale_id', $en->id)->update(['name' => 'After', 'path' => '/guides/changed', 'list_excerpt' => 'New excerpt']);
    $this->assertSame('After', $block->pageListItems()->first()->title);
    $this->nextRequest();
    $item = $block->pageListItems()->first();
    $this->assertSame('/guides/changed', $item->url);
    $this->assertSame('New excerpt', $item->description);
    foreach (['draft', 'published', 'archived', 'published'] as $status) {
      $page->update(['status' => $status]);
      $this->nextRequest();
      $this->assertCount($status === 'published' ? 1 : 0, $block->pageListItems());
    }
    $page->delete();
    $this->nextRequest();
    $this->assertCount(0, $block->pageListItems());
    $replacement = $this->page($site, $en, $type, 'New page', '/guides/new');
    $this->assertSame('New page', $block->pageListItems()->first()->title);
    $replacement->translations()->delete();
    $this->assertCount(0, $block->pageListItems());
  }

  #[Test]
  public function cached_public_discovery_never_includes_protected_draft_or_internal_pages(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $public = $this->page($site, $en, $type, 'Public', '/guides/a');
    $this->page($site, $en, $type, 'Draft', '/guides/draft')->update(['status' => 'draft']);
    $this->page($site, $en, $type, 'Internal', '/guides/internal')->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
    $this->assertSame(['Public'], $block->pageListItems()->pluck('title')->all());
    DB::table('wbcms_page_access_rules')->insert(['page_id' => $public->id, 'package_id' => null]);
    $this->assertCount(0, $block->pageListItems());
    $this->nextRequest();
    $this->assertCount(0, $block->pageListItems());
    DB::table('wbcms_page_access_rules')->where('page_id', $public->id)->delete();
    $this->assertSame(['Public'], $block->pageListItems()->pluck('title')->all());
    DB::statement('update wbcms_pages set status = ? where id = ?', ['draft', $public->id]);
    $this->nextRequest();
    $this->assertCount(0, $block->pageListItems());
  }

  #[Test]
  public function thumbnails_round_trip_without_private_metadata_and_refresh_on_media_writes(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $page = $this->page($site, $en, $type, 'Guide', '/guides/a');
    $media = Media::create(['disk' => 'public', 'path' => 'synthetic/a.svg', 'filename' => 'a.svg', 'mime_type' => 'image/svg+xml', 'kind' => 'image', 'visibility' => 'public', 'alt_text' => 'A diagram', 'description' => 'Private upload note']);
    $page->translations()->update(['og_image_media_id' => $media->id]);
    $item = $block->pageListItems()->first();
    $beforeUrl = $item->thumbnailUrl();
    $this->assertStringNotContainsString('Private upload note', serialize($item->cacheData()));
    $this->nextRequest();
    $this->assertSame(0, $this->listQueries(fn () => $block->pageListItems()));
    $this->assertSame($beforeUrl, $block->pageListItems()->first()->thumbnailUrl());
    $this->assertSame('A diagram', $block->pageListItems()->first()->thumbnailAltText());
    DB::table('wbcms_media')->where('id', $media->id)->update(['alt_text' => 'Changed diagram', 'visibility' => 'private']);
    $this->nextRequest();
    $this->assertNull($block->pageListItems()->first()->thumbnailUrl());
    $this->assertSame('Changed diagram', $block->pageListItems()->first()->thumbnailAltText());
    $media->delete();
    $this->assertNull($block->pageListItems()->first()->thumbnail);
  }

  #[Test]
  public function transactions_bypass_shared_data_and_rollback_cannot_publish_uncommitted_rows(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $page = $this->page($site, $en, $type, 'Public', '/guides/a');
    $block->pageListItems();
    $generation = DB::table(PageListCache::TABLE)->value('generation');
    DB::beginTransaction();
    $page->translations()->update(['name' => 'Uncommitted']);
    $this->assertSame('Uncommitted', $block->pageListItems()->first()->title);
    DB::beginTransaction();
    $page->translations()->update(['name' => 'Nested']);
    $this->assertSame('Nested', $block->pageListItems()->first()->title);
    DB::rollBack();
    $this->assertSame('Uncommitted', $block->pageListItems()->first()->title);
    DB::rollBack();
    $this->assertSame($generation, DB::table(PageListCache::TABLE)->value('generation'));
    $this->nextRequest();
    $this->assertSame(0, $this->listQueries(fn () => $this->assertSame('Public', $block->pageListItems()->first()->title)));
    DB::transaction(function () use ($page, $block): void {
      $page->translations()->update(['name' => 'Committed']);
      $this->assertSame('Committed', $block->pageListItems()->first()->title);
    });
    $this->nextRequest();
    $this->assertSame('Committed', $block->pageListItems()->first()->title);
  }

  #[Test]
  public function a_fill_started_before_a_write_cannot_poison_the_new_generation_or_request_memo(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $page = $this->page($site, $en, $type, 'In flight', '/guides/a');
    $armed = true;
    // Interleave a write after the page SELECT has fetched its old snapshot,
    // before eager loading and cache publication finish.
    DB::listen(function (QueryExecuted $event) use (&$armed, $page): void {
      if ($armed && preg_match('/^select .* from ["`]wbcms_pages["`]/is', $event->sql)) {
        $armed = false;
        DB::table('wbcms_pages')->where('id', $page->id)->update(['status' => 'draft']);
      }
    });
    $this->assertSame('In flight', $block->pageListItems()->first()->title);
    $this->assertFalse($armed);
    $this->assertCount(0, $block->pageListItems());
    $this->nextRequest();
    $this->assertSame(0, $this->listQueries(fn () => $this->assertCount(0, $block->pageListItems())));
  }

  #[Test]
  public function cache_failure_and_recovery_cannot_revive_a_pre_change_listing(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $page = $this->page($site, $en, $type, 'Before', '/guides/a');
    $block->pageListItems();
    $manager = Cache::getFacadeRoot();
    $failing = \Mockery::mock(CacheManager::class);
    $failing->shouldReceive('get')->andThrow(new \RuntimeException('Synthetic cache outage'));
    $failing->shouldReceive('put')->andThrow(new \RuntimeException('Synthetic cache outage'));
    Cache::swap($failing);
    try {
      $page->translations()->update(['name' => 'During outage']);
      $this->nextRequest();
      $this->assertSame('During outage', $block->pageListItems()->first()->title);
    } finally {
      Cache::swap($manager);
    }
    $this->nextRequest();
    $this->assertSame('During outage', $block->pageListItems()->first()->title);
  }

  #[Test]
  public function schema_migration_is_idempotent_and_unmigrated_consumers_keep_working(): void
  {
    [$site, $en, , $type, $block] = $this->fixture();
    $this->page($site, $en, $type, 'Guide', '/guides/a');
    $migration = require dirname(__DIR__, 2).'/database/migrations/fresh/2026_10_31_120000_create_page_list_cache_versions.php';
    $generation = DB::table(PageListCache::TABLE)->value('generation');
    $update = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_31_120000_create_page_list_cache_versions.php';
    $update->up();
    $this->assertSame($generation, DB::table(PageListCache::TABLE)->value('generation'));
    $this->assertSame(1, DB::table(PageListCache::TABLE)->count());
    $migration->down();
    $this->assertFalse(Schema::hasTable(PageListCache::TABLE));
    $this->assertSame(['Guide'], $block->pageListItems()->pluck('title')->all());
    $migration->up();
    $this->nextRequest();
    $block->pageListItems();
    $this->nextRequest();
    $this->assertSame(0, $this->listQueries(fn () => $block->pageListItems()));
  }

  #[Test]
  public function database_connections_and_table_prefixes_have_independent_generations(): void
  {
    $this->fixture();
    config()->set('database.connections.listing_other', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'tenant_']);
    $other = DB::connection('listing_other');
    $other->getSchemaBuilder()->create(PageListCache::TABLE, function (Blueprint $table): void {
      $table->integer('id')->primary();
      $table->string('generation');
    });
    $other->getSchemaBuilder()->create('wbcms_pages', function (Blueprint $table): void {
      $table->integer('id')->primary();
      $table->string('status');
    });
    $generation = DB::table(PageListCache::TABLE)->value('generation');
    $other->table(PageListCache::TABLE)->insert(['id' => 1, 'generation' => $generation]);
    $cache = app(PageListCache::class);
    $first = fn () => collect([new PageListItem(1, 'First installation', '/one', null, null)]);
    $second = fn () => collect([new PageListItem(1, 'Second installation', '/two', null, null)]);
    $this->assertSame('First installation', $cache->remember(DB::connection(), ['same-context'], $first)->first()->title);
    $this->assertSame('Second installation', $cache->remember($other, ['same-context'], $second)->first()->title);
    $other->table('wbcms_pages')->insert(['id' => 1, 'status' => 'draft']);
    $this->assertNotSame($generation, $other->table(PageListCache::TABLE)->value('generation'));
    $this->assertSame($generation, DB::table(PageListCache::TABLE)->value('generation'));
    $this->nextRequest();
    $this->assertSame('Second installation', $cache->remember($other, ['same-context'], $second)->first()->title);
    $this->assertSame('First installation', $cache->remember(DB::connection(), ['same-context'], $first)->first()->title);
    DB::disconnect('listing_other');
  }

  #[Test]
  public function page_type_and_locale_pivot_changes_invalidate_warmed_results(): void
  {
    [$site, $en, $tr, $type, $block] = $this->fixture();
    $page = $this->page($site, $en, $type, 'Guide', '/guides/a');
    $this->translation($page, $tr, 'Kilavuz', '/kilavuz/a');
    $block->setAttribute('requested_locale_code', 'tr');
    $this->assertSame(['Kilavuz'], $block->pageListItems()->pluck('title')->all());
    $site->locales()->updateExistingPivot($tr->id, ['is_enabled' => false]);
    $this->nextRequest();
    $this->assertNotContains('Kilavuz', $block->pageListItems()->pluck('title')->all());
    $site->locales()->updateExistingPivot($tr->id, ['is_enabled' => true]);
    $this->nextRequest();
    $this->assertSame(['Kilavuz'], $block->pageListItems()->pluck('title')->all());
    DB::table('wbcms_page_types')->where('id', $type->id)->update(['slug' => 'renamed']);
    $this->nextRequest();
    $this->assertCount(0, $block->pageListItems());
  }

  private function nextRequest(): void
  {
    app()->instance('request', Request::create('/synthetic-listing'));
    app()->forgetScopedInstances();
  }

  private function listQueries(callable $work): int
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return count(array_filter(DB::getQueryLog(), fn ($query) => preg_match('/^select .* from ["`]wbcms_pages["`]/is', $query['query'])));
    } finally {
      DB::disableQueryLog();
      DB::flushQueryLog();
    }
  }

  private function fixture(): array
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'listing', 'is_primary' => true]);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    $type = PageType::create(['name' => 'Guide', 'slug' => 'guide', 'status' => 'published']);
    $hostType = PageType::create(['name' => 'Page', 'slug' => 'page', 'status' => 'published']);
    $host = $this->page($site, $en, $hostType, 'Index', '/index');

    return [$site, $en, $tr, $type, $this->block($host)];
  }

  private function block(Page $host): Block
  {
    $type = BlockType::firstOrCreate(['slug' => 'page-list'], ['name' => 'Page List', 'is_active' => true]);
    $block = Block::create(['page_id' => $host->id, 'type' => 'page-list', 'block_type_id' => $type->id, 'status' => 'published', 'slot' => 'main', 'settings' => ['scope' => 'page_type', 'page_type' => 'guide', 'sort' => 'path_asc']]);

    return $block->fresh(['blockType', 'page.site']);
  }

  private function page(Site $site, Locale $locale, PageType $type, string $name, string $path): Page
  {
    $page = Page::create(['site_id' => $site->id, 'page_type_id' => $type->id, 'status' => 'published', 'published_at' => now()]);
    $this->translation($page, $locale, $name, $path);

    return $page;
  }

  private function translation(Page $page, Locale $locale, string $name, string $path): void
  {
    PageTranslation::create(['site_id' => $page->site_id, 'page_id' => $page->id, 'locale_id' => $locale->id, 'name' => $name, 'path' => $path, 'slug' => basename($path), 'list_excerpt' => 'Synthetic excerpt']);
  }
}
