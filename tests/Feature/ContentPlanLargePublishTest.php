<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
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
    $source = $this->seedPage((int) (getenv('CMS_PROFILE_BLOCKS') ?: 300));
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic large-page publisher', capabilities: ['content.apply', 'content.publish'])->plainToken;
    $queries = [];
    $measuring = false;
    $sourceSlotScans = 0;
    $savedBlocks = [];
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
    $this->assertCount($source->blocks()->count(), $savedBlocks, 'Each unchanged root copy needs only its initial save.');
    $stage = Page::findOrFail($stageId);
    $firstBlock = $stage->blocks()->orderBy('sort_order')->firstOrFail();
    $french = Locale::where('code', 'fr')->sole();
    $firstBlock->textTranslations()->where('locale_id', $french->id)->firstOrFail()->update(['content' => 'Published French amendment']);
    $createQueries = $queries;
    $createSeconds = microtime(true) - $start;
    $queries = [];
    $savedBlocks = [];
    $sourceSlotScans = 0;
    $measuring = true;
    $start = microtime(true);
    $this->withToken($token)->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $stageId,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/large-page', 'promote_slots' => ['main'],
    ]])->assertCreated()->assertJsonPath('ok', true);
    $measuring = false;
    $seconds = microtime(true) - $start;
    $this->assertNull(Page::find($stageId));
    $this->assertSame((int) (getenv('CMS_PROFILE_BLOCKS') ?: 300), $source->blocks()->count());
    $this->assertSame(6, DB::table('wbcms_public_search_index')->where('page_id', $source->id)->count());
    $this->assertSame($source->blocks()->count(), count($savedBlocks));
    $this->assertLessThanOrEqual(3, $sourceSlotScans, 'Policy checks and deletion read each source slot a bounded number of times, not once per root.');
    $this->assertSame($source->blocks()->count() * 6, DB::table('wbcms_block_text_translations')->whereIn('block_id', $source->blocks()->select('id'))->count());
    $this->assertStringContainsString('Published French amendment', DB::table('wbcms_public_search_index')->where('page_id', $source->id)->where('locale_id', $french->id)->value('content'));
    $this->assertFalse(PublicSearchIndexer::isCoalescing());
    if (getenv('CMS_PROFILE_BLOCKS')) {
      arsort($queries);
      arsort($createQueries);
      fwrite(STDERR, json_encode(['blocks' => $source->blocks()->count(), 'create_seconds' => round($createSeconds, 3), 'promote_seconds' => round($seconds, 3), 'create_queries' => array_sum($createQueries), 'promote_queries' => array_sum($queries), 'create_hotspots' => array_slice($createQueries, 0, 14, true), 'promote_hotspots' => array_slice($queries, 0, 14, true)], JSON_PRETTY_PRINT).PHP_EOL);
    }
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
      $page = Page::create(['site_id' => $site->id, 'title' => 'Large page', 'slug' => 'large-page', 'status' => 'published', 'settings' => ['public_shell' => 'default']]);
      foreach ($locales as $locale) {
        $page->translations()->updateOrCreate(['locale_id' => $locale->id], ['site_id' => $site->id, 'name' => 'Large page '.$locale->code, 'slug' => 'large-page', 'path' => '/large-page']);
      }
      PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE]);
      $rows = [];
      for ($i = 0; $i < $count; $i++) {
        $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => $type->slug, 'slot_type_id' => $slot->id, 'slot' => 'main', 'status' => 'published', 'sort_order' => $i]);
        foreach ($locales as $locale) {
        $rows[] = ['block_id' => $block->id, 'locale_id' => $locale->id, 'content' => 'Synthetic block '.$i.' locale '.$locale->code];
        }
      }
      DB::table('wbcms_block_text_translations')->insert($rows);

      return $page;
    });
  }
}
