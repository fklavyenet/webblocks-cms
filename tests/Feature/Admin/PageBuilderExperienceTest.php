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
        $pageSlot = PageSlot::query()->where('page_id', $page->id)->orderBy('sort_order')->firstOrFail();

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

        $editResponse = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $editResponse->assertOk();
        $editResponse->assertSee('<th>Actions</th>', false);
        $editResponse->assertDontSee('<th class="wb-text-end">Actions</th>', false);
        $editResponse->assertSee('<div class="wb-action-group">', false);
        $editResponse->assertDontSee('<td class="wb-text-end">', false);
        $editResponse->assertSee('href="'.route('admin.pages.slots.blocks', [$page, $pageSlot]).'"', false);
        $editResponse->assertSee('data-wb-slot-remove', false);
    }

    #[Test]
    public function slot_block_picker_lists_all_published_foundation_block_types(): void
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
        $response->assertSee('Cluster');
        $response->assertSee('Grid');
        $response->assertSee('Content Header');
        $response->assertSee('Button Link');
        $response->assertSee('Card');
        $response->assertSee('Stat Card');
        $response->assertSee('Alert');
        $response->assertSee('Link List');
        $response->assertSee('Link List Item');
        $response->assertSee('Breadcrumb');
        $response->assertDontSee('Hero');
        $response->assertDontSee('Rich Text');
    }

    #[Test]
    public function card_is_seeded_as_published_container_block_with_limited_child_support(): void
    {
        $this->seedFoundation();

        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
        $card = new Block(['type' => 'card', 'block_type_id' => $cardType->id]);
        $card->setRelation('blockType', $cardType);

        $this->assertSame('published', $cardType->status);
        $this->assertTrue($cardType->is_container);
        $this->assertTrue($card->canAcceptChildren());
        $this->assertSame(['cluster', 'button_link'], $card->allowedChildTypeSlugs());
        $this->assertTrue($card->canAcceptChildType('cluster'));
        $this->assertTrue($card->canAcceptChildType('button_link'));
        $this->assertFalse($card->canAcceptChildType('plain_text'));
    }

    #[Test]
    public function stat_card_is_seeded_as_published_content_block(): void
    {
        $this->seedFoundation();

        $statCardType = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

        $this->assertSame('Stat Card', $statCardType->name);
        $this->assertSame('published', $statCardType->status);
        $this->assertSame('content', $statCardType->category);
        $this->assertFalse($statCardType->is_container);
    }

    #[Test]
    public function slot_block_picker_renders_a_single_sorted_block_list_and_compact_filter_row_markup(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertDontSee('Recommended');
        $response->assertSee('class="wb-cluster wb-cluster-between wb-cluster-2"', false);
        $response->assertSee('id="slot_block_type_search" name="block_type_search" class="wb-input"', false);
        $response->assertSee('id="slot_block_type_sort" name="block_type_sort" class="wb-select"', false);
        $response->assertSee('<option value="default" selected>Default order</option>', false);
        $response->assertSee('<div class="wb-cluster wb-cluster-end wb-cluster-2">', false);
        $this->assertNotFalse($content);
        $this->assertFalse(strpos($content, 'slot-block-picker-recommended-title'));
        $this->assertFalse(strpos($content, 'slot-block-picker-all-title'));

        $listStart = strpos($content, '<div class="wb-list wb-list-sm">');
        $footerStart = strpos($content, '<div class="wb-modal-footer');

        $this->assertNotFalse($listStart);
        $this->assertNotFalse($footerStart);

        $listMarkup = substr($content, $listStart, $footerStart - $listStart);

        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Content Header</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Section</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Container</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Cluster</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Grid</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Header</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Plain Text</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Button Link</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Card</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Stat Card</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Alert</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Link List</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Link List Item</span>'));
        $this->assertSame(1, substr_count($listMarkup, 'wb-list-item-title">Breadcrumb</span>'));
        $response->assertSeeInOrder([
            'wb-list-item-title">Content Header</span>',
            'wb-list-item-title">Section</span>',
            'wb-list-item-title">Container</span>',
            'wb-list-item-title">Cluster</span>',
            'wb-list-item-title">Grid</span>',
            'wb-list-item-title">Header</span>',
            'wb-list-item-title">Plain Text</span>',
            'wb-list-item-title">Button Link</span>',
            'wb-list-item-title">Card</span>',
            'wb-list-item-title">Stat Card</span>',
            'wb-list-item-title">Alert</span>',
            'wb-list-item-title">Link List</span>',
            'wb-list-item-title">Link List Item</span>',
            'wb-list-item-title">Breadcrumb</span>',
        ], false);
    }

    #[Test]
    public function breadcrumb_is_seeded_as_a_published_navigation_system_block(): void
    {
        $this->seedFoundation();

        $breadcrumbType = BlockType::query()->where('slug', 'breadcrumb')->firstOrFail();

        $this->assertSame('Breadcrumb', $breadcrumbType->name);
        $this->assertSame('published', $breadcrumbType->status);
        $this->assertSame('navigation', $breadcrumbType->category);
        $this->assertTrue($breadcrumbType->is_system);
        $this->assertFalse($breadcrumbType->is_container);
    }

    #[Test]
    public function breadcrumb_form_is_dedicated_and_can_be_added_to_the_header_slot(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $header = $this->slotType('header', 'Header', 1);
        [$page, $pageSlot] = $this->pageWithSlot($header);
        $breadcrumbType = BlockType::query()->where('slug', 'breadcrumb')->firstOrFail();

        $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $breadcrumbType->id]));

        $formResponse->assertOk();
        $formResponse->assertSee('Add Block: Breadcrumb');
        $formResponse->assertSee('System Breadcrumb');
        $formResponse->assertSee('name="breadcrumb_home_label"', false);
        $formResponse->assertSee('name="breadcrumb_include_current"', false);
        $formResponse->assertDontSee('Generic Block Form');
        $formResponse->assertDontSee('name="title"', false);
        $formResponse->assertDontSee('name="content"', false);

        $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $header->id,
            'block_type_id' => $breadcrumbType->id,
            'sort_order' => 0,
            'breadcrumb_home_label' => 'Start',
            'breadcrumb_include_current' => '1',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'breadcrumb')->firstOrFail();

        $storeResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'breadcrumb',
            'slot' => 'header',
            'title' => null,
            'content' => null,
            'is_system' => true,
        ]);
        $this->assertSame('admin.blocks.types.breadcrumb', $block->adminFormView());
        $this->assertSame('pages.partials.blocks.breadcrumb', $block->publicRenderView());
    }

    #[Test]
    public function edit_slot_blocks_list_renders_native_sortable_markup_and_fallback_move_controls(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

        $section = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'block_type_id' => $alertType->id,
            'type' => 'alert',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $response->assertOk();
        $response->assertSee('data-admin-sortable-list', false);
        $response->assertSee('data-admin-sortable-mode="slot-blocks"', false);
        $response->assertSee('data-admin-sortable-reorder-url', false);
        $response->assertSee('data-admin-sortable-item', false);
        $response->assertSee('data-page-id="'.$page->id.'"', false);
        $response->assertSee('data-slot-type-id="'.$main->id.'"', false);
        $response->assertSee('data-slot-block-row', false);
        $response->assertSee('data-slot-block-toggle', false);
        $response->assertSee('data-block-id="'.$section->id.'"', false);
        $response->assertSee('data-parent-id=""', false);
        $response->assertSee('data-slot-type-id="'.$main->id.'"', false);
        $response->assertSee('draggable="true"', false);
        $response->assertSee('data-admin-sortable-handle', false);
        $response->assertSee('wb-icon-grip-vertical', false);
        $response->assertSee('<th>Actions</th>', false);
        $response->assertSee('<div class="wb-action-group">', false);
        $response->assertSee('title="Move block up"', false);
        $response->assertSee('title="Move block down"', false);
        $response->assertSee('title="Edit block"', false);
        $response->assertSee('title="Add child block"', false);
        $response->assertSee('action="'.route('admin.blocks.destroy', $section).'"', false);
        $response->assertSee('name="_method" value="DELETE"', false);
        $response->assertDontSee('name="expanded"', false);
        $response->assertDontSee('?expanded=', false);
        $response->assertDontSee('&expanded=', false);
    }

    #[Test]
    public function slot_block_reorder_endpoint_updates_sort_order_for_valid_same_parent_group(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

        $first = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $second = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
            'blocks' => [$second->id, $first->id],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'message' => 'Saved']);
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    #[Test]
    public function slot_block_reorder_endpoint_updates_public_render_order_for_reordered_top_level_blocks(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

        $first = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $plainTextType->id,
            'type' => 'plain_text',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'content' => 'Alpha block',
            'status' => 'published',
            'is_system' => false,
        ]);

        $second = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $plainTextType->id,
            'type' => 'plain_text',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'content' => 'Beta block',
            'status' => 'published',
            'is_system' => false,
        ]);

        $third = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $plainTextType->id,
            'type' => 'plain_text',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 2,
            'content' => 'Gamma block',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
            'blocks' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk();

        $orderedIds = Block::query()
            ->where('page_id', $page->id)
            ->whereNull('parent_id')
            ->where('slot_type_id', $main->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$third->id, $first->id, $second->id], $orderedIds);

        $publicResponse = $this->get(route('pages.show', 'about'));

        $publicResponse->assertOk();
        $publicResponse->assertSeeInOrder([
            'Gamma block',
            'Alpha block',
            'Beta block',
        ]);
    }

    #[Test]
    public function slot_block_reorder_endpoint_rejects_mixed_parent_groups(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

        $section = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $child = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'block_type_id' => $alertType->id,
            'type' => 'alert',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
            'blocks' => [$section->id, $child->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['blocks']);
    }

    #[Test]
    public function slot_block_reorder_endpoint_rejects_blocks_from_another_page(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        [$otherPage] = $this->pageWithSlot($main, 'Docs', 'docs');
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

        $local = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $foreign = Block::query()->create([
            'page_id' => $otherPage->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
            'blocks' => [$local->id, $foreign->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['blocks']);
    }

    #[Test]
    public function slot_block_reorder_endpoint_rejects_blocks_from_another_slot(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 2);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebar->id,
            'sort_order' => 1,
        ]);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

        $mainBlock = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $sidebarBlock = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'sidebar',
            'slot_type_id' => $sidebar->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.pages.slots.blocks.reorder', [$page, $pageSlot]), [
            'blocks' => [$mainBlock->id, $sidebarBlock->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['blocks']);
    }

    #[Test]
    public function stat_card_form_and_store_preserve_zero_value_in_translation_and_admin_summary(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $statCardType = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

        $formResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $statCardType->id]));

        $formResponse->assertOk();
        $formResponse->assertSee('Add Block: Stat Card');
        $formResponse->assertSee('name="subtitle"', false);
        $formResponse->assertSee('name="title"', false);
        $formResponse->assertSee('name="content"', false);
        $formResponse->assertSee('name="url"', false);
        $formResponse->assertSee('This may be 0, 6, 14+, 173');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $statCardType->id,
            'sort_order' => 0,
            'subtitle' => 'Dependencies',
            'title' => '0',
            'content' => 'No framework requirement for the package itself',
            'url' => '/package',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'stat-card')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'stat-card',
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'url' => '/package',
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => '0',
            'subtitle' => 'Dependencies',
            'content' => 'No framework requirement for the package itself',
        ]);
        $this->assertSame('0', $block->fresh()->editorLabel());
        $this->assertSame('0', $block->fresh()->editorSummary());

        $treeResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $treeResponse->assertOk();
        $treeResponse->assertSee('>0<', false);
    }

    #[Test]
    public function alert_form_renders_translated_fields_and_shared_variant_settings(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $alertType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Alert');
        $response->assertSee('name="title"', false);
        $response->assertSee('name="content"', false);
        $response->assertSee('name="alert_variant"', false);
        $response->assertSee('Alert title and body copy are translated per locale. Alert variant stays shared across locales.');
    }

    #[Test]
    public function alert_store_creates_translated_copy_and_shared_variant(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $alertType->id,
            'sort_order' => 0,
            'title' => 'What this page is proving',
            'content' => 'This page proves docs callouts can ship as first-class blocks.',
            'alert_variant' => 'success',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'alert')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'What this page is proving',
            'content' => 'This page proves docs callouts can ship as first-class blocks.',
        ]);
        $this->assertSame('success', $block->fresh()->alertVariant());
    }

    #[Test]
    public function slot_block_picker_can_sort_by_name(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
            $page,
            $pageSlot,
            'picker' => 1,
            'block_type_sort' => 'name',
        ]));

        $response->assertOk();
        $response->assertSee('<option value="name" selected>Name A-Z</option>', false);
        $response->assertSeeInOrder([
            'wb-list-item-title">Alert</span>',
            'wb-list-item-title">Breadcrumb</span>',
            'wb-list-item-title">Button Link</span>',
            'wb-list-item-title">Card</span>',
            'wb-list-item-title">Cluster</span>',
            'wb-list-item-title">Container</span>',
            'wb-list-item-title">Content Header</span>',
            'wb-list-item-title">Grid</span>',
            'wb-list-item-title">Header</span>',
            'wb-list-item-title">Plain Text</span>',
            'wb-list-item-title">Section</span>',
        ], false);
    }

    #[Test]
    public function slot_block_picker_can_sort_by_category(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
            $page,
            $pageSlot,
            'picker' => 1,
            'block_type_sort' => 'category',
        ]));

        $response->assertOk();
        $response->assertSee('<option value="category" selected>Category</option>', false);
        $response->assertSeeInOrder([
            'wb-list-item-title">Header</span>',
            'wb-list-item-title">Plain Text</span>',
            'wb-list-item-title">Button Link</span>',
            'wb-list-item-title">Card</span>',
            'wb-list-item-title">Stat Card</span>',
            'wb-list-item-title">Section</span>',
            'wb-list-item-title">Container</span>',
            'wb-list-item-title">Cluster</span>',
            'wb-list-item-title">Grid</span>',
            'wb-list-item-title">Link List</span>',
            'wb-list-item-title">Link List Item</span>',
            'wb-list-item-title">Breadcrumb</span>',
            'wb-list-item-title">Content Header</span>',
            'wb-list-item-title">Alert</span>',
        ], false);
    }

    #[Test]
    public function slot_block_picker_search_matches_slug_name_description_and_category_terms(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);

        foreach ([
            ['term' => 'content header', 'expected' => 'Content Header'],
            ['term' => 'intro', 'expected' => 'Content Header'],
            ['term' => 'meta', 'expected' => 'Content Header'],
            ['term' => 'content_header', 'expected' => 'Content Header'],
            ['term' => 'alert', 'expected' => 'Alert'],
            ['term' => 'callout', 'expected' => 'Alert'],
            ['term' => 'pattern', 'expected' => 'Alert'],
            ['term' => 'button', 'expected' => 'Button Link'],
            ['term' => 'button link', 'expected' => 'Button Link'],
            ['term' => 'cluster', 'expected' => 'Cluster'],
            ['term' => 'section', 'expected' => 'Section'],
            ['term' => 'container', 'expected' => 'Container'],
            ['term' => 'layout', 'expected' => 'Cluster'],
        ] as $search) {
            $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
                $page,
                $pageSlot,
                'picker' => 1,
                'block_type_search' => $search['term'],
            ]));

            $response->assertOk();
            $response->assertSee($search['expected']);
        }

        $sortedSearchResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
            $page,
            $pageSlot,
            'picker' => 1,
            'block_type_sort' => 'name',
            'block_type_search' => 'button',
        ]));

        $sortedSearchResponse->assertOk();
        $sortedSearchResponse->assertSee('Button Link');
        $sortedSearchResponse->assertSee('<option value="name" selected>Name A-Z</option>', false);
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
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

        $sectionResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $sectionType->id]));
        $containerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $containerType->id]));
        $clusterResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $clusterType->id]));

        $sectionResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="spacing"', false)->assertSee('Admin-only label used in the block tree and parent selector.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
        $containerResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="width"', false)->assertSee('Admin-only label used in the block tree and parent selector.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
        $clusterResponse->assertOk()->assertSee('name="name"', false)->assertSee('name="cluster_gap"', false)->assertSee('name="cluster_alignment"', false)->assertSee('Admin-only label used in the block tree and parent selector.')->assertSee('This layout block has no public content fields.')->assertDontSee('name="text"', false);
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
    public function content_header_form_renders_editor_friendly_fields_and_settings(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $contentHeaderType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Content Header');
        $response->assertSee('name="title"', false);
        $response->assertSee('name="intro_text"', false);
        $response->assertSee('name="meta_items[]"', false);
        $response->assertSee('name="title_level"', false);
        $response->assertSee('name="alignment"', false);
        $response->assertSee('Title, intro text, and meta items are translated per locale. Title level and alignment stay shared across locales.');
    }

    #[Test]
    public function button_link_form_renders_translated_label_and_shared_link_settings(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $buttonLinkType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Button Link');
        $response->assertSee('name="label"', false);
        $response->assertSee('name="url"', false);
        $response->assertSee('name="target"', false);
        $response->assertSee('name="variant"', false);
        $response->assertSee('Button label is translated per locale. URL, target, and variant stay shared across locales.');
        $response->assertSee('Applies shipped WebBlocks UI button classes only.');
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
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

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
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $clusterType->id,
            'sort_order' => 0,
            'name' => 'Action row',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

        $this->assertSame('Hero area', $section->fresh()->setting('layout_name'));
        $this->assertSame('Hero content', $container->fresh()->setting('layout_name'));
        $this->assertSame('Action row', $cluster->fresh()->setting('layout_name'));

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $response->assertOk();
        $response->assertSee('Section -- Hero area');
        $response->assertSee('Container -- Hero content');
        $response->assertSee('Cluster — Action row');
        $response->assertDontSee('— Section');
        $response->assertDontSee('— Container');
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
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
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

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'cluster',
            'block_type_id' => $clusterType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'settings' => json_encode(['layout_name' => 'Actions'], JSON_UNESCAPED_SLASHES),
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

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $container->id]));

        $response->assertOk();
        $response->assertSee('<option value="">No parent</option>', false);
        $response->assertSee('Section: Hero area');
        $response->assertDontSee('<option value="'.$header->id.'">', false);
        $response->assertDontSee('>Header</option>', false);
        $response->assertDontSee('>Plain Text</option>', false);
        $response->assertDontSee('<option value="'.$container->id.'"', false);

        $headerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $header->id]));

        $headerResponse->assertOk();
        $headerResponse->assertSee('Section: Hero area');
        $headerResponse->assertSee('— Container: Hero content');
        $headerResponse->assertSee('— — Cluster: Actions');
        $headerResponse->assertDontSee('>Header</option>', false);
        $headerResponse->assertDontSee('>Plain Text</option>', false);
        $headerResponse->assertDontSee('<option value="'.$header->id.'">', false);
        $headerResponse->assertDontSee('<option value="'.$plainTextType->id.'">', false);
    }

    #[Test]
    public function cluster_edit_modal_lists_eligible_card_parent_candidates_and_allows_move_under_card(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
        $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $section = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
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
            'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'card',
            'block_type_id' => $cardType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $card->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
        ]);

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'cluster',
            'block_type_id' => $clusterType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'settings' => json_encode(['layout_name' => 'Actions'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $button = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $cluster->id,
            'type' => 'button_link',
            'block_type_id' => $buttonLinkType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'variant' => 'primary',
            'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $button->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Start Here',
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 2,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $cluster->id]));

        $response->assertOk();
        $response->assertSee('Section: Page Header');
        $response->assertSee('— Container: Page Header');
        $response->assertSee('— — Card: WebBlocks UI - UI building blocks for humans and AI.', false);
        $response->assertDontSee('Cluster: Actions</option>', false);
        $response->assertDontSee('Button Link: Start Here</option>', false);

        $updateResponse = $this->actingAs($user)->put(route('admin.blocks.update', $cluster), [
            'page_id' => $page->id,
            'parent_id' => $card->id,
            'block_type_id' => $clusterType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'name' => 'Actions',
            'status' => 'published',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $cluster->id,
        ]);

        $updateResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertSame($card->id, $cluster->fresh()->parent_id);

        $movedResponse = $this->get(route('pages.show', 'about'));

        $movedResponse->assertOk();
        $movedResponse->assertSeeInOrder([
            '<article class="wb-card">',
            '<div class="wb-card-footer">',
            '<div class="wb-cluster">',
            '<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>',
        ], false);
    }

    #[Test]
    public function card_still_appears_as_cluster_parent_candidate_when_container_metadata_is_stale(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

        $container = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'container',
            'block_type_id' => $containerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'card',
            'block_type_id' => $cardType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $card->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
        ]);

        BlockType::query()->whereKey($cardType->id)->update(['is_container' => false]);

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'cluster',
            'block_type_id' => $clusterType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'settings' => json_encode(['layout_name' => 'Actions'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $cluster->id]));

        $response->assertOk();
        $response->assertSee('Card: WebBlocks UI - UI building blocks for humans and AI.');
    }

    #[Test]
    public function parent_dropdown_excludes_card_when_it_cannot_accept_the_edited_block_type(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $container = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'container',
            'block_type_id' => $containerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Page Header'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'card',
            'block_type_id' => $cardType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $card->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
        ]);

        $header = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'edit' => $header->id]));

        $response->assertOk();
        $response->assertSee('Container: Page Header');
        $response->assertDontSee('Card: WebBlocks UI - UI building blocks for humans and AI.');
    }

    #[Test]
    public function slot_block_picker_filters_child_block_types_for_card_context(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $cardType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id]));

        $response->assertOk();
        $response->assertSee('Showing block types allowed inside Card.');
        $response->assertSee('wb-list-item-title">Cluster</span>', false);
        $response->assertSee('wb-list-item-title">Button Link</span>', false);
        $response->assertDontSee('wb-list-item-title">Header</span>', false);
        $response->assertDontSee('wb-list-item-title">Plain Text</span>', false);
        $response->assertDontSee('wb-list-item-title">Content Header</span>', false);
        $response->assertDontSee('wb-list-item-title">Grid</span>', false);
    }

    #[Test]
    public function card_nested_blocks_can_be_created_and_are_visible_in_admin_tree(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
        $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

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
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $contentHeaderType->id,
            'sort_order' => 0,
            'title' => 'Documentation',
            'intro_text' => 'Intro',
            'title_level' => 'h1',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $cardType->id,
            'sort_order' => 1,
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'content' => 'Footer actions should be nested.',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $card = Block::query()->where('page_id', $page->id)->where('type', 'card')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $card->id,
            'block_type_id' => $clusterType->id,
            'sort_order' => 0,
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $cluster->id,
            'block_type_id' => $buttonLinkType->id,
            'sort_order' => 0,
            'label' => 'Start Here',
            'url' => '/start-here',
            'variant' => 'primary',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $cluster->id,
            'block_type_id' => $buttonLinkType->id,
            'sort_order' => 1,
            'label' => 'See primitives',
            'url' => '/see-primitives',
            'variant' => 'secondary',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'cluster', 'parent_id' => $card->id]);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'button_link', 'parent_id' => $cluster->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'button_link', 'parent_id' => $cluster->id, 'sort_order' => 1]);
        $this->assertTrue($card->fresh()->canAcceptChildren());
        $this->assertTrue($card->fresh(['blockType'])->canAcceptChildType('cluster'));
        $this->assertTrue($card->fresh(['blockType'])->canAcceptChildType('button_link'));
        $this->assertFalse($card->fresh(['blockType'])->canAcceptChildType('plain_text'));

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $response->assertOk();
        $response->assertSee('Children: 1 item');
        $response->assertSee('Children: 2 items');
        $response->assertSee('data-wb-slot-toggle="'.$card->id.'"', false);
        $response->assertSee('data-wb-slot-block-id="'.$card->id.'"', false);
        $response->assertSee('data-wb-slot-parent-id="'.$card->id.'"', false);
        $response->assertSee('data-base-url="', false);
        $response->assertSee('picker=1', false);
        $response->assertSee('parent_id='.$card->id, false);
        $response->assertSee('Start Here');
        $response->assertSee('See primitives');
    }

    #[Test]
    public function card_rejects_unsupported_child_block_types(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $cardType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id, 'block_type_id' => $headerType->id]))
            ->post(route('admin.blocks.store'), [
                'page_id' => $page->id,
                'slot_type_id' => $main->id,
                'parent_id' => $card->id,
                'block_type_id' => $headerType->id,
                'sort_order' => 0,
                'text' => 'Invalid child',
                'level' => 'h2',
                'status' => 'published',
                '_slot_block_mode' => 'create',
            ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'parent_id' => $card->id, 'block_type_id' => $headerType->id]));
        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('blocks', ['page_id' => $page->id, 'type' => 'header', 'parent_id' => $card->id]);
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
    public function card_store_creates_translated_eyebrow_and_shared_variant_url_and_target(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $cardType->id,
            'sort_order' => 0,
            'eyebrow' => 'Source-visible UI system',
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'subtitle' => 'Optional support line',
            'content' => 'Promo cards should map cleanly to the docs pattern.',
            'action_label' => 'Read more',
            'card_url' => '/getting-started',
            'card_target' => '_blank',
            'card_variant' => 'promo',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'card')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'card',
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'variant' => null,
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'eyebrow' => 'Source-visible UI system',
            'subtitle' => 'Optional support line',
            'content' => 'Promo cards should map cleanly to the docs pattern.',
            'meta' => 'Read more',
        ]);
        $this->assertSame('promo', $block->fresh()->cardVariant());
        $this->assertSame('/getting-started', $block->fresh()->cardUrl());
        $this->assertSame('_blank', $block->fresh()->cardTarget());
    }

    #[Test]
    public function invalid_card_variant_is_rejected(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page] = $this->pageWithSlot($main);
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $cardType->id,
            'sort_order' => 0,
            'title' => 'Promo card',
            'card_variant' => 'hero',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertSessionHasErrors('card_variant');
    }

    #[Test]
    public function moving_a_nested_section_up_only_swaps_with_the_previous_sibling_under_the_same_parent(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page] = $this->pageWithSlot($main);
        $containerType = BlockType::query()->where('slug', 'container')->firstOrFail();
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();

        $container = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $containerType->id,
            'type' => 'container',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $sections = collect(range(0, 4))->map(function (int $index) use ($page, $main, $container, $sectionType) {
            return Block::query()->create([
                'page_id' => $page->id,
                'parent_id' => $container->id,
                'block_type_id' => $sectionType->id,
                'type' => 'section',
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $main->id,
                'sort_order' => $index,
                'status' => 'published',
                'is_system' => false,
                'settings' => json_encode(['layout_name' => 'Section '.($index + 1)], JSON_UNESCAPED_SLASHES),
            ]);
        });

        $target = $sections[3];

        $response = $this->actingAs($user)->post(route('admin.blocks.move-up', $target));

        $response->assertRedirect();

        $orderedIds = Block::query()
            ->where('parent_id', $container->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $this->assertSame([
            $sections[0]->id,
            $sections[1]->id,
            $sections[3]->id,
            $sections[2]->id,
            $sections[4]->id,
        ], $orderedIds);
    }

    #[Test]
    public function moving_a_nested_card_up_swaps_with_the_previous_sibling_inside_the_same_section(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page] = $this->pageWithSlot($main);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $alertType = BlockType::query()->where('slug', 'alert')->firstOrFail();
        $cardType = BlockType::query()->where('slug', 'card')->firstOrFail();

        $section = Block::query()->create([
            'page_id' => $page->id,
            'block_type_id' => $sectionType->id,
            'type' => 'section',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $alert = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'block_type_id' => $alertType->id,
            'type' => 'alert',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
            'settings' => json_encode(['alert_variant' => 'info'], JSON_UNESCAPED_SLASHES),
        ]);

        $card = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'block_type_id' => $cardType->id,
            'type' => 'card',
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
            'settings' => json_encode(['card_variant' => 'default'], JSON_UNESCAPED_SLASHES),
        ]);

        $response = $this->actingAs($user)->post(route('admin.blocks.move-up', $card));

        $response->assertRedirect();

        $orderedIds = Block::query()
            ->where('parent_id', $section->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $this->assertSame([$card->id, $alert->id], $orderedIds);
    }

    #[Test]
    public function content_header_store_creates_translation_backed_fields_and_shared_settings(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $contentHeaderType->id,
            'sort_order' => 0,
            'title' => 'Docs heading',
            'intro_text' => 'Intro copy',
            'meta_items' => ['Updated today', '5 min read', 'API'],
            'title_level' => 'h2',
            'alignment' => 'center',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'content_header')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'content_header',
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'variant' => 'h2',
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Docs heading',
            'subtitle' => 'Intro copy',
            'content' => null,
            'meta' => json_encode(['Updated today', '5 min read', 'API'], JSON_UNESCAPED_SLASHES),
        ]);
        $this->assertSame('center', $block->fresh()->setting('alignment'));
    }

    #[Test]
    public function button_link_store_creates_translated_label_and_shared_url_target_and_variant(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 1);
        [$page, $pageSlot] = $this->pageWithSlot($main);
        $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $buttonLinkType->id,
            'sort_order' => 0,
            'label' => 'Start here',
            'url' => '/start-here',
            'target' => '_blank',
            'variant' => 'secondary',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'button_link')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'type' => 'button_link',
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'variant' => 'secondary',
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Start here',
            'subtitle' => null,
            'content' => null,
        ]);
        $this->assertSame('/start-here', $block->fresh()->setting('url'));
        $this->assertSame('_blank', $block->fresh()->setting('target'));
    }

    #[Test]
    public function cluster_is_seeded_as_published_container_block(): void
    {
        $this->seedFoundation();

        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

        $this->assertSame('published', $clusterType->status);
        $this->assertTrue($clusterType->is_container);
        $this->assertSame('layout', $clusterType->category);
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

        $contentHeaderType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $contentHeaderType->id,
            'sort_order' => 0,
            'title' => 'Docs heading',
            'meta_items' => ['ok', 123],
            'title_level' => 'hero',
            'alignment' => 'diagonal',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertSessionHasErrors(['title_level', 'alignment', 'meta_items.1']);

        $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $buttonLinkType->id,
            'sort_order' => 0,
            'label' => '',
            'url' => 'not-a-link',
            'target' => '_parent',
            'variant' => 'ghost',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertSessionHasErrors(['label', 'url', 'target', 'variant']);

        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $clusterType->id,
            'sort_order' => 0,
            'name' => 'Actions',
            'cluster_gap' => '3',
            'cluster_alignment' => 'between',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertSessionHasErrors(['cluster_gap', 'cluster_alignment']);
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
        $clusterType = BlockType::query()->where('slug', 'cluster')->firstOrFail();
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $buttonLinkType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

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
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $container = Block::query()->where('page_id', $page->id)->where('type', 'container')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $clusterType->id,
            'sort_order' => 0,
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $cluster = Block::query()->where('page_id', $page->id)->where('type', 'cluster')->firstOrFail();

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $cluster->id,
            'block_type_id' => $buttonLinkType->id,
            'sort_order' => 0,
            'label' => 'Primary action',
            'url' => '/primary-action',
            'variant' => 'primary',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

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
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'parent_id' => $container->id,
            'block_type_id' => $plainTextType->id,
            'sort_order' => 1,
            'text' => 'Nested paragraph',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $section->refresh();
        $container->refresh();

        $this->assertNull($section->parent_id);
        $this->assertSame($section->id, $container->parent_id);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'header', 'parent_id' => $container->id]);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'plain_text', 'parent_id' => $container->id]);
        $this->assertDatabaseHas('blocks', ['page_id' => $page->id, 'type' => 'button_link', 'parent_id' => $cluster->id]);
        $this->assertTrue($cluster->fresh()->canAcceptChildren());

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $response->assertOk();
        $response->assertSee('Section');
        $response->assertSee('Container');
        $response->assertSee('Cluster');
        $response->assertSee('Nested title');
        $response->assertSee('Nested paragraph');
        $response->assertSee('Primary action');
        $response->assertDontSee('<th>Order</th>', false);
        $response->assertSee('data-wb-slot-block-row', false);
        $response->assertSee('data-wb-cms-slot-block-tree', false);
        $response->assertSee('data-wb-slot-id="'.$pageSlot->id.'"', false);
        $response->assertSee('data-page-id="'.$page->id.'"', false);
        $response->assertSee('data-slot-type-id="'.$main->id.'"', false);
        $response->assertSee('data-wb-slot-block-id="'.$section->id.'"', false);
        $response->assertSee('data-wb-slot-block-id="'.$container->id.'"', false);
        $response->assertSee('data-wb-slot-block-id="'.$cluster->id.'"', false);
        $response->assertSee('title="Move block up"', false);
        $response->assertSee('title="Move block down"', false);
        $response->assertSee('class="wb-block-row wb-block-row-depth-0"', false);
        $response->assertSee('wb-block-row-depth-1', false);
        $response->assertSee('wb-block-row-depth-2', false);
        $response->assertSee('data-depth="0"', false);
        $response->assertSee('data-depth="1"', false);
        $response->assertSee('data-depth="2"', false);
        $response->assertSee('style="--block-depth: 0;"', false);
        $response->assertSee('style="--block-depth: 1;"', false);
        $response->assertSee('style="--block-depth: 2;"', false);
        $response->assertSee('data-wb-slot-parent-id="'.$section->id.'"', false);
        $response->assertSee('data-wb-slot-toggle="'.$section->id.'"', false);
        $response->assertSee('data-wb-slot-toggle="'.$container->id.'"', false);
        $response->assertSee('data-wb-slot-toggle="'.$cluster->id.'"', false);
        $response->assertSee('class="wb-block-hierarchy-cell"', false);
        $response->assertSee('class="wb-block-hierarchy wb-stack wb-gap-1"', false);
        $response->assertSee('assets/webblocks-cms/css/admin.css', false);
        $response->assertDontSee('site/css/admin.css', false);
        $response->assertSee('assets/webblocks-cms/js/admin/slot-block-tree.js', false);
        $response->assertDontSee('assets/webblocks-cms/js/admin/slot-blocks.js', false);
        $response->assertDontSee('— Cluster', false);
        $response->assertDontSee('>0.1<', false);
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
