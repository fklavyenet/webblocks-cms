<?php

namespace Tests\Feature;

use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Blocks\PublicBodyEndRegistry;
use WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry;

class PublicRichContentTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function code_block_renders_pre_code(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $this->blockType('code', 'Code', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'Example snippet',
      'content' => "<script>alert('x')</script>\nreturn true;",
      'settings' => json_encode(['language' => 'php'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<pre>', false);
    $response->assertSee('<code data-language="php">', false);
    $response->assertSee('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', false);
    $response->assertDontSee('<script>alert(', false);
    $response->assertDontSee('wb-card', false);
    $response->assertDontSee('wb-card-muted', false);
    $response->assertDontSee('wb-card-body', false);
    $response->assertDontSee('>php<', false);
  }

  #[Test]
  public function code_block_does_not_render_visible_header_metadata_and_escapes_html(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $this->blockType('code', 'Code', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'Escaped snippet',
      'subtitle' => '<demo.js>',
      'content' => "console.log('<b>safe</b>');",
      'settings' => json_encode(['language' => 'js'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<pre><code data-language="js">console.log(&#039;&lt;b&gt;safe&lt;/b&gt;&#039;);</code></pre>', false);
    $response->assertSee('console.log(&#039;&lt;b&gt;safe&lt;/b&gt;&#039;);', false);
    $response->assertDontSee('<b>safe</b>', false);
    $response->assertDontSee('Escaped snippet');
    $response->assertDontSee('&lt;demo.js&gt;', false);
    $response->assertDontSee('<demo.js>', false);
  }

  #[Test]
  public function code_block_skips_empty_content_even_when_language_exists(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $this->blockType('code', 'Code', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => '   ',
      'settings' => json_encode(['language' => 'html'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('<pre>', false);
    $response->assertDontSee('<code', false);
  }

  #[Test]
  public function code_block_sanitizes_language_attribute_without_visible_label(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $this->blockType('code', 'Code', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => '<script>alert(1)</script>',
      'settings' => json_encode(['language' => 'C# Script<script>'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<code data-language="c#-script-script">', false);
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    $response->assertDontSee('<script>alert(1)</script>', false);
    $response->assertDontSee('>C# Script', false);
  }

  #[Test]
  public function code_block_does_not_render_historical_child_blocks_publicly(): void
  {
    $page = $this->pageWithMainSlot();

    $code = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'code',
      'block_type_id' => $this->blockType('code', 'Code', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => 'echo true;',
      'settings' => json_encode(['language' => 'php'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $code->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => 'Historical child',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<pre><code data-language="php">echo true;</code></pre>', false);
    $response->assertDontSee('Historical child');
  }

  #[Test]
  public function toc_renders_link_list_when_headings_exist(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'toc',
      'block_type_id' => $this->blockType('toc', 'Table of Contents', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'On this page',
      'status' => 'published',
      'is_system' => false,
    ]);

    $overview = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'variant' => 'h2',
      'url' => 'overview',
      'settings' => json_encode(['anchor' => 'overview'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($overview, [
      'title' => 'Overview',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($overview->fresh(['textTranslations']));

    $details = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'variant' => 'h3',
      'url' => 'details',
      'settings' => json_encode(['anchor' => 'details'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($details, [
      'title' => 'Details',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($details->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('wb-link-list', false);
    $response->assertSee('wb-link-list-item', false);
    $response->assertSee('<a class="wb-link-list-item" href="#overview">', false);
    $response->assertSee('<span class="wb-link-list-title">Overview</span>', false);
    $response->assertSee('<a class="wb-link-list-item" href="#details">', false);
    $response->assertSee('<span class="wb-link-list-title">Details</span>', false);
  }

  #[Test]
  public function toc_not_rendered_when_no_headings(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'toc',
      'block_type_id' => $this->blockType('toc', 'Table of Contents', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'On this page',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('wb-link-list', false);
    $response->assertDontSee('On this page');
  }

  #[Test]
  public function toc_uses_translated_header_titles_and_canonical_header_anchors(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->firstOrFail();
    $french = Locale::query()->updateOrCreate(
      ['code' => 'fr'],
      ['name' => 'French', 'is_default' => false, 'is_enabled' => true],
    );
    $site->locales()->syncWithoutDetaching([$french->id]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
    );
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $french->id],
      ['site_id' => $site->id, 'name' => 'A propos', 'slug' => 'a-propos', 'path' => '/p/a-propos'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'toc',
      'block_type_id' => $this->blockType('toc', 'TOC', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'On this page',
      'status' => 'published',
      'is_system' => false,
    ]);

    $header = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'variant' => 'h2',
      'url' => 'overview',
      'settings' => json_encode(['anchor' => 'overview'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($header, ['title' => 'Overview'], null, true);
    app(BlockTranslationWriter::class)->sync($header, ['title' => 'Vue d\'ensemble'], 'fr');
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

    $response = $this->get('/fr/p/a-propos');

    $response->assertOk();
    $response->assertSee('<a class="wb-link-list-item" href="#overview">', false);
    $response->assertSee('<span class="wb-link-list-title">Vue d&#039;ensemble</span>', false);
  }

  #[Test]
  public function toc_includes_nested_public_headers_and_skips_unanchored_or_hidden_entries(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'toc',
      'block_type_id' => $this->blockType('toc', 'TOC', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'On this page',
      'status' => 'published',
      'is_system' => false,
    ]);

    $section = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'section',
      'block_type_id' => $this->blockType('section', 'Section', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'status' => 'published',
      'is_system' => false,
    ]);

    $container = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $section->id,
      'type' => 'container',
      'block_type_id' => $this->blockType('container', 'Container', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    $nestedHeader = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'variant' => 'h3',
      'url' => 'nested-details',
      'settings' => json_encode(['anchor' => 'nested-details'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($nestedHeader, ['title' => 'Nested details'], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($nestedHeader->fresh(['textTranslations']));

    $unanchoredHeader = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'variant' => 'h3',
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($unanchoredHeader, ['title' => 'Missing anchor'], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($unanchoredHeader->fresh(['textTranslations']));

    $hiddenHeader = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $container->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'variant' => 'h3',
      'url' => 'hidden-section',
      'settings' => json_encode(['anchor' => 'hidden-section'], JSON_UNESCAPED_SLASHES),
      'status' => 'draft',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($hiddenHeader, ['title' => 'Hidden section'], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($hiddenHeader->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<a class="wb-link-list-item" href="#nested-details">', false);
    $response->assertSee('<span class="wb-link-list-title">Nested details</span>', false);
    $response->assertDontSee('href="#Missing anchor"', false);
    $response->assertDontSee('href="#hidden-section"', false);
  }

  #[Test]
  public function toc_does_not_render_historical_child_blocks_publicly(): void
  {
    $page = $this->pageWithMainSlot();

    $toc = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'toc',
      'block_type_id' => $this->blockType('toc', 'TOC', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'On this page',
      'status' => 'published',
      'is_system' => false,
    ]);

    $header = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'header',
      'block_type_id' => $this->blockType('header', 'Header', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'variant' => 'h2',
      'url' => 'overview',
      'settings' => json_encode(['anchor' => 'overview'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($header, ['title' => 'Overview'], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

    Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $toc->id,
      'type' => 'plain_text',
      'block_type_id' => $this->blockType('plain_text', 'Plain Text', 3)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'content' => 'Hidden child content',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<a class="wb-link-list-item" href="#overview">', false);
    $response->assertDontSee('Hidden child content');
  }

  #[Test]
  public function table_quote_and_html_blocks_ignore_historical_child_rows_without_crashing(): void
  {
    $page = $this->pageWithMainSlot();

    $table = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'table',
      'block_type_id' => $this->blockType('table', 'Table', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'Plans',
      'content' => "Plan | Seats\nStarter | 3",
      'variant' => 'header-row',
      'status' => 'published',
      'is_system' => false,
    ]);

    $quote = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'quote',
      'block_type_id' => $this->blockType('quote', 'Quote', 2)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 1,
      'title' => 'Editor',
      'subtitle' => 'Docs Team',
      'content' => 'Stay close to the shipped contract.',
      'status' => 'published',
      'is_system' => false,
    ]);

    $html = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'html',
      'block_type_id' => $this->blockType('html', 'HTML (Trusted)', 3)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 2,
      'content' => '<div class="wb-card">Trusted body</div>',
      'status' => 'published',
      'is_system' => false,
    ]);

    foreach ([$table, $quote, $html] as $parent) {
      Block::query()->create([
        'page_id' => $page->id,
        'parent_id' => $parent->id,
        'type' => 'plain_text',
        'block_type_id' => $this->blockType('plain_text', 'Plain Text', 4)->id,
        'source_type' => 'static',
        'slot' => 'main',
        'slot_type_id' => $this->mainSlotType()->id,
        'sort_order' => 0,
        'content' => 'Historical child row',
        'status' => 'published',
        'is_system' => false,
      ]);
    }

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<h3>Plans</h3>', false);
    $response->assertSee('<th>Plan</th>', false);
    $response->assertSee('Stay close to the shipped contract.');
    $response->assertSee('<div class="wb-card">Trusted body</div>', false);
    $response->assertDontSee('Historical child row');
    $this->assertSame([], app(PublicOverlayRegistry::class)->all()->all());
    $this->assertSame([], app(PublicBodyEndRegistry::class)->all()->all());
  }

  #[Test]
  public function faq_renders_without_breaking_layout(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'faq',
      'block_type_id' => $this->blockType('faq', 'FAQ', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'What does this do?',
      'content' => 'It keeps FAQ rendering simple and stable.',
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('wb-card wb-card-muted', false);
    $response->assertSee('wb-card-body wb-stack wb-gap-2', false);
    $response->assertSee('What does this do?');
    $response->assertSee('It keeps FAQ rendering simple and stable.');
  }

  #[Test]
  public function no_invalid_classes_present(): void
  {
    $page = $this->pageWithMainSlot();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($block, [
      'content' => 'Rich text `content`',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('wb-cluster-3', false);
    $response->assertDontSee('wb-prose', false);
    $response->assertDontSee('wb-promo-muted', false);
    $response->assertDontSee('wb-promo-accent', false);
  }

  #[Test]
  public function rich_text_renders_safe_html_fragment_from_translation_content(): void
  {
    $page = $this->pageWithMainSlot();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($block, [
      'content' => '<p>Intro with <strong>bold</strong>, <em>italic</em>, and <a href="https://example.com">docs</a>.</p><ol><li>First item</li><li>Second item</li></ol>',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('wb-rich-text', false);
    $response->assertSee('wb-rich-text-readable', false);
    $response->assertSee('<div class="wb-rich-text wb-rich-text-readable"><p>Intro with <strong>bold</strong>, <em>italic</em>, and <a href="https://example.com" rel="noopener noreferrer">docs</a>.</p><ol><li>First item</li><li>Second item</li></ol></div>', false);
  }

  #[Test]
  public function rich_text_public_rendering_escapes_unsafe_markup_and_rejects_unsafe_links(): void
  {
    $page = $this->pageWithMainSlot();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($block, [
      'content' => '<p><script>alert(1)</script><a href="javascript:alert(1)">bad</a> and <code>safe</code></p>',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('<p>bad and <code>safe</code></p>', false);
    $response->assertDontSee('<script>alert(1)</script>', false);
    $response->assertDontSee('href="javascript:alert(1)"', false);
  }

  #[Test]
  public function rich_text_public_rendering_does_not_add_public_javascript(): void
  {
    $page = $this->pageWithMainSlot();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($block, [
      'content' => '<p>Safe body copy.</p>',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('cms/js/admin/rich-text-editor.js', false);
    $response->assertDontSee('data-wb-rich-text-editor', false);
  }

  #[Test]
  public function rich_text_does_not_render_empty_wrapper_when_translation_content_is_empty(): void
  {
    $page = $this->pageWithMainSlot();

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($block, [
      'content' => null,
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('<div class="wb-rich-text wb-rich-text-readable"></div>', false);
    $response->assertDontSee('wb-rich-text wb-rich-text-readable', false);
  }

  #[Test]
  public function rich_text_site_variable_values_are_rendered_as_plain_text_and_not_executed(): void
  {
    $page = $this->pageWithMainSlot();
    $page->site->siteVariables()->create([
      'key' => 'promo_text',
      'label' => 'Promo Text',
      'value' => '<script>alert(1)</script><strong>Unsafe</strong>',
      'is_enabled' => true,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rich-text',
      'block_type_id' => $this->blockType('rich-text', 'Rich Text', 1)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    app(BlockTranslationWriter::class)->sync($block, [
      'content' => '<p>{{ site.promo_text }}</p>',
    ], null, true);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;&lt;strong&gt;Unsafe&lt;/strong&gt;', false);
    $response->assertDontSee('<script>alert(1)</script>', false);
    $response->assertDontSee('<strong>Unsafe</strong>', false);
  }

  private function pageWithMainSlot(): Page
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $site = Site::query()->firstOrFail();

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
    ]);

    return $page;
  }

  private function mainSlotType(): SlotType
  {
    return SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );
  }

  private function blockType(string $slug, string $name, int $sortOrder): BlockType
  {
    return BlockType::query()->updateOrCreate(
      ['slug' => $slug],
      ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => false],
    );
  }
}
