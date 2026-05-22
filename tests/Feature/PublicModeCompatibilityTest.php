<?php

namespace Tests\Feature;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicModeCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cms_public_css_only_contains_small_structural_public_mode_tweaks(): void
    {
        $css = File::get(public_path('cms/css/public.css'));

        $this->assertStringContainsString('.wb-public-footer-fallback {', $css);
        $this->assertStringContainsString('padding-top: 0;', $css);
        $this->assertStringContainsString('.wb-public-footer .wb-footer-cookie-settings-link {', $css);
        $this->assertStringContainsString('padding-inline: 0;', $css);
        $this->assertStringContainsString('min-height: auto;', $css);
        $this->assertStringContainsString('.wb-card-footer > .wb-cluster {', $css);
        $this->assertStringContainsString('width: 100%;', $css);

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}/', $css);
        $this->assertStringNotContainsString('rgb(', $css);
        $this->assertStringNotContainsString('rgba(', $css);
        $this->assertStringNotContainsString('hsl(', $css);
        $this->assertStringNotContainsString('hsla(', $css);
        $this->assertStringNotContainsString('var(--wb-text)', $css);
        $this->assertStringNotContainsString('var(--wb-bg)', $css);
        $this->assertStringNotContainsString('var(--wb-surface)', $css);
        $this->assertStringNotContainsString('var(--wb-border)', $css);
        $this->assertStringNotContainsString('var(--wb-accent)', $css);
    }

    #[Test]
    public function representative_public_page_markup_stays_class_and_token_driven(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $headerType = $this->slotType('header', 'Header', 1);
        $mainType = $this->slotType('main', 'Main', 2);
        $sidebarType = $this->slotType('sidebar', 'Sidebar', 3);
        $footerType = $this->slotType('footer', 'Footer', 4);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => 'home',
            'page_type' => 'default',
            'status' => 'published',
            'settings' => ['public_shell' => 'docs'],
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Home', 'slug' => 'home', 'path' => '/'],
        );

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $headerType->id,
            'sort_order' => 0,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainType->id,
            'sort_order' => 1,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarType->id,
            'sort_order' => 2,
        ]);
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $footerType->id,
            'sort_order' => 3,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'breadcrumb',
            'block_type_id' => $this->blockType('breadcrumb', 'Breadcrumb', 13, true)->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerType->id,
            'sort_order' => 0,
            'settings' => json_encode(['include_current' => true], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header-actions',
            'block_type_id' => $this->blockType('header-actions', 'Header Actions', 14, true)->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerType->id,
            'sort_order' => 1,
            'settings' => json_encode(['show_mode_toggle' => true, 'show_accent_toggle' => true], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $hero = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'hero',
            'block_type_id' => $this->blockType('hero', 'Hero', 20)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'sort_order' => 0,
            'variant' => 'default',
            'status' => 'published',
            'is_system' => false,
        ]);
        $hero->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Mode safe hero',
            'content' => 'Promo content stays token driven.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($hero->fresh(['textTranslations']));

        $contentHeader = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'content_header',
            'block_type_id' => $this->blockType('content_header', 'Content Header', 7)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'sort_order' => 1,
            'status' => 'published',
            'is_system' => false,
        ]);
        $contentHeader->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Content Header',
            'content' => 'Uses shipped content header styling.',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($contentHeader->fresh(['textTranslations']));

        $alert = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'alert',
            'block_type_id' => $this->blockType('alert', 'Alert', 9)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'sort_order' => 2,
            'settings' => json_encode(['variant' => 'warning'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $alert->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Alert title',
            'content' => 'Alert copy',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($alert->fresh(['textTranslations']));

        $card = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'card',
            'block_type_id' => $this->blockType('card', 'Card', 10)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'sort_order' => 3,
            'status' => 'published',
            'is_system' => false,
        ]);
        $card->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Card title',
            'content' => 'Card copy',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($card->fresh(['textTranslations']));

        $stat = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'stat-card',
            'block_type_id' => $this->blockType('stat-card', 'Stat Card', 11)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'sort_order' => 4,
            'status' => 'published',
            'is_system' => false,
        ]);
        $stat->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Metric',
            'subtitle' => '14+',
            'content' => 'Token-driven stat detail',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($stat->fresh(['textTranslations']));

        $linkList = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list',
            'block_type_id' => $this->blockType('link-list', 'Link List', 12)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'sort_order' => 5,
            'status' => 'published',
            'is_system' => false,
        ]);

        $linkItem = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list-item',
            'block_type_id' => $this->blockType('link-list-item', 'Link List Item', 13)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainType->id,
            'parent_id' => $linkList->id,
            'sort_order' => 0,
            'url' => 'https://example.test/docs',
            'status' => 'published',
            'is_system' => false,
        ]);
        $linkItem->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Link title',
            'subtitle' => 'Reference',
            'content' => 'Mode-safe list item',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($linkItem->fresh(['textTranslations']));

        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('class="wb-card wb-promo"', false);
        $response->assertSee('<header class="wb-content-header" data-wb-public-block-type="content-header">', false);
        $response->assertSee('<div class="wb-alert wb-alert-warning">', false);
        $response->assertSee('<article class="wb-card" data-wb-public-block-type="card">', false);
        $response->assertSee('<div class="wb-stat">', false);
        $response->assertSee('<div class="wb-link-list">', false);
        $response->assertSee('data-wb-header-actions', false);
        $response->assertSee('<div class="wb-dashboard-shell">', false);
        $response->assertSee('<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>', false);
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertDontSee('style="color:', false);
        $response->assertDontSee('style="background:', false);
        $response->assertDontSee('style="background-color:', false);
        $response->assertDontSee('style="border-color:', false);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}/', $content);
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
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
