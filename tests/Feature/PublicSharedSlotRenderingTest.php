<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\SharedSlotBlock;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\Pages\PublicPagePresenter;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicSharedSlotRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function existing_page_owned_slot_rendering_still_works(): void
    {
        $context = $this->publishedPageWithSharedSlotSource([
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'shared_slot_id' => null,
        ]);
        $slotBlockCountBefore = SharedSlotBlock::query()->count();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertSee('Page Header Content', false);
        $this->assertSame($slotBlockCountBefore, SharedSlotBlock::query()->count());
        $this->assertCount(1, $context['pageSlot']->fresh()->blocks()->get());
    }

    #[Test]
    public function shared_slot_renders_inside_the_existing_slot_wrapper(): void
    {
        $this->publishedPageWithSharedSlotSource();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertSee('Shared Header Title', false);
        $response->assertSee('Shared Header Child', false);
        $response->assertDontSee('Page Header Content', false);
        $response->assertDontSee('<main data-wb-slot="header"', false);
    }

    #[Test]
    public function shared_slot_replaces_page_owned_content_without_copying_blocks(): void
    {
        $context = $this->publishedPageWithSharedSlotSource();

        $pageBlockCountBefore = Block::query()->where('page_id', $context['page']->id)->count();
        $slotBlockCountBefore = SharedSlotBlock::query()->count();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Shared Header Title', false);
        $response->assertDontSee('Page Header Content', false);

        $this->assertSame($pageBlockCountBefore, Block::query()->where('page_id', $context['page']->id)->count());
        $this->assertSame($slotBlockCountBefore, SharedSlotBlock::query()->count());
        $this->assertCount(0, $context['pageSlot']->fresh()->blocks()->get());
        $this->assertDatabaseHas('blocks', ['id' => $context['pageHeader']->id]);
    }

    #[Test]
    public function disabled_slot_remains_empty(): void
    {
        $this->publishedPageWithSharedSlotSource([
            'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
            'shared_slot_id' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertDontSee('Page Header Content', false);
        $response->assertDontSee('Shared Header Title', false);
    }

    #[Test]
    public function legacy_fallback_source_types_remain_page_owned_for_public_rendering(): void
    {
        $context = $this->publishedPageWithSharedSlotSource([
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'shared_slot_id' => null,
        ]);

        $basePage = $context['page']->fresh()->load(['slots.slotType', 'blocks']);
        $headerSlot = $basePage->slots->firstWhere('slotType.slug', 'header');

        $missingSourceTypeSlot = new PageSlot;
        $missingSourceTypeSlot->setRawAttributes(array_diff_key($headerSlot->getAttributes(), ['source_type' => true]), true);
        $missingSourceTypeSlot->setRelation('slotType', $headerSlot->slotType);
        $missingSourceTypeSlot->setRelation('page', $basePage);

        $nullSourceTypeSlot = new PageSlot;
        $nullAttributes = $headerSlot->getAttributes();
        $nullAttributes['source_type'] = null;
        $nullSourceTypeSlot->setRawAttributes($nullAttributes, true);
        $nullSourceTypeSlot->setRelation('slotType', $headerSlot->slotType);
        $nullSourceTypeSlot->setRelation('page', $basePage);

        $emptySourceTypeSlot = new PageSlot;
        $emptyAttributes = $headerSlot->getAttributes();
        $emptyAttributes['source_type'] = '';
        $emptySourceTypeSlot->setRawAttributes($emptyAttributes, true);
        $emptySourceTypeSlot->setRelation('slotType', $headerSlot->slotType);
        $emptySourceTypeSlot->setRelation('page', $basePage);

        foreach ([$missingSourceTypeSlot, $nullSourceTypeSlot, $emptySourceTypeSlot] as $slot) {
            $this->assertTrue($slot->usesPageOwnedBlocks());
            $presented = app(PublicPagePresenter::class)->present(tap(clone $basePage, function (Page $page) use ($slot, $basePage): void {
                $page->setRelation('slots', $basePage->slots->map(fn (PageSlot $candidate) => $candidate->id === $slot->id ? $slot : $candidate));
            }));

            $header = collect($presented['slots'])->firstWhere('slug', 'header');

            $this->assertNotNull($header);
            $this->assertCount(1, $header['blocks']);
            $this->assertSame('Page Header Content', $header['blocks'][0]->title);
        }
    }

    #[Test]
    public function cross_site_shared_slot_assignments_render_no_shared_content(): void
    {
        $context = $this->publishedPageWithSharedSlotSource();
        $otherSite = Site::query()->create([
            'name' => 'Other Site',
            'handle' => 'other-site',
            'domain' => 'other.example.test',
            'is_primary' => false,
        ]);

        $context['sharedSlot']->update(['site_id' => $otherSite->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<nav data-wb-slot="header" class="wb-navbar wb-navbar-glass wb-w-full">', false);
        $response->assertDontSee('Shared Header Title', false);
        $response->assertDontSee('Page Header Content', false);
    }

    #[Test]
    public function shared_slot_public_shell_must_match_when_specified_and_null_shell_is_generic(): void
    {
        $matching = $this->publishedPageWithSharedSlotSource([
            'page_shell' => 'docs',
            'shared_public_shell' => 'docs',
        ]);

        $this->get('/')->assertOk()->assertSee('Shared Header Title', false);

        $mismatch = $this->publishedPageWithSharedSlotSource([
            'page_slug' => 'mismatch-shell',
            'page_shell' => 'default',
            'shared_public_shell' => 'docs',
        ]);

        $this->get(route('pages.show', 'mismatch-shell'))->assertOk()->assertDontSee('Shared Header Title', false);

        $generic = $this->publishedPageWithSharedSlotSource([
            'page_slug' => 'generic-shell',
            'page_shell' => 'default',
            'shared_public_shell_provided' => true,
            'shared_public_shell' => null,
        ]);

        $this->get(route('pages.show', 'generic-shell'))->assertOk()->assertSee('Shared Header Title', false);
        $this->assertSame('docs', $matching['sharedSlot']->fresh()->public_shell);
        $this->assertSame('docs', $mismatch['sharedSlot']->fresh()->public_shell);
        $this->assertNull($generic['sharedSlot']->fresh()->public_shell);
    }

    #[Test]
    public function shared_slot_name_must_match_when_specified_and_null_name_is_generic(): void
    {
        $matching = $this->publishedPageWithSharedSlotSource([
            'shared_slot_name' => 'header',
        ]);

        $this->get('/')->assertOk()->assertSee('Shared Header Title', false);

        $mismatch = $this->publishedPageWithSharedSlotSource([
            'page_slug' => 'mismatch-slot',
            'shared_slot_name' => 'sidebar',
        ]);

        $this->get(route('pages.show', 'mismatch-slot'))->assertOk()->assertDontSee('Shared Header Title', false);

        $generic = $this->publishedPageWithSharedSlotSource([
            'page_slug' => 'generic-slot',
            'shared_slot_name_provided' => true,
            'shared_slot_name' => null,
        ]);

        $this->get(route('pages.show', 'generic-slot'))->assertOk()->assertSee('Shared Header Title', false);
        $this->assertSame('header', $matching['sharedSlot']->fresh()->slot_name);
        $this->assertSame('sidebar', $mismatch['sharedSlot']->fresh()->slot_name);
        $this->assertSame('', (string) $generic['sharedSlot']->fresh()->slot_name);
    }

    #[Test]
    public function nested_shared_slot_block_tree_renders_in_the_same_structure_style(): void
    {
        $this->publishedPageWithSharedSlotSource();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder([
            '<section class="wb-section wb-stack" data-wb-public-block-type="section">',
            '<div class="wb-container wb-stack" data-wb-public-block-type="container">',
            '<h2 data-wb-public-block-type="header">Shared Header Title</h2>',
            '<div class="wb-cluster" data-wb-public-block-type="cluster">',
            '<p>Shared Header Child</p>',
        ], false);
    }

    #[Test]
    public function shared_slot_translations_follow_the_existing_public_translation_rules(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $french = Locale::query()->create([
            'code' => 'fr',
            'name' => 'French',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([
            $french->id => ['is_enabled' => true],
        ]);

        $context = $this->publishedPageWithSharedSlotSource([
            'site' => $site,
        ]);

        $context['sharedTitle']->textTranslations()->create([
            'locale_id' => $french->id,
            'title' => 'En-tete partage',
        ]);
        $context['sharedLeaf']->textTranslations()->create([
            'locale_id' => $french->id,
            'content' => 'Contenu partage enfant',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($context['sharedTitle']->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($context['sharedLeaf']->fresh(['textTranslations']));

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $context['page']->id, 'locale_id' => $french->id],
            ['site_id' => $site->id, 'name' => 'Accueil', 'slug' => 'accueil', 'path' => '/fr/p/accueil'],
        );

        $defaultResponse = $this->get('/');
        $frenchResponse = $this->get('/fr/p/accueil');

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('Shared Header Title', false);
        $defaultResponse->assertSee('Shared Header Child', false);

        $frenchResponse->assertOk();
        $frenchResponse->assertSee('En-tete partage', false);
        $frenchResponse->assertSee('Contenu partage enfant', false);
        $frenchResponse->assertDontSee('Shared Header Child', false);
    }

    private function publishedPageWithSharedSlotSource(array $overrides = []): array
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = $overrides['site'] ?? Site::query()->firstOrFail();
        $headerSlotType = $this->slotType('header', 'Header', 1);
        $mainSlotType = $this->slotType('main', 'Main', 2);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 3);
        $headerType = $this->blockType('header', 'Header', 1);
        $plainTextType = $this->blockType('plain_text', 'Plain Text', 2);
        $sectionType = $this->blockType('section', 'Section', 3);
        $containerType = $this->blockType('container', 'Container', 4);
        $clusterType = $this->blockType('cluster', 'Cluster', 5);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Home',
            'slug' => $overrides['page_slug'] ?? 'home',
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => $overrides['page_shell'] ?? 'docs'],
        ]);

        $slug = $overrides['page_slug'] ?? 'home';
        $path = $overrides['path'] ?? ($slug === 'home' ? '/' : '/p/'.$slug);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => 'Home', 'slug' => $slug, 'path' => $path],
        );

        $sharedSourcePage = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Shared Source '.($slug === 'home' ? 'root' : $slug),
            'slug' => 'shared-source-'.($slug === 'home' ? 'root' : $slug),
            'status' => Page::STATUS_DRAFT,
            'settings' => ['public_shell' => $overrides['shared_public_shell'] ?? 'docs'],
        ]);

        $sharedSlotAttributes = [
            'site_id' => $site->id,
            'name' => 'Reusable Header',
            'handle' => 'reusable-header'.($overrides['page_slug'] ?? ''),
            'is_active' => $overrides['shared_is_active'] ?? true,
        ];

        if (($overrides['shared_slot_name_provided'] ?? false) || array_key_exists('shared_slot_name', $overrides)) {
            $sharedSlotAttributes['slot_name'] = $overrides['shared_slot_name'];
        } else {
            $sharedSlotAttributes['slot_name'] = 'header';
        }

        if (($overrides['shared_public_shell_provided'] ?? false) || array_key_exists('shared_public_shell', $overrides)) {
            $sharedSlotAttributes['public_shell'] = $overrides['shared_public_shell'];
        } else {
            $sharedSlotAttributes['public_shell'] = 'docs';
        }

        $sharedSlot = SharedSlot::query()->create([
            ...$sharedSlotAttributes,
        ]);

        $pageSlotAttributes = [
            'page_id' => $page->id,
            'slot_type_id' => $headerSlotType->id,
            'source_type' => $overrides['source_type'] ?? PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => array_key_exists('shared_slot_id', $overrides)
                ? $overrides['shared_slot_id']
                : $sharedSlot->id,
            'sort_order' => 0,
        ];

        if (PageSlot::normalizeRuntimeSourceType($pageSlotAttributes['source_type']) !== PageSlot::SOURCE_TYPE_SHARED_SLOT) {
            $pageSlotAttributes['shared_slot_id'] = null;
        }

        $pageSlot = PageSlot::query()->create($pageSlotAttributes);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $mainSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 1,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 2,
        ]);

        $pageHeader = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);
        $pageHeader->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Page Header Content',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($pageHeader->fresh(['textTranslations']));

        foreach ([
            ['slot_type' => $mainSlotType, 'content' => 'Page Main Content'],
            ['slot_type' => $sidebarSlotType, 'content' => 'Page Sidebar Content'],
        ] as $definition) {
            $block = Block::query()->create([
                'page_id' => $page->id,
                'type' => 'plain_text',
                'block_type_id' => $plainTextType->id,
                'source_type' => 'static',
                'slot' => $definition['slot_type']->slug,
                'slot_type_id' => $definition['slot_type']->id,
                'sort_order' => 0,
                'status' => 'published',
                'is_system' => false,
            ]);
            $block->textTranslations()->create([
                'locale_id' => Page::defaultLocaleId(),
                'content' => $definition['content'],
            ]);
            app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));
        }

        $sharedRoot = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 10,
            'status' => 'published',
            'is_system' => false,
        ]);
        $sharedRoot->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Shared Header Root',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedRoot->fresh(['textTranslations']));

        $sharedContainer = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedRoot->id,
            'type' => 'container',
            'block_type_id' => $containerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $sharedTitle = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedContainer->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);
        $sharedTitle->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'title' => 'Shared Header Title',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedTitle->fresh(['textTranslations']));

        $sharedCluster = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedContainer->id,
            'type' => 'cluster',
            'block_type_id' => $clusterType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $sharedLeaf = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedCluster->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $sharedLeaf->textTranslations()->create([
            'locale_id' => Page::defaultLocaleId(),
            'content' => 'Shared Header Child',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedLeaf->fresh(['textTranslations']));

        $sharedRootAssignment = SharedSlotBlock::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'block_id' => $sharedRoot->id,
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $sharedContainerAssignment = SharedSlotBlock::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'block_id' => $sharedContainer->id,
            'parent_id' => $sharedRootAssignment->id,
            'sort_order' => 0,
        ]);

        SharedSlotBlock::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'block_id' => $sharedTitle->id,
            'parent_id' => $sharedContainerAssignment->id,
            'sort_order' => 0,
        ]);

        $sharedClusterAssignment = SharedSlotBlock::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'block_id' => $sharedCluster->id,
            'parent_id' => $sharedContainerAssignment->id,
            'sort_order' => 1,
        ]);

        SharedSlotBlock::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'block_id' => $sharedLeaf->id,
            'parent_id' => $sharedClusterAssignment->id,
            'sort_order' => 0,
        ]);

        return [
            'page' => $page,
            'pageSlot' => $pageSlot,
            'pageHeader' => $pageHeader,
            'sharedSlot' => $sharedSlot,
            'sharedRoot' => $sharedRoot,
            'sharedTitle' => $sharedTitle,
            'sharedLeaf' => $sharedLeaf,
        ];
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder],
        );
    }
}
