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
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\TestCase;

class ContentPlanLargePublishTest extends TestCase
{
  private const VERSION_TABLES = [
    'wbcms_seo_source_versions',
    'wbcms_page_list_cache_versions',
    'wbcms_navigation_cache_versions',
  ];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function a_large_six_locale_page_can_be_staged_and_promoted_over_http(): void
  {
    $nested = (bool) getenv('CMS_PROFILE_NESTED');
    $source = $this->seedPage((int) (getenv('CMS_PROFILE_BLOCKS') ?: 300));
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic large-page publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $queries = [];
    $measuring = false;
    $sourceSlotScans = 0;
    $savedBlocks = [];
    $deletedBlocks = [];
    Block::deleted(function (Block $block) use (&$deletedBlocks, &$measuring, $source): void {
      if ($measuring && $block->page_id === $source->id) {
        $deletedBlocks[] = $block->id;
      }
    });
    Block::saved(function (Block $block) use (&$savedBlocks, &$measuring): void {
      if ($measuring) {
      $savedBlocks[] = $block->id;
      }
    });
    DB::listen(function ($query) use (&$queries, &$measuring, &$sourceSlotScans, $source): void {
      if (! $measuring) {
      return;
      }
      preg_match('/(?:from|into|update|join) ["`]?([a-z_]+)/i', $query->sql, $match);
      $key = ($match[1] ?? 'other').' '.strtok(strtolower($query->sql), ' ');
      $queries[$key] = ($queries[$key] ?? 0) + 1;
      if (preg_match('/^select .*from ["`]wbcms_blocks["`] where ["`]page_id["`] = \? and ["`]slot_type_id["`] = \?/', $query->sql)
        && (int) ($query->bindings[0] ?? 0) === $source->id) {
        $sourceSlotScans++;
        }
    });
    $measuring = true;
    $start = microtime(true);
    $created = $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/large-page', 'managed_slots' => ['main'],
    ]])->assertCreated()->assertJsonPath('ok', true);
    $measuring = false;
    $stageId = $created->json('data.staged_page.id');
    foreach (self::VERSION_TABLES as $table) {
      $this->assertSame(1, $queries[$table.' update'] ?? 0, $table.' must be invalidated once for the stage.');
    }
    $this->assertArrayNotHasKey('wbcms_snapshot_text_translations delete', $queries, 'Recording the snapshot hash must not rewrite the new snapshot.');
    $expectedSaves = $source->blocks()->count() + ($nested ? $source->blocks()->count() - 1 : 0);
    $this->assertCount($expectedSaves, $savedBlocks, 'Root copies save once; children also save their mapped parent.');
    $stage = Page::findOrFail($stageId);
    $firstBlock = $stage->blocks()->when($nested, fn ($query) => $query->where('type', 'plain_text'))->orderBy('sort_order')->firstOrFail();
    $french = Locale::where('code', 'fr')->sole();
    $firstBlock->textTranslations()->where('locale_id', $french->id)->firstOrFail()->update(['content' => 'Published French amendment']);
    $createQueries = $queries;
    $createSeconds = microtime(true) - $start;
    $queries = [];
    $savedBlocks = [];
    $sourceSlotScans = 0;
    $originalBlockIds = $source->blocks()->orderBy('id')->pluck('id')->all();
    $measuring = true;
    $start = microtime(true);
    $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $stageId,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/large-page', 'promote_slots' => ['main'],
    ]])->assertCreated()->assertJsonPath('ok', true);
    $measuring = false;
    $seconds = microtime(true) - $start;
    foreach (self::VERSION_TABLES as $table) {
      $this->assertSame(1, $queries[$table.' update'] ?? 0, $table.' must be invalidated once for promotion.');
    }
    $this->assertNull(Page::find($stageId));
    $this->assertSame((int) (getenv('CMS_PROFILE_BLOCKS') ?: 300), $source->blocks()->count());
    $this->assertSame(6, DB::table('wbcms_public_search_index')->where('page_id', $source->id)->count());
    $this->assertSame($expectedSaves, count($savedBlocks));
    $expectedDeleted = $nested ? [...array_slice($originalBlockIds, 1), $originalBlockIds[0]] : $originalBlockIds;
    $this->assertSame($expectedDeleted, $deletedBlocks, 'Replacing source blocks must still fire every model deletion event.');
    $this->assertLessThanOrEqual(3, $sourceSlotScans, 'Policy checks and deletion read each source slot a bounded number of times, not once per root.');
    $this->assertSame($source->blocks()->count() * 6, DB::table('wbcms_block_text_translations')->whereIn('block_id', $source->blocks()->select('id'))->count());
    $this->assertSame($source->blocks()->count() * 6, DB::table('wbcms_persistent_documents')->where('attribute', 'meta')
      ->whereIn('block_text_translation_id', DB::table('wbcms_block_text_translations')->whereIn('block_id', $source->blocks()->select('id'))->select('id'))->count());
    $this->assertStringContainsString('Published French amendment', DB::table('wbcms_public_search_index')->where('page_id', $source->id)->where('locale_id', $french->id)->value('content'));
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
    if (getenv('CMS_PROFILE_BLOCKS')) {
      arsort($queries);
      arsort($createQueries);
      fwrite(STDERR, json_encode(['blocks' => $source->blocks()->count(), 'nested' => $nested, 'create_seconds' => round($createSeconds, 3), 'promote_seconds' => round($seconds, 3), 'create_queries' => array_sum($createQueries), 'promote_queries' => array_sum($queries), 'create_hotspots' => array_slice($createQueries, 0, 14, true), 'promote_hotspots' => array_slice($queries, 0, 14, true)], JSON_PRETTY_PRINT).PHP_EOL);
    }
  }

  #[Test]
  public function catalog_changes_from_an_observer_are_visible_to_the_next_copy(): void
  {
    $source = $this->seedPage(2);
    $sourceBlock = $source->blocks()->firstOrFail();
    $copies = 0;
    Block::created(function (Block $block) use ($source, $sourceBlock, &$copies): void {
      if ($block->page_id !== $source->id && ++$copies === 1) {
        DB::table((new BlockType)->getTable())->where('id', $sourceBlock->block_type_id)->update(['slug' => 'rich-text']);
        DB::table((new SlotType)->getTable())->where('id', $sourceBlock->slot_type_id)->update(['slug' => 'body']);
      }
    });
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic catalog publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $created = $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/large-page', 'managed_slots' => ['main'],
    ]])->assertCreated()->assertJsonPath('ok', true);
    $blocks = Block::where('page_id', $created->json('data.staged_page.id'))->orderBy('id')->get();
    $this->assertSame(['plain_text', 'rich-text'], $blocks->pluck('type')->all());
    $this->assertSame(['main', 'body'], $blocks->pluck('slot')->all());
  }

  #[Test]
  public function promotion_preserves_child_before_parent_deletion_and_the_copied_tree(): void
  {
    $source = $this->seedPage(3);
    [$root, $child, $leaf] = $source->blocks()->orderBy('id')->get()->all();
    $section = BlockType::create(['name' => 'Section', 'slug' => 'section', 'source_type' => 'static', 'status' => 'published', 'is_container' => true]);
    $root->update(['block_type_id' => $section->id]);
    $child->update(['parent_id' => $root->id]);
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic tree publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $created = $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/large-page', 'managed_slots' => ['main'],
    ]])->assertCreated()->assertJsonPath('ok', true);
    $deleted = [];
    Block::deleted(function (Block $block) use ($source, &$deleted): void {
      if ($block->page_id === $source->id) {
        $deleted[] = $block->id;
      }
    });
    $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $created->json('data.staged_page.id'),
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/large-page', 'promote_slots' => ['main'],
    ]])->assertCreated()->assertJsonPath('ok', true);
    $this->assertSame([$child->id, $root->id, $leaf->id], $deleted);
    $blocks = $source->blocks()->orderBy('sort_order')->get();
    $this->assertCount(3, $blocks);
    $this->assertNull($blocks[0]->parent_id);
    $this->assertSame($blocks[0]->id, $blocks[1]->parent_id);
    $this->assertNull($blocks[2]->parent_id);
    $this->assertSame(6, DB::table('wbcms_public_search_index')->where('page_id', $source->id)->count());
  }

  #[Test]
  public function a_translation_event_veto_rolls_back_the_copy_and_does_not_leave_metadata_pending(): void
  {
    $source = $this->seedPage(2);
    $cancel = true;
    BlockTextTranslation::creating(function (BlockTextTranslation $translation) use ($source, &$cancel): bool {
      return ! $cancel || $translation->block->page_id === $source->id || $translation->locale->code !== 'fr';
    });
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic event publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $plan = ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/large-page', 'managed_slots' => ['main'],
    ]];
    $this->withToken($token)->postJson('/webadmin/api/content/apply', $plan)->assertUnprocessable()->assertJsonPath('ok', false);
    $this->assertSame(1, Page::count());
    $this->assertSame(2, $source->blocks()->count());
    $this->assertSame(12, BlockTextTranslation::count());
    $this->assertSame(0, DB::table('wbcms_page_revisions')->count());
    $this->assertSame(0, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->count());
    $cancel = false;
    $created = $this->withToken($token)->postJson('/webadmin/api/content/apply', $plan)->assertCreated()->assertJsonPath('ok', true);
    $stage = Page::findOrFail($created->json('data.staged_page.id'));
    $this->assertSame(2, $stage->blocks()->count());
    $this->assertSame(12, DB::table('wbcms_persistent_documents')->whereNotNull('block_text_translation_id')->count());
  }

  #[Test]
  public function copying_translations_still_rejects_disabled_locales_and_rolls_back_the_whole_stage(): void
  {
    $source = $this->seedPage(2);
    $source->site->locales()->updateExistingPivot(Locale::where('code', 'fr')->sole()->id, ['is_enabled' => false]);
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/large-page', 'managed_slots' => ['main'],
    ]])->assertUnprocessable()->assertJsonPath('ok', false);
    $this->assertSame(1, Page::count());
    $this->assertSame(2, $source->blocks()->count());
    $this->assertSame(12, DB::table('wbcms_block_text_translations')->count());
    $this->assertSame(0, DB::table('wbcms_page_revisions')->count());
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
  }

  #[Test]
  public function a_locale_change_from_a_model_observer_invalidates_bulk_validation_and_rolls_back(): void
  {
    $source = $this->seedPage(2);
    $locale = Locale::where('code', 'fr')->sole();
    $created = 0;
    Block::created(function (Block $block) use ($source, $locale, &$created): void {
      if ($block->page_id !== $source->id && ++$created === 2) {
        $source->site->locales()->updateExistingPivot($locale->id, ['is_enabled' => false]);
      }
    });
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic atomic publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $generations = [];
    foreach (self::VERSION_TABLES as $table) {
      $generations[$table] = DB::table($table)->where('id', 1)->value('generation');
    }
    $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/large-page', 'managed_slots' => ['main'],
    ]])->assertUnprocessable()->assertJsonPath('ok', false);
    $this->assertSame(1, Page::count());
    $this->assertSame(2, $source->blocks()->count());
    $this->assertSame(12, DB::table('wbcms_block_text_translations')->count());
    $this->assertSame(0, DB::table('wbcms_page_revisions')->count());
    $this->assertTrue((bool) $source->site->locales()->where('wbcms_locales.id', $locale->id)->sole()->pivot->is_enabled);
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
    foreach ($generations as $table => $generation) {
      $this->assertSame($generation, DB::table($table)->where('id', 1)->value('generation'));
    }

    $source->update(['title' => 'Edited after rollback']);
    foreach ($generations as $table => $generation) {
      $this->assertNotSame($generation, DB::table($table)->where('id', 1)->value('generation'));
    }
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
      $nested = (bool) getenv('CMS_PROFILE_NESTED');
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
