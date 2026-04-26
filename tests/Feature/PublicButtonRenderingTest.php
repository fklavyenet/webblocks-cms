<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicButtonRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function button_block_maps_primary_variant_to_webblocks_button(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Start here',
            'url' => '/start',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<a href="/start"', false);
        $response->assertSee('wb-btn', false);
        $response->assertSee('wb-btn-primary', false);
        $response->assertSee('Start here');
    }

    #[Test]
    public function button_block_maps_secondary_variant(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Read more',
            'url' => '/read-more',
            'variant' => 'secondary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-btn-secondary', false);
    }

    #[Test]
    public function button_block_falls_back_safely_for_unknown_variant(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Fallback CTA',
            'url' => '/fallback',
            'variant' => 'neon',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<a href="/fallback"', false);
        $response->assertSee('wb-btn-primary', false);
        $response->assertDontSee('neon', false);
    }

    #[Test]
    public function hero_child_buttons_render_inside_promo_actions(): void
    {
        $page = $this->pageWithMainSlot();
        $hero = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'hero',
            'block_type_id' => $this->blockType('hero', 'Hero', 1, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Hero title',
            'content' => 'Hero content',
            'status' => 'published',
            'is_system' => true,
        ]);

        foreach ([
            ['label' => 'Primary action', 'variant' => 'primary', 'sort' => 0],
            ['label' => 'Secondary action', 'variant' => 'secondary', 'sort' => 1],
        ] as $button) {
            Block::query()->create([
                'page_id' => $page->id,
                'parent_id' => $hero->id,
                'type' => 'button',
                'block_type_id' => $this->blockType('button', 'Button', 2)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $button['sort'],
                'title' => $button['label'],
                'url' => '/cta-'.$button['sort'],
                'variant' => $button['variant'],
                'status' => 'published',
                'is_system' => false,
            ]);
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-promo-actions', false);
        $response->assertSee('Primary action');
        $response->assertSee('Secondary action');
        $response->assertSee('wb-btn-primary', false);
        $response->assertSee('wb-btn-secondary', false);
    }

    #[Test]
    public function promo_section_child_buttons_render_inside_promo_actions(): void
    {
        $page = $this->pageWithMainSlot();
        $section = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->blockType('section', 'Section', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Promo section',
            'content' => 'Section copy',
            'variant' => 'promo',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Section CTA',
            'url' => '/section-cta',
            'variant' => 'outline',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-promo-actions', false);
        $response->assertSee('Section CTA');
        $response->assertSee('wb-btn-outline', false);
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

    private function blockType(string $slug, string $name, int $sortOrder, bool $isSystem = false): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => $isSystem],
        );
    }
}
