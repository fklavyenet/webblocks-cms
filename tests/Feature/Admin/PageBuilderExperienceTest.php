<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageBuilderExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function assertTextTranslation(Block $block, int $localeId, array $expected): void
    {
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $block->id, 'locale_id' => $localeId] + $expected);
    }

    private function pageWithSlot(SlotType $slotType, string $title = 'About', string $slug = 'about'): array
    {
        $site = $this->defaultSite();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
            ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => '/p/'.$slug],
        );

        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        return [$page, $pageSlot];
    }

    #[Test]
    public function creating_a_page_starts_empty_and_persists_selected_slots(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $site = $this->defaultSite();

        $response = $this->actingAs($user)->post(route('admin.pages.store'), [
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
            'slots' => [
                ['slot_type_id' => $header->id],
                ['slot_type_id' => $main->id],
            ],
        ]);

        $page = Page::query()->where('site_id', $site->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertSame(0, Block::query()->where('page_id', $page->id)->count());
        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $header->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $main->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'name' => 'About',
            'slug' => 'about',
        ]);
    }

    #[Test]
    public function slot_block_picker_lists_header_plain_text_section_and_container(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));

        $response->assertOk();
        $response->assertSee('Header');
        $response->assertSee('Plain Text');
        $response->assertSee('Section');
        $response->assertSee('Container');
        $response->assertDontSee('Hero');
        $response->assertDontSee('Rich Text');
    }

    #[Test]
    public function header_admin_form_uses_text_and_level_fields(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Header');
        $response->assertSee('name="text"', false);
        $response->assertSee('name="level"', false);
        $response->assertDontSee('Heading Text');
        $response->assertDontSee('Anchor ID');
    }

    #[Test]
    public function new_block_modal_defaults_status_to_published_and_shows_compact_block_info_fields(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerType->id]));

        $response->assertOk();
        $response->assertSee('Block Info');
        $response->assertSee('Block Fields');
        $response->assertSee('Settings');
        $response->assertSee('Parent Block');
        $response->assertSee('Sort Order');
        $response->assertSee('Status');
        $response->assertSee('<option value="published" selected>published</option>', false);
        $response->assertDontSee('Translation Status');
        $response->assertDontSee('Selected page');
        $response->assertDontSee('This block type defines the current builder behavior.');
        $response->assertDontSee('Runtime output comes from editorial fields authored on this block.');
        $response->assertDontSee('Runtime output comes from application data and compact block config.');
    }

    #[Test]
    public function plain_text_admin_form_uses_text_field(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $plainTextType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Plain Text');
        $response->assertSee('name="text"', false);
        $response->assertDontSee('Rich Text');
    }

    #[Test]
    public function layout_block_admin_forms_show_expected_fields_and_settings_controls(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();

        $sectionResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $sectionType->id]));
        $containerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $containerType->id]));

        $sectionResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="spacing"', false)->assertSee('Admin-only label used in the block tree and parent selector.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
        $containerResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="width"', false)->assertSee('Admin-only label used in the block tree and parent selector.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
    }

    #[Test]
    public function header_and_plain_text_settings_controls_render_in_the_settings_tab(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $headerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $headerType->id]));
        $plainTextResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $plainTextType->id]));

        $headerResponse->assertOk()->assertSee('Settings')->assertSee('name="alignment"', false)->assertSee('Applies shipped WebBlocks UI text alignment classes only.');
        $plainTextResponse->assertOk()->assertSee('Settings')->assertSee('name="alignment"', false)->assertSee('Applies shipped WebBlocks UI text alignment classes only.');
    }

    #[Test]
    public function layout_block_name_is_saved_and_rendered_in_admin_tree(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $sectionType->id,
            'sort_order' => 0,
            'name' => 'Hero area',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $containerType->id,
            'parent_id' => $section->id,
            'sort_order' => 0,
            'name' => 'Hero content',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $section->id]));

        $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

        $this->assertSame('Hero area', $section->fresh()->setting('layout_name'));
        $this->assertSame('Hero content', $container->fresh()->setting('layout_name'));

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $section->id]));

        $response->assertOk();
        $response->assertSee('Section -- Hero area');
        $response->assertSee('Container -- Hero content');
    }

    #[Test]
    public function parent_dropdown_lists_only_container_blocks_and_excludes_current_block_and_descendants(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $section = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Hero area'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $container = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'container',
            'block_type_id' => $containerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Hero content'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $header = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $container->id, 'expanded' => $section->id.','.$container->id]));

        $response->assertOk();
        $response->assertSee('<option value="">No parent</option>', false);
        $response->assertSee('Section: Hero area');
        $response->assertDontSee('<option value="'.$header->id.'">', false);
        $response->assertDontSee('>Header</option>', false);
        $response->assertDontSee('>Plain Text</option>', false);
        $response->assertDontSee('<option value="'.$container->id.'"', false);

        $headerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $header->id, 'expanded' => $section->id.','.$container->id]));

        $headerResponse->assertOk();
        $headerResponse->assertSee('Section: Hero area');
        $headerResponse->assertSee('— Container: Hero content');
        $headerResponse->assertDontSee('>Header</option>', false);
        $headerResponse->assertDontSee('>Plain Text</option>', false);
    }

    #[Test]
    public function header_block_store_creates_translation_backed_text_and_shared_level(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $headerType->id,
            'sort_order' => 0,
            'text' => 'Welcome title',
            'level' => 'h1',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'header')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'header',
            'title' => null,
            'content' => null,
            'variant' => 'h1',
        ]);
        $this->assertTextTranslation($block, $this->defaultLocale()->id, [
            'title' => 'Welcome title',
            'subtitle' => null,
            'content' => null,
        ]);
        $this->assertNull($block->fresh()->setting('alignment'));
    }

    #[Test]
    public function plain_text_block_store_creates_translation_backed_text_only(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $plainTextType->id,
            'sort_order' => 0,
            'text' => 'Plain paragraph copy',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'plain_text')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'plain_text',
            'title' => null,
            'content' => null,
            'variant' => null,
        ]);
        $this->assertTextTranslation($block, $this->defaultLocale()->id, [
            'title' => null,
            'subtitle' => null,
            'content' => 'Plain paragraph copy',
        ]);
        $this->assertNull($block->fresh()->setting('alignment'));
    }

    #[Test]
    public function block_settings_are_saved_as_shared_non_translatable_settings(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $headerType->id,
            'sort_order' => 0,
            'text' => 'Aligned heading',
            'level' => 'h2',
            'alignment' => 'center',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $plainTextType->id,
            'sort_order' => 1,
            'text' => 'Aligned paragraph',
            'alignment' => 'right',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $sectionType->id,
            'sort_order' => 2,
            'name' => 'Feature zone',
            'spacing' => 'lg',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $containerType->id,
            'sort_order' => 3,
            'width' => 'xl',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $header = Block::query()->where('page_id', $page->id)->where('type', 'header')->firstOrFail();
        $plainText = Block::query()->where('page_id', $page->id)->where('type', 'plain_text')->firstOrFail();
        $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();
        $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

        $this->assertSame('center', $header->fresh()->setting('alignment'));
        $this->assertSame('right', $plainText->fresh()->setting('alignment'));
        $this->assertSame('Feature zone', $section->fresh()->setting('layout_name'));
        $this->assertSame('lg', $section->fresh()->setting('spacing'));
        $this->assertSame('xl', $container->fresh()->setting('width'));
    }

    #[Test]
    public function invalid_block_settings_are_rejected(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $headerType->id,
            'sort_order' => 0,
            'text' => 'Heading',
            'level' => 'h2',
            'alignment' => 'diagonal',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertSessionHasErrors('alignment');

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $sectionType->id,
            'sort_order' => 0,
            'spacing' => 'xl',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertSessionHasErrors('spacing');
    }

    #[Test]
    public function primitive_block_validation_requires_text_and_valid_header_level(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $headerResponse = $this->actingAs($user)
            ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
            ->post(route('admin.blocks.store'), [
                'page_id' => $page->id,
                'slot_type_id' => $main->id,
                'block_type_id' => $headerType->id,
                'sort_order' => 0,
                'text' => '',
                'level' => 'div',
                'status' => 'published',
            ]);

        $headerResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $headerResponse->assertSessionHasErrors(['text', 'level']);

        $plainTextResponse = $this->actingAs($user)
            ->from(route('admin.pages.slots.blocks', [$page, $pageSlot]))
            ->post(route('admin.blocks.store'), [
                'page_id' => $page->id,
                'slot_type_id' => $main->id,
                'block_type_id' => $plainTextType->id,
                'sort_order' => 1,
                'text' => '',
                'status' => 'published',
            ]);

        $plainTextResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $plainTextResponse->assertSessionHasErrors(['text']);
    }

    #[Test]
    public function nested_layout_blocks_can_be_created_in_admin_slot_editor(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $sectionType->id,
            'sort_order' => 0,
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $section = Block::query()->where('page_id', $page->id)->where('type', 'section')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $section->id,
            'block_type_id' => $containerType->id,
            'sort_order' => 0,
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $section->id]));

        $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $headerType->id,
            'sort_order' => 0,
            'text' => 'Nested title',
            'level' => 'h1',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $container->id]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $plainTextType->id,
            'sort_order' => 1,
            'text' => 'Nested paragraph',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $container->id]));

        $section->refresh();
        $container->refresh();

        $this->assertNull($section->parent_id);
        $this->assertSame($section->id, $container->parent_id);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'header', 'parent_id' => $container->id]);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'plain_text', 'parent_id' => $container->id]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $section->id.','.$container->id]));

        $response->assertOk();
        $response->assertSee('Section');
        $response->assertSee('Container');
        $response->assertSee('Nested title');
        $response->assertSee('Nested paragraph');
        $response->assertSee('data-wb-slot-block-row', false);
        $response->assertSee('data-wb-slot-block-id="'.$section->id.'"', false);
        $response->assertSee('data-wb-slot-block-id="'.$container->id.'"', false);
        $response->assertSee('data-wb-slot-parent-id="'.$section->id.'"', false);
        $response->assertSee('data-wb-slot-toggle="'.$section->id.'"', false);
        $response->assertSee('data-wb-slot-toggle="'.$container->id.'"', false);
    }

    #[Test]
    public function header_locale_update_only_changes_translated_text_and_keeps_shared_level(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $turkish->id],
            ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/p/hakkinda'],
        );

        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $header = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);
        $header->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'English heading',
            'subtitle' => null,
            'content' => null,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $header), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $headerType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'text' => 'Turkce baslik',
            'level' => 'h5',
            'status' => 'published',
            'locale' => 'tr',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $header->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'locale' => 'tr']));
        $this->assertTextTranslation($header, $turkish->id, [
            'title' => 'Turkce baslik',
            'subtitle' => null,
            'content' => null,
        ]);
        $this->assertSame('h2', $header->fresh()->variant);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $header->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'English heading',
        ]);
    }
}
