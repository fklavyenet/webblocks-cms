<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    }

    #[Test]
    public function code_block_renders_optional_header_metadata_without_executing_html(): void
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
        $response->assertSee('Escaped snippet');
        $response->assertSee('&lt;demo.js&gt;', false);
        $response->assertDontSee('<demo.js>', false);
        $response->assertSee('console.log(&#039;&lt;b&gt;safe&lt;/b&gt;&#039;);', false);
        $response->assertDontSee('<b>safe</b>', false);
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

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'heading',
            'block_type_id' => $this->blockType('heading', 'Heading', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 1,
            'title' => 'Overview',
            'variant' => 'h2',
            'url' => 'overview',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'heading',
            'block_type_id' => $this->blockType('heading', 'Heading', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 2,
            'title' => 'Details',
            'variant' => 'h3',
            'url' => 'details',
            'status' => 'published',
            'is_system' => false,
        ]);

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
    public function rich_text_renders_safe_inline_code_from_translation_content(): void
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
            'content' => 'Intro `bold`, `emphasis`, and `code`.',
        ], null, true);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-stack wb-gap-2">Intro <code>bold</code>, <code>emphasis</code>, and <code>code</code>.</div>', false);
    }

    #[Test]
    public function rich_text_public_rendering_escapes_unsafe_markup_and_only_formats_inline_code(): void
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
            'content' => '<script>alert(1)</script> and `safe`',
        ], null, true);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; and <code>safe</code>', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
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
        $response->assertDontSee('<div class="wb-stack wb-gap-2"></div>', false);
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
