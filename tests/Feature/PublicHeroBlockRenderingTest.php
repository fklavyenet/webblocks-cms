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
            'variant' => 'soft',
            'settings' => json_encode(['layout' => 'centered'], JSON_UNESCAPED_SLASHES),
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
        $response->assertSee('wb-card', false);
        $response->assertSee('wb-card-muted', false);
        $response->assertSee('wb-text-center', false);
        $response->assertSee('Ship your content model faster');
        $response->assertSee('Structured publishing');
        $response->assertSee('Build editorial pages from reusable blocks and predictable layout rules.');
        $response->assertSee('Get started');
        $response->assertDontSee('wb-public-hero', false);
        $response->assertDontSee('wb-prose', false);
    }

    #[Test]
    public function hero_block_respects_title_tag_setting(): void
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
            'title' => 'Nested hero title',
            'content' => 'Nested hero content',
            'settings' => json_encode(['title_tag' => 'h2'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<h2 class="wb-promo-title">Nested hero title</h2>', false);
        $response->assertDontSee('<h1 class="wb-promo-title">Nested hero title</h1>', false);
    }

    #[Test]
    public function hero_block_falls_back_to_default_content_when_legacy_block_type_is_not_translatable(): void
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

        $cta = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $hero->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Default CTA',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);
        $cta->buttonTranslations()->create([
            'locale_id' => Locale::query()->where('is_default', true)->value('id'),
            'title' => 'Default CTA',
        ]);
        $cta->buttonTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Turkce CTA',
        ]);

        $response = $this->get('/tr/p/hakkinda');

        $response->assertOk();
        $response->assertSee('Default hero');
        $response->assertSee('Default eyebrow');
        $response->assertSee('Default content');
        $response->assertSee('Turkce CTA');
        $response->assertDontSee('Turkce kahraman');
        $response->assertDontSee('Yerel etiket');
        $response->assertDontSee('Turkce destekleyici metin');
        $response->assertDontSee('Default CTA');
    }

    #[Test]
    public function hero_block_renders_a_single_cta_when_only_one_button_is_complete(): void
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

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $hero->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Primary action',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Primary action');
        $response->assertDontSee('Secondary action');
    }

    #[Test]
    public function hero_block_renders_two_ctas_inline(): void
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
            ['label' => 'Primary action', 'url' => '/cta-0', 'variant' => 'primary', 'sort_order' => 0],
            ['label' => 'Secondary action', 'url' => '/cta-1', 'variant' => 'secondary', 'sort_order' => 1],
        ] as $button) {
            Block::query()->create([
                'page_id' => $page->id,
                'parent_id' => $hero->id,
                'type' => 'button',
                'block_type_id' => $this->blockType('button', 'Button', 2)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $button['sort_order'],
                'title' => $button['label'],
                'url' => $button['url'],
                'variant' => $button['variant'],
                'status' => 'published',
                'is_system' => false,
            ]);
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-promo-actions wb-cluster wb-cluster-2', false);
        $response->assertSee('Primary action');
        $response->assertSee('Secondary action');
    }

    #[Test]
    public function hero_block_does_not_render_empty_buttons(): void
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

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $hero->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Missing URL',
            'url' => null,
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $hero->id,
            'type' => 'button',
            'block_type_id' => $this->blockType('button', 'Button', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 1,
            'title' => null,
            'url' => '/cta-2',
            'variant' => 'secondary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertDontSee('Missing URL');
        $response->assertDontSee('wb-promo-actions', false);
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
    public function hero_block_works_in_multisite_context_without_environment_specific_content(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $site->locales()->syncWithoutDetaching([
            Page::defaultLocaleId() => ['is_enabled' => true],
        ]);

        $page = $this->pageWithMainSlot($site, 'Landing', 'landing');
        $hero = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'hero',
            'block_type_id' => $this->blockType('hero', 'Hero', 1, true)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Campaign hero',
            'content' => 'No local environment values are embedded here.',
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
            'title' => 'Explore',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get('http://campaign.example.test/p/landing');

        $response->assertOk();
        $response->assertSee('Campaign hero');
        $response->assertSee('Explore');
        $response->assertDontSee('.ddev.site');
    }

    #[Test]
    public function section_default_rendering_remains_stable(): void
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
            'status' => 'published',
            'is_system' => false,
        ]);
        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'plain_text',
            'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'content' => 'Section copy',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<section class="wb-section">', false);
        $response->assertSee('Section copy');
        $response->assertDontSee('wb-promo', false);
    }

    #[Test]
    public function section_renders_only_layout_wrapper_without_legacy_promo_markup(): void
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
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<section class="wb-section">', false);
        $response->assertSee('Section CTA');
        $response->assertDontSee('wb-promo', false);
        $response->assertDontSee('wb-promo-title', false);
        $response->assertDontSee('wb-promo-text', false);
    }

    #[Test]
    public function quote_testimonial_variant_renders_correctly(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'quote',
            'block_type_id' => $this->blockType('quote', 'Quote', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'A. Editor',
            'subtitle' => 'Content Team',
            'content' => 'This workflow keeps publishing tidy.',
            'variant' => 'testimonial',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-card wb-card-muted', false);
        $response->assertSee('<blockquote class="wb-stack wb-gap-2">', false);
        $response->assertSee('A. Editor | Content Team');
    }

    #[Test]
    public function testimonial_block_uses_the_quote_testimonial_variant(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'testimonial',
            'block_type_id' => $this->blockType('testimonial', 'Testimonial', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'A. Editor',
            'subtitle' => 'Content Team',
            'content' => 'Quote-style testimonial rendering is enough here.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-card wb-card-muted', false);
        $response->assertSee('Quote-style testimonial rendering is enough here.');
        $response->assertSee('A. Editor | Content Team');
    }

    #[Test]
    public function feature_grid_delegates_to_the_columns_cards_pattern(): void
    {
        $page = $this->pageWithMainSlot();
        $featureGrid = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'feature-grid',
            'block_type_id' => $this->blockType('feature-grid', 'Feature Grid', 3)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Core features',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $featureGrid->id,
            'type' => 'column_item',
            'block_type_id' => $this->blockType('column_item', 'Column Item', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Use the same card pattern as columns.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-card-body wb-stack wb-gap-2', false);
        $response->assertSee('Fast setup');
    }

    #[Test]
    public function feature_grid_renders_feature_item_children_with_the_columns_cards_pattern(): void
    {
        $page = $this->pageWithMainSlot();
        $featureGrid = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'feature-grid',
            'block_type_id' => $this->blockType('feature-grid', 'Feature Grid', 3)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Core features',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $featureGrid->id,
            'type' => 'feature-item',
            'block_type_id' => $this->blockType('feature-item', 'Feature Item', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Editorial control',
            'content' => 'Feature items use the same card shell.',
            'url' => '/p/features',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Editorial control');
        $response->assertSee('Feature items use the same card shell.');
        $response->assertSee('href="/p/features"', false);
    }

    #[Test]
    public function cta_block_renders_promo_markup_with_managed_buttons(): void
    {
        $page = $this->pageWithMainSlot();
        $cta = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'cta',
            'block_type_id' => $this->blockType('cta', 'CTA', 3)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Try WebBlocks CMS',
            'subtitle' => 'Ready to ship',
            'content' => 'Launch a reusable marketing section with managed actions.',
            'variant' => 'accent',
            'status' => 'published',
            'is_system' => false,
        ]);

        foreach ([
            ['label' => 'Get started', 'url' => '/cta-0', 'variant' => 'primary', 'sort_order' => 0],
            ['label' => 'Read docs', 'url' => '/cta-1', 'variant' => 'secondary', 'sort_order' => 1],
        ] as $button) {
            Block::query()->create([
                'page_id' => $page->id,
                'parent_id' => $cta->id,
                'type' => 'button',
                'block_type_id' => $this->blockType('button', 'Button', 2)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $button['sort_order'],
                'title' => $button['label'],
                'url' => $button['url'],
                'variant' => $button['variant'],
                'status' => 'published',
                'is_system' => false,
            ]);
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-promo', false);
        $response->assertSee('wb-card-accent', false);
        $response->assertSee('Ready to ship');
        $response->assertSee('Try WebBlocks CMS');
        $response->assertSee('Launch a reusable marketing section with managed actions.');
        $response->assertSee('Get started');
        $response->assertSee('Read docs');
    }

    #[Test]
    public function card_grid_matches_the_columns_cards_structure(): void
    {
        $page = $this->pageWithMainSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card-grid',
            'block_type_id' => $this->blockType('card-grid', 'Card Grid', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'title' => 'Legacy cards',
            'settings' => json_encode([
                'items' => [
                    ['title' => 'Structured', 'content' => 'Uses the same core card shell.'],
                    ['title' => 'Reusable', 'content' => 'No separate legacy grid markup.'],
                ],
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-grid wb-grid-2', false);
        $response->assertSee('wb-card-body wb-stack wb-gap-2', false);
        $response->assertSee('Structured');
        $response->assertSee('Reusable');
    }

    private function pageWithMainSlot(?Site $site = null, string $title = 'About', string $slug = 'about'): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site ??= Site::query()->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
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
