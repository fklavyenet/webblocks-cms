<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Catalog\CatalogRepairer;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Backport of the multilingual gaps fklavye-net had to patch with site-local
 * vendor view overrides: hreflang emission in the public head, a public
 * language switcher in header-actions, and a single <h1> per page no matter
 * how many content_header blocks it stacks. Overrides don't travel with
 * export/import, so these belong to the package.
 */
class MultilingualPublicSurfaceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function hreflang_links_are_emitted_for_each_resolvable_locale_plus_x_default(): void
  {
    $page = $this->seedPage('a', withSecondLocale: true);

    $html = $this->renderFullPage($page);

    $this->assertStringContainsString('hreflang="en" href="http://localhost/about-a"', $html);
    $this->assertStringContainsString('hreflang="tr" href="http://localhost/tr/hakkinda-a"', $html);
    $this->assertStringContainsString('hreflang="x-default" href="http://localhost/about-a"', $html);
  }

  #[Test]
  public function a_single_locale_site_emits_no_hreflang_at_all(): void
  {
    $page = $this->seedPage('b', withSecondLocale: false);

    $html = $this->renderFullPage($page);

    $this->assertStringNotContainsString('hreflang', $html);
  }

  #[Test]
  public function the_shared_head_meta_partial_ignores_an_inherited_page_variable(): void
  {
    // The partial is also included by the admin and guest layouts, whose views
    // have their own $page in scope via Blade's inherited data. hreflang must
    // only react to the explicit $hreflangPage the public layout passes.
    $page = $this->seedPage('c', withSecondLocale: true);

    $html = view('webblocks-cms::partials.head-meta', ['page' => $page, 'title' => 'Admin screen'])->render();

    $this->assertStringNotContainsString('hreflang', $html);
  }

  #[Test]
  public function only_the_first_content_header_on_a_page_renders_an_h1(): void
  {
    $page = $this->seedPage('d', withSecondLocale: false, contentHeaders: 2);

    $html = $this->renderFullPage($page);

    $this->assertSame(1, substr_count($html, '<h1'), 'A page must ship exactly one h1.');
    $this->assertStringContainsString('<h1 class="wb-content-title">Heading 1</h1>', $html);
    $this->assertStringContainsString('<h2 class="wb-content-title">Heading 2</h2>', $html);
  }

  #[Test]
  public function header_actions_renders_a_language_switcher_linking_translated_paths(): void
  {
    $page = $this->seedPage('e', withSecondLocale: true);

    $html = $this->renderHeaderActions($page);

    $this->assertStringContainsString('wb-language-switcher', $html);
    $this->assertStringContainsString('href="/about-e"', $html);
    $this->assertStringContainsString('href="/tr/hakkinda-e"', $html);
    $this->assertStringContainsString('aria-current="true"', $html);
  }

  #[Test]
  public function the_language_switcher_can_be_turned_off_per_block(): void
  {
    $page = $this->seedPage('f', withSecondLocale: true);

    $html = $this->renderHeaderActions($page, ['show_language_switcher' => false]);

    $this->assertStringNotContainsString('wb-language-switcher', $html);
  }

  #[Test]
  public function a_single_locale_site_renders_no_language_switcher(): void
  {
    $page = $this->seedPage('g', withSecondLocale: false);

    $html = $this->renderHeaderActions($page);

    $this->assertStringNotContainsString('wb-language-switcher', $html);
  }

  #[Test]
  public function catalog_repair_keeps_operator_css_classes_on_slots_without_a_canonical_value(): void
  {
    $repairer = $this->app->make(CatalogRepairer::class);
    $repairer->repair(['page-layouts'], dryRun: false);

    $layout = PageLayout::query()->where('handle', 'default')->firstOrFail();
    $footerSlot = PageLayoutSlot::query()->where('page_layout_id', $layout->id)->where('slot_name', 'footer')->firstOrFail();
    $mainSlot = PageLayoutSlot::query()->where('page_layout_id', $layout->id)->where('slot_name', 'main')->firstOrFail();

    $footerSlot->forceFill(['css_classes' => 'site-footer custom'])->save();
    $mainSlot->forceFill(['css_classes' => 'broken'])->save();

    $repairer->repair(['page-layouts'], dryRun: false);

    $this->assertSame('site-footer custom', $footerSlot->fresh()->css_classes, 'Repair must not wipe operator classes on a slot the catalog states no value for.');
    $this->assertSame('wb-public-main', $mainSlot->fresh()->css_classes, 'Repair must still restore a canonical value the catalog does state.');
  }

  private function renderFullPage(Page $page): string
  {
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', ['format' => 'html']);

    return (string) $this->app->make(InternalPageRenderController::class)->show($request, $page)->getContent();
  }

  private function renderHeaderActions(Page $page, array $settings = []): string
  {
    $block = Block::create([
      'page_id' => $page->id,
      'type' => 'header-actions',
      'slot_type_id' => SlotType::query()->where('slug', 'header')->value('id'),
      'sort_order' => 0,
      'settings' => json_encode($settings),
      'status' => 'published',
    ]);

    $renderPage = $page->fresh();
    $renderPage->setRelation('currentTranslation', $renderPage->translations()->whereHas('locale', fn ($query) => $query->where('code', 'en'))->first());
    $block->setRelation('renderPage', $renderPage);

    return view('webblocks-cms::pages.partials.blocks.header-actions', ['block' => $block])->render();
  }

  private function seedPage(string $suffix, bool $withSecondLocale, int $contentHeaders = 0): Page
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-'.$suffix, 'is_primary' => true]);
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true]]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'about-'.$suffix,
      'status' => Page::STATUS_PUBLISHED,
    ]);
    $page->translations()->create([
      'locale_id' => $english->id,
      'name' => 'About',
      'slug' => 'about-'.$suffix,
      'path' => '/about-'.$suffix,
    ]);

    if ($withSecondLocale) {
      $turkish = Locale::query()->firstOrCreate(['code' => 'tr'], ['name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
      $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
      $page->translations()->create([
        'locale_id' => $turkish->id,
        'name' => 'Hakkında',
        'slug' => 'hakkinda-'.$suffix,
        'path' => '/hakkinda-'.$suffix,
      ]);
    }

    foreach (['header', 'main'] as $index => $slug) {
      $slotType = SlotType::query()->firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'status' => 'published', 'sort_order' => $index],
      );
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => $index]);
    }

    $mainSlotTypeId = SlotType::query()->where('slug', 'main')->value('id');

    for ($i = 1; $i <= $contentHeaders; $i++) {
      Block::create([
        'page_id' => $page->id,
        'type' => 'content_header',
        'slot_type_id' => $mainSlotTypeId,
        'sort_order' => $i,
        'title' => 'Heading '.$i,
        'status' => 'published',
      ]);
    }

    return $page->fresh();
  }
}
