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
        foreach (['header', 'plain_text', 'section', 'container', 'cluster', 'grid', 'content_header', 'button_link', 'card', 'alert'] as $slug) {
            $this->assertTrue(View::exists('pages.partials.blocks.'.$slug));
        }
    }

    #[Test]
    public function alert_renders_title_content_and_variant_class(): void
    {
        $page = $this->pageWithMainSlot();
        $alert = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'alert',
            'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['variant' => 'success'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $alert->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'What this page is proving',
            'content' => 'This page proves docs callouts can ship as first-class blocks.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($alert->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-alert wb-alert-success">', false);
        $response->assertSee('<h3 class="wb-alert-title">What this page is proving</h3>', false);
        $response->assertSee('<p>This page proves docs callouts can ship as first-class blocks.</p>', false);
        $response->assertDontSee('<strong>', false);
        $response->assertDontSee('<div class="wb-alert-title">', false);
    }

    #[Test]
    public function alert_skips_empty_title_and_content_markup(): void
    {
        $page = $this->pageWithMainSlot();

        $titleOnlyAlert = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'alert',
            'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['variant' => 'warning'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $titleOnlyAlert->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Title only alert',
            'content' => '   ',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($titleOnlyAlert->fresh(['textTranslations']));

        $contentOnlyAlert = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'alert',
            'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 1,
            'settings' => json_encode(['variant' => 'info'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $contentOnlyAlert->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => '   ',
            'content' => 'Content only alert.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($contentOnlyAlert->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-alert wb-alert-warning">', false);
        $response->assertSee('<h3 class="wb-alert-title">Title only alert</h3>', false);
        $response->assertDontSee('<p></p>', false);
        $response->assertSee('<div class="wb-alert wb-alert-info">', false);
        $response->assertSee('<p>Content only alert.</p>', false);
        $response->assertDontSee('<h3 class="wb-alert-title"></h3>', false);
    }

    #[Test]
    public function alert_defaults_to_info_variant_and_invalid_variant_falls_back_to_info(): void
    {
        $page = $this->pageWithMainSlot();

        foreach ([null, 'ghost'] as $index => $variant) {
            $alert = Block::query()->create([
                'page_id' => $page->id,
                'type' => 'alert',
                'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $index,
                'settings' => json_encode(array_filter(['variant' => $variant], fn ($value) => $value !== null), JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => false,
            ]);

            $alert->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'title' => 'Alert '.$index,
                'content' => 'Fallback variant should stay info.',
            ]);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($alert->fresh(['textTranslations']));
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'wb-alert wb-alert-info'));
        $response->assertDontSee('wb-alert-success', false);
    }

    #[Test]
    public function grid_renders_child_cards_with_webblocks_grid_and_card_markup(): void
    {
        $page = $this->pageWithMainSlot();
        $grid = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'grid',
            'block_type_id' => $this->blockType('grid', 'Grid', 5)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['columns' => '3', 'gap' => '4'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $grid->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['url' => '/getting-started', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Pattern-first workflow',
            'subtitle' => 'How to build',
            'content' => 'Start from the nearest shipped pattern and trim it to fit the page job.',
            'meta' => 'Read more',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<div class="wb-grid wb-grid-3 wb-gap-4">',
            '<article class="wb-card">',
            '<div class="wb-card-header">How to build</div>',
            '<div class="wb-card-body wb-stack wb-gap-2">',
            '<strong>Pattern-first workflow</strong>',
            '<p class="wb-m-0">Start from the nearest shipped pattern and trim it to fit the page job.</p>',
            '<div class="wb-card-footer">',
            '<a href="/getting-started" class="wb-btn wb-btn-secondary">Read more</a>',
        ], false);
    }

    #[Test]
    public function card_renders_translation_backed_title_and_description(): void
    {
        $page = $this->pageWithMainSlot();
        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'HTML stays HTML',
            'content' => 'You write explicit markup and attach shipped WebBlocks classes.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<article class="wb-card">', false);
        $response->assertSee('<div class="wb-card-body wb-stack wb-gap-2">', false);
        $response->assertSee('<strong>HTML stays HTML</strong>', false);
        $response->assertSee('<p class="wb-m-0">You write explicit markup and attach shipped WebBlocks classes.</p>', false);
    }

    #[Test]
    public function promo_card_renders_webblocks_promo_markup_with_optional_eyebrow(): void
    {
        $page = $this->pageWithMainSlot();
        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['variant' => 'promo'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'eyebrow' => 'Source-visible UI system',
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'subtitle' => 'Docs entry card',
            'content' => 'Use promo cards when the docs entry point should look like a shipped marketing pattern.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<section class="wb-card wb-promo">', false);
        $response->assertSee('<div class="wb-card-body wb-promo-copy wb-stack wb-gap-3">', false);
        $response->assertSee('<p class="wb-eyebrow">Source-visible UI system</p>', false);
        $response->assertSee('<h2 class="wb-promo-title">WebBlocks UI - UI building blocks for humans and AI.</h2>', false);
        $response->assertSee('<p class="wb-promo-text">Use promo cards when the docs entry point should look like a shipped marketing pattern.</p>', false);
        $response->assertDontSee('<article class="wb-card">', false);
    }

    #[Test]
    public function promo_card_renders_child_cluster_inside_promo_actions(): void
    {
        $page = $this->pageWithMainSlot();
        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['variant' => 'promo', 'url' => '/legacy-action', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'eyebrow' => 'Source-visible UI system',
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'content' => 'Card promo actions should stay nested.',
            'meta' => 'Legacy action',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $card->id,
            'type' => 'cluster',
            'block_type_id' => $this->blockType('cluster', 'Cluster', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['alignment' => 'end'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        foreach ([
            ['label' => 'Start Here', 'url' => '/start-here', 'variant' => 'primary', 'sort' => 0],
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
        $response->assertSee('<div class="wb-promo-actions wb-cluster wb-cluster-2">', false);
        $response->assertSee('wb-cluster-end', false);
        $response->assertSee('<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>', false);
        $response->assertSee('<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>', false);
        $response->assertDontSee('<a href="/legacy-action" class="wb-btn wb-btn-secondary">Legacy action</a>', false);
    }

    #[Test]
    public function promo_card_uses_legacy_action_inside_promo_actions_when_no_children_exist(): void
    {
        $page = $this->pageWithMainSlot();
        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['variant' => 'promo', 'url' => '/getting-started', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'eyebrow' => 'Source-visible UI system',
            'title' => 'Pattern-first workflow',
            'content' => 'Use the nearest shipped pattern first.',
            'meta' => 'Read more',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-promo-actions wb-cluster wb-cluster-2">', false);
        $response->assertSee('<a href="/getting-started" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Read more</a>', false);
        $response->assertDontSee('<div class="wb-card-footer">', false);
    }

    #[Test]
    public function invalid_or_missing_card_variant_falls_back_to_default_card_rendering(): void
    {
        $page = $this->pageWithMainSlot();

        foreach ([null, 'ghost'] as $index => $variant) {
            $card = Block::query()->create([
                'page_id' => $page->id,
                'type' => 'card',
                'block_type_id' => $this->blockType('card', 'Card', 8)->id,
                'source_type' => 'static',
                'slot' => 'main',
                'slot_type_id' => $this->mainSlotType()->id,
                'sort_order' => $index,
                'settings' => json_encode(array_filter(['variant' => $variant], fn ($value) => $value !== null), JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => false,
            ]);

            $card->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'title' => 'Default card '.$index,
                'content' => 'Fallback rendering should stay on wb-card.',
            ]);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));
        }

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '<article class="wb-card">'));
        $response->assertDontSee('<section class="wb-card wb-promo">', false);
    }

    #[Test]
    public function card_renders_child_cluster_inside_footer(): void
    {
        $page = $this->pageWithMainSlot();
        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['url' => '/legacy-action', 'target' => '_self'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
            'content' => 'Card footer actions should be nested content.',
            'meta' => 'Legacy action',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $cluster = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $card->id,
            'type' => 'cluster',
            'block_type_id' => $this->blockType('cluster', 'Cluster', 4)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['alignment' => 'end'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        foreach ([
            ['label' => 'Start Here', 'url' => '/start-here', 'variant' => 'primary', 'sort' => 0],
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
            '<article class="wb-card">',
            '<div class="wb-card-body wb-stack wb-gap-2">',
            '<strong>WebBlocks UI - UI building blocks for humans and AI.</strong>',
            '<div class="wb-card-footer">',
            '<a href="/start-here" class="wb-btn wb-btn-primary">Start Here</a>',
            '<a href="/see-primitives" class="wb-btn wb-btn-secondary">See primitives</a>',
            '</div>',
            '</article>',
        ], false);
        $response->assertSee('wb-cluster-end', false);
        $response->assertDontSee('<a href="/legacy-action" class="wb-btn wb-btn-secondary">Legacy action</a>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<div class="wb-card-footer">'));
        $this->assertStringContainsString('.wb-card-footer > .wb-cluster {', file_get_contents(public_path('assets/webblocks-cms/css/public.css')));
        $this->assertStringContainsString('width: 100%;', file_get_contents(public_path('assets/webblocks-cms/css/public.css')));
    }

    #[Test]
    public function card_uses_legacy_action_footer_when_no_child_blocks_exist(): void
    {
        $page = $this->pageWithMainSlot();
        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 8)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
            'settings' => json_encode(['url' => '/getting-started', 'target' => '_blank'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Pattern-first workflow',
            'content' => 'Use the nearest shipped pattern first.',
            'meta' => 'Read more',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<div class="wb-card-footer">', false);
        $response->assertSee('<a href="/getting-started" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Read more</a>', false);
        $response->assertDontSee('<div class="wb-cluster">', false);
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
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => false, 'is_container' => $slug === 'card' || in_array($slug, ['section', 'container', 'cluster', 'grid'], true)],
        );
    }
}
