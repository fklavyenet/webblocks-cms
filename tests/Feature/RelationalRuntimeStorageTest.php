<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Relational\PersistentSchema;
use WebBlocks\Cms\Support\Relational\RelationalSchema;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Tests\TestCase;

class RelationalRuntimeStorageTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadRelationalBridgeFixtureMigrations();
  }

  private function page(array $settings = []): Page
  {
    $site = Site::first() ?? Site::create(['name' => 'Synthetic', 'handle' => 'synthetic']);

    return Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft', 'settings' => $settings]);
  }

  private function token(array $attributes = []): CmsApiToken
  {
    return CmsApiToken::create(array_replace(['name' => 'Synthetic', 'token_hash' => hash('sha256', uniqid('', true)), 'token_preview' => 'synthetic', 'capabilities' => ['content.read'], 'allowed_site_ids' => [], 'allowed_ip_ranges' => []], $attributes));
  }

  #[Test]
  public function sql_is_authoritative_for_getters_api_arrays_filters_and_rollback_copies(): void
  {
    $settings = ['public_shell' => 'default', 'seo' => ['keep' => true], 'staged_update' => ['type' => 'published_page_update', 'source_page_id' => 1234, 'state' => 'draft', 'managed_slots' => ['body', 'body', 'footer']]];
    $page = $this->page($settings);
    DB::table('wbcms_pages')->where('id', $page->id)->update(['settings' => json_encode(['public_shell' => 'tampered', 'seo' => ['keep' => true], 'staged_update' => ['state' => 'promoted']])]);
    $page->refresh();
    $this->assertEquals($settings, $page->settings);
    $this->assertEquals($settings, $page->toArray()['settings']);
    $this->assertEquals($settings, json_decode($page->serializedSettings(), true));
    $this->assertSame($page->id, Page::whereRuntimeSetting('staged_update.state', 'draft')->value('id'));
    $this->assertFalse(Page::whereRuntimeSetting('staged_update.state', 'promoted')->exists());
    $page->title = 'Unrelated change';
    $page->save();
    $this->assertEquals($settings, json_decode(DB::table('wbcms_pages')->where('id', $page->id)->value('settings'), true));
  }

  #[Test]
  public function upgrade_preserves_legacy_documents_lists_and_invalid_types_without_rewriting_originals(): void
  {
    $page = $this->page();
    $token = $this->token();
    PersistentSchema::dropFreshTables();
    RelationalSchema::dropFreshTables();
    $document = ['public_shell' => 'default', 'shared_slot_id' => '99999', 'source_sync' => ['type' => 'markdown', 'source_id' => 'abc', 'source_path' => '/docs', 'managed_slots' => ['body', 'body']], 'staged_update' => null, 'revision_restore_candidate' => 'broken', 'seo' => ['title' => 'Keep']];
    $raw = json_encode($document);
    DB::table('wbcms_pages')->where('id', $page->id)->update(['settings' => $raw]);
    DB::table('wbcms_sites')->where('id', $page->site_id)->update(['notification_settings' => null]);
    DB::table('wbcms_cms_api_tokens')->where('id', $token->id)->update(['allowed_site_ids' => '["99999",99999,-1]', 'capabilities' => '["content.read","content.read"]', 'allowed_ip_ranges' => 'null', 'capabilities_mode' => null, 'allowed_site_ids_mode' => null, 'allowed_ip_ranges_mode' => null]);
    SystemSetting::create(['key' => SchedulerHealth::STATE_KEY, 'value' => json_encode(['scheduler_last_seen_at' => now()->utc()->toIso8601String(), 'notifications_completed_at' => now()->utc()->toIso8601String()])]);
    RelationalSchema::ensure();
    $this->assertSame($raw, DB::table('wbcms_pages')->where('id', $page->id)->value('settings'));
    $this->assertEquals($document, $page->refresh()->settings);
    $this->assertNull($page->site->notification_settings);
    $this->assertSame(['99999', 99999, -1], $token->refresh()->allowed_site_ids);
    $this->assertSame(['content.read', 'content.read'], $token->capabilities);
    $this->assertNull($token->allowed_ip_ranges);
    $this->assertSame('healthy', app(SchedulerHealth::class)->snapshot()['status']);
    $this->assertNull(DB::table('wbcms_page_runtime_settings')->where('page_id', $page->id)->value('resolved_shared_slot_id'));
    $this->assertFalse(Page::whereRuntimeSettingNull('revision_restore_candidate')->exists());
    $this->assertTrue(Page::whereRuntimeSettingNull('staged_update.state')->exists());
    $page->update(['settings' => ['public_shell' => 'sql-after-upgrade', 'staged_update' => ['state' => 'Draft']]]);
    $this->assertFalse(Page::whereKey($page->id)->whereRuntimeSetting('staged_update.state', 'draft')->exists());
    RelationalSchema::ensure();
    $this->assertSame('sql-after-upgrade', $page->refresh()->settings['public_shell']);
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_13_090000_ensure_relational_runtime_settings.php')->down();
    $this->assertSame('sql-after-upgrade', $page->refresh()->settings['public_shell']);
  }

  #[Test]
  public function migration_crosses_batch_boundaries_without_skipping_or_overwriting_rows(): void
  {
    $first = $this->page();
    PersistentSchema::dropFreshTables();
    RelationalSchema::dropFreshTables();
    $rows = [];
    for ($i = 0; $i < 405; $i++) {
      $rows[] = ['site_id' => $first->site_id, 'title' => 'Synthetic '.$i, 'status' => 'draft', 'settings' => json_encode(['shared_slot_id' => $i + 1])];
    }
    foreach (array_chunk($rows, 100) as $batch) {
      DB::table('wbcms_pages')->insert($batch);
    }
    RelationalSchema::ensure();
    $this->assertDatabaseCount('wbcms_page_runtime_settings', 406);
    $this->assertSame(405, (int) DB::table('wbcms_page_runtime_settings')->max('shared_slot_reference'));
    RelationalSchema::ensure();
    $this->assertDatabaseCount('wbcms_page_runtime_settings', 406);
  }

  #[Test]
  public function absent_null_empty_and_malformed_fields_preserve_their_previous_api_values(): void
  {
    foreach ([[], ['source_sync' => []], ['source_sync' => null], ['source_sync' => ['managed_slots' => null]], ['source_sync' => ['managed_slots' => ['key' => 'body']]], ['shared_slot_id' => ['bad']], ['public_shell' => str_repeat('x', 200)]] as $settings) {
      $page = $this->page($settings);
      $this->assertEquals($settings, $page->fresh()->settings);
    }
    $page = $this->page(['revision_restore_candidate' => null]);
    DB::table('wbcms_pages')->where('id', $page->id)->update(['settings' => '{"revision_restore_candidate":{"state":"ready"}}']);
    $this->assertTrue(Page::whereKey($page->id)->whereRuntimeSettingNull('revision_restore_candidate')->exists());
    $this->assertNull($page->fresh()->settings['revision_restore_candidate']);
  }

  #[Test]
  public function token_null_empty_restricted_and_wildcard_lists_do_not_change_authorization_scope(): void
  {
    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic']);
    foreach ([null, [], ['content.read'], ['*'], ['content.read', 'content.read']] as $capabilities) {
      $token = $this->token(['capabilities' => $capabilities, 'allowed_site_ids' => [$site->id], 'allowed_ip_ranges' => ['127.0.0.1/32', '::1/128']]);
      DB::table($token->getTable())->where('id', $token->id)->update(['capabilities' => '["admin.*"]', 'allowed_site_ids' => '[]', 'allowed_ip_ranges' => '[]']);
      $this->assertSame($capabilities, $token->refresh()->capabilities);
      $this->assertSame([$site->id], $token->allowed_site_ids);
      $this->assertSame(['127.0.0.1/32', '::1/128'], $token->allowed_ip_ranges);
      $this->assertArrayNotHasKey('capabilities_mode', $token->toArray());
      $token->update(['name' => 'Renamed']);
      $this->assertSame($capabilities, json_decode(DB::table($token->getTable())->where('id', $token->id)->value('capabilities'), true));
    }
  }

  #[Test]
  public function application_references_use_sql_for_reads_queries_and_serialization(): void
  {
    $page = $this->page();
    $settings = ['application_handle' => 'synthetic-app', 'other' => true];
    $block = Block::create(['page_id' => $page->id, 'type' => 'application', 'settings' => json_encode($settings)]);
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['settings' => '{"application_handle":"tampered","other":true}']);
    $block->refresh();
    $this->assertSame('synthetic-app', json_decode($block->settings, true)['application_handle']);
    $this->assertSame($block->id, Block::whereRuntimeSetting('application_handle', 'synthetic-app')->value('id'));
    $this->assertEquals($settings, json_decode($block->portableAttributes()['settings'], true));
    $this->assertEquals($settings, json_decode($block->toArray()['settings'], true));
    $block->delete();
    $this->assertDatabaseCount('wbcms_block_application_references', 0);
  }

  #[Test]
  public function foreign_keys_preserve_dangling_reference_values_and_remove_owned_children(): void
  {
    $source = $this->page();
    $page = $this->page(['staged_update' => ['source_page_id' => $source->id, 'managed_slots' => ['body']]]);
    $this->assertSame($source->id, (int) DB::table('wbcms_page_runtime_settings')->where('page_id', $page->id)->value('resolved_staged_source_id'));
    $source->delete();
    $row = DB::table('wbcms_page_runtime_settings')->where('page_id', $page->id)->first();
    $this->assertNull($row->resolved_staged_source_id);
    $this->assertSame($source->id, (int) $row->staged_source_reference);
    $page->delete();
    $this->assertDatabaseCount('wbcms_page_runtime_items', 0);
    $this->assertDatabaseCount('wbcms_page_runtime_settings', 0);
  }

  #[Test]
  public function failed_writes_roll_back_sql_lists_and_legacy_copies_together(): void
  {
    $page = $this->page(['source_sync' => ['managed_slots' => ['body']]]);
    $before = $page->settings;
    try {
      DB::transaction(function () use ($page): void {
        $page->update(['settings' => ['source_sync' => ['managed_slots' => ['footer']]]]);
        throw new RuntimeException('Synthetic failure');
      });
    } catch (RuntimeException) {
    }
    $this->assertEquals($before, $page->refresh()->settings);
    $this->assertEquals($before, json_decode(DB::table('wbcms_pages')->where('id', $page->id)->value('settings'), true));
    $this->assertSame(['body'], DB::table('wbcms_page_runtime_items')->pluck('value')->all());
  }

  #[Test]
  public function unrelated_saves_from_stale_instances_preserve_concurrent_settings_and_token_grants(): void
  {
    $page = $this->page(['public_shell' => 'default']);
    $this->assertSame('default', $page->settings['public_shell']);
    $page->fresh()->update(['settings' => ['public_shell' => 'updated']]);
    $page->update(['title' => 'Unrelated']);
    $this->assertSame('updated', $page->refresh()->settings['public_shell']);
    $token = $this->token();
    $this->assertSame(['content.read'], $token->capabilities);
    $token->fresh()->update(['capabilities' => [], 'allowed_site_ids' => [123]]);
    $token->update(['last_used_at' => now()]);
    $this->assertSame([], $token->refresh()->capabilities);
    $this->assertSame([123], $token->allowed_site_ids);
    $this->assertSame([], json_decode(DB::table($token->getTable())->where('id', $token->id)->value('capabilities'), true));
  }

  #[Test]
  public function scheduler_reads_sql_and_restores_its_compatibility_copy_on_real_callbacks(): void
  {
    $health = app(SchedulerHealth::class);
    $health->scheduledHeartbeat();
    $health->dispatchCompleted();
    SystemSetting::where('key', SchedulerHealth::STATE_KEY)->update(['value' => '{}']);
    $this->assertSame('healthy', $health->snapshot()['status']);
    $health->dispatchStarted();
    $mirror = json_decode(SystemSetting::where('key', SchedulerHealth::STATE_KEY)->value('value'), true);
    $this->assertNotNull($mirror['scheduler_last_seen_at']);
    $this->assertNotNull($mirror['notifications_completed_at']);
  }

  #[Test]
  public function snapshot_hashes_ignore_equivalent_serialized_settings_order_and_escaping(): void
  {
    $this->assertSame(SnapshotContent::hash(['page' => ['settings' => '{"public_shell":"default","path":"\\/docs"}']]), SnapshotContent::hash(['page' => ['settings' => '{"path":"/docs","public_shell":"default"}']]));
    $this->assertNotSame(SnapshotContent::hash(['page' => ['settings' => '{"public_shell":"default"}']]), SnapshotContent::hash(['page' => ['settings' => '{"public_shell":"docs"}']]));
  }
}
