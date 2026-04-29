<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEditorialBlocksRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function canonical_public_block_renderers_exist_for_current_layout_and_content_blocks(): void
    {
        foreach (['header', 'plain_text', 'section', 'container', 'cluster', 'content_header', 'button_link'] as $slug) {
            $this->assertTrue(View::exists('pages.partials.blocks.'.$slug));
        }
    }

    #[Test]
    public function cluster_renders_button_link_children_without_admin_name_output(): void
    {
        $page = $this->pageWithMainSlot();
        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'cluster',
            'block_type_id' => $this->blockType('cluster', 'Cluster', 5)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Action Row'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        foreach ([
            ['label' => 'Start here', 'url' => '/start-here', 'variant' => 'primary', 'sort' => 0],
            ['label' => 'See primitives', 'url' => '/see-primitives', 'variant' => 'secondary', 'sort' => 1],
        ] as $button) {
            $child = Block::query()->create([
                'page_id' => $page->id,
                'parent_id' => $cluster->id,
                'type' => 'button_link',
                'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $button['sort'],
                'variant' => $button['variant'],
                'settings' => json_encode(['url' => $button['url'], 'target' => '_self'], JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => false,
            ]);

            $child->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'title' => $button['label'],
            ]);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<div class="wb-cluster">',
            '<a href="/start-here" class="wb-btn wb-btn-primary">Start here</a>',
            '<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>',
            '</div>',
        ], false);
        $response->assertDontSee('Action Row');
    }

    #[Test]
    public function cluster_appends_only_verified_gap_and_alignment_classes(): void
    {
        $page = $this->pageWithMainSlot();
        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'cluster',
            'block_type_id' => $this->blockType('cluster', 'Cluster', 5)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['gap' => '4', 'alignment' => 'center'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $child = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $cluster->id,
            'type' => 'button_link',
            'block_type_id' => $this->blockType('button_link', 'Button Link', 7)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'primary',
            'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $child->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Start here',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($child->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-cluster wb-cluster-4 wb-cluster-center">', false);
        $response->assertDontSee('wb-cluster-3', false);
        $response->assertDontSee('wb-cluster-between', false);
    }

    #[Test]
    public function button_link_renders_expected_anchor_markup_and_blank_target_attributes(): void
    {
        $page = $this->pageWithMainSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'button_link',
            'block_type_id' => $this->blockType('button_link', 'Button Link', 6)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'secondary',
            'settings' => json_encode(['url' => '/primitives', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'See primitives',
        ]);
        app(\App\Support\Blocks\BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<a href="/primitives" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">See primitives</a>', false);
        $response->assertDontSee('<div class="wb-btn', false);
    }

    #[Test]
    public function button_link_uses_shared_settings_and_translated_label_per_locale(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $turkish = \App\Models\Locale::query()->updateOrCreate(
            ['code' => 'tr'],
            ['name' => 'Turkish', 'is_default' => false, 'is_enabled' => true],
        );
        $site->locales()->syncWithoutDetaching([$turkish->id]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'page_type' => 'default',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
        );
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $turkish->id],
            ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/p/hakkinda'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'button_link',
            'block_type_id' => $this->blockType('button_link', 'Button Link', 6)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'primary',
            'settings' => json_encode(['url' => '/start-here', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Start here',
        ]);
        $block->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Buradan basla',
        ]);
        app(\App\Support\Blocks\BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $defaultResponse = $this->get('/p/about');
        $turkishResponse = $this->get('/tr/p/hakkinda');

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('<a href="/start-here" class="wb-btn wb-btn-primary">Start here</a>', false);

        $turkishResponse->assertOk();
        $turkishResponse->assertSee('<a href="/start-here" class="wb-btn wb-btn-primary">Buradan basla</a>', false);
    }

    #[Test]
    public function content_header_renders_expected_webblocks_markup_without_extra_wrappers(): void
    {
        $page = $this->pageWithMainSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'content_header',
            'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'settings' => json_encode(['alignment' => 'center'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Docs title',
            'subtitle' => 'Short intro',
            'meta' => json_encode(['Updated today', '5 min read', 'API'], JSON_UNESCAPED_SLASHES),
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<header class="wb-content-header wb-text-center">',
            '<h1 class="wb-content-title">Docs title</h1>',
            '<p class="wb-content-subtitle">Short intro</p>',
            '<div class="wb-content-meta">',
            '<span>Updated today</span>',
            '<span class="wb-content-meta-divider"></span>',
            '<span>5 min read</span>',
            '<span class="wb-content-meta-divider"></span>',
            '<span>API</span>',
            '</div>',
            '</header>',
        ], false);
        $response->assertDontSee('<section class="wb-content-header', false);
        $response->assertDontSee('<div class="wb-content-header', false);
    }

    #[Test]
    public function content_header_skips_optional_intro_and_meta_sections_when_empty(): void
    {
        $page = $this->pageWithMainSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'content_header',
            'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Only title',
            'subtitle' => null,
            'meta' => null,
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<header class="wb-content-header">', false);
        $response->assertSee('<h2 class="wb-content-title">Only title</h2>', false);
        $response->assertDontSee('wb-content-subtitle', false);
        $response->assertDontSee('wb-content-meta', false);
        $response->assertDontSee('wb-content-meta-divider', false);
    }

    #[Test]
    public function content_header_uses_shared_alignment_and_translated_fields_per_locale(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $turkish = \App\Models\Locale::query()->updateOrCreate(
            ['code' => 'tr'],
            ['name' => 'Turkish', 'is_default' => false, 'is_enabled' => true],
        );
        $site->locales()->syncWithoutDetaching([$turkish->id]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'page_type' => 'default',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
        );
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $turkish->id],
            ['site_id' => $site->id, 'name' => 'Hakkinda', 'slug' => 'hakkinda', 'path' => '/p/hakkinda'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'content_header',
            'block_type_id' => $this->blockType('content_header', 'Content Header', 5)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h3',
            'settings' => json_encode(['alignment' => 'right'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'English docs title',
            'subtitle' => 'English intro',
            'meta' => json_encode(['Updated', 'Guide'], JSON_UNESCAPED_SLASHES),
        ]);
        $block->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Turkce baslik',
            'subtitle' => 'Turkce giris',
            'meta' => json_encode(['Guncel', 'Rehber'], JSON_UNESCAPED_SLASHES),
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $defaultResponse = $this->get('/p/about');
        $turkishResponse = $this->get('/tr/p/hakkinda');

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('<header class="wb-content-header wb-text-right">', false);
        $defaultResponse->assertSee('<h3 class="wb-content-title">English docs title</h3>', false);
        $defaultResponse->assertSee('<p class="wb-content-subtitle">English intro</p>', false);
        $defaultResponse->assertSee('<span>Updated</span>', false);
        $defaultResponse->assertSee('<span>Guide</span>', false);

        $turkishResponse->assertOk();
        $turkishResponse->assertSee('<header class="wb-content-header wb-text-right">', false);
        $turkishResponse->assertSee('<h3 class="wb-content-title">Turkce baslik</h3>', false);
        $turkishResponse->assertSee('<p class="wb-content-subtitle">Turkce giris</p>', false);
        $turkishResponse->assertSee('<span>Guncel</span>', false);
        $turkishResponse->assertSee('<span>Rehber</span>', false);
    }

    #[Test]
    public function section_and_container_render_nested_header_and_plain_text_structure(): void
    {
        $page = $this->pageWithMainSlot();
        $section = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->blockType('section', 'Section', 3)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Hero area'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $container = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'container',
            'block_type_id' => $this->blockType('container', 'Container', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Hero content'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $header = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'header',
            'block_type_id' => $this->blockType('header', 'Header', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);
        $header->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Nested heading',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

        $plainText = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'plain_text',
            'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
        ]);
        $plainText->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Nested paragraph',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($plainText->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<section class="wb-section">',
            '<div class="wb-container">',
            '<h1>Nested heading</h1>',
            '<p>Nested paragraph</p>',
            '</div>',
            '</section>',
        ], false);
        $response->assertDontSee('Hero area');
        $response->assertDontSee('Hero content');
    }

    #[Test]
    public function public_rendering_only_uses_whitelisted_appearance_classes(): void
    {
        $page = $this->pageWithMainSlot();
        $section = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $this->blockType('section', 'Section', 3)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['layout_name' => 'Feature zone', 'spacing' => 'lg', 'background' => 'muted'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $container = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'container',
            'block_type_id' => $this->blockType('container', 'Container', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['width' => 'xl', 'alignment' => 'center', 'arbitrary' => 'wb-made-up'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $header = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'header',
            'block_type_id' => $this->blockType('header', 'Header', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'settings' => json_encode(['alignment' => 'center', 'class' => 'wb-content-title'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $header->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Centered heading',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

        $plainText = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $container->id,
            'type' => 'plain_text',
            'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 1,
            'settings' => json_encode(['alignment' => 'right', 'class' => 'wb-content-subtitle'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $plainText->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Aligned paragraph',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($plainText->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<section class="wb-section wb-section-lg">', false);
        $response->assertSee('<div class="wb-container wb-container-xl">', false);
        $response->assertSee('<h2 class="wb-text-center">Centered heading</h2>', false);
        $response->assertSee('<p class="wb-text-right">Aligned paragraph</p>', false);
        $response->assertDontSee('wb-bg-muted', false);
        $response->assertDontSee('wb-content-title', false);
        $response->assertDontSee('wb-content-subtitle', false);
        $response->assertDontSee('wb-made-up', false);
    }

    #[Test]
    public function header_block_renders_selected_heading_level_with_escaped_translated_text(): void
    {
        $page = $this->pageWithMainSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $this->blockType('header', 'Header', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h3',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Title <script>alert(1)</script>',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<h3>Title &lt;script&gt;alert(1)&lt;/script&gt;</h3>', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function multilingual_text_rendering_is_unchanged_when_shared_settings_are_present(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $french = \App\Models\Locale::query()->updateOrCreate(
            ['code' => 'fr'],
            ['name' => 'French', 'is_default' => false, 'is_enabled' => true],
        );
        $site->locales()->syncWithoutDetaching([$french->id]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'page_type' => 'default',
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

        $header = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $this->blockType('header', 'Header', 1)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'settings' => json_encode(['alignment' => 'center'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $header->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'English title',
        ]);
        $header->textTranslations()->create([
            'locale_id' => $french->id,
            'title' => 'Titre francais',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));

        $defaultResponse = $this->get(route('pages.show', 'about'));
        $frenchResponse = $this->get('/fr/p/a-propos');

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('<h2 class="wb-text-center">English title</h2>', false);
        $frenchResponse->assertOk();
        $frenchResponse->assertSee('<h2 class="wb-text-center">Titre francais</h2>', false);
    }

    #[Test]
    public function plain_text_block_renders_plain_paragraph_with_escaped_translated_text(): void
    {
        $page = $this->pageWithMainSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $this->blockType('plain_text', 'Plain Text', 2)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Paragraph <strong>copy</strong>',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<p>Paragraph &lt;strong&gt;copy&lt;/strong&gt;</p>', false);
        $response->assertDontSee('<strong>copy</strong>', false);
    }

    private function pageWithMainSlot(string $title = 'About', string $slug = 'about'): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site = Site::query()->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'page_type' => 'default',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => $title, 'slug' => $slug, 'path' => '/p/'.$slug],
        );

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
