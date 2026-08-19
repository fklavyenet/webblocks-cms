<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PageType;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Pages\PageListItem;
use WebBlocks\Cms\Support\Pages\PageListSettings;
use WebBlocks\Cms\Tests\TestCase;

/**
 * `page-list` is the first block whose visible rows come from a page query
 * rather than from authored content, so the filters it does *not* expose as
 * settings are the ones worth testing: an unpublished, foreign-site,
 * untranslated, or Shared-Slot-internal page must never reach a visitor,
 * regardless of how the block is configured.
 */
class PageListBlockRenderTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function page_list_is_a_system_block_type(): void
  {
    $definition = collect(app(CoreBlockTypeCatalogSyncer::class)->definitions())
      ->firstWhere('slug', 'page-list');

    $this->assertNotNull($definition, 'The core catalog must define a page-list block type.');
    $this->assertTrue($definition['is_system'], 'page-list must be a system block type: it has no editorial content fields.');
    $this->assertFalse($definition['is_container'], 'page-list rows come from a query, so it must not accept children.');
  }

  #[Test]
  public function it_lists_only_published_pages_of_the_configured_page_type(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $this->seedPage($site, $locale, 'Published guide', '/guides/published', $guideType);
    $this->seedPage($site, $locale, 'Draft guide', '/guides/draft', $guideType, status: Page::STATUS_DRAFT);
    $this->seedPage($site, $locale, 'Archived guide', '/guides/archived', $guideType, status: Page::STATUS_ARCHIVED);
    $this->seedPage($site, $locale, 'Ordinary page', '/about', $this->pageType('page'));

    $titles = $this->itemsFor($site, $locale)->pluck('title')->all();

    $this->assertSame(['Published guide'], $titles);
  }

  #[Test]
  public function it_never_lists_pages_from_another_site(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $otherSite = Site::query()->create(['name' => 'Other', 'handle' => 'other']);

    $this->seedPage($site, $locale, 'Own guide', '/guides/own', $guideType);
    $this->seedPage($otherSite, $locale, 'Foreign guide', '/guides/foreign', $guideType);

    $titles = $this->itemsFor($site, $locale)->pluck('title')->all();

    $this->assertSame(['Own guide'], $titles);
  }

  #[Test]
  public function it_skips_pages_without_a_translation_in_the_render_locale(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);

    $this->seedPage($site, $locale, 'Translated guide', '/guides/translated', $guideType);
    $this->seedPage($site, $german, 'Nur Deutsch', '/guides/nur-deutsch', $guideType);

    $titles = $this->itemsFor($site, $locale)->pluck('title')->all();

    $this->assertSame(['Translated guide'], $titles);
  }

  #[Test]
  public function it_excludes_shared_slot_source_pages(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $this->seedPage($site, $locale, 'Real guide', '/guides/real', $guideType);
    $this->seedPage($site, $locale, 'Hidden source', '/guides/source', $guideType, pageType: Page::TYPE_SHARED_SLOT_SOURCE);

    $titles = $this->itemsFor($site, $locale)->pluck('title')->all();

    $this->assertSame(['Real guide'], $titles);
  }

  #[Test]
  public function it_excludes_the_page_the_block_sits_on(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $index = $this->seedPage($site, $locale, 'Guides index', '/guides', $guideType);
    $this->seedPage($site, $locale, 'A guide', '/guides/a', $guideType);

    $excluded = $this->itemsFor($site, $locale, host: $index)->pluck('title')->all();
    $included = $this->itemsFor($site, $locale, ['exclude_current' => false], host: $index)->pluck('title')->all();

    $this->assertSame(['A guide'], $excluded);
    $this->assertContains('Guides index', $included);
  }

  #[Test]
  public function it_caps_the_result_at_the_configured_limit(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    foreach (range(1, 5) as $index) {
      $this->seedPage($site, $locale, 'Guide '.$index, '/guides/'.$index, $guideType);
    }

    $this->assertCount(2, $this->itemsFor($site, $locale, ['limit' => 2]));
  }

  #[Test]
  public function it_sorts_by_translated_title(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $this->seedPage($site, $locale, 'Zebra', '/guides/zebra', $guideType);
    $this->seedPage($site, $locale, 'Anchor', '/guides/anchor', $guideType);
    $this->seedPage($site, $locale, 'Mango', '/guides/mango', $guideType);

    $titles = $this->itemsFor($site, $locale, [
      'sort' => PageListSettings::SORT_TITLE_ASC,
    ])->pluck('title')->all();

    $this->assertSame(['Anchor', 'Mango', 'Zebra'], $titles);
  }

  #[Test]
  public function a_path_prefix_scope_lists_the_whole_subtree_and_nothing_outside_it(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $this->seedPage($site, $locale, 'Guides index', '/guides', $guideType);
    $this->seedPage($site, $locale, 'Install', '/guides/install', $guideType);
    $this->seedPage($site, $locale, 'Docker', '/guides/install/docker', $guideType);
    $this->seedPage($site, $locale, 'Guidelines', '/guidelines', $guideType);

    $titles = $this->itemsFor($site, $locale, [
      'scope' => PageListSettings::SCOPE_PATH_PREFIX,
      'path_prefix' => '/guides',
      'sort' => PageListSettings::SORT_PATH_ASC,
    ])->pluck('title')->all();

    // '/guidelines' shares the '/guides' string prefix but is not in the
    // subtree, which is why the query matches on a path segment boundary.
    $this->assertSame(['Guides index', 'Install', 'Docker'], $titles);
  }

  #[Test]
  public function the_subtree_scope_reads_the_prefix_from_the_hosting_page(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $index = $this->seedPage($site, $locale, 'Guides index', '/guides', $guideType);
    $this->seedPage($site, $locale, 'Install', '/guides/install', $guideType);
    $this->seedPage($site, $locale, 'About', '/about', $this->pageType('page'));

    $titles = $this->itemsFor($site, $locale, [
      'scope' => PageListSettings::SCOPE_SUBTREE_OF_CURRENT,
    ], host: $index)->pluck('title')->all();

    $this->assertSame(['Install'], $titles);
  }

  #[Test]
  public function an_unconfigured_block_renders_nothing(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $this->seedPage($site, $locale, 'A guide', '/guides/a', $guideType);

    $block = $this->block($site, ['scope' => PageListSettings::SCOPE_PAGE_TYPE, 'page_type' => null]);

    $this->assertCount(0, $block->pageListItems());
    $this->assertSame('', trim($this->render($block)));
  }

  #[Test]
  public function the_card_layout_links_each_row_from_its_title(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $this->seedPage($site, $locale, 'A guide', '/guides/a', $guideType, description: 'How to do the thing.');

    $html = $this->render($this->block($site, ['columns' => '2']));

    $this->assertStringContainsString('wb-grid wb-grid-2', $html);
    $this->assertStringContainsString('<a href="/guides/a" class="wb-link">A guide</a>', $html);
    $this->assertStringContainsString('How to do the thing.', $html);
  }

  #[Test]
  public function the_clickable_card_layout_renders_each_row_as_one_semantic_link(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $this->seedPage($site, $locale, 'A guide', '/guides/a', $guideType, description: 'How to do the thing.');

    $html = $this->render($this->block($site, ['clickable_card' => true]));

    $this->assertStringContainsString('<a href="/guides/a" class="wb-card wb-no-decoration">', $html);
    $this->assertStringContainsString('<strong>', $html);
    $this->assertStringNotContainsString('<article class="wb-card">', $html);
    $this->assertSame(1, substr_count($html, 'href="/guides/a"'), 'A clickable card must not contain a nested title link.');
  }

  #[Test]
  public function the_links_layout_renders_a_link_list(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $this->seedPage($site, $locale, 'A guide', '/guides/a', $guideType);

    $html = $this->render($this->block($site, ['layout' => PageListSettings::LAYOUT_LINKS]));

    $this->assertStringContainsString('wb-link-list', $html);
    $this->assertStringContainsString('href="/guides/a"', $html);
    $this->assertStringNotContainsString('wb-grid', $html);
  }

  #[Test]
  public function a_list_excerpt_overrides_the_seo_description(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $this->seedPage($site, $locale, 'Written for search', '/guides/a', $guideType, description: 'Install WebBlocks CMS on a Laravel app - the complete step by step reference guide for operators.');
    $this->seedPage($site, $locale, 'Written for the card', '/guides/b', $guideType, description: 'An SEO description nobody should see here.', listExcerpt: 'A short line composed for this card.');

    $items = $this->itemsFor($site, $locale)->keyBy('title');

    $this->assertSame('A short line composed for this card.', $items['Written for the card']->description);
    $this->assertStringStartsWith('Install WebBlocks CMS', (string) $items['Written for search']->description);
  }

  #[Test]
  public function a_list_excerpt_renders_whole_while_a_borrowed_seo_description_is_trimmed(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();

    $longExcerpt = str_repeat('excerpt ', 30);
    $longSeo = str_repeat('seo ', 60);

    $this->seedPage($site, $locale, 'Authored', '/guides/a', $guideType, listExcerpt: $longExcerpt);
    $this->seedPage($site, $locale, 'Borrowed', '/guides/b', $guideType, description: $longSeo);

    $items = $this->itemsFor($site, $locale)->keyBy('title');

    // Written for this card, so it is shown as written; the 300-character cap
    // is enforced on the way in rather than by cutting it at render time.
    $this->assertSame(trim($longExcerpt), $items['Authored']->description);
    $this->assertStringEndsWith('...', (string) $items['Borrowed']->description);
    $this->assertLessThan(mb_strlen($longSeo), mb_strlen((string) $items['Borrowed']->description));
  }

  #[Test]
  public function the_list_excerpt_is_per_locale(): void
  {
    [$site, $locale, $guideType] = $this->seedSite();
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);

    $page = $this->seedPage($site, $locale, 'Guide', '/guides/a', $guideType, listExcerpt: 'The English card line.');
    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $german->id,
      'name' => 'Anleitung',
      'slug' => 'a',
      'path' => '/anleitungen/a',
      'list_excerpt' => 'Die deutsche Kartenzeile.',
    ]);

    $this->assertSame('The English card line.', $this->itemsFor($site, $locale)->first()->description);
  }

  #[Test]
  public function the_admin_form_renders_its_own_translated_fields(): void
  {
    [$site, , $guideType] = $this->seedSite();
    $this->pageType('page');

    $html = view('webblocks-cms::admin.blocks.types.page-list', [
      'block' => $this->block($site, ['scope' => PageListSettings::SCOPE_PATH_PREFIX, 'path_prefix' => '/guides']),
    ])->render();

    $this->assertStringContainsString('name="page_list_scope"', $html);
    $this->assertStringContainsString('name="page_list_limit"', $html);
    $this->assertStringContainsString('name="page_list_clickable_card"', $html);
    $this->assertStringContainsString('value="/guides"', $html);
    $this->assertStringContainsString($guideType->name, $html, 'Page type options come from the page_types catalog.');
    // A missing lang key renders as the raw dotted key, so this also proves
    // the six-locale admin strings landed under the right nesting.
    $this->assertStringNotContainsString('admin.blocks.page_list.', $html);
  }

  #[Test]
  public function the_inline_builder_form_submits_the_same_field_names(): void
  {
    [$site] = $this->seedSite();
    $this->pageType('page');

    $html = view('webblocks-cms::admin.blocks.types.page-list-inline', [
      'block' => $this->block($site),
      'prefix' => 'blocks[2]',
      'index' => 2,
    ])->render();

    // PageRequest reads these off blocks.*; a mismatched name here would save
    // nothing while looking like a working form.
    $this->assertStringContainsString('name="blocks[2][page_list_scope]"', $html);
    $this->assertStringContainsString('name="blocks[2][page_list_limit]"', $html);
    $this->assertStringContainsString('name="blocks[2][page_list_clickable_card]"', $html);
    $this->assertStringNotContainsString('admin.blocks.page_list.', $html);
  }

  /**
   * @param  array<string, mixed>  $settings
   * @return Collection<int, PageListItem>
   */
  private function itemsFor(Site $site, Locale $locale, array $settings = [], ?Page $host = null)
  {
    return $this->block($site, $settings, $host)->pageListItems();
  }

  /**
   * @param  array<string, mixed>  $settings
   */
  private function block(Site $site, array $settings = [], ?Page $host = null): Block
  {
    $host ??= $this->hostPage ??= $this->seedPage(
      $site,
      Locale::query()->where('is_default', true)->firstOrFail(),
      'Host',
      '/host',
      $this->pageType('page'),
    );

    $blockType = BlockType::query()->firstOrCreate(['slug' => 'page-list'], ['name' => 'Page List', 'is_active' => true]);

    $block = Block::query()->create([
      'page_id' => $host->id,
      'type' => 'page-list',
      'block_type_id' => $blockType->id,
      'source_type' => 'pages',
      'slot' => 'main',
      'sort_order' => 0,
      'status' => 'published',
      'settings' => json_encode(array_merge([
        'scope' => PageListSettings::SCOPE_PAGE_TYPE,
        'page_type' => 'guide',
        'sort' => PageListSettings::SORT_PATH_ASC,
      ], $settings), JSON_UNESCAPED_SLASHES),
    ]);

    return $block->fresh(['blockType', 'page']);
  }

  private function render(Block $block): string
  {
    return view('webblocks-cms::pages.partials.blocks.page-list', ['block' => $block])->render();
  }

  private ?Page $hostPage = null;

  /**
   * @return array{0: Site, 1: Locale, 2: PageType}
   */
  private function seedSite(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    return [$site, $locale, $this->pageType('guide')];
  }

  private function pageType(string $slug): PageType
  {
    return PageType::query()->firstOrCreate(
      ['slug' => $slug],
      ['name' => ucfirst($slug), 'is_system' => false, 'sort_order' => 0, 'status' => 'published'],
    );
  }

  private function seedPage(
    Site $site,
    Locale $locale,
    string $name,
    string $path,
    PageType $type,
    string $status = Page::STATUS_PUBLISHED,
    string $pageType = Page::TYPE_DEFAULT,
    ?string $description = null,
    ?string $listExcerpt = null,
  ): Page {
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => $pageType,
      'page_type_id' => $type->id,
      'status' => $status,
      'published_at' => now(),
    ]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => $name,
      'slug' => trim(basename($path), '/') ?: 'home',
      'path' => $path,
      'seo_description' => $description,
      'list_excerpt' => $listExcerpt,
    ]);

    return $page;
  }
}
