<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RichTextBlockTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function mainSlotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function pageWithMainSlot(): array
    {
        $page = Page::query()->create([
            'site_id' => $this->defaultSite()->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
            ['site_id' => $page->site_id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
        );

        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
        ]);

        return [$page, $pageSlot];
    }

    #[Test]
    public function RichText_block_type_is_seeded_as_a_first_class_content_block(): void
    {
        $this->seedFoundation();

        $this->assertDatabaseHas('block_types', [
            'slug' => 'rich-text',
            'name' => 'Rich Text',
            'category' => 'content',
            'status' => 'published',
            'is_container' => false,
        ]);
    }

    #[Test]
    public function RichText_seeder_upgrades_an_existing_legacy_catalog_entry_and_keeps_it_visible_on_rerun(): void
    {
        $this->seedFoundation();

        BlockType::query()->where('slug', 'rich-text')->update([
            'category' => 'legacy',
            'description' => null,
            'sort_order' => 100,
            'status' => 'draft',
        ]);

        $this->seed(BlockTypeSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->assertSame(1, BlockType::query()->where('slug', 'rich-text')->count());
        $this->assertDatabaseHas('block_types', [
            'slug' => 'rich-text',
            'name' => 'Rich Text',
            'category' => 'content',
            'status' => 'published',
            'is_container' => false,
            'sort_order' => 6,
        ]);
    }

    #[Test]
    public function RichText_visibility_migration_normalizes_existing_legacy_catalog_rows(): void
    {
        $this->seedFoundation();

        BlockType::query()->where('slug', 'rich-text')->update([
            'category' => 'legacy',
            'description' => 'Old description',
            'sort_order' => 100,
            'status' => 'draft',
        ]);

        $migration = require base_path('database/migrations/2026_05_04_160000_normalize_rich_text_block_type_visibility.php');
        $migration->up();

        $this->assertDatabaseHas('block_types', [
            'slug' => 'rich-text',
            'name' => 'Rich Text',
            'category' => 'content',
            'source_type' => 'static',
            'is_container' => false,
            'sort_order' => 6,
            'status' => 'published',
        ]);
    }

    #[Test]
    public function RichText_admin_form_loads_safe_editor_toolbar_and_named_asset(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot] = $this->pageWithMainSlot();
        $richTextType = BlockType::query()->where('slug', 'rich-text')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $richTextType->id]));

        $response->assertOk();
        $response->assertSee('<label for="content">Rich Text</label>', false);
        $response->assertSee('class="wb-admin-rich-text-editor"', false);
        $response->assertSee('class="wb-toolbar wb-toolbar-sm wb-admin-rich-text-toolbar" role="toolbar" aria-label="Rich Text formatting"', false);
        $response->assertSee('class="wb-action-group" role="group" aria-label="Inline formatting"', false);
        $response->assertSee('class="wb-action-group" role="group" aria-label="Links"', false);
        $response->assertSee('class="wb-action-group" role="group" aria-label="Lists"', false);
        $response->assertSee('class="wb-toolbar-divider" aria-hidden="true"', false);
        $response->assertSee('name="content" class="wb-textarea wb-admin-rich-text-textarea"', false);
        $response->assertSee('data-wb-rich-text-editor', false);
        $response->assertSee('data-wb-rich-text-action="bold"', false);
        $response->assertSee('data-wb-rich-text-action="italic"', false);
        $response->assertSee('data-wb-rich-text-action="code"', false);
        $response->assertSee('data-wb-rich-text-action="link"', false);
        $response->assertSee('data-wb-rich-text-action="bullet-list"', false);
        $response->assertSee('data-wb-rich-text-action="numbered-list"', false);
        $response->assertSee('type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bold" aria-label="Bold" title="Bold">B</button>', false);
        $response->assertSee('type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="italic" aria-label="Italic" title="Italic">I</button>', false);
        $response->assertSee('>Code</button>', false);
        $response->assertSee('>Link</button>', false);
        $response->assertSee('>• List</button>', false);
        $response->assertSee('>1. List</button>', false);
        $response->assertSee('Headings should use Header blocks.', false);
        $response->assertSee('assets/webblocks-cms/js/admin/rich-text-editor.js', false);

        $assetContents = file_get_contents(public_path('assets/webblocks-cms/js/admin/rich-text-editor.js'));
        $partialContents = file_get_contents(resource_path('views/admin/blocks/types/partials/rich-text-editor.blade.php'));

        $this->assertNotFalse($assetContents);
        $this->assertNotFalse($partialContents);
        $this->assertStringContainsString('function toggleWrap(textarea, before, after, placeholder)', $assetContents);
        $this->assertStringContainsString('function toggleLinePrefix(textarea, applyPrefixFn, detectPrefixRegex, stripPrefixRegex, fallback)', $assetContents);
        $this->assertStringContainsString('function getSelectedLinesRange(value, start, end)', $assetContents);
        $this->assertStringContainsString('function getMarkdownLinkRange(value, start, end)', $assetContents);
        $this->assertStringContainsString('function toggleLink(textarea)', $assetContents);
        $this->assertStringContainsString("button.dataset.wbRichTextAction", $assetContents);
        $this->assertStringNotContainsString('<script', $partialContents);
    }

    #[Test]
    public function RichText_is_stored_in_translation_backed_content_as_plain_text(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot] = $this->pageWithMainSlot();
        $richTextType = BlockType::query()->where('slug', 'rich-text')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $richTextType->id,
            'slot_type_id' => $pageSlot->slot_type_id,
            'sort_order' => 0,
            'content' => 'Use `light`, `dark`, or `auto`.',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $block = Block::query()->where('page_id', $page->id)->where('type', 'rich-text')->firstOrFail();
        $content = DB::table('block_text_translations')->where('block_id', $block->id)->value('content');

        $this->assertNull($block->fresh()->getRawOriginal('content'));
        $this->assertSame('Use `light`, `dark`, or `auto`.', $content);
    }

    #[Test]
    public function RichText_public_renderer_outputs_safe_markdown_like_html_and_escaped_content(): void
    {
        $this->seedFoundation();

        [$page] = $this->pageWithMainSlot();

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'rich-text',
            'block_type_id' => BlockType::query()->where('slug', 'rich-text')->firstOrFail()->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->sync($block, [
            'content' => "Intro with **bold** and *italic*.\n\n- Item with `code`\n- [Docs](https://example.com)\n\n<script>alert(1)</script>",
        ], null, true);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('data-wb-public-block-type="rich-text"', false);
        $response->assertSee('wb-rich-text', false);
        $response->assertSee('wb-rich-text-readable', false);
        $response->assertSee('<div class="wb-rich-text wb-rich-text-readable">', false);
        $response->assertSee('<p>Intro with <strong>bold</strong> and <em>italic</em>.</p>', false);
        $response->assertSee('<ul><li>Item with <code>code</code></li><li><a href="https://example.com" rel="noopener noreferrer">Docs</a></li></ul>', false);
        $response->assertSee('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('<div class="wb-stack wb-gap-3">', false);
        $response->assertDontSee('wb-prose', false);
    }
}
