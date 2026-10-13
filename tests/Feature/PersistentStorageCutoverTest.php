<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Relational\PersistentFields;
use WebBlocks\Cms\Support\Relational\PersistentSchema;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Tests\TestCase;

class PersistentStorageCutoverTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function page(): Page
  {
    $site = Site::first() ?? Site::create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);

    return Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft']);
  }

  #[Test]
  public function cutover_removes_every_registered_copy_and_keeps_full_snapshot_data(): void
  {
    $page = $this->page();
    $block = Block::create(['page_id' => $page->id, 'type' => 'hero', 'title' => 'Original', 'settings' => '{"columns":"3","plugin":{"a":[false,null,1,"1"]}}', 'meta' => '{"source":{"nested":[1,2]}}']);
    Block::create(['page_id' => $page->id, 'parent_id' => $block->id, 'type' => 'text', 'title' => 'Child']);
    $first = app(PageRevisionManager::class)->capture($page);
    $expected = $first->snapshot;
    $block->update(['title' => 'Changed', 'settings' => '{"columns":"4"}']);
    $second = app(PageRevisionManager::class)->capture($page);
    PersistentSchema::migrateAndRetire();
    PersistentSchema::migrateAndRetire();
    foreach (PersistentFields::MODELS as [$owner, $table, $attributes]) {
      foreach (array_keys($attributes) as $attribute) {
        $this->assertFalse(Schema::hasColumn($table, $attribute), $table.'.'.$attribute);
      }
    }
    foreach ([new Block, new EmbeddedApplication] as $model) {
      foreach (SettingDefinitions::attributes($model) as $attribute) {
        $this->assertFalse(Schema::hasColumn($model->getTable(), $attribute));
      }
    }
    $actual = $first->fresh()->snapshot;
    $this->assertSame(SnapshotContent::hash($expected), SnapshotContent::hash($actual));
    $this->assertSame('3', json_decode($actual['blocks'][0]['settings'], true)['columns']);
    $this->assertSame([false, null, 1, '1'], json_decode($actual['blocks'][0]['settings'], true)['plugin']['a']);
    $this->assertSame(['source' => ['nested' => [1, 2]]], json_decode($actual['blocks'][0]['meta'], true));
    $this->assertSame('Original', $actual['blocks'][0]['title']);
    $this->assertSame('Changed', $second->fresh()->snapshot['blocks'][0]['title']);
    $this->assertSame($second->id, app(PageRevisionManager::class)->capture($page->fresh())->id);
    $this->assertSame([$page->id], Page::whereRuntimeSettingNull('staged_update')->pluck('id')->all());
    $page->update(['settings' => ['staged_update' => ['source_page_id' => $page->id, 'type' => 'staged_update']]]);
    $this->assertSame([$page->id], Page::whereRuntimeSetting('staged_update.source_page_id', $page->id)->pluck('id')->all());
    $this->assertSame([], Page::whereRuntimeSettingNull('staged_update')->pluck('id')->all());
    $page->update(['settings' => ['public_shell' => str_repeat('x', 200)]]);
    $this->assertSame([$page->id], Page::whereRuntimeSetting('public_shell', str_repeat('x', 200))->pluck('id')->all());
    $block->delete();
    $this->assertSame(SnapshotContent::hash($expected), SnapshotContent::hash($first->fresh()->snapshot));
    $candidate = $this->page();
    app(PageRevisionManager::class)->applySnapshotToCandidate($candidate, $first->fresh()->snapshot, ['source_page_id' => $page->id, 'revision_id' => $first->id]);
    $restored = $candidate->blocks()->whereNull('parent_id')->firstOrFail();
    $this->assertSame('Original', $restored->title);
    $this->assertSame('3', $restored->setting('columns'));
    $this->assertSame('Child', $restored->children()->firstOrFail()->title);
    $this->assertSame(['source' => ['nested' => [1, 2]]], json_decode($restored->meta, true));
    $this->assertDatabaseHas('wbcms_snapshot_blocks', ['title' => 'Original']);
    $this->assertSame(strlen(json_encode($actual, JSON_THROW_ON_ERROR)), $first->fresh()->snapshot_bytes);
    $first->delete();
    $this->assertDatabaseMissing('wbcms_persistent_documents', ['page_revision_id' => $first->id]);
  }

  #[Test]
  public function loaded_bridge_request_can_finish_after_physical_result_column_is_removed(): void
  {
    $request = SystemUpdateRequest::create(['id' => (string) Str::uuid(), 'cms_api_token_id' => 1, 'idempotency_key' => 'synthetic', 'expected_current_version' => '1.98.6', 'target_version' => '1.98.7', 'checksum_sha256' => str_repeat('a', 64), 'status' => 'running']);
    // The previous updater can write its final result after preparation has run.
    DB::table($request->getTable())->where('id', $request->id)->update(['result' => '{"version":"1.98.7","output":"previous process"}']);
    $this->assertSame('previous process', $request->fresh()->result['output']);
    PersistentSchema::migrateAndRetire();
    $this->assertSame('previous process', $request->fresh()->result['output']);
    $request->update(['status' => 'completed', 'result' => ['version' => '1.98.8', 'duration_ms' => 12345, 'details' => ['keep' => true]]]);
    $this->assertSame('completed', $request->fresh()->status);
    $this->assertSame(['version' => '1.98.8', 'duration_ms' => 12345, 'details' => ['keep' => true]], $request->fresh()->result);
    $this->assertTrue($request->wasChanged('result'));
  }

  #[Test]
  public function virtual_updates_preserve_shapes_api_output_stale_writes_defaults_and_transactions(): void
  {
    $this->page();
    $value = ['01' => false, 'items' => ['x', 'x', [], null, 1, '1'], 'unicode' => 'İçerik 🎯'];
    $layout = PageLayout::create(['handle' => 'synthetic', 'name' => 'Synthetic', 'slot_schema' => $value, 'wrapper_schema' => []]);
    $import = SiteImport::create(['status' => 'partial', 'source_archive_name' => 'synthetic.zip', 'archive_disk' => 'local', 'archive_path' => 'synthetic.zip', 'resume_state' => ['maps' => ['blocks' => ['01' => 4, '2' => '5']], 'copied_files' => ['one', 'one']], 'summary_json' => [], 'manifest_json' => null]);
    PersistentSchema::migrateAndRetire();
    $this->assertSame($value, $layout->fresh()->toArray()['slot_schema']);
    $this->assertSame([], $layout->fresh()->wrapper_schema);
    $this->assertNull($import->fresh()->manifest_json);
    $this->assertSame($import->resume_state, $import->fresh()->resume_state);
    $stale = $layout->fresh();
    $layout->fresh()->update(['slot_schema' => ['new' => true]]);
    $stale->update(['name' => 'Renamed']);
    $this->assertSame(['new' => true], $layout->fresh()->slot_schema);
    try {
      DB::transaction(function () use ($layout): void {
        $layout->fresh()->update(['slot_schema' => ['rollback' => true]]);
        throw new RuntimeException('Synthetic rollback');
      });
    } catch (RuntimeException) {
    }
    $this->assertSame(['new' => true], $layout->fresh()->slot_schema);
    $block = Block::create(['page_id' => $this->page()->id, 'type' => 'hero']);
    $application = EmbeddedApplication::create(['handle' => 'synthetic', 'name' => 'Synthetic', 'version' => '1.0.0', 'render_mode' => 'inline', 'mount_element' => 'div']);
    $this->assertNull($block->fresh()->settings);
    $this->assertNull($application->fresh()->css_assets);
    $this->assertDatabaseHas('wbcms_setting_documents', ['block_id' => $block->id, 'attribute' => 'settings']);
    $this->assertDatabaseHas('wbcms_setting_documents', ['application_id' => $application->id, 'attribute' => 'css_assets']);
    app(SchedulerHealth::class)->scheduledHeartbeat();
    $this->assertDatabaseMissing('wbcms_system_settings', ['key' => SchedulerHealth::STATE_KEY]);
    $this->assertDatabaseHas('wbcms_scheduler_health', ['key' => 'notifications']);
  }

  #[Test]
  public function relational_icon_filters_match_keywords_and_whole_tags(): void
  {
    $icon = IconCatalogItem::create(['slug' => 'synthetic', 'label' => 'Example', 'categories' => ['ui'], 'contexts' => ['button'], 'keywords' => ['synthetic-word']]);
    PersistentSchema::migrateAndRetire();
    $this->assertSame([$icon->id], IconCatalogItem::search('synthetic-word')->pluck('id')->all());
    $this->assertSame([$icon->id], IconCatalogItem::tagged('ui')->pluck('id')->all());
    $this->assertSame([$icon->id], IconCatalogItem::tagged('button')->pluck('id')->all());
    $this->assertSame([], IconCatalogItem::tagged('but')->pluck('id')->all());
  }

  #[Test]
  public function legacy_plugin_files_are_imported_once_and_retirement_keeps_newer_sql_state(): void
  {
    $root = sys_get_temp_dir().'/wb-persistence-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($root.'/synthetic/1.0.0');
    config(['webblocks-plugins.install.root' => $root]);
    $repository = app(InstalledPluginRepository::class);
    try {
      file_put_contents($root.'/synthetic/enabled.json', '{"version":"1.0.0","extra":{"keep":true}}');
      file_put_contents($root.'/synthetic/1.0.0/setup.json', '{"setup":{"tables":["one","one"]}}');
      PersistentSchema::migrateAndRetire(false);
      $this->assertSame('1.0.0', $repository->enabledVersion('synthetic'));
      $this->assertSame(['tables' => ['one', 'one']], $repository->setupResult('synthetic', '1.0.0'));
      $repository->enable('synthetic', '2.0.0');
      $repository->recordSetupResult('synthetic', '1.0.0', ['new' => true]);
      PersistentSchema::migrateAndRetire();
      $this->assertSame('2.0.0', $repository->enabledVersion('synthetic'));
      $this->assertTrue($repository->setupResult('synthetic', '1.0.0')['new']);
      $this->assertArrayHasKey('ran_at', $repository->setupResult('synthetic', '1.0.0'));
      $this->assertFileDoesNotExist($root.'/synthetic/enabled.json');
      $this->assertFileDoesNotExist($root.'/synthetic/1.0.0/setup.json');
      $repository->disable('synthetic');
      $this->assertFileExists($root.'/synthetic/.disabled');
      $this->assertFileDoesNotExist($root.'/synthetic/disabled.json');
      $this->assertTrue($repository->isDisabled('synthetic'));
    } finally {
      File::deleteDirectory($root);
    }
  }

  #[Test]
  public function unchanged_icon_sync_avoids_per_icon_writes_and_retains_operator_settings(): void
  {
    $syncer = app(WebBlocksIconManifestSyncer::class);
    $first = $syncer->sync(WebBlocksIconManifestSyncer::bundledManifestPath());
    $icon = IconCatalogItem::first();
    $icon->update(['is_active' => false, 'sort_order' => 23]);
    $this->travel(5)->seconds();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $second = $syncer->sync(WebBlocksIconManifestSyncer::bundledManifestPath());
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertSame(200, $first['created']);
    $this->assertSame(200, $second['unchanged']);
    $this->assertSame(0, $second['updated']);
    $this->assertFalse($icon->fresh()->is_active);
    $this->assertSame(23, $icon->fresh()->sort_order);
    $writes = array_filter($queries, fn ($query) => preg_match('/^(insert|update|delete)/i', $query['query']));
    $this->assertCount(1, $writes);
    $this->assertSame(now()->toDateTimeString(), $icon->fresh()->synced_at->toDateTimeString());
  }

  #[Test]
  public function update_steps_record_elapsed_time_and_finish_on_stage_changes(): void
  {
    $this->travelTo(now()->startOfSecond());
    $run = SystemUpdateRun::create(['status' => 'running', 'started_at' => now()]);
    $progress = app(CmsUpdateProgress::class);
    $progress->start($run);
    $progress->stage('backup');
    $this->travel(3)->seconds();
    $progress->stage('migrations');
    $this->travel(2)->seconds();
    $progress->finish();
    $steps = DB::table('wbcms_system_update_run_steps')->orderBy('id')->get();
    $this->assertSame(['backup', 'migrations'], $steps->pluck('stage')->all());
    $this->assertSame([3000, 2000], $steps->pluck('duration_ms')->all());
    $run->update(['status' => 'completed', 'duration_ms' => 5000]);
    $this->assertSame(5000, $progress->payload()['elapsed_ms']);
    $this->assertFalse($progress->payload()['running']);
  }
}
