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
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LinkListBlockTest extends TestCase
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

    private function slotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function pageWithSlot(): array
    {
        $site = $this->defaultSite();
        $slotType = $this->slotType();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
            ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
        );

        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        return [$page, $pageSlot, $slotType];
    }

    #[Test]
    public function block_type_seeder_creates_link_list_and_link_list_item(): void
    {
        $this->seedFoundation();

        $linkList = BlockType::query()->where('slug', 'link-list')->firstOrFail();
        $linkListItem = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

        $this->assertSame('published', $linkList->status);
        $this->assertSame('published', $linkListItem->status);
        $this->assertTrue($linkList->is_container);
        $this->assertFalse($linkListItem->is_container);
    }

    #[Test]
    public function slot_block_picker_includes_link_list_and_link_list_item(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot] = $this->pageWithSlot();

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1]));

        $response->assertOk();
        $response->assertSee('Link List');
        $response->assertSee('Link List Item');
    }

    #[Test]
    public function admin_can_save_link_list_item_with_translated_copy_and_shared_url(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot, $slotType] = $this->pageWithSlot();
        $linkListType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
        $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

        $linkList = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list',
            'block_type_id' => $linkListType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'parent_id' => $linkList->id,
            'slot_type_id' => $slotType->id,
            'block_type_id' => $linkListItemType->id,
            'sort_order' => 0,
            'title' => 'Getting Started',
            'subtitle' => 'Includes, root attributes, first workflow',
            'content' => 'Use this page first if you need the shortest correct setup path for a real project.',
            'url' => 'getting-started.html',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $item = Block::query()->where('page_id', $page->id)->where('type', 'link-list-item')->firstOrFail();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $linkList->id]));
        $this->assertSame('getting-started.html', $item->fresh()->url);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $item->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Getting Started',
            'subtitle' => 'Includes, root attributes, first workflow',
            'content' => 'Use this page first if you need the shortest correct setup path for a real project.',
        ]);
    }

    #[Test]
    public function public_rendering_matches_wb_link_list_pattern(): void
    {
        $this->seedFoundation();

        [$page, , $slotType] = $this->pageWithSlot();
        $linkListType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
        $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

        $linkList = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list',
            'block_type_id' => $linkListType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $linkList->id,
            'type' => 'link-list-item',
            'block_type_id' => $linkListItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => 'Getting Started',
            'subtitle' => 'Includes, root attributes, first workflow',
            'content' => 'Use this page first if you need the shortest correct setup path for a real project.',
            'url' => 'getting-started.html',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-link-list">', false);
        $response->assertSee('class="wb-link-list-item"', false);
        $response->assertSee('class="wb-link-list-main"', false);
        $response->assertSee('class="wb-link-list-title"', false);
        $response->assertSee('class="wb-link-list-meta"', false);
        $response->assertSee('class="wb-link-list-desc"', false);
        $response->assertSee('href="getting-started.html"', false);
        $response->assertSee('Getting Started');
        $response->assertSee('Includes, root attributes, first workflow');
        $response->assertSee('Use this page first if you need the shortest correct setup path for a real project.');
        $response->assertDontSee('wb-card', false);
    }

    #[Test]
    public function zero_string_values_are_not_dropped_for_link_list_item(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot, $slotType] = $this->pageWithSlot();
        $linkListType = BlockType::query()->where('slug', 'link-list')->firstOrFail();
        $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();

        $linkList = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list',
            'block_type_id' => $linkListType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'parent_id' => $linkList->id,
            'slot_type_id' => $slotType->id,
            'block_type_id' => $linkListItemType->id,
            'sort_order' => 0,
            'title' => '0',
            'subtitle' => '0',
            'content' => '0',
            'url' => 'foundation.html',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ])->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot, 'expanded' => $linkList->id]));

        $item = Block::query()->where('page_id', $page->id)->where('type', 'link-list-item')->firstOrFail();

        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $item->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => '0',
            'subtitle' => '0',
            'content' => '0',
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('href="foundation.html"', false);
        $response->assertSee('<span class="wb-link-list-title">0</span>', false);
        $response->assertSee('<span class="wb-link-list-meta">0</span>', false);
        $response->assertSee('<span class="wb-link-list-desc">0</span>', false);
    }
}
