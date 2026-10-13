<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Applications\ApplicationRegistry;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Tests\TestCase;

class StructuredSettingsStorageTest extends TestCase
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

  private function block(mixed $settings): Block
  {
    return Block::create(['page_id' => $this->page()->id, 'type' => 'hero', 'settings' => is_array($settings) ? json_encode($settings, JSON_THROW_ON_ERROR) : $settings]);
  }

  private function application(array $values = []): EmbeddedApplication
  {
    return EmbeddedApplication::create(array_replace(['handle' => 'synthetic-'.EmbeddedApplication::count(), 'name' => 'Synthetic', 'version' => '1.0.0', 'render_mode' => 'inline', 'mount_element' => 'div'], $values));
  }

  private function document(Block|EmbeddedApplication $model, string $attribute = 'settings'): int
  {
    return (int) DB::table('wbcms_setting_documents')->where($model instanceof Block ? 'block_id' : 'application_id', $model->id)->where('attribute', $attribute)->value('id');
  }

  #[Test]
  public function typed_core_fields_and_content_references_are_sql_authority_for_api_and_snapshots(): void
  {
    $template = $this->block(null);
    $settings = ['columns' => '3', 'autoplay' => false, 'interval_ms' => 6000, 'menu_key' => 'primary', 'content_bindings' => ['title' => ['source' => 'synthetic::catalog', 'record' => 'opaque:key', 'field' => 'name']], 'content_collection' => ['source' => 'synthetic::items', 'template_block_id' => $template->id, 'limit' => 12, 'paginate' => false, 'source_settings' => ['nested' => ['keep' => true]]], 'application_settings' => ['duration' => 30, 'dark' => false]];
    $block = $this->block($settings);
    $document = $this->document($block);
    $this->assertDatabaseHas('wbcms_block_appearance_settings', ['document_id' => $document, 'columns' => '3']);
    $this->assertDatabaseHas('wbcms_block_media_settings', ['document_id' => $document, 'autoplay' => 0, 'interval_ms' => 6000]);
    $this->assertDatabaseHas('wbcms_block_content_bindings', ['document_id' => $document, 'source' => 'synthetic::catalog', 'record' => 'opaque:key', 'field' => 'name']);
    $this->assertDatabaseHas('wbcms_block_content_collections', ['document_id' => $document, 'resolved_template_id' => $template->id]);
    $before = app(InternalContentApiPresenter::class)->block($block->fresh(), false);
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['settings' => '{"columns":"tampered"}']);
    $this->assertSame($before, app(InternalContentApiPresenter::class)->block($block->fresh(), false));
    $this->assertSame($settings, $block->fresh()->decodedSettings());
    DB::table('wbcms_block_appearance_settings')->where('document_id', $document)->update(['columns' => '4']);
    $block = $block->fresh();
    $this->assertSame('4', $block->setting('columns'));
    $snapshot = app(PageRevisionManager::class)->snapshotForInspection($block->page->fresh());
    $snapshotBlock = collect($snapshot['blocks'])->firstWhere('snapshot_id', $block->id);
    $this->assertSame('4', json_decode($snapshotBlock['settings'], true)['columns']);
    $block->update(['title' => 'Unrelated']);
    $this->assertSame('4', json_decode(DB::table('wbcms_blocks')->where('id', $block->id)->value('settings'), true)['columns']);
  }

  #[Test]
  public function nested_unknown_fields_types_keys_and_duplicate_lists_survive_mirror_corruption(): void
  {
    $settings = ['plugin.value' => ['0' => null, '01' => false, 'items' => ['x', 'x', 1, '1', [], null]], 'plugin' => ['unicode' => 'İçerik 🎯', 'big' => PHP_INT_MAX, 'decimal' => 0.125, 'long' => str_repeat('x', 70000)], 'autoplay' => 'legacy-yes', 'columns' => ['legacy-array'], 'content_collection' => null, 'content_bindings' => ['bad' => null], 'application_settings' => ['nested' => ['arbitrary' => true]]];
    $block = $this->block($settings);
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['settings' => 'null']);
    $this->assertSame($settings, $block->fresh()->decodedSettings());
    $this->assertSame($settings, json_decode($block->fresh()->portableAttributes()['settings'], true));
    $this->assertSame($settings, json_decode($block->fresh()->toArray()['settings'], true));
  }

  #[Test]
  public function application_lists_schema_defaults_and_registry_checksum_keep_their_contract(): void
  {
    $values = ['css_assets' => ['/a.css', '/a.css', '/b.css'], 'js_assets' => ['/old.js', ['path' => '/new.js', 'type' => 'module', 'load_position' => 'head', 'legacy' => ['keep' => true]]], 'supports' => ['locale' => true, 'theme' => false, 'fullscreen' => null, 'unknown' => ['keep' => true]], 'settings_schema' => ['enabled' => ['type' => 'boolean', 'default' => false], 'duration' => ['type' => 'integer', 'min' => -1, 'max' => 120, 'default' => 30], 'style' => ['type' => 'enum', 'values' => ['a', 'a', 'b'], 'default' => 'a'], 'title' => ['type' => 'string', 'default' => '', 'max_length' => 200], 'empty' => [], 'malformed' => 'retain']];
    $application = $this->application($values);
    $before = app(ApplicationRegistry::class)->find($application->handle)->toArray();
    DB::table('wbcms_embedded_applications')->where('id', $application->id)->update(array_fill_keys(SettingDefinitions::APPLICATION_ATTRIBUTES, 'null'));
    $application = $application->fresh();
    foreach ($values as $attribute => $expected) {
      $this->assertSame($expected, $application->{$attribute});
      $this->assertSame($expected, $application->toArray()[$attribute]);
    }
    $this->assertSame($before, app(ApplicationRegistry::class)->find($application->handle)->toArray());
    $this->assertDatabaseCount('wbcms_application_assets', 5);
    $this->assertDatabaseCount('wbcms_application_setting_options', 3);
    $application->update(['name' => 'Renamed']);
    foreach ($values as $attribute => $expected) {
      $this->assertSame($expected, json_decode(DB::table('wbcms_embedded_applications')->where('id', $application->id)->value($attribute), true));
    }
  }

  #[Test]
  public function migration_is_repeatable_preserves_originals_and_reads_phase_one_reference_authority(): void
  {
    $block = $this->block(['application_handle' => 'sql-authority', 'columns' => '3']);
    $application = $this->application(['css_assets' => ['/a.css'], 'settings_schema' => ['style' => ['type' => 'enum', 'values' => ['a', 'a']]]]);
    StructuredSettingsSchema::dropFreshTables();
    $raw = '{"application_handle":"stale-mirror","columns":"3","unknown":{"keep":true}}';
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['settings' => $raw]);
    $original = DB::table('wbcms_embedded_applications')->where('id', $application->id)->first();
    $beforeSnapshot = app(PageRevisionManager::class)->snapshotForInspection($block->page->fresh());
    StructuredSettingsSchema::ensure();
    $this->assertSame(SnapshotContent::hash($beforeSnapshot), SnapshotContent::hash(app(PageRevisionManager::class)->snapshotForInspection($block->page->fresh())));
    $this->assertSame($raw, DB::table('wbcms_blocks')->where('id', $block->id)->value('settings'));
    $this->assertSame('sql-authority', $block->fresh()->setting('application_handle'));
    $this->assertSame(['keep' => true], $block->fresh()->setting('unknown'));
    foreach (SettingDefinitions::APPLICATION_ATTRIBUTES as $attribute) {
      $this->assertSame($original->{$attribute}, DB::table('wbcms_embedded_applications')->where('id', $application->id)->value($attribute));
    }
    $block->fresh()->update(['settings' => '{"columns":"4"}']);
    StructuredSettingsSchema::ensure();
    $this->assertSame('4', $block->fresh()->setting('columns'));
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_13_140000_ensure_structured_block_and_application_settings.php')->down();
    $this->assertSame('4', $block->fresh()->setting('columns'));
    $this->assertSame(['/a.css'], $application->fresh()->css_assets);
  }

  #[Test]
  public function migration_crosses_batches_and_does_not_sort_payload_columns(): void
  {
    $page = $this->page();
    StructuredSettingsSchema::dropFreshTables();
    $rows = [];
    for ($i = 0; $i < 205; $i++) {
      $rows[] = ['page_id' => $page->id, 'type' => 'hero', 'settings' => json_encode(['columns' => (string) ($i + 1), 'unknown' => str_repeat('x', 1024)])];
    }
    foreach (array_chunk($rows, 50) as $batch) {
      DB::table('wbcms_blocks')->insert($batch);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    StructuredSettingsSchema::ensure();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertDatabaseCount('wbcms_setting_documents', 205);
    $this->assertSame('205', Block::latest('id')->first()->setting('columns'));
    foreach ($queries as $query) {
      $sql = strtolower($query['query']);
      if (str_contains($sql, 'select') && str_contains($sql, 'order by') && str_contains($sql, 'from "wbcms_blocks"')) {
        $this->assertStringNotContainsString('settings', $sql);
      }
      $this->assertStringNotContainsString('offset', $sql);
    }
  }

  #[Test]
  public function null_empty_omitted_and_malformed_values_remain_distinct(): void
  {
    foreach ([null, '[]', '{}', '{"content_bindings":null,"content_collection":[],"unknown":null}', '{"content_bindings":[],"content_collection":null}'] as $value) {
      $block = $this->block($value);
      $expected = $value === null ? null : json_decode($value, true);
      $actual = $block->fresh()->settings;
      $this->assertSame($expected, $actual === null ? null : json_decode($actual, true));
    }
    foreach ([null, [], ['locale' => null], ['locale' => false]] as $value) {
      $application = $this->application(['supports' => $value]);
      $this->assertSame($value, $application->fresh()->supports);
    }
    $block = Block::create(['page_id' => $this->page()->id, 'type' => 'hero']);
    $application = $this->application();
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['settings' => '{"autoplay":true}']);
    DB::table('wbcms_embedded_applications')->where('id', $application->id)->update(['css_assets' => '["/unapproved.css"]']);
    $this->assertNull($block->fresh()->settings);
    $this->assertNull($application->fresh()->css_assets);
  }

  #[Test]
  public function stale_unrelated_saves_keep_new_settings_and_application_schema(): void
  {
    $block = $this->block(['columns' => '3']);
    $this->assertSame('3', $block->setting('columns'));
    $block->fresh()->update(['settings' => '{"columns":"4","autoplay":false}']);
    $block->update(['title' => 'Unrelated']);
    $this->assertSame(['columns' => '4', 'autoplay' => false], $block->fresh()->decodedSettings());
    $application = $this->application(['css_assets' => ['/a.css'], 'settings_schema' => []]);
    $this->assertSame([], $application->settings_schema);
    $application->fresh()->update(['css_assets' => ['/b.css'], 'settings_schema' => ['enabled' => ['type' => 'boolean', 'default' => false]]]);
    $application->update(['name' => 'Unrelated']);
    $this->assertSame(['/b.css'], $application->fresh()->css_assets);
    $this->assertSame(['enabled' => ['type' => 'boolean', 'default' => false]], $application->fresh()->settings_schema);
  }

  #[Test]
  public function transaction_failure_restores_sql_nodes_rows_and_copies_together(): void
  {
    $block = $this->block(['columns' => '3', 'unknown' => [1, 2]]);
    $application = $this->application(['settings_schema' => ['style' => ['type' => 'enum', 'values' => ['a']]]]);
    try {
      DB::transaction(function () use ($block, $application): void {
        $block->update(['settings' => '{"columns":"4","unknown":[3]}']);
        $application->update(['settings_schema' => ['style' => ['type' => 'enum', 'values' => ['b']]]]);
        throw new RuntimeException('Synthetic rollback');
      });
    } catch (RuntimeException) {
    }
    $this->assertSame(['columns' => '3', 'unknown' => [1, 2]], $block->fresh()->decodedSettings());
    $this->assertSame(['columns' => '3', 'unknown' => [1, 2]], json_decode(DB::table('wbcms_blocks')->where('id', $block->id)->value('settings'), true));
    $this->assertSame(['a'], $application->fresh()->settings_schema['style']['values']);
    $this->assertDatabaseCount('wbcms_application_setting_options', 1);
  }

  #[Test]
  public function owner_deletion_cascades_and_missing_template_keeps_its_original_id(): void
  {
    $template = $this->block(null);
    $block = $this->block(['content_collection' => ['source' => 'synthetic::items', 'template_block_id' => $template->id]]);
    $template->delete();
    $row = DB::table('wbcms_block_content_collections')->where('document_id', $this->document($block))->first();
    $this->assertNull($row->resolved_template_id);
    $this->assertSame($template->id, (int) $row->template_block_id);
    $this->assertSame($template->id, $block->fresh()->setting('content_collection.template_block_id'));
    $application = $this->application(['css_assets' => ['/a.css'], 'settings_schema' => ['style' => ['type' => 'enum', 'values' => ['a']]]]);
    $block->delete();
    $application->delete();
    $this->assertDatabaseCount('wbcms_setting_documents', 0);
    $this->assertDatabaseCount('wbcms_setting_nodes', 0);
    foreach (array_keys(SettingDefinitions::tables()) as $table) {
      $this->assertDatabaseCount('wbcms_'.$table, 0);
    }
  }

  #[Test]
  public function large_collection_values_are_loaded_in_byte_bounded_groups(): void
  {
    $page = $this->page();
    $value = str_repeat('x', 65536);
    for ($i = 0; $i < 40; $i++) {
      Block::create(['page_id' => $page->id, 'type' => 'hero', 'settings' => json_encode(['unknown' => $value])]);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $blocks = Block::where('page_id', $page->id)->get();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $reads = array_filter($queries, fn ($query) => str_contains($query['query'], 'from "wbcms_setting_nodes"') || str_contains($query['query'], 'from `wbcms_setting_nodes`'));
    $this->assertCount(2, $reads);
    $this->assertCount(40, $blocks);
    foreach ($blocks as $block) {
      $this->assertSame($value, $block->setting('unknown'));
    }
    foreach ($reads as $query) {
      $this->assertStringNotContainsString('order by', strtolower($query['query']));
    }
  }

  #[Test]
  public function eager_settings_reads_stay_bounded_instead_of_querying_each_block(): void
  {
    $page = $this->page();
    for ($i = 0; $i < 45; $i++) {
      Block::create(['page_id' => $page->id, 'type' => 'hero', 'settings' => '{"layout":"split","autoplay":false,"unknown":{"keep":true}}']);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $blocks = Block::where('page_id', $page->id)->get();
    foreach ($blocks as $block) {
      $this->assertSame('split', $block->setting('layout'));
      $this->assertFalse($block->setting('autoplay'));
      $this->assertTrue($block->setting('unknown.keep'));
    }
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertLessThanOrEqual(12, count($queries));
    DB::enableQueryLog();
    DB::flushQueryLog();
    Block::selectRaw('COUNT(*) as count')->first();
    $aggregateQueries = DB::getQueryLog();
    DB::disableQueryLog();
    foreach ($aggregateQueries as $query) {
      $this->assertStringNotContainsString('wbcms_setting_', $query['query']);
    }
    foreach ($queries as $query) {
      $this->assertStringNotContainsString('order by', strtolower($query['query']));
    }
  }
}
