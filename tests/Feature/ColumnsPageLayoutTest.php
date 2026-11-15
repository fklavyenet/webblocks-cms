<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Pages\WritePageSlotSource;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Pages\PageDraftInitializer;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotComparison;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Tests\TestCase;

class ColumnsPageLayoutTest extends TestCase
{
  private Site $site;

  private Locale $locale;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    app(CoreLayoutCatalogSyncer::class)->sync();
    $this->site = Site::create(['name' => 'Studio', 'handle' => 'studio', 'is_primary' => true]);
    $this->locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
  }

  #[Test]
  public function layouts_seed_only_standard_slots_and_keep_existing_article_selectable_for_its_page(): void
  {
    $layouts = app(PageLayoutManager::class);
    $this->assertSame(['default', 'docs', 'columns'], array_column($layouts->pageSelectionOptions(), 'value'));
    $this->assertSame(['default', 'docs', 'columns', 'article'], array_column($layouts->pageSelectionOptions('article'), 'value'));

    foreach ([
      'default' => ['header', 'main', 'footer'],
      'docs' => ['header', 'sidebar', 'main', 'footer'],
      'columns' => ['header', 'main', 'footer'],
    ] as $layout => $slots) {
      $page = $this->draft($layout);
      $this->assertSame($slots, $page->slots()->with('slotType')->get()->map->slotSlug()->all());
      $this->assertSame(0, app(PageLayoutSlotComparison::class)->compare($page)['missing_count']);
    }
  }

  #[Test]
  public function columns_renders_each_side_from_its_own_source_and_blocks_loss_on_layout_change(): void
  {
    $page = $this->draft('columns', ['sidebar-left', 'sidebar-right']);
    $left = $this->slot($page, 'sidebar-left');
    $right = $this->slot($page, 'sidebar-right');
    $this->textBlock($page, $left, 'Left page content');
    $shared = SharedSlot::create([
      'site_id' => $page->site_id, 'name' => 'Right links', 'handle' => 'right-links',
      'slot_name' => 'sidebar-right', 'public_shell' => 'columns', 'is_active' => true,
    ]);
    $source = app(SharedSlotSourcePageManager::class)->ensureFor($shared);
    $sharedBlock = $this->textBlock($source, $this->slot($source, 'sidebar-right'), 'Right shared content');
    $shared->slotBlocks()->create(['block_id' => $sharedBlock->id, 'sort_order' => 0]);
    $this->assertFalse($shared->isCompatibleWithPageSlot($page, 'sidebar-left'));
    app(WritePageSlotSource::class)->execute($page, $right, PageSlot::SOURCE_TYPE_SHARED_SLOT, $shared->id);

    $page = $page->fresh(['site', 'slots.slotType', 'blocks']);
    $render = app(PublicPagePresenter::class)->present($page, preview: true, locale: $this->locale);
    $html = view('webblocks-cms::pages.show', $render)->render();
    preg_match_all('/<(?:header|main|aside|footer) data-wb-slot="([^"]+)"/', $html, $markers);
    $this->assertSame(['header', 'sidebar-left', 'main', 'sidebar-right', 'footer'], $markers[1]);
    $this->assertMatchesRegularExpression('/<aside data-wb-slot="sidebar-left"[^>]*>[^<]*(?:(?!<\/aside>).)*Left page content/s', $html);
    $this->assertMatchesRegularExpression('/<aside data-wb-slot="sidebar-right"[^>]*>[^<]*(?:(?!<\/aside>).)*Right shared content/s', $html);
    $this->assertStringContainsString('class="wb-public-columns"', $html);

    $this->expectException(ValidationException::class);
    DB::transaction(fn () => app(PageLayoutSlotSyncer::class)->changeLayout($page, 'default'));
  }

  #[Test]
  public function main_is_required_at_publication_and_cannot_be_disabled(): void
  {
    $page = $this->draft('default');
    $main = $this->slot($page, 'main');
    try {
      app(WritePageSlotSource::class)->execute($page, $main, PageSlot::SOURCE_TYPE_DISABLED, null);
      $this->fail('Main source should be protected.');
    } catch (ValidationException $exception) {
      $this->assertArrayHasKey('source_type', $exception->errors());
    }
    $main->delete(); // Simulate an existing page without a Main slot.
    try {
      app(PageWorkflowManager::class)->transitionAuthorizedPage($page, PageWorkflowManager::ACTION_PUBLISH);
      $this->fail('The publication should require Main.');
    } catch (ValidationException $exception) {
      $this->assertArrayHasKey('main_slot', $exception->errors());
    }

    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');
    $this->assertTrue(app(PageWorkflowManager::class)->transitionAuthorizedPage($page, PageWorkflowManager::ACTION_PUBLISH));
    $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
  }

  #[Test]
  public function upgrade_preserves_existing_markup_slots_and_layout_assignments(): void
  {
    $page = $this->draft('article');
    $legacySlot = SlotType::query()->where('slug', 'sidebar')->firstOrFail();
    $page->slots()->create(['slot_type_id' => $legacySlot->id]);
    $article = PageLayout::where('handle', 'article')->firstOrFail();
    $article->update(['is_active' => true, 'body_class' => 'layout-article existing-style']);
    PageLayout::where('handle', 'columns')->delete();
    SlotType::whereIn('slug', ['sidebar-left', 'sidebar-right'])->delete();

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_15_140000_add_columns_page_layout.php';
    $migration->up();

    $this->assertFalse($article->fresh()->is_active);
    $this->assertSame('layout-article existing-style', $article->fresh()->body_class);
    $this->assertSame('article', $page->fresh()->publicShellPreset());
    $this->assertSame($legacySlot->id, $this->slot($page, 'sidebar')->slot_type_id);
    $this->assertSame(['header', 'sidebar-left', 'main', 'sidebar-right', 'footer'], PageLayout::where('handle', 'columns')->firstOrFail()->layoutSlots()->pluck('slot_name')->all());
  }

  private function draft(string $layout, array $sidebars = []): Page
  {
    return app(PageDraftInitializer::class)->create(
      ['site_id' => $this->site->id, 'settings' => ['public_shell' => $layout]],
      ['locale_id' => $this->locale->id, 'name' => ucfirst($layout), 'slug' => $layout, 'path' => '/'.$layout],
      $layout,
      $sidebars,
    );
  }

  private function slot(Page $page, string $slug): PageSlot
  {
    return $page->slots()->whereHas('slotType', fn ($query) => $query->where('slug', $slug))->firstOrFail();
  }

  private function textBlock(Page $page, PageSlot $slot, string $content): Block
  {
    return Block::create(['page_id' => $page->id, 'slot_type_id' => $slot->slot_type_id, 'type' => 'plain_text', 'content' => $content, 'status' => 'published']);
  }
}
