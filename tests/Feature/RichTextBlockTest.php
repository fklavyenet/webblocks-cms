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
    public function RichText_admin_form_loads_editor_controls_and_named_asset(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot] = $this->pageWithMainSlot();
        $richTextType = BlockType::query()->where('slug', 'rich-text')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $richTextType->id]));

        $response->assertOk();
        $response->assertSee('assets/webblocks-cms/js/admin/rich-text-editor.js', false);
        $response->assertSee('data-wb-rich-text-editor', false);
        $response->assertSee('data-wb-rich-text-input', false);
        $response->assertSee('data-wb-rich-text-surface', false);
        $response->assertSee('data-wb-rich-text-command="bold"', false);
        $response->assertSee('data-wb-rich-text-command="italic"', false);
        $response->assertSee('data-wb-rich-text-command="code"', false);
        $response->assertSee('data-wb-rich-text-command="unordered-list"', false);
        $response->assertSee('data-wb-rich-text-command="ordered-list"', false);
        $response->assertSee('data-wb-rich-text-command="blockquote"', false);
        $response->assertSee('data-wb-rich-text-command="link"', false);
        $response->assertSee('data-wb-rich-text-command="clear"', false);
    }

    #[Test]
    public function RichText_is_stored_in_translation_backed_content_after_server_side_sanitization(): void
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
            'content' => '<p onclick="evil()">Hello <strong>world</strong> <a href="javascript:alert(1)">bad</a> <a href="/docs">safe</a> <a href="https://example.com" target="_blank">blank</a></p>',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $block = Block::query()->where('page_id', $page->id)->where('type', 'rich-text')->firstOrFail();
        $content = DB::table('block_text_translations')->where('block_id', $block->id)->value('content');

        $this->assertNull($block->fresh()->getRawOriginal('content'));
        $this->assertSame('<p>Hello <strong>world</strong> <a>bad</a> <a href="/docs">safe</a> <a href="https://example.com" target="_blank" rel="noopener noreferrer">blank</a></p>', $content);
    }

    #[Test]
    public function RichText_public_renderer_outputs_safe_markup_only(): void
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
            'content' => '<p>Intro <strong>bold</strong> <a href="https://example.com" target="_blank">docs</a></p><script>alert(1)</script>',
        ], null, true);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('data-wb-public-block-type="rich-text"', false);
        $response->assertSee('<div class="wb-stack wb-gap-2">', false);
        $response->assertSee('<p>Intro <strong>bold</strong> <a href="https://example.com" target="_blank" rel="noopener noreferrer">docs</a></p>', false);
        $response->assertDontSee('alert(1)', false);
        $response->assertDontSee('javascript:', false);
    }
}
