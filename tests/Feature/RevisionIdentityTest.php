<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Pages\PageDuplicator;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionIdentityMatcher;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\RevisionIdentitySchema;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Sites\ExportImport\ImportDataMapper;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportDataBuilder;
use WebBlocks\Cms\Support\Sites\SiteCloneOptions;
use WebBlocks\Cms\Support\Sites\SiteCloneService;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RevisionIdentityTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $database = getenv('WEBBLOCKS_IDENTITY_TEST_DATABASE');
    if ($database !== false && $database !== '') {
      if (! preg_match('/^wb_identity_test_[a-f0-9]+$/D', $database)) {
        throw new LogicException('Identity integration tests require an isolated database name.');
      }
      $app['config']->set('database.default', 'mysql');
      $app['config']->set('database.connections.mysql', ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]);
    }
  }

  protected function defineDatabaseMigrations(): void
  {
    $isolatedMysql = $this->app['config']->get('database.default') === 'mysql';
    if ($isolatedMysql) {
      // defineEnvironment accepts only a disposable, task-specific database.
      // Rebuild it per case; old additive migrations deliberately retain tables.
      Schema::dropAllTables();
    }
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
    if ($isolatedMysql) {
      // Like the base SQLite harness, discard this disposable connection instead
      // of replaying unrelated historical down migrations. The tested identity
      // migration's up/rerun/down behavior is exercised explicitly below.
      array_shift($this->cachedTestMigratorProcessors);
      $this->beforeApplicationDestroyed(fn () => DB::disconnect());
    }
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
  }

  #[Test]
  public function snapshots_round_trip_typed_identities_and_match_without_writes(): void
  {
    [$page, $root, $gallery, $first, $second] = $this->fixture();
    $revision = $this->capture($page);
    $this->assertSame(2, $revision->fresh()->snapshot['schema_version']);
    $snapshotGallery = collect($revision->fresh()->snapshot['blocks'])->firstWhere('snapshot_id', $gallery->id);
    $this->assertSame($gallery->revision_key, $snapshotGallery['revision_key']);
    $this->assertSame($first->revision_key, $snapshotGallery['block_media'][0]['revision_key']);
    $this->assertSame($first->id, $snapshotGallery['block_media'][0]['snapshot_id']);
    $this->assertNotSame($first->revision_key, $second->revision_key);
    $before = $this->state($page);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision->fresh());
    $writes = array_filter(DB::getQueryLog(), fn ($row) => preg_match('/^\s*(insert|update|delete|alter|create)\b/i', $row['query']));
    DB::disableQueryLog();
    $this->assertSame([], $writes);
    $this->assertTrue($result['compatible'], json_encode($result['issues']));
    $this->assertSame([$root->id => $root->id, $gallery->id => $gallery->id], $result['blocks']);
    $this->assertSame([$first->id, $second->id], $result['media'][$gallery->id]);
    $this->assertSame($before, $this->state($page));
    $this->assertNotSame('blocked', app(PageRevisionInspector::class)->inspect($page, $revision)['health']['status']);
  }

  #[Test]
  public function restore_preview_and_repeated_full_restore_keep_lineage_across_new_database_ids(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $actor = $this->actor();
    $revision = $this->capture($page);
    $manager = app(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page, $revision, $actor);
    $preview = $candidate->candidatePage->blocks()->where('revision_key', $gallery->revision_key)->sole();
    $this->assertNotSame($gallery->id, $preview->id);
    $this->assertSame($first->revision_key, $preview->blockAssets()->orderBy('position')->first()->revision_key);
    $this->assertSame($gallery->id, $page->blocks()->where('revision_key', $gallery->revision_key)->sole()->id);
    $manager->apply($candidate, $actor);
    $restored = $page->blocks()->where('revision_key', $gallery->revision_key)->sole();
    $this->assertNotSame($gallery->id, $restored->id);
    $this->assertSame($first->revision_key, $restored->blockAssets()->orderBy('position')->first()->revision_key);
    $this->assertTrue(app(PageRevisionIdentityMatcher::class)->match($page->fresh(), $revision->fresh())['compatible']);
    app(PageRevisionManager::class)->restore($page->fresh(), $revision, $actor);
    $this->assertTrue(app(PageRevisionIdentityMatcher::class)->match($page->fresh(), $revision->fresh())['compatible']);
  }

  #[Test]
  public function staged_copy_and_promotion_preserve_identity(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $page->update(['status' => Page::STATUS_PUBLISHED]);
    $service = app(InternalContentPlanService::class);
    $created = $service->apply(['plan' => ['mode' => 'create_staged_update_for_published_page', 'source_page_id' => $page->id, 'expected_source_path' => '/service', 'managed_slots' => []]]);
    $this->assertTrue($created->ok, json_encode($created->errors));
    $staged = Page::findOrFail($created->data['staged_page']['id']);
    $copy = $staged->blocks()->where('revision_key', $gallery->revision_key)->sole();
    $this->assertSame($first->revision_key, $copy->blockAssets()->orderBy('position')->first()->revision_key);
    $promoted = $service->apply(['plan' => ['mode' => 'promote_staged_page_update', 'staged_page_id' => $staged->id, 'expected_source_page_id' => $page->id, 'expected_source_path' => '/service', 'promote_slots' => ['main']]]);
    $this->assertTrue($promoted->ok, json_encode($promoted->errors));
    $this->assertSame($first->revision_key, $page->blocks()->where('revision_key', $gallery->revision_key)->sole()->blockAssets()->orderBy('position')->first()->revision_key);
  }

  #[Test]
  public function independent_replication_and_page_duplication_get_fresh_keys(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $replica = $gallery->replicate();
    $replica->save();
    $this->assertNotSame($gallery->revision_key, $replica->revision_key);
    $replica->delete();
    $item = $first->replicate();
    $item->position = 9;
    $item->save();
    $this->assertNotSame($first->revision_key, $item->revision_key);
    $duplicate = app(PageDuplicator::class)->duplicate($page->fresh(), $page->site, $this->actor(), collect([['locale_id' => $page->defaultTranslation()->locale_id, 'name' => 'Independent copy', 'slug' => 'independent', 'path' => '/independent']]));
    $originalKeys = $page->blocks()->pluck('revision_key')->all();
    $this->assertSame([], array_intersect($originalKeys, $duplicate->page->blocks()->pluck('revision_key')->all()));
    $this->assertSame([], array_intersect($gallery->blockAssets()->pluck('revision_key')->all(), $duplicate->page->blocks()->where('type', 'gallery')->sole()->blockAssets()->pluck('revision_key')->all()));
  }

  public static function identityOwners(): array
  {
    return ['block' => [false], 'gallery item' => [true]];
  }

  #[Test]
  #[DataProvider('identityOwners')]
  public function live_identity_is_immutable(bool $galleryItem): void
  {
    [, , $gallery, $first] = $this->fixture();
    $model = $galleryItem ? $first : $gallery;
    $model->revision_key = (string) Str::uuid();
    $this->expectException(LogicException::class);
    $model->save();
  }

  #[Test]
  #[DataProvider('identityOwners')]
  public function same_owner_cannot_have_duplicate_identity(bool $galleryItem): void
  {
    [$page, $root, $gallery, $first, $second] = $this->fixture();
    $this->expectException(QueryException::class);
    DB::table($galleryItem ? 'wbcms_block_media' : 'wbcms_blocks')->where('id', $galleryItem ? $second->id : $gallery->id)->update(['revision_key' => $galleryItem ? $first->revision_key : $root->revision_key]);
  }

  #[Test]
  public function legacy_live_blocks_match_but_legacy_gallery_items_are_not_guessed(): void
  {
    [$page, $root, $gallery] = $this->fixture();
    $legacy = $this->legacy($this->capture($page));
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $legacy);
    $this->assertSame($root->id, $result['blocks'][$root->id]);
    $this->assertSame($gallery->id, $result['blocks'][$gallery->id]);
    $this->assertFalse($result['compatible']);
    $this->assertSame([], $result['media']);
    $this->assertContains('legacy_media_identity_missing', array_column($result['issues'], 'code'));
    $this->assertNotSame('blocked', app(PageRevisionInspector::class)->inspect($page, $legacy)['health']['status']);
  }

  #[Test]
  public function legacy_full_restore_remains_supported_without_fabricating_matching_history(): void
  {
    [$page, $root, $gallery] = $this->fixture();
    $legacy = $this->legacy($this->capture($page));
    $before = $legacy->fresh()->snapshot;
    app(PageRevisionManager::class)->restore($page->fresh(), $legacy, $this->actor());
    $result = app(PageRevisionIdentityMatcher::class)->match($page->fresh(), $legacy->fresh());
    $this->assertSame([], $result['blocks']);
    $this->assertFalse($result['compatible']);
    $this->assertSame($before, $legacy->fresh()->snapshot);
    $this->assertSame(2, $page->blocks()->count());
  }

  #[Test]
  public function gallery_reordering_maps_the_exact_item_and_reports_structural_change(): void
  {
    [$page, $root, $gallery, $first, $second] = $this->fixture();
    $revision = $this->capture($page);
    $first->update(['position' => 8]);
    $second->update(['position' => 0]);
    $first->update(['position' => 1]);
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
    $this->assertSame([$first->id, $second->id], $result['media'][$gallery->id]);
    $this->assertFalse($result['compatible']);
    $this->assertContains('media_placement_changed', array_column($result['issues'], 'code'));
  }

  #[Test]
  public function deleting_and_recreating_identical_copy_does_not_establish_identity(): void
  {
    [$page, $root, $gallery] = $this->fixture();
    $revision = $this->capture($page);
    $copy = $gallery->replicate();
    $gallery->delete();
    $copy->save();
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
    $this->assertArrayNotHasKey($gallery->id, $result['blocks']);
    $this->assertContains('block_identity_not_found', array_column($result['issues'], 'code'));
  }

  public static function changedStructure(): array
  {
    return ['type' => ['type', 'header', 'block_type_changed'], 'parent' => ['parent_id', null, 'block_parent_changed'], 'order' => ['sort_order', 8, 'block_placement_changed']];
  }

  #[Test]
  #[DataProvider('changedStructure')]
  public function structural_changes_are_not_reported_as_compatible(string $field, mixed $value, string $code): void
  {
    [$page, $root, $gallery] = $this->fixture();
    $revision = $this->capture($page);
    $gallery->update([$field => $value]);
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
    $this->assertFalse($result['compatible']);
    $this->assertContains($code, array_column($result['issues'], 'code'));
  }

  #[Test]
  public function duplicate_or_missing_modern_keys_cannot_fall_back_to_database_ids(): void
  {
    [$page] = $this->fixture();
    $revision = $this->capture($page);
    foreach (['duplicate', 'missing', 'invalid'] as $case) {
      $snapshot = $revision->snapshot;
      $snapshot['blocks'][1]['revision_key'] = match ($case) {
      'duplicate' => $snapshot['blocks'][0]['revision_key'], 'invalid' => 'not-a-uuid', default => null
      };
      $revision->snapshot = $snapshot;
      $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
      $this->assertFalse($result['compatible']);
      $this->assertArrayNotHasKey($snapshot['blocks'][1]['snapshot_id'], $result['blocks']);
      $this->assertContains('invalid_snapshot_block_identity', array_column($result['issues'], 'code'));
    }
  }

  #[Test]
  public function foreign_page_revision_is_refused_even_if_keys_were_copied(): void
  {
    [$page] = $this->fixture();
    $revision = $this->capture($page);
    $other = Page::create(['site_id' => $page->site_id, 'title' => 'Other page', 'slug' => 'other', 'status' => 'draft']);
    $result = app(PageRevisionIdentityMatcher::class)->match($other, $revision);
    $this->assertSame([], $result['blocks']);
    $this->assertSame('revision_owner_mismatch', $result['issues'][0]['code']);
  }

  #[Test]
  public function migration_backfills_only_live_nulls_and_preserves_historical_hashes_and_timestamps(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $revision = $this->legacy($this->capture($page));
    $historical = $revision->fresh()->getAttributes();
    $snapshot = $revision->fresh()->snapshot;
    $timestamps = [$gallery->updated_at->toDateTimeString(), $first->updated_at->toDateTimeString()];
    DB::table('wbcms_blocks')->where('id', $gallery->id)->update(['revision_key' => null]);
    DB::table('wbcms_block_media')->where('id', $first->id)->update(['revision_key' => null]);
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_01_120000_add_revision_identities.php';
    $migration->up();
    $keys = [$gallery->fresh()->revision_key, $first->fresh()->revision_key];
    $this->assertTrue(Str::isUuid($keys[0]));
    $this->assertTrue(Str::isUuid($keys[1]));
    $migration->up();
    $migration->down();
    $this->assertSame($keys, [$gallery->fresh()->revision_key, $first->fresh()->revision_key]);
    $this->assertSame($root->revision_key, $root->fresh()->revision_key);
    $this->assertSame($timestamps, [$gallery->fresh()->updated_at->toDateTimeString(), $first->fresh()->updated_at->toDateTimeString()]);
    $this->assertSame($historical, $revision->fresh()->getAttributes());
    $this->assertSame($snapshot, $revision->fresh()->snapshot);
  }

  #[Test]
  public function site_export_excludes_local_history_keys_and_import_ignores_supplied_keys(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $export = app(SiteExportDataBuilder::class)->build($page->site, false);
    $this->assertArrayNotHasKey('revision_key', $export['blocks'][0]);
    $type = BlockType::firstOrCreate(['slug' => 'gallery'], ['name' => 'Gallery', 'source_type' => 'static']);
    $other = Page::create(['site_id' => $page->site_id, 'title' => 'Imported page', 'slug' => 'imported', 'status' => 'draft']);
    $log = [];
    $payload = ['blocks' => [['id' => 1, 'page_id' => 1, 'type' => 'gallery', 'block_type_slug' => 'gallery', 'slot_type_slug' => 'main', 'revision_key' => $gallery->revision_key]]];
    $mapper = app(ImportDataMapper::class);
    $method = new \ReflectionMethod($mapper, 'importBlocks');
    $ids = $method->invokeArgs($mapper, [$payload, [1 => $other->id], [], &$log]);
    $imported = Block::findOrFail($ids[1]);
    $this->assertNotSame($gallery->revision_key, $imported->revision_key);
    $method = new \ReflectionMethod($mapper, 'importBlockAssets');
    $media = ['block_media' => [['id' => 1, 'block_id' => 1, 'media_id' => 1, 'role' => 'gallery', 'position' => 0, 'revision_key' => $first->revision_key]]];
    $ids = $method->invokeArgs($mapper, [$media, [1 => $imported->id], [1 => $first->media_id], &$log]);
    $this->assertNotSame($first->revision_key, BlockMedia::findOrFail($ids[1])->revision_key);
  }

  #[Test]
  public function shared_slot_restore_preserves_source_block_and_gallery_lineage(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $shared = SharedSlot::create(['site_id' => $page->site_id, 'name' => 'Shared gallery', 'handle' => 'shared-gallery', 'slot_name' => 'main', 'is_active' => false]);
    $source = app(SharedSlotSourcePageManager::class)->ensureFor($shared);
    $page->blocks()->update(['page_id' => $source->id]);
    $manager = app(SharedSlotRevisionManager::class);
    $revision = $manager->capture($shared->fresh(), $this->actor());
    $this->assertSame(2, $revision->fresh()->snapshot['schema_version']);
    $manager->restore($shared->fresh(), $revision->fresh(), $this->actor());
    $restored = $source->blocks()->where('revision_key', $gallery->revision_key)->sole();
    $this->assertNotSame($gallery->id, $restored->id);
    $this->assertSame($first->revision_key, $restored->blockAssets()->orderBy('position')->first()->revision_key);
  }

  #[Test]
  public function independent_site_clone_does_not_inherit_history_keys(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $clone = app(SiteCloneService::class)->clone($page->site_id, 'independent-studio', new SiteCloneOptions(copyMediaFiles: false));
    $keys = Block::whereHas('page', fn ($query) => $query->where('site_id', $clone->targetSite->id))->pluck('revision_key')->all();
    $this->assertCount(2, $keys);
    $this->assertSame([], array_intersect([$root->revision_key, $gallery->revision_key], $keys));
  }

  #[Test]
  public function actual_old_schema_upgrade_adds_fields_without_altering_legacy_snapshots(): void
  {
    [$page] = $this->fixture();
    $revision = $this->legacy($this->capture($page));
    $snapshot = $revision->fresh()->snapshot;
    $hash = $revision->fresh()->snapshot_hash;
    foreach (['wbcms_blocks' => 'page_id', 'wbcms_block_media' => 'block_id'] as $table => $owner) {
      // InnoDB may replace its auto-created FK index with the new composite
      // index. Restore a legacy owner index before reconstructing old schema.
      Schema::table($table, fn ($blueprint) => $blueprint->index($owner, $table.'_identity_legacy_owner'));
      Schema::table($table, fn ($blueprint) => $blueprint->dropUnique($table.'_revision_identity'));
      Schema::table($table, fn ($blueprint) => $blueprint->dropColumn('revision_key'));
    }
    foreach (['wbcms_snapshot_blocks' => ['revision_key'], 'wbcms_snapshot_block_media' => ['revision_key', 'snapshot_id']] as $table => $fields) {
      foreach ($fields as $field) {
        Schema::table($table, fn ($blueprint) => $blueprint->dropColumn([$field, $field.'_kind', $field.'_text']));
      }
    }
    RevisionIdentitySchema::ensure();
    $this->assertSame($snapshot, $revision->fresh()->snapshot);
    $this->assertSame($hash, $revision->fresh()->snapshot_hash);
    $this->assertSame(0, $page->blocks()->whereNull('revision_key')->count());
    $this->assertTrue(Schema::hasColumn('wbcms_snapshot_block_media', 'revision_key_kind'));
  }

  #[Test]
  public function changed_gallery_media_and_ambiguous_item_keys_are_reported(): void
  {
    [$page, $root, $gallery, $first] = $this->fixture();
    $revision = $this->capture($page);
    $other = Media::create(['disk' => 'public', 'path' => 'synthetic/other.jpg', 'filename' => 'other.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'visibility' => 'public']);
    $first->update(['media_id' => $other->id]);
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
    $this->assertContains('media_assignment_changed', array_column($result['issues'], 'code'));
    $snapshot = $revision->snapshot;
    $snapshot['blocks'][1]['block_media'][1]['revision_key'] = $snapshot['blocks'][1]['block_media'][0]['revision_key'];
    $revision->snapshot = $snapshot;
    $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
    $this->assertFalse($result['compatible']);
    $this->assertEmpty($result['media']);
    $this->assertContains('invalid_snapshot_media_identity', array_column($result['issues'], 'code'));
  }

  #[Test]
  public function malformed_snapshots_and_unknown_versions_fail_closed(): void
  {
    [$page] = $this->fixture();
    $revision = $this->capture($page);
    $original = $revision->snapshot;
    foreach (['unknown_version', 'scalar_block', 'scalar_media', 'duplicate_id'] as $case) {
      $snapshot = $original;
      if ($case === 'unknown_version') {
        $snapshot['schema_version'] = 99;
      } elseif ($case === 'scalar_block') {
        $snapshot['blocks'][0] = 'broken';
      } elseif ($case === 'scalar_media') {
        $snapshot['blocks'][1]['block_media'] = ['broken'];
      } else {
        $snapshot['blocks'][1]['snapshot_id'] = $snapshot['blocks'][0]['snapshot_id'];
      }
      $revision->snapshot = $snapshot;
      $result = app(PageRevisionIdentityMatcher::class)->match($page, $revision);
      $this->assertFalse($result['compatible']);
      $this->assertSame([], $result['blocks']);
    }
  }

  #[Test]
  public function partial_promotion_cannot_duplicate_identity_in_an_unmanaged_source_slot(): void
  {
    [$page, $root, $gallery] = $this->fixture();
    $page->update(['status' => Page::STATUS_PUBLISHED]);
    $other = SlotType::create(['name' => 'Other', 'slug' => 'other', 'status' => 'published']);
    $page->slots()->create(['slot_type_id' => $other->id, 'sort_order' => 1, 'source_type' => 'page']);
    $service = app(InternalContentPlanService::class);
    $created = $service->apply(['plan' => ['mode' => 'create_staged_update_for_published_page', 'source_page_id' => $page->id, 'expected_source_path' => '/service', 'managed_slots' => []]]);
    $this->assertTrue($created->ok, json_encode($created->errors));
    $staged = Page::findOrFail($created->data['staged_page']['id']);
    foreach ($staged->blocks as $block) {
      $block->update(['slot_type_id' => $other->id]);
    }
    $before = $this->state($page);
    $count = $page->revisions()->count();
    $result = $service->apply(['plan' => ['mode' => 'promote_staged_page_update', 'staged_page_id' => $staged->id, 'expected_source_page_id' => $page->id, 'expected_source_path' => '/service', 'promote_slots' => ['other']]]);
    $this->assertFalse($result->ok);
    $this->assertSame($before, $this->state($page));
    $this->assertSame($count, $page->revisions()->count());
    $this->assertNotNull($staged->fresh());
  }

  private function fixture(): array
  {
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'studio', 'is_primary' => true]);
    Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Service', 'slug' => 'service', 'status' => Page::STATUS_DRAFT]);
    $page->slots()->create(['slot_type_id' => $slot->id, 'sort_order' => 0, 'source_type' => 'page']);
    $root = $page->blocks()->create(['type' => 'grid', 'slot_type_id' => $slot->id, 'source_type' => 'static', 'sort_order' => 0, 'status' => 'draft']);
    $gallery = $page->blocks()->create(['type' => 'gallery', 'parent_id' => $root->id, 'slot_type_id' => $slot->id, 'source_type' => 'static', 'sort_order' => 0, 'status' => 'draft']);
    $media = Media::create(['disk' => 'public', 'path' => 'synthetic/photo.jpg', 'filename' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'visibility' => 'public']);
    $first = $gallery->blockAssets()->create(['media_id' => $media->id, 'role' => 'gallery', 'position' => 0]);
    $second = $gallery->blockAssets()->create(['media_id' => $media->id, 'role' => 'gallery', 'position' => 1]);

    return [$page->fresh(), $root, $gallery, $first, $second];
  }

  private function capture(Page $page): PageRevision
  {
    return app(PageRevisionManager::class)->capture($page->fresh());
  }

  private function actor()
  {
    $class = 'App\\Models\\User';

    return $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
  }

  private function legacy(PageRevision $revision): PageRevision
  {
    $snapshot = $revision->snapshot;
    $snapshot['schema_version'] = 1;
    foreach ($snapshot['blocks'] as &$block) {
      unset($block['revision_key']);
      foreach ($block['block_media'] as &$media) {
        unset($media['revision_key'], $media['snapshot_id']);
      }
      unset($media);
    }
    unset($block);

    return PageRevision::create(['page_id' => $revision->page_id, 'site_id' => $revision->site_id, 'snapshot' => $snapshot, 'snapshot_hash' => SnapshotContent::hash($snapshot), 'snapshot_bytes' => strlen(json_encode($snapshot, JSON_THROW_ON_ERROR))]);
  }

  private function state(Page $page): array
  {
    $snapshot = app(PageRevisionManager::class)->snapshotForInspection($page->fresh());
    unset($snapshot['captured_at']);

    return $snapshot;
  }
}
