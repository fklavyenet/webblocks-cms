<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class LargeRevisionRestoreTest extends TestCase
{
  private const VERSIONS = ['wbcms_seo_source_versions', 'wbcms_page_list_cache_versions', 'wbcms_navigation_cache_versions'];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(LargeRevisionRestoreTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
  }

  #[Test]
  public function a_six_locale_nested_page_restores_with_events_and_independent_revisions(): void
  {
    $count = (int) (getenv('CMS_PROFILE_RESTORE_BLOCKS') ?: 12);
    $page = $this->seedPage($count);
    $class = 'App\\Models\\User';
    $actor = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $watch = false;
    $queries = 0;
    $ownerReads = 0;
    $writes = [];
    $events = [];
    foreach ([Block::class, BlockTextTranslation::class, PageRevision::class] as $model) {
      foreach (['saving', 'creating', 'created', 'saved'] as $event) {
        $model::{$event}(function () use (&$watch, &$events, $model, $event): void {
          if ($watch) {
            $events[$model][$event] = ($events[$model][$event] ?? 0) + 1;
          }
        });
      }
    }
    DB::listen(function ($query) use (&$watch, &$queries, &$writes, &$ownerReads): void {
      if (! $watch) {
        return;
      }
      $queries++;
      if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'wbcms_persistent_documents')
        && str_contains($query->sql, 'page_slot_id') && str_contains($query->sql, 'page_layout_id')) {
        $ownerReads++;
      }
      if (preg_match('/^(insert into|update|delete from) ["`]?([a-z_]+)/i', $query->sql, $match)) {
        $key = $match[2].' '.$match[1];
        $writes[$key] = ($writes[$key] ?? 0) + 1;
      }
    });
    if (getenv('CMS_PROFILE_RESTORE_BLOCKS')) {
      memory_reset_peak_usage();
    }
    $memoryStart = memory_get_usage(true);
    $watch = true;
    $start = microtime(true);
    // The panel/API middleware already collects indexing on writes. Include its
    // final flush in both baseline and optimized measurements.
    PublicSearchIndexer::coalescing(fn () => $manager->restore($page, $revision, $actor));
    $seconds = microtime(true) - $start;
    $memoryPeak = memory_get_peak_usage(true);
    $memoryEnd = memory_get_usage(true);
    $watch = false;
    if (getenv('CMS_PROFILE_RESTORE_BLOCKS')) {
      fwrite(STDERR, json_encode(['blocks' => $count, 'locales' => 6, 'nested' => true, 'queries' => $queries, 'document_owner_reads' => $ownerReads, 'seconds' => round($seconds, 3), 'memory_start_bytes' => $memoryStart, 'memory_peak_bytes' => $memoryPeak, 'memory_end_bytes' => $memoryEnd, 'writes' => $writes, 'events' => $events], JSON_PRETTY_PRINT).PHP_EOL);
    }
    foreach ([Block::class => $count, BlockTextTranslation::class => $count * 6, PageRevision::class => 2] as $model => $expected) {
      foreach (['saving', 'creating', 'created', 'saved'] as $event) {
        $this->assertSame($expected, $events[$model][$event] ?? 0, $model.' '.$event);
      }
    }
    if (! getenv('CMS_PROFILE_OWNER_BASELINE')) {
      $this->assertSame(2, $ownerReads, 'Snapshot ownership is read once initially and once after its final metadata update.');
    }
    if (! getenv('CMS_PROFILE_RESTORE_BASELINE')) {
      foreach (self::VERSIONS as $table) {
        $this->assertSame(1, $writes[$table.' update'] ?? 0, $table);
      }
    }
    $this->assertSame(1, $writes['wbcms_public_search_index delete from'] ?? 0);
    $this->assertSame(6, $writes['wbcms_public_search_index insert into'] ?? 0);
    $this->assertSame(6, DB::table('wbcms_public_search_index')->where('page_id', $page->id)->count());
    $this->assertSame($count, $page->blocks()->count());
    $root = $page->blocks()->whereNull('parent_id')->sole();
    $this->assertSame($count - 1, $page->blocks()->where('parent_id', $root->id)->count());
    $this->assertSame($count * 6, BlockTextTranslation::whereIn('block_id', $page->blocks()->select('id'))->count());
    $this->assertSame($count, DB::table('wbcms_persistent_documents')->where('attribute', 'meta')->whereIn('block_id', $page->blocks()->select('id'))->count());
    $this->assertSame($count * 6, DB::table('wbcms_persistent_documents')->where('attribute', 'meta')->whereIn('block_text_translation_id', BlockTextTranslation::whereIn('block_id', $page->blocks()->select('id'))->select('id'))->count());
    $ids = $page->revisions()->orderBy('id')->pluck('id');
    $this->assertCount(3, $ids);
    foreach ($ids as $id) {
      $saved = PageRevision::findOrFail($id);
      $snapshot = $saved->snapshot;
      $this->assertCount($count, $snapshot['blocks']);
      $this->assertCount(6, $snapshot['translations']);
      foreach ($snapshot['blocks'] as $block) {
        $this->assertCount(6, $block['text_translations']);
        $this->assertSame('{"alignment":"left","plugin":{"items":[false,null,1,"1"]}}', $block['settings']);
      }
      unset($saved, $snapshot);
    }
    $last = PageRevision::findOrFail($ids->last());
    $hash = SnapshotContent::hash($last->snapshot);
    foreach ($ids->slice(0, -1) as $id) {
      PageRevision::findOrFail($id)->delete();
    }
    $this->assertSame($hash, SnapshotContent::hash($last->fresh()->snapshot));
    $this->assertDatabaseHas('wbcms_revision_capture_events', ['page_revision_id' => $last->id, 'snapshot_created' => true]);
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  private function seedPage(int $count): Page
  {
    return PublicSearchIndexer::coalescing(function () use ($count): Page {
      $site = Site::create(['name' => 'Synthetic bulk publishing', 'handle' => 'synthetic-bulk', 'is_primary' => true]);
      $locales = collect(['en', 'tr', 'de', 'es', 'it', 'fr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_enabled' => true, 'is_default' => $code === 'en']));
      $site->locales()->sync($locales->mapWithKeys(fn ($l) => [$l->id => ['is_enabled' => true]])->all());
      $layout = PageLayout::create(['name' => 'Default', 'handle' => 'default', 'is_active' => true]);
      $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
      PageLayoutSlot::create(['page_layout_id' => $layout->id, 'slot_type_id' => $slot->id, 'slot_name' => 'main', 'is_active' => true, 'is_required' => true]);
      $type = BlockType::create(['name' => 'Plain text', 'slug' => 'plain_text', 'source_type' => 'static', 'status' => 'published']);
      $nested = true;
      $section = $nested ? BlockType::create(['name' => 'Section', 'slug' => 'section', 'source_type' => 'static', 'status' => 'published', 'is_container' => true]) : null;
      $page = Page::create(['site_id' => $site->id, 'title' => 'Large page', 'slug' => 'large-page', 'status' => 'published', 'settings' => ['public_shell' => 'default']]);
      foreach ($locales as $locale) {
        $page->translations()->updateOrCreate(['locale_id' => $locale->id], ['site_id' => $site->id, 'name' => 'Large page '.$locale->code, 'slug' => 'large-page', 'path' => '/large-page']);
      }
      PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE]);
      $rows = [];
      $rootId = null;
      for ($i = 0; $i < $count; $i++) {
        $selectedType = $nested && $i === 0 ? $section : $type;
        $attributes = ['page_id' => $page->id, 'block_type_id' => $selectedType->id, 'type' => $selectedType->slug, 'slot_type_id' => $slot->id, 'slot' => 'main', 'status' => 'published', 'sort_order' => $i];
        if ($nested) {
          $attributes += ['parent_id' => $rootId, 'settings' => '{"alignment":"left","plugin":{"items":[false,null,1,"1"]}}'];
        }
        $block = Block::create($attributes);
        $rootId ??= $block->id;
        foreach ($locales as $locale) {
        $rows[] = ['block_id' => $block->id, 'locale_id' => $locale->id, 'content' => 'Synthetic block '.$i.' locale '.$locale->code];
        }
      }
      DB::table('wbcms_block_text_translations')->insert($rows);

      return $page;
    });
  }
}

class LargeRevisionRestoreTestUser extends CmsSqlTestUser
{
  protected $table = 'users';
}
