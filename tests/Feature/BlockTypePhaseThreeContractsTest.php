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
use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\BlockTypes\BlockTypeContractRegistry;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockTypePhaseThreeContractsTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
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

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        return [$page, $slotType];
    }

    #[Test]
    public function code_and_table_are_registered_as_text_translatable_block_types(): void
    {
        $this->seedFoundation();

        $registry = app(BlockTranslationRegistry::class);

        $this->assertSame('text', $registry->familyFor('code'));
        $this->assertSame('text', $registry->familyFor('table'));
    }

    #[Test]
    public function public_code_block_uses_translated_fields_with_shared_language_setting(): void
    {
        $this->seedFoundation();

        [$page, $slotType] = $this->pageWithSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'code',
            'block_type_id' => BlockType::query()->where('slug', 'code')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'settings' => json_encode(['language' => 'php'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Config bootstrap',
            'subtitle' => 'bootstrap.php',
            'content' => '<?php echo $safe; ?>',
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('data-language="php"', false)
            ->assertSee('&lt;?php echo $safe; ?&gt;', false);
    }

    #[Test]
    public function public_table_block_uses_translated_title_and_rows(): void
    {
        $this->seedFoundation();

        [$page, $slotType] = $this->pageWithSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'table',
            'block_type_id' => BlockType::query()->where('slug', 'table')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'variant' => 'header-row',
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Plans',
            'content' => "Plan | Seats\nStarter | 3",
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('<h3>Plans</h3>', false)
            ->assertSee('<th>Plan</th>', false)
            ->assertSee('<td>Starter</td>', false);
    }

    #[Test]
    public function public_link_list_renders_translated_intro_copy_and_items(): void
    {
        $this->seedFoundation();

        [$page, $slotType] = $this->pageWithSlot();
        $list = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list',
            'block_type_id' => BlockType::query()->where('slug', 'link-list')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $list->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Getting Started',
            'subtitle' => 'Docs',
            'content' => 'Use this section first.',
        ]);
        $item = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $list->id,
            'type' => 'link-list-item',
            'block_type_id' => BlockType::query()->where('slug', 'link-list-item')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'url' => '/getting-started',
            'status' => 'published',
            'is_system' => false,
        ]);
        $item->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Overview',
            'subtitle' => 'Setup',
            'content' => 'Shortest correct setup path.',
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('>Docs<', false)
            ->assertSee('<h2>Getting Started</h2>', false)
            ->assertSee('Use this section first.')
            ->assertSee('href="/getting-started"', false)
            ->assertSee('Overview');
    }

    #[Test]
    public function phase_three_contracts_report_the_resolved_block_gaps(): void
    {
        $this->seedFoundation();

        $contracts = app(BlockTypeContractRegistry::class);

        $code = $contracts->resolve('code');
        $table = $contracts->resolve('table');
        $image = $contracts->resolve('image');
        $gallery = $contracts->resolve('gallery');
        $download = $contracts->resolve('download');
        $file = $contracts->resolve('file');
        $video = $contracts->resolve('video');
        $audio = $contracts->resolve('audio');
        $quote = $contracts->resolve('quote');
        $toc = $contracts->resolve('toc');
        $html = $contracts->resolve('html');
        $navbar = $contracts->resolve('sticky-navbar');
        $navbarBrand = $contracts->resolve('navbar-brand');
        $sidebarBrand = $contracts->resolve('sidebar-brand');
        $sidebarNavItem = $contracts->resolve('sidebar-nav-item');
        $sidebarNavGroup = $contracts->resolve('sidebar-nav-group');
        $breadcrumb = $contracts->resolve('breadcrumb');
        $statCard = $contracts->resolve('stat-card');
        $linkList = $contracts->resolve('link-list');

        $this->assertSame(['title', 'subtitle', 'content'], $code->translatableFields);
        $this->assertSame('clear', $code->currentContractStatus);
        $this->assertSame([], $code->knownGaps);
        $this->assertSame(['title', 'content'], $table->translatableFields);
        $this->assertSame('mostly clear', $table->currentContractStatus);
        $this->assertSame(['Renderer still supports a legacy settings fallback path for rows.'], $table->knownGaps);
        $this->assertSame(['title', 'subtitle'], $image->translatableFields);
        $this->assertSame(['media_id', 'url'], $image->sharedSettingsFields);
        $this->assertSame('clear', $image->currentContractStatus);
        $this->assertSame(['title', 'subtitle'], $gallery->translatableFields);
        $this->assertSame('transitional', $gallery->currentContractStatus);
        $this->assertSame(['Public rendering still preserves legacy settings-based gallery items when no canonical block_media rows exist.'], $gallery->knownGaps);
        $this->assertSame(['title', 'subtitle'], $download->translatableFields);
        $this->assertSame('clear', $download->currentContractStatus);
        $this->assertSame(['title', 'content'], $file->translatableFields);
        $this->assertSame('clear', $file->currentContractStatus);
        $this->assertSame(['title', 'content'], $video->translatableFields);
        $this->assertSame('clear', $video->currentContractStatus);
        $this->assertSame(['title', 'content'], $audio->translatableFields);
        $this->assertSame('clear', $audio->currentContractStatus);
        $this->assertSame('clear', $quote->currentContractStatus);
        $this->assertSame([], $quote->knownGaps);
        $this->assertSame('clear', $toc->currentContractStatus);
        $this->assertSame([], $toc->knownGaps);
        $this->assertSame('mostly clear', $html->currentContractStatus);
        $this->assertSame(['Trusted markup can also affect shared overlay or body-end output beyond the visible root.'], $html->knownGaps);
        $this->assertSame('clear', $navbar->currentContractStatus);
        $this->assertTrue($navbar->ownsPublicRootHelper);
        $this->assertSame('clear', $navbarBrand->currentContractStatus);
        $this->assertSame([], $navbarBrand->knownGaps);
        $this->assertSame('clear', $sidebarBrand->currentContractStatus);
        $this->assertSame([], $sidebarBrand->knownGaps);
        $this->assertSame(['settings.url', 'settings.target', 'settings.aria_label'], $sidebarBrand->sharedSettingsFields);
        $this->assertSame('clear', $sidebarNavItem->currentContractStatus);
        $this->assertSame('clear', $sidebarNavGroup->currentContractStatus);
        $this->assertSame([], $sidebarNavGroup->knownGaps);
        $this->assertSame('clear', $breadcrumb->currentContractStatus);
        $this->assertSame([], $breadcrumb->knownGaps);
        $this->assertSame('clear', $statCard->currentContractStatus);
        $this->assertSame([], $statCard->knownGaps);
        $this->assertSame('clear', $linkList->currentContractStatus);
        $this->assertSame([], $linkList->knownGaps);
    }

    #[Test]
    public function sticky_navbar_owns_its_public_root_using_the_persisted_slug(): void
    {
        $this->seedFoundation();

        $navbarType = BlockType::query()->where('slug', 'sticky-navbar')->firstOrFail();
        $navbar = new Block(['type' => 'sticky-navbar', 'block_type_id' => $navbarType->id]);
        $navbar->setRelation('blockType', $navbarType);

        $this->assertTrue($navbar->ownsPublicRoot());
    }

    #[Test]
    public function deferred_non_container_blocks_do_not_accept_new_children(): void
    {
        $this->seedFoundation();

        foreach (['image', 'gallery', 'download', 'file', 'video', 'audio', 'code', 'table', 'quote', 'toc', 'html'] as $slug) {
            $blockType = BlockType::query()->where('slug', $slug)->firstOrFail();
            $block = new Block(['type' => $slug, 'block_type_id' => $blockType->id]);
            $block->setRelation('blockType', $blockType);

            $this->assertFalse($block->canAcceptChildren(), $slug.' should not accept child blocks.');
            $this->assertNull($block->allowedChildTypeSlugs(), $slug.' should not expose normal child whitelists.');
        }
    }

    #[Test]
    public function media_visual_block_types_are_registered_for_phase_three_audit_output(): void
    {
        $this->seedFoundation();

        foreach (['image', 'gallery', 'download', 'file', 'video', 'audio'] as $slug) {
            $blockType = BlockType::query()->where('slug', $slug)->first();

            $this->assertNotNull($blockType, $slug.' should exist in the synced block type catalog.');
            $this->assertSame('published', $blockType->status);
            $this->assertSame('content', $blockType->category);
        }
    }
}
