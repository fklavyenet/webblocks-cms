<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Support\Relational\SettingScalar;
use WebBlocks\Cms\Tests\TestCase;

class RevisionSnapshotSchemaUpgradeTest extends TestCase
{
  protected bool $cacheFreshDatabaseSchema = false;

  private const ADDED_FIELDS = ['sort_order', 'show_approved', 'show_author_name', 'store_submissions'];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function fixture(): Page
  {
    $site = Site::create(['name' => 'Snapshot fixture', 'handle' => 'snapshot-fixture', 'is_primary' => true]);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->sync([$locale->id]);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $layout = PageLayout::create(['name' => 'Default', 'handle' => 'default', 'is_active' => true]);
    PageLayoutSlot::create(['page_layout_id' => $layout->id, 'slot_type_id' => $slot->id, 'slot_name' => 'main', 'is_required' => true, 'is_active' => true]);
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $page = Page::create(['site_id' => $site->id, 'title' => 'Existing page', 'slug' => 'existing', 'status' => 'draft']);
    $page->slots()->create(['slot_type_id' => $slot->id, 'source_type' => 'page']);

    return $page;
  }

  private function comments(): array
  {
    return ['data_scope' => 'page', 'form_enabled' => true, 'show_approved' => true, 'show_author_name' => false, 'sort_order' => 'newest'];
  }

  private function oldSnapshotSchema(): void
  {
    // Older snapshots retained these values as scalar nodes before declaration.
    $table = 'snapshot_block_content_settings';
    foreach (DB::table('wbcms_persistent_nodes')->where('ref_table', $table)->whereIn('ref_field', self::ADDED_FIELDS)->get() as $node) {
      $row = DB::table('wbcms_'.$table)->where('document_id', $node->document_id)->where('item_index', $node->ref_index)->first();
      $value = SettingScalar::unpack($row, $node->ref_field, SettingDefinitions::tables()['block_content_settings'][$node->ref_field]);
      DB::table('wbcms_persistent_nodes')->where('document_id', $node->document_id)->where('node_index', $node->node_index)->update([
        'kind' => 'value', 'ref_table' => null, 'ref_index' => null, 'ref_field' => null,
        ...SettingScalar::pack('value', 'variant', $value),
      ]);
    }
    Schema::table('wbcms_'.$table, function (Blueprint $schema): void {
      $schema->dropColumn(array_merge(...array_map(fn ($field) => [$field, $field.'_kind', $field.'_text'], self::ADDED_FIELDS)));
    });
    app(PersistentDocuments::class)->clear();
  }

  private function upgrade(): void
  {
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_25_160000_ensure_declared_revision_snapshot_fields.php')->up();
  }

  #[Test]
  public function upgrading_and_rerunning_preserve_existing_revision_values_hashes_and_identity(): void
  {
    $page = $this->fixture();
    Block::create(['page_id' => $page->id, 'type' => 'comments', 'block_type_id' => BlockType::where('slug', 'comments')->value('id'), 'settings' => json_encode($this->comments()), 'status' => 'draft']);
    $revision = app(PageRevisionManager::class)->capture($page->fresh());
    $snapshot = $revision->fresh()->snapshot;
    $hash = $revision->fresh()->snapshot_hash;
    $bytes = $revision->fresh()->snapshot_bytes;
    $this->oldSnapshotSchema();
    $this->assertSame($snapshot, $revision->fresh()->snapshot);

    $this->upgrade();
    $this->upgrade();

    foreach (self::ADDED_FIELDS as $field) {
      foreach ([$field, $field.'_kind', $field.'_text'] as $column) {
        $this->assertTrue(Schema::hasColumn('wbcms_snapshot_block_content_settings', $column), $column);
      }
    }
    $this->assertSame($snapshot, $revision->fresh()->snapshot);
    $this->assertSame($hash, $revision->fresh()->snapshot_hash);
    $this->assertSame($bytes, $revision->fresh()->snapshot_bytes);
    $this->assertDatabaseCount('wbcms_page_revisions', 1);
    $this->assertSame($revision->id, app(PageRevisionManager::class)->capture($page->fresh())->id);
  }

  #[Test]
  public function upgraded_schema_accepts_comments_from_plans_incremental_api_and_native_writer(): void
  {
    $page = $this->fixture();
    $this->oldSnapshotSchema();
    $plan = ['plan' => [
      'mode' => 'create_draft_page', 'site' => 'snapshot-fixture', 'locale' => 'en', 'layout' => 'default',
      'page' => ['title' => 'Comments page', 'path' => '/comments'],
      'slots' => ['main' => [['type' => 'comments', 'settings' => $this->comments()]]],
    ]];
    $service = app(InternalContentPlanService::class);
    $this->assertTrue($service->validate($plan)->ok);
    $failed = $service->apply($plan);
    $this->assertFalse($failed->ok);
    $this->assertSame(InternalContentPlanService::APPLY_WRITE_ERROR_CODE, $failed->errors[0]['code']);
    $this->assertDatabaseCount('wbcms_pages', 1);
    $this->assertDatabaseCount('wbcms_blocks', 0);
    $this->assertDatabaseCount('wbcms_page_revisions', 0);

    $this->upgrade();

    $applied = $service->apply($plan);
    $this->assertTrue($applied->ok, json_encode($applied->errors));
    $created = Page::findOrFail($applied->data['page']['id']);
    $this->assertSame($this->comments(), json_decode($created->revisions()->sole()->snapshot['blocks'][0]['settings'], true));

    $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['type' => 'comments', 'settings' => $this->comments()]));
    $response = app(InternalContentResourceController::class)->storeSlotBlock($request, $page->fresh(), 'main');
    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame($this->comments(), json_decode($page->revisions()->latest('id')->first()->snapshot['blocks'][0]['settings'], true));

    // The panel uses this writer and captures the revision in the same transaction.
    DB::transaction(function () use ($page): void {
      app(BlockPayloadWriter::class)->save(new Block, $page, [
        'page_id' => $page->id, 'block_type_id' => BlockType::where('slug', 'comments')->value('id'), 'type' => 'comments',
        'slot_type_id' => SlotType::where('slug', 'main')->value('id'), 'sort_order' => 1, 'status' => 'draft',
        'settings' => json_encode($this->comments()),
      ], 'en');
      app(PageRevisionManager::class)->capture($page->fresh());
    });
    $blocks = $page->revisions()->latest('id')->first()->snapshot['blocks'];
    $this->assertCount(2, $blocks);
    foreach ($blocks as $block) {
      $this->assertSame($this->comments(), json_decode($block['settings'], true));
    }
  }
}
