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
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicHeroBlockRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function hero_block_renders_webblocks_promo_markup(): void
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
            'title' => 'Ship your content model faster',
            'subtitle' => 'Structured publishing',
            'content' => 'Build editorial pages from reusable blocks and predictable layout rules.',
            'variant' => 'centered',
            'status' => 'published',
            'is_system' => true,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $hero->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Get started',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-promo', false);
        $response->assertSee('wb-promo-copy', false);
        $response->assertSee('wb-eyebrow', false);
        $response->assertSee('wb-promo-title', false);
        $response->assertSee('wb-promo-text', false);
        $response->assertSee('wb-promo-actions', false);
        $response->assertSee('wb-text-center', false);
        $response->assertSee('Ship your content model faster');
        $response->assertSee('Structured publishing');
        $response->assertSee('Build editorial pages from reusable blocks and predictable layout rules.');
        $response->assertSee('Get started');
        $response->assertDontSee('wb-public-hero', false);
        $response->assertDontSee('wb-prose', false);
    }

    #[Test]
    public function hero_block_uses_translated_content(): void
    {
        $site = Site::query()->firstOrFail();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $page = $this->pageWithMainSlot($site);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $hero = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'hero',
            'block_type_id' => $this->blockType('hero', 'Hero', 1, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Default hero',
            'subtitle' => 'Default eyebrow',
            'content' => 'Default content',
            'status' => 'published',
            'is_system' => true,
        ]);

        $hero->textTranslations()->create([
            'locale_id' => Locale::query()->where('is_default', true)->value('id'),
            'title' => 'Default hero',
            'subtitle' => 'Default eyebrow',
            'content' => 'Default content',
        ]);
        $hero->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Turkce kahraman',
            'subtitle' => 'Yerel etiket',
            'content' => 'Turkce destekleyici metin',
        ]);

        $response = $this->get('/tr/p/hakkinda');

        $response->assertOk();
        $response->assertSee('Turkce kahraman');
        $response->assertSee('Yerel etiket');
        $response->assertSee('Turkce destekleyici metin');
        $response->assertDontSee('Default hero');
        $response->assertDontSee('Default content');
    }

    #[Test]
    public function hero_block_falls_back_to_legacy_settings_when_canonical_fields_are_empty(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'hero',
            'block_type_id' => $this->blockType('hero', 'Hero', 1, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'settings' => json_encode([
                'label' => 'Legacy eyebrow',
                'headline' => 'Legacy hero title',
                'body' => 'Legacy hero copy',
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Legacy eyebrow');
        $response->assertSee('Legacy hero title');
        $response->assertSee('Legacy hero copy');
    }

    #[Test]
    public function section_default_rendering_remains_stable(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->blockType('section', 'Section', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Standard section',
            'content' => 'Section copy',
            'variant' => 'default',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<section class="wb-section ">', false);
        $response->assertSee('Standard section');
        $response->assertSee('Section copy');
        $response->assertDontSee('wb-promo', false);
    }

    #[Test]
    public function section_promo_variant_renders_promo_only_if_implemented(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->blockType('section', 'Section', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Promo section',
            'content' => 'Section promo copy',
            'variant' => 'promo',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-promo', false);
        $response->assertSee('wb-promo-title', false);
        $response->assertSee('wb-promo-text', false);
    }

    private function pageWithMainSlot(?Site $site = null): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site ??= Site::query()->firstOrFail();

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
