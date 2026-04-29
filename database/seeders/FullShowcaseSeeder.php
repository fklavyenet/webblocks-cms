<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockType;
use App\Models\DemoAssetReference;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FullShowcaseSeeder extends Seeder
{
    private Collection $blockTypes;

    private Collection $slotTypes;

    private Collection $pages;

    private Collection $folders;

    private Collection $assets;

    private ?int $uploaderId = null;

    private ?int $defaultLocaleId = null;

    public function __construct(private readonly BlockTranslationWriter $blockTranslationWriter) {}

    public function run(): void
    {
        throw new \RuntimeException('FullShowcaseSeeder is quarantined while the CMS foundation is limited to header and plain_text blocks. Rebuild showcase content deliberately before re-enabling this seeder.');
    }

    private function seedFolders(): void
    {
        collect([
            'branding' => 'Branding',
            'heroes' => 'Heroes',
            'services' => 'Services',
            'team' => 'Team',
            'blog' => 'Blog',
            'case-studies' => 'Case Studies',
            'gallery' => 'Gallery',
            'downloads' => 'Downloads',
            'legal' => 'Legal',
            'misc' => 'Misc',
        ])->each(function (string $name, string $slug): void {
            $folder = AssetFolder::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'parent_id' => null],
            );

            $this->folders->put($slug, $folder);
        });
    }

    private function seedAssets(): void
    {
        $this->copyPublicImageAsset(
            'brand-logo',
            'public/brand/logo-180.png',
            'branding',
            'northstar-brand-mark.png',
            'Northstar Labs brand mark',
            'Northstar Labs brand mark',
            'Primary brand mark used across the demo site.'
        );

        $this->copyPublicImageAsset(
            'brand-icon',
            'public/brand/icon-192x192.png',
            'branding',
            'northstar-icon.png',
            'Northstar Labs app icon',
            'Northstar Labs application icon',
            'Square icon used for compact navigation and app surfaces.'
        );

        $this->storeSvgAsset('hero-home', 'heroes', 'home-editorial-command-center.svg', 'Editorial command center', 'A structured content command center on large monitors', 'Hero scene used on the homepage.', '#1d4ed8', '#0f172a');
        $this->storeSvgAsset('hero-services', 'heroes', 'services-workshop.svg', 'Implementation workshop', 'A team workshop focused on service design and content modeling', 'Service workshop image used on service pages.', '#7c3aed', '#111827');
        $this->storeSvgAsset('hero-docs', 'heroes', 'documentation-guide.svg', 'Documentation workspace', 'A product team reviewing structured guides and release notes', 'Documentation hero image.', '#0f766e', '#0f172a');

        $this->storeSvgAsset('service-governance', 'services', 'service-governance.svg', 'Governance program', 'A governance planning board with content stages and review loops', 'Service card image for governance engagements.', '#2563eb', '#1e293b');
        $this->storeSvgAsset('service-migration', 'services', 'service-migration.svg', 'Migration delivery', 'A migration dashboard with content batches and QA checkpoints', 'Service card image for migration delivery.', '#7c3aed', '#1e293b');
        $this->storeSvgAsset('service-measurement', 'services', 'service-measurement.svg', 'Measurement layer', 'An analytics board combining content performance and operations metrics', 'Service card image for measurement services.', '#0f766e', '#1e293b');

        $this->storeSvgAsset('team-ava', 'team', 'ava-carson.svg', 'Ava Carson', 'Portrait illustration of Ava Carson', 'Founder and strategy lead portrait.', '#f97316', '#1e293b', 900, 900);
        $this->storeSvgAsset('team-james', 'team', 'james-morita.svg', 'James Morita', 'Portrait illustration of James Morita', 'Implementation director portrait.', '#2563eb', '#1e293b', 900, 900);
        $this->storeSvgAsset('team-priya', 'team', 'priya-sen.svg', 'Priya Sen', 'Portrait illustration of Priya Sen', 'Design systems lead portrait.', '#7c3aed', '#1e293b', 900, 900);
        $this->storeSvgAsset('team-daniel', 'team', 'daniel-reed.svg', 'Daniel Reed', 'Portrait illustration of Daniel Reed', 'Lifecycle content lead portrait.', '#0f766e', '#1e293b', 900, 900);

        $this->storeSvgAsset('blog-governance', 'blog', 'blog-governance-models.svg', 'Governance models', 'Illustration for a post about content governance models', 'Blog cover image for governance article.', '#1d4ed8', '#111827');
        $this->storeSvgAsset('blog-components', 'blog', 'blog-component-audits.svg', 'Component audits', 'Illustration for a post about block inventory and audits', 'Blog cover image for component audit article.', '#7c3aed', '#111827');

        $this->storeSvgAsset('case-atlas', 'case-studies', 'case-atlas-logistics.svg', 'Atlas Logistics rollout', 'Illustration showing logistics workflows moving into a governed CMS', 'Case study cover image for Atlas Logistics.', '#0f766e', '#111827');
        $this->storeSvgAsset('case-lumen', 'case-studies', 'case-lumen-energy.svg', 'Lumen Energy knowledge base', 'Illustration showing a support knowledge base migration for an energy company', 'Case study cover image for Lumen Energy.', '#f97316', '#111827');

        $this->storeSvgAsset('gallery-ops-wall', 'gallery', 'ops-wall.svg', 'Operations wall', 'Editorial operations wall with release lanes and QA states', 'Gallery image showing the operations wall.', '#2563eb', '#111827');
        $this->storeSvgAsset('gallery-dashboard', 'gallery', 'dashboard-panels.svg', 'Dashboard panels', 'Content dashboard with publishing and service metrics', 'Gallery image showing dashboard panels.', '#7c3aed', '#111827');
        $this->storeSvgAsset('gallery-workshop', 'gallery', 'workshop-notes.svg', 'Workshop notes', 'Service blueprinting notes from an implementation workshop', 'Gallery image showing workshop notes.', '#0f766e', '#111827');

        $this->storeSvgAsset('partner-atlas', 'branding', 'partner-atlas.svg', 'Atlas Logistics', 'Atlas Logistics partner logo', 'Partner logo for Atlas Logistics.', '#1d4ed8', '#e2e8f0', 700, 400, true);
        $this->storeSvgAsset('partner-harbor', 'branding', 'partner-harbor.svg', 'Harbor Health', 'Harbor Health partner logo', 'Partner logo for Harbor Health.', '#0f766e', '#e2e8f0', 700, 400, true);
        $this->storeSvgAsset('partner-lighthouse', 'branding', 'partner-lighthouse.svg', 'Lighthouse Retail', 'Lighthouse Retail partner logo', 'Partner logo for Lighthouse Retail.', '#7c3aed', '#e2e8f0', 700, 400, true);

        $this->storePdfAsset('implementation-checklist', 'downloads', 'implementation-checklist.pdf', 'Implementation checklist', [
            'Northstar Labs Implementation Checklist',
            '1. Audit page types and existing block usage.',
            '2. Define editorial ownership and approval flow.',
            '3. Map block types to repeatable page patterns.',
            '4. Populate demo content and verify media bindings.',
            '5. Validate publishing, navigation, and download flows.',
        ], 'A concise PDF checklist used by implementation teams.');

        $this->storePdfAsset('buyers-guide', 'downloads', 'buyers-guide.pdf', 'CMS buyer guide', [
            'Northstar Labs CMS Buyer Guide',
            'Use this guide to compare governance, workflow, and publishing needs.',
            'The demo installation mirrors the kinds of pages most evaluation teams inspect first.',
            'Included sections cover operations, service delivery, pricing, resources, and legal content.',
        ], 'A buyer guide PDF used in download examples.');

        $this->storePdfAsset('privacy-summary', 'legal', 'privacy-summary.pdf', 'Privacy summary', [
            'Northstar Labs Privacy Summary',
            'This demo collects only the data required for account access and editorial administration.',
            'Operational analytics are presented as sample content only.',
            'Contact forms in the demo illustrate structure rather than live collection.',
        ], 'A privacy summary PDF linked from legal pages.');

        $this->storeTextAsset('feature-matrix', 'downloads', 'feature-matrix.csv', "Capability,Starter,Scale,Enterprise\nEditorial workflow,Yes,Yes,Yes\nGovernance tools,No,Yes,Yes\nStructured documentation,No,Yes,Yes\nSuccess planning,No,No,Yes\n", 'Feature matrix', 'CSV comparison used for file and resource blocks.', 'text/csv');
        $this->storeTextAsset('brand-guidelines', 'downloads', 'brand-guidelines.txt', "Northstar Labs demo brand guidelines\n- Use English only\n- Keep headlines concise\n- Prefer operational clarity over marketing filler\n- Link pages into clear editorial journeys\n", 'Brand guidelines', 'Text download used in resource examples.');
        $this->storeWavAsset('audio-briefing', 'misc', 'implementation-briefing.wav', 'Implementation briefing', 'Short audio placeholder used for the audio block.');

        foreach (config('demo_media.items', []) as $item) {
            if (! is_array($item) || empty($item['key'])) {
                continue;
            }

            $asset = DemoAssetReference::query()
                ->where('source_key', $item['key'])
                ->with('asset')
                ->first()?->asset;

            if ($asset) {
                $this->assets->put($item['key'], $asset);
            }
        }
    }

    private function seedPages(): void
    {
        foreach ($this->pageBlueprints() as $blueprint) {
            $page = Page::query()->updateOrCreate(
                ['slug' => $blueprint['slug']],
                [
                    'title' => $blueprint['title'],
                    'page_type' => $blueprint['page_type'],
                    'status' => 'published',
                ],
            );

            $this->pages->put($blueprint['slug'], $page);
        }
    }

    private function seedNavigation(): void
    {
        NavigationItem::query()->whereIn('menu_key', NavigationItem::menuKeys())->delete();

        $primaryHome = $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Home', 'home', 1);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'About', 'about', 2);
        $servicesGroup = $this->createGroupNavItem(NavigationItem::MENU_PRIMARY, 'Services', 3);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Services Overview', 'services', 1, $servicesGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Service Detail', 'service-implementation-ops', 2, $servicesGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Features', 'features', 4);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Pricing', 'pricing', 5);
        $resourcesGroup = $this->createGroupNavItem(NavigationItem::MENU_PRIMARY, 'Resources', 6);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Blog', 'blog', 1, $resourcesGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Resources', 'resources', 2, $resourcesGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Documentation', 'documentation-guide', 3, $resourcesGroup->id);
        $companyGroup = $this->createGroupNavItem(NavigationItem::MENU_PRIMARY, 'Company', 7);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Case Studies', 'case-studies', 1, $companyGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Team', 'team', 2, $companyGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Careers', 'careers', 3, $companyGroup->id);
        $this->createPageNavItem(NavigationItem::MENU_PRIMARY, 'Contact', 'contact', 8);

        $this->createPageNavItem(NavigationItem::MENU_FOOTER, 'About', 'about', 1);
        $this->createPageNavItem(NavigationItem::MENU_FOOTER, 'Services', 'services', 2);
        $this->createPageNavItem(NavigationItem::MENU_FOOTER, 'Pricing', 'pricing', 3);
        $this->createPageNavItem(NavigationItem::MENU_FOOTER, 'Resources', 'resources', 4);
        $this->createPageNavItem(NavigationItem::MENU_FOOTER, 'Contact', 'contact', 5);

        $this->createPageNavItem(NavigationItem::MENU_MOBILE, 'Home', 'home', 1);
        $this->createPageNavItem(NavigationItem::MENU_MOBILE, 'Services', 'services', 2);
        $this->createPageNavItem(NavigationItem::MENU_MOBILE, 'Features', 'features', 3);
        $this->createPageNavItem(NavigationItem::MENU_MOBILE, 'Pricing', 'pricing', 4);
        $this->createPageNavItem(NavigationItem::MENU_MOBILE, 'Blog', 'blog', 5);
        $this->createPageNavItem(NavigationItem::MENU_MOBILE, 'Contact', 'contact', 6);

        $this->createPageNavItem(NavigationItem::MENU_LEGAL, 'Privacy Policy', 'privacy-policy', 1);
        $this->createPageNavItem(NavigationItem::MENU_LEGAL, 'Terms of Service', 'terms-of-service', 2);
        $this->createPageNavItem(NavigationItem::MENU_LEGAL, '404 Demo', '404-demo', 3);

        if ($primaryHome) {
            $this->createUrlNavItem(NavigationItem::MENU_PRIMARY, 'Admin Sign In', '/login', 9, null, '_self');
        }
    }

    private function seedPageContent(): void
    {
        foreach ($this->pageBlueprints() as $blueprint) {
            /** @var Page $page */
            $page = $this->pages->get($blueprint['slug']);

            Block::query()->where('page_id', $page->id)->delete();
            PageSlot::query()->where('page_id', $page->id)->delete();

            $slotDefinitions = array_merge(
                [
                    'header' => $this->commonHeaderBlocks(),
                    'footer' => $this->commonFooterBlocks(),
                ],
                $blueprint['slots'],
            );

            foreach ($slotDefinitions as $slotSlug => $blocks) {
                $slotType = $this->slotTypes->get($slotSlug);

                if (! $slotType || $blocks === []) {
                    continue;
                }

                PageSlot::query()->updateOrCreate(
                    ['page_id' => $page->id, 'slot_type_id' => $slotType->id],
                    ['sort_order' => $this->slotSortOrder($slotSlug)],
                );

                foreach (array_values($blocks) as $sortOrder => $blockDefinition) {
                    $this->createBlock($page, $slotSlug, $blockDefinition, null, $sortOrder);
                }
            }
        }
    }

    private function createBlock(Page $page, string $slotSlug, array $definition, ?Block $parent, int $sortOrder): Block
    {
        /** @var BlockType $blockType */
        $blockType = $this->blockTypes->get($definition['type']);
        /** @var SlotType $slotType */
        $slotType = $this->slotTypes->get($slotSlug);

        $settings = $definition['settings'] ?? null;
        $contactTranslation = null;

        if (in_array($definition['type'], ['navigation-auto', 'menu'], true)) {
            $settings = ['menu_key' => $definition['menu_key'] ?? NavigationItem::MENU_PRIMARY];
        }

        if ($definition['type'] === 'contact_form') {
            $settings = is_array($settings) ? $settings : [];
            $contactTranslation = [
                'title' => $definition['title'] ?? null,
                'content' => $definition['content'] ?? null,
                'submit_label' => trim((string) ($settings['submit_label'] ?? '')) ?: 'Send message',
                'success_message' => trim((string) ($settings['success_message'] ?? '')) ?: config('contact.success_message'),
            ];

            unset($settings['submit_label'], $settings['success_message']);
        }

        $block = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $parent?->id,
            'type' => $blockType->slug,
            'block_type_id' => $blockType->id,
            'source_type' => $blockType->source_type ?? 'static',
            'slot' => $slotSlug,
            'slot_type_id' => $slotType->id,
            'sort_order' => $sortOrder,
            'title' => $definition['title'] ?? null,
            'subtitle' => $definition['subtitle'] ?? null,
            'content' => $definition['content'] ?? null,
            'url' => $definition['url'] ?? null,
            'asset_id' => isset($definition['asset_key']) ? $this->asset($definition['asset_key'])->id : null,
            'variant' => $definition['variant'] ?? null,
            'meta' => $definition['meta'] ?? null,
            'settings' => is_array($settings) ? json_encode($settings, JSON_UNESCAPED_SLASHES) : $settings,
            'status' => $definition['status'] ?? 'published',
            'is_system' => $definition['is_system'] ?? $blockType->is_system,
        ]);

        if (! empty($definition['gallery_asset_keys'])) {
            foreach (array_values($definition['gallery_asset_keys']) as $position => $assetKey) {
                BlockAsset::query()->create([
                    'block_id' => $block->id,
                    'asset_id' => $this->asset($assetKey)->id,
                    'role' => 'gallery_item',
                    'position' => $position,
                ]);
            }
        }

        if (! empty($definition['attachment_asset_key'])) {
            BlockAsset::query()->create([
                'block_id' => $block->id,
                'asset_id' => $this->asset($definition['attachment_asset_key'])->id,
                'role' => 'attachment',
                'position' => 0,
            ]);
        }

        foreach (array_values($definition['children'] ?? []) as $childSortOrder => $childDefinition) {
            $this->createBlock($page, $slotSlug, $childDefinition, $block, $childSortOrder);
        }

        if ($definition['type'] === 'contact_form' && $this->defaultLocaleId && $contactTranslation) {
            $block->contactFormTranslations()->updateOrCreate(
                ['locale_id' => $this->defaultLocaleId],
                $contactTranslation,
            );
        }

        $this->blockTranslationWriter->normalizeCanonicalStorage($block->fresh([
            'textTranslations',
            'buttonTranslations',
            'imageTranslations',
            'contactFormTranslations',
        ]));

        return $block;
    }

    private function commonHeaderBlocks(): array
    {
        return [
            [
                'type' => 'navigation-auto',
                'menu_key' => NavigationItem::MENU_PRIMARY,
            ],
        ];
    }

    private function commonFooterBlocks(): array
    {
        return [
            [
                'type' => 'rich-text',
                'content' => 'Northstar Labs helps operations-heavy teams design, launch, and govern structured content systems without adding unnecessary complexity.',
            ],
            [
                'type' => 'navigation-auto',
                'menu_key' => NavigationItem::MENU_FOOTER,
            ],
            [
                'type' => 'navigation-auto',
                'menu_key' => NavigationItem::MENU_LEGAL,
            ],
        ];
    }

    private function pageBlueprints(): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'Northstar Labs',
                'page_type' => 'home',
                'slots' => [
                    'main' => [
                        [
                            'type' => 'section',
                            'title' => 'Structured publishing for teams that cannot afford chaos',
                            'content' => 'Northstar Labs is a demo company showing how WebBlocks CMS can power services, documentation, marketing, and operational content from one editorial foundation.',
                            'children' => [
                                ['type' => 'button', 'title' => 'Explore services', 'url' => $this->pageUrl('services'), 'variant' => 'primary'],
                                ['type' => 'button', 'title' => 'Read the guide', 'url' => $this->pageUrl('documentation-guide'), 'variant' => 'ghost'],
                            ],
                        ],
                        [
                            'type' => 'image',
                            'title' => 'Editorial command center',
                            'subtitle' => 'Modern workspace with laptop and desk',
                            'asset_key' => $this->preferredAssetKey('home-hero-01', 'hero-home'),
                        ],
                        [
                            'type' => 'slider',
                            'title' => 'A quick tour of the working environment',
                            'subtitle' => 'Curated workspace and collaboration scenes from the local demo media library.',
                            'gallery_asset_keys' => $this->preferredGalleryAssetKeys(['gallery-01', 'gallery-02', 'gallery-03', 'gallery-04'], ['gallery-ops-wall', 'gallery-dashboard', 'gallery-workshop']),
                        ],
                        [
                            'type' => 'stats',
                            'title' => 'Designed for governed publishing',
                            'content' => 'This showcase uses realistic structures instead of lorem ipsum so each block type has a believable role.',
                            'settings' => [
                                'items' => [
                                    ['title' => '65', 'subtitle' => 'block types', 'content' => 'Every visible block type is placed at least once in the seeded site.'],
                                    ['title' => '23', 'subtitle' => 'published pages', 'content' => 'The site includes marketing, support, legal, and utility content.'],
                                    ['title' => '10', 'subtitle' => 'media folders', 'content' => 'Assets are organized for quick inspection from the admin library.'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'feature-grid',
                            'title' => 'What this demo highlights',
                            'content' => 'Use the pages below to inspect real editorial combinations rather than isolated samples.',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Reusable page patterns', 'content' => 'Compare archive pages, detail pages, legal content, and utility flows.'],
                                    ['title' => 'Media-backed components', 'content' => 'Images, galleries, downloads, audio, and file assets are wired into the CMS library.'],
                                    ['title' => 'System-aware blocks', 'content' => 'Navigation, related content, auth, page metadata, and legal outputs all render meaningful demo data.'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'logo-cloud',
                            'title' => 'Representative client logos',
                            'content' => 'Partner logos are lightweight placeholder assets generated by the seeder and organized in the branding folder.',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Atlas Logistics', 'media_url' => $this->assetUrl('partner-atlas')],
                                    ['title' => 'Harbor Health', 'media_url' => $this->assetUrl('partner-harbor')],
                                    ['title' => 'Lighthouse Retail', 'media_url' => $this->assetUrl('partner-lighthouse')],
                                ],
                            ],
                        ],
                        [
                            'type' => 'testimonial',
                            'title' => 'Marta Silva',
                            'subtitle' => 'VP Operations, Atlas Logistics',
                            'content' => 'The seeded demo makes it easy to understand what each block type is good for because every page feels like part of one credible site.',
                            'asset_key' => 'team-ava',
                        ],
                        [
                            'type' => 'callout',
                            'title' => 'Inspect the admin as you browse',
                            'content' => 'Pages, blocks, navigation items, and media assets all use clear English labels so the structure is easy to understand in the CMS.',
                            'variant' => 'success',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'about',
                'title' => 'About Northstar Labs',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'split',
                            'title' => 'Why this demo brand exists',
                            'content' => 'Northstar Labs represents a modern implementation partner that needs marketing pages, documentation, resources, and system content in one place.',
                            'children' => [
                                ['type' => 'rich-text', 'content' => "Northstar Labs works with product, support, and operations teams that need structured content they can trust.\n\nThe showcase site uses concise, realistic copy so editors can understand what each block type contributes to a page."],
                                ['type' => 'image', 'title' => 'Implementation workshop', 'subtitle' => 'Team having a meeting', 'asset_key' => $this->preferredAssetKey('about-team-01', 'hero-services')],
                            ],
                        ],
                        [
                            'type' => 'timeline',
                            'title' => 'A believable company history',
                            'content' => 'The timeline block is placed on the About page where visitors naturally expect organizational milestones.',
                            'settings' => [
                                'items' => [
                                    ['title' => '2021', 'subtitle' => 'Initial consulting practice', 'content' => 'Northstar Labs began by helping teams map governance responsibilities before platform selection.'],
                                    ['title' => '2023', 'subtitle' => 'Structured delivery model', 'content' => 'The company introduced repeatable migration, documentation, and launch playbooks.'],
                                    ['title' => '2026', 'subtitle' => 'Unified demo installation', 'content' => 'This demo site now shows the full CMS capability end to end.'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'metric-card',
                            'title' => '96% adoption in the first 60 days',
                            'subtitle' => 'Average onboarding completion across guided implementations',
                            'content' => 'Teams move faster when page patterns, block usage, and media structure are predictable from the start.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'services',
                'title' => 'Services',
                'page_type' => 'archive',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'product-grid',
                            'title' => 'Northstar Labs service lines',
                            'content' => 'The product-style grid works well for service cards in a demo environment.',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Governance Design', 'subtitle' => 'Decision rights and editorial ownership', 'content' => 'Define the operating model behind your CMS rollout.', 'url' => $this->pageUrl('service-implementation-ops'), 'url_label' => 'View service detail', 'media_url' => $this->assetUrl($this->preferredAssetKey('services-workspace-01', 'service-governance'))],
                                    ['title' => 'Migration Delivery', 'subtitle' => 'Content inventory and launch readiness', 'content' => 'Move legacy pages into structured, reusable content models.', 'url' => $this->pageUrl('case-study-atlas-logistics'), 'url_label' => 'See migration case study', 'media_url' => $this->assetUrl($this->preferredAssetKey('services-workspace-01', 'service-migration'))],
                                    ['title' => 'Measurement Layer', 'subtitle' => 'Operational and content analytics', 'content' => 'Track the impact of page patterns, publishing throughput, and service outcomes.', 'url' => $this->pageUrl('features'), 'url_label' => 'Review feature coverage', 'media_url' => $this->assetUrl($this->preferredAssetKey('services-workspace-01', 'service-measurement'))],
                                ],
                            ],
                        ],
                        [
                            'type' => 'columns',
                            'title' => 'What each engagement includes',
                            'subtitle' => 'Reusable service framing in three concise columns.',
                            'content' => 'The columns block is used here for quick scannability on the overview page.',
                            'children' => [
                                ['type' => 'column_item', 'title' => 'Discovery', 'content' => 'Page audit, block inventory, taxonomy review, and stakeholder alignment.'],
                                ['type' => 'column_item', 'title' => 'Delivery', 'content' => 'Hands-on implementation, sample content creation, and editor-ready structures.'],
                                ['type' => 'column_item', 'title' => 'Enablement', 'content' => 'Governance guidance, documentation, and launch QA for production teams.'],
                            ],
                        ],
                        [
                            'type' => 'product-card',
                            'title' => 'Implementation Ops Sprint',
                            'subtitle' => 'A focused six-week engagement',
                            'content' => 'For teams that need a credible foundation quickly: working templates, seeded content, media structure, and admin clarity.',
                            'asset_key' => $this->preferredAssetKey('services-workspace-01', 'service-migration'),
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'service-implementation-ops',
                'title' => 'Service Detail: Implementation Ops Sprint',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'breadcrumb'],
                        ['type' => 'page-title'],
                        [
                            'type' => 'split',
                            'title' => 'A delivery model built for inspection',
                            'content' => 'This page mixes navigation, content, media, list, and download patterns in a way that still feels like a real service detail.',
                            'children' => [
                                ['type' => 'image', 'title' => 'Workshop visual', 'subtitle' => 'Startup workspace environment', 'asset_key' => $this->preferredAssetKey('services-workspace-01', 'hero-services')],
                                ['type' => 'rich-text', 'content' => "The Implementation Ops Sprint aligns block structure, sample content, media organization, and editorial governance into one repeatable rollout.\n\nTeams use it when they need a believable pilot site before scaling across departments."],
                            ],
                        ],
                        [
                            'type' => 'list',
                            'title' => 'Typical sprint outputs',
                            'content' => "Content model alignment\nNavigation and footer structure\nMedia folder taxonomy\nPriority page templates\nLaunch checklist and QA runbook",
                        ],
                        [
                            'type' => 'download',
                            'title' => 'Download the implementation checklist',
                            'subtitle' => 'A concise PDF used during kickoff and launch prep.',
                            'asset_key' => 'implementation-checklist',
                            'variant' => 'primary',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'features',
                'title' => 'Features',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'tabs',
                            'title' => 'Three feature lenses',
                            'subtitle' => 'Editorial, operational, and governance concerns',
                            'content' => 'Tabs are useful when a page needs lightweight segmentation without becoming an overwhelming wall of copy.',
                        ],
                        [
                            'type' => 'comparison',
                            'title' => 'How the showcase separates concerns',
                            'settings' => [
                                'rows' => [
                                    ['Structured Area', 'What you can inspect', 'Where to look'],
                                    ['Editorial blocks', 'Realistic content examples and nesting', 'Home, services, blog, and documentation pages'],
                                    ['System-driven blocks', 'Navigation, auth, related content, cookie notice, and page metadata', 'Header, footer, utility, and legal pages'],
                                    ['Media-backed blocks', 'Images, galleries, downloads, audio, and file examples', 'Resources, case studies, and media library'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pricing',
                'title' => 'Pricing',
                'page_type' => 'landing',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'pricing',
                            'title' => 'Three engagement levels',
                            'content' => 'Pricing tiers help demonstrate complex item-based blocks with short, believable commercial copy.',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Starter', 'subtitle' => '$6,500', 'content' => 'Audit, block map, and a focused demo page rollout.'],
                                    ['title' => 'Scale', 'subtitle' => '$18,000', 'content' => 'Multi-page implementation, media library setup, and editorial guidance.'],
                                    ['title' => 'Enterprise', 'subtitle' => 'Custom', 'content' => 'Cross-team governance, migration planning, and launch support.'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'table',
                            'title' => 'What changes between plans',
                            'settings' => [
                                'rows' => [
                                    ['Capability', 'Starter', 'Scale', 'Enterprise'],
                                    ['Governance workshop', 'Shared session', 'Dedicated', 'Dedicated'],
                                    ['Page build-out', '3 pages', '12 pages', '20+ pages'],
                                    ['Documentation', 'Outline only', 'Guide + templates', 'Full operating playbook'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'cart-summary',
                            'title' => 'Sample engagement summary',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Scale engagement', 'subtitle' => '$18,000'],
                                    ['title' => 'Media library setup', 'subtitle' => '$2,500'],
                                    ['title' => 'Total', 'subtitle' => '$20,500'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'checkout-summary',
                            'title' => 'Implementation checkout view',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Discovery complete', 'subtitle' => 'Included'],
                                    ['title' => 'Seeded pages approved', 'subtitle' => 'Included'],
                                    ['title' => 'Launch QA', 'subtitle' => 'Scheduled'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'faq',
                'title' => 'Frequently Asked Questions',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'faq-list',
                            'title' => 'Common buyer questions',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Is this meant to be a production design?', 'content' => 'No. It is a rich demo installation meant to make the CMS easy to inspect.'],
                                    ['title' => 'Will the seeded content be reproducible?', 'content' => 'Yes. The showcase is created by a dedicated seeder layer rather than ad hoc controller logic.'],
                                    ['title' => 'Does every block type appear somewhere?', 'content' => 'Yes. Each visible block type is attached to at least one page or nested block tree.'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'accordion',
                            'title' => 'Implementation questions',
                            'settings' => [
                                'items' => [
                                    ['title' => 'How should I inspect media usage?', 'content' => 'Open the media library and the asset detail screens. Each seeded asset has a clear folder, title, and attached usage context.'],
                                    ['title' => 'Why are legal pages included?', 'content' => 'They provide a natural home for page metadata, cookie notice content, downloads, and HTML-rich blocks.'],
                                    ['title' => 'Can I reseed safely?', 'content' => 'Yes. The showcase seeder updates managed demo pages and recreates their block trees cleanly.'],
                                ],
                            ],
                        ],
                        [
                            'type' => 'faq',
                            'title' => 'Can this demo help with stakeholder reviews?',
                            'content' => 'Yes. It gives product owners, editors, and implementers a common reference site with realistic page and block combinations.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'contact_form',
                            'title' => 'Contact us',
                            'content' => 'Tell us what you are planning and we will route your message to the right editorial or implementation contact.',
                            'settings' => [
                                'submit_label' => 'Send message',
                                'success_message' => 'Thanks for your message. We will get back to you soon.',
                                'recipient_email' => null,
                                'send_email_notification' => true,
                                'store_submissions' => true,
                            ],
                        ],
                        [
                            'type' => 'map',
                            'title' => 'Northstar Labs studio',
                            'content' => '500 Market Street, Suite 210, Portland, OR 97204',
                            'url' => 'https://maps.google.com/?q=500+Market+Street+Portland+OR+97204',
                        ],
                        [
                            'type' => 'image',
                            'title' => 'Office interior',
                            'subtitle' => 'Office interior space',
                            'asset_key' => $this->preferredAssetKey('contact-office-01', 'hero-docs'),
                        ],
                        [
                            'type' => 'social-links',
                            'title' => 'Stay connected',
                            'settings' => [
                                'items' => [
                                    ['title' => 'LinkedIn', 'url' => 'https://www.linkedin.com/'],
                                    ['title' => 'GitHub', 'url' => 'https://github.com/'],
                                    ['title' => 'YouTube', 'url' => 'https://www.youtube.com/'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'blog',
                'title' => 'Blog',
                'page_type' => 'blog',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'card-group',
                            'title' => 'Latest editorial notes',
                            'content' => 'Each card links deeper into the seeded site and demonstrates a believable archive layout.',
                            'children' => [
                                ['type' => 'section', 'title' => 'Launching a governed content platform', 'content' => 'A walkthrough of the pilot rollout process, from inventory to editorial sign-off.', 'children' => [['type' => 'button', 'title' => 'Read article', 'url' => $this->pageUrl('blog-launching-a-governed-content-platform')]]],
                                ['type' => 'section', 'title' => 'Why block inventories matter before redesign', 'content' => 'Most teams benefit more from a usage audit than from immediate visual overhaul.', 'children' => [['type' => 'button', 'title' => 'Inspect feature page', 'url' => $this->pageUrl('features')]]],
                                ['type' => 'section', 'title' => 'What a buyer guide should actually answer', 'content' => 'Good demo content helps stakeholders evaluate structure, workflow, and maintainability.', 'children' => [['type' => 'button', 'title' => 'Open resources', 'url' => $this->pageUrl('resources')]]],
                            ],
                        ],
                    ],
                    'sidebar' => [
                        ['type' => 'search', 'title' => 'Search the blog', 'subtitle' => 'Search posts, guides, and case studies'],
                    ],
                ],
            ],
            [
                'slug' => 'blog-launching-a-governed-content-platform',
                'title' => 'Blog Post: Launching a Governed Content Platform',
                'page_type' => 'blog',
                'slots' => [
                    'main' => [
                        ['type' => 'breadcrumb'],
                        ['type' => 'heading', 'variant' => 'h1', 'title' => 'Launching a governed content platform without creating process drag'],
                        ['type' => 'image', 'title' => 'Governance models', 'subtitle' => 'Person writing on laptop', 'asset_key' => $this->preferredAssetKey('blog-writing-01', 'blog-governance')],
                        ['type' => 'rich-text', 'content' => "The best pilot sites make structure visible. Editors should understand where content belongs, how it is labeled, and which blocks are most appropriate for each message.\n\nIn this showcase, the blog post becomes a natural place to demonstrate share actions, reader comments, related content, and pagination while still reading like a real article."],
                        ['type' => 'quote', 'title' => 'Priya Sen', 'subtitle' => 'Design Systems Lead', 'content' => 'When a demo site is coherent, stakeholders stop asking what the CMS cannot do and start discussing how they want to use it.'],
                        ['type' => 'share-buttons', 'title' => 'Share this article', 'settings' => ['items' => [['title' => 'Share on LinkedIn', 'url' => 'https://www.linkedin.com/'], ['title' => 'Share by Email', 'url' => 'mailto:?subject=Governed%20content%20platform']]]],
                        ['type' => 'comments', 'title' => 'Reader discussion', 'settings' => ['items' => [['title' => 'Lena, Content Ops Manager', 'subtitle' => '2 days ago', 'content' => 'This is the clearest seeded demo structure I have seen in a CMS project.'], ['title' => 'Marco, Product Lead', 'subtitle' => '1 day ago', 'content' => 'Seeing legal, docs, and marketing content together makes stakeholder review much easier.']]]],
                        [
                            'type' => 'link-list',
                            'title' => 'Continue exploring',
                            'children' => [
                                ['type' => 'link-list-item', 'title' => 'Resources', 'subtitle' => 'Library', 'content' => 'Browse guides, templates, and buyer-facing reference material.', 'url' => $this->pageUrl('resources')],
                                ['type' => 'link-list-item', 'title' => 'Documentation Guide', 'subtitle' => 'Docs', 'content' => 'Review the documentation patterns used across the seeded site.', 'url' => $this->pageUrl('documentation-guide')],
                                ['type' => 'link-list-item', 'title' => 'Atlas Logistics', 'subtitle' => 'Case Study', 'content' => 'See how a detailed rollout story is structured with reusable blocks.', 'url' => $this->pageUrl('case-study-atlas-logistics')],
                            ],
                        ],
                        ['type' => 'pagination'],
                    ],
                ],
            ],
            [
                'slug' => 'case-studies',
                'title' => 'Case Studies',
                'page_type' => 'archive',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'grid',
                            'title' => 'Transformation stories',
                            'content' => 'The grid helper is used here as a practical listing wrapper for detail-page teasers.',
                            'children' => [
                                ['type' => 'section', 'title' => 'Atlas Logistics', 'content' => 'Replaced a fragmented service site with governed, reusable page patterns and launch-ready documentation.'],
                                ['type' => 'section', 'title' => 'Lighthouse Retail', 'content' => 'Rebuilt partner resources around clearer navigation, downloads, and inspectable content blocks.'],
                                ['type' => 'section', 'title' => 'Harbor Health', 'content' => 'Unified support content, legal updates, and editorial workflows into one maintainable stack.'],
                            ],
                        ],
                        [
                            'type' => 'gallery',
                            'title' => 'Selected project visuals',
                            'subtitle' => 'A representative gallery sourced from the curated local photo set.',
                            'gallery_asset_keys' => $this->preferredGalleryAssetKeys(['gallery-01', 'gallery-02', 'gallery-03', 'gallery-04'], ['case-atlas', 'case-lumen', 'gallery-dashboard']),
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'case-study-atlas-logistics',
                'title' => 'Case Study Detail: Atlas Logistics',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'breadcrumb'],
                        ['type' => 'page-title'],
                        ['type' => 'image', 'title' => 'Atlas Logistics rollout', 'subtitle' => 'Atlas Logistics case study cover image', 'asset_key' => 'case-atlas'],
                        ['type' => 'rich-text', 'content' => "Atlas Logistics needed one place to explain services, host downloads, publish customer stories, and maintain internal review discipline.\n\nThe seeded demo case study highlights how media, metrics, and link-list patterns can work together without introducing extra tooling."],
                        ['type' => 'download', 'title' => 'Download the buyer guide', 'subtitle' => 'A PDF resource linked from the case study detail page.', 'asset_key' => 'buyers-guide'],
                        [
                            'type' => 'link-list',
                            'title' => 'More relevant pages',
                            'children' => [
                                ['type' => 'link-list-item', 'title' => 'Services', 'subtitle' => 'Overview', 'content' => 'Review the services page structure used across the seeded experience.', 'url' => $this->pageUrl('services')],
                                ['type' => 'link-list-item', 'title' => 'Pricing', 'subtitle' => 'Reference', 'content' => 'Compare package framing and reusable plan presentation blocks.', 'url' => $this->pageUrl('pricing')],
                                ['type' => 'link-list-item', 'title' => 'Resources', 'subtitle' => 'Library', 'content' => 'Open the shared downloads and guides library.', 'url' => $this->pageUrl('resources')],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'team',
                'title' => 'Team',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'team',
                            'title' => 'The demo leadership team',
                            'content' => 'Team cards use portrait assets and short role summaries so the page feels believable and easy to inspect.',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Ava Carson', 'subtitle' => 'Founder and Strategy Lead', 'content' => 'Owns pilot scope, stakeholder alignment, and editorial operating models.', 'media_url' => $this->assetUrl('team-ava')],
                                    ['title' => 'James Morita', 'subtitle' => 'Implementation Director', 'content' => 'Leads page architecture, migrations, and launch readiness.', 'media_url' => $this->assetUrl('team-james')],
                                    ['title' => 'Priya Sen', 'subtitle' => 'Design Systems Lead', 'content' => 'Connects block patterns, interface consistency, and authoring clarity.', 'media_url' => $this->assetUrl('team-priya')],
                                    ['title' => 'Daniel Reed', 'subtitle' => 'Lifecycle Content Lead', 'content' => 'Owns documentation quality, resource strategy, and content maintenance models.', 'media_url' => $this->assetUrl('team-daniel')],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'careers',
                'title' => 'Careers',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'stack',
                            'title' => 'What working here looks like',
                            'content' => 'The stack helper is used here for a simple, believable hiring narrative.',
                            'children' => [
                                ['type' => 'text', 'title' => 'Small teams, high accountability', 'content' => 'Northstar Labs keeps project teams compact so editorial decisions stay visible and fast.'],
                                ['type' => 'text', 'title' => 'Documentation is part of delivery', 'content' => 'We expect implementation work to leave behind clear admin and editorial guidance.'],
                                ['type' => 'button', 'title' => 'View open role', 'url' => $this->pageUrl('career-senior-implementation-strategist')],
                            ],
                        ],
                        [
                            'type' => 'list',
                            'title' => 'Benefits',
                            'content' => "Remote-first collaboration\nQuarterly in-person workshops\nDedicated learning budget\nDocumentation-first delivery culture",
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'career-senior-implementation-strategist',
                'title' => 'Career Detail: Senior Implementation Strategist',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'breadcrumb'],
                        ['type' => 'page-title'],
                        ['type' => 'rich-text', 'content' => "We are hiring someone who can translate stakeholder goals into page patterns, block plans, media structures, and governance-friendly launch flows.\n\nThe role mixes implementation craft with calm editorial judgment."],
                        ['type' => 'button', 'title' => 'Start the conversation', 'url' => $this->pageUrl('contact')],
                    ],
                ],
            ],
            [
                'slug' => 'testimonials',
                'title' => 'Testimonials',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        ['type' => 'quote', 'title' => 'Kelsey Warren', 'subtitle' => 'Head of Digital, Harbor Health', 'content' => 'The demo felt like a site our teams would actually use, which made approval dramatically faster.'],
                        ['type' => 'quote', 'title' => 'Tom Becker', 'subtitle' => 'Director of Support, Lighthouse Retail', 'content' => 'We could inspect navigation, downloads, guides, and system content in one walkthrough instead of imagining the final shape.'],
                    ],
                ],
            ],
            [
                'slug' => 'partners',
                'title' => 'Partners',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'logo-cloud',
                            'title' => 'Representative partner ecosystem',
                            'content' => 'Partner pages are a natural home for logo-driven block types.',
                            'settings' => [
                                'items' => [
                                    ['title' => 'Atlas Logistics', 'media_url' => $this->assetUrl('partner-atlas')],
                                    ['title' => 'Harbor Health', 'media_url' => $this->assetUrl('partner-harbor')],
                                    ['title' => 'Lighthouse Retail', 'media_url' => $this->assetUrl('partner-lighthouse')],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'resources',
                'title' => 'Resources',
                'page_type' => 'archive',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        [
                            'type' => 'container',
                            'title' => 'Resources ready for download or review',
                            'content' => 'The container helper groups asset-backed content in a way that still feels editorially natural.',
                            'children' => [
                                ['type' => 'download', 'title' => 'Download the CMS buyer guide', 'subtitle' => 'PDF guide for stakeholder reviews', 'asset_key' => 'buyers-guide'],
                                ['type' => 'file', 'title' => 'Open the feature matrix', 'content' => 'A CSV file used for comparison and procurement discussions.', 'asset_key' => 'feature-matrix'],
                                ['type' => 'audio', 'title' => 'Listen to the implementation briefing', 'content' => 'A short placeholder audio file stored in the media library.', 'asset_key' => 'audio-briefing'],
                                ['type' => 'video', 'title' => 'Watch the platform walkthrough', 'content' => 'Video blocks can also point to hosted media when local uploads are not part of the current project stack.', 'url' => 'https://samplelib.com/lib/preview/mp4/sample-5s.mp4'],
                            ],
                        ],
                    ],
                    'sidebar' => [
                        ['type' => 'search', 'title' => 'Search resources', 'subtitle' => 'Guides, downloads, and briefings'],
                    ],
                ],
            ],
            [
                'slug' => 'documentation-guide',
                'title' => 'Documentation Guide',
                'page_type' => 'page',
                'slots' => [
                    'main' => [
                        ['type' => 'breadcrumb'],
                        [
                            'type' => 'section',
                            'children' => [
                                [
                                    'type' => 'container',
                                    'children' => [
                                        [
                                            'type' => 'content_header',
                                            'title' => 'Documentation',
                                            'subtitle' => 'Start with the shipped WebBlocks primitives, then inspect how they compose into reusable page structure.',
                                            'variant' => 'h1',
                                        ],
                                        [
                                            'type' => 'card',
                                            'settings' => ['variant' => 'promo'],
                                            'eyebrow' => 'Source-visible UI system',
                                            'title' => 'WebBlocks UI - UI building blocks for humans and AI.',
                                            'content' => 'Use the card footer for grouped actions so docs entry points stay compact and reusable.',
                                            'children' => [
                                                [
                                                    'type' => 'cluster',
                                                    'children' => [
                                                        ['type' => 'button_link', 'title' => 'Start Here', 'settings' => ['url' => '/docs/getting-started', 'target' => '_self'], 'variant' => 'primary'],
                                                        ['type' => 'button_link', 'title' => 'See primitives', 'settings' => ['url' => '/docs/blocks', 'target' => '_self'], 'variant' => 'secondary'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        ['type' => 'rich-text', 'content' => "Use this page to inspect long-form guidance patterns. It combines a breadcrumb, page metadata, table of contents, code sample, HTML fragment, and practical reference tables.\n\nThis makes the documentation page one of the most inspection-friendly parts of the demo site."],
                        ['type' => 'code', 'title' => 'Seeder pattern', 'content' => "return [\n    'slug' => 'documentation-guide',\n    'title' => 'Documentation Guide',\n    'page_type' => 'page',\n];"],
                        ['type' => 'html', 'content' => '<div class="wb-alert wb-alert-info"><div><div class="wb-alert-title">Documentation note</div><div>This HTML block is used sparingly for curated callouts and embedded markup that should not be escaped.</div></div></div>'],
                    ],
                    'sidebar' => [
                        ['type' => 'toc', 'title' => 'On this page'],
                        ['type' => 'page-meta', 'title' => 'Page metadata'],
                    ],
                ],
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'page_type' => 'system',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        ['type' => 'cookie-notice', 'title' => 'How this demo uses cookies', 'content' => 'The demo uses session cookies for sign-in and admin access. No extra tracking setup is required for the showcase.'],
                        ['type' => 'html', 'content' => '<p>Northstar Labs uses this demo site to illustrate editorial structure, media relationships, and navigation behavior. Contact and form content is illustrative. Media assets are lightweight placeholders generated for inspection and testing.</p>'],
                        ['type' => 'download', 'title' => 'Download the privacy summary', 'subtitle' => 'PDF summary for legal and stakeholder review', 'asset_key' => 'privacy-summary'],
                        ['type' => 'page-meta', 'title' => 'Policy metadata'],
                    ],
                ],
            ],
            [
                'slug' => 'terms-of-service',
                'title' => 'Terms of Service',
                'page_type' => 'system',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        ['type' => 'html', 'content' => '<p>These sample terms explain how Northstar Labs frames demo usage, editorial review responsibility, and non-production sample content. They exist to give legal and system blocks a meaningful page context.</p>'],
                    ],
                ],
            ],
            [
                'slug' => 'utility-demo',
                'title' => 'Utility Demo',
                'page_type' => 'system',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        ['type' => 'auth-form', 'title' => 'Inspect the authentication flow', 'content' => 'Use the existing Breeze auth screens to review how system pages fit into the broader CMS demo.'],
                        ['type' => 'page-content', 'title' => 'Page summary', 'content' => 'This utility page intentionally groups system-oriented block types into one place for easy admin inspection.'],
                    ],
                    'sidebar' => [
                        ['type' => 'menu', 'menu_key' => NavigationItem::MENU_MOBILE, 'status' => 'published', 'is_system' => true],
                    ],
                ],
            ],
            [
                'slug' => '404-demo',
                'title' => '404 Demo',
                'page_type' => 'system',
                'slots' => [
                    'main' => [
                        ['type' => 'page-title'],
                        ['type' => 'callout', 'title' => 'Page not found', 'content' => 'This utility page demonstrates how a fallback experience could be authored inside the CMS.', 'variant' => 'warning'],
                        ['type' => 'button', 'title' => 'Return to the homepage', 'url' => $this->pageUrl('home')],
                    ],
                ],
            ],
        ];
    }

    private function createGroupNavItem(string $menuKey, string $title, int $position): NavigationItem
    {
        return NavigationItem::query()->create([
            'menu_key' => $menuKey,
            'parent_id' => null,
            'page_id' => null,
            'title' => $title,
            'link_type' => NavigationItem::LINK_GROUP,
            'url' => null,
            'target' => null,
            'position' => $position,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => true,
        ]);
    }

    private function createPageNavItem(string $menuKey, string $title, string $pageSlug, int $position, ?int $parentId = null): NavigationItem
    {
        return NavigationItem::query()->create([
            'menu_key' => $menuKey,
            'parent_id' => $parentId,
            'page_id' => $this->pages->get($pageSlug)?->id,
            'title' => $title,
            'link_type' => NavigationItem::LINK_PAGE,
            'url' => null,
            'target' => null,
            'position' => $position,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => true,
        ]);
    }

    private function createUrlNavItem(string $menuKey, string $title, string $url, int $position, ?int $parentId = null, ?string $target = null): NavigationItem
    {
        return NavigationItem::query()->create([
            'menu_key' => $menuKey,
            'parent_id' => $parentId,
            'page_id' => null,
            'title' => $title,
            'link_type' => NavigationItem::LINK_CUSTOM_URL,
            'url' => $url,
            'target' => $target,
            'position' => $position,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => true,
        ]);
    }

    private function slotSortOrder(string $slotSlug): int
    {
        return match ($slotSlug) {
            'header' => 0,
            'main' => 1,
            'sidebar' => 2,
            'footer' => 3,
            default => 9,
        };
    }

    private function pageUrl(string $slug): string
    {
        return '/p/'.$slug;
    }

    private function asset(string $key): Asset
    {
        /** @var Asset $asset */
        $asset = $this->assets->get($key);

        return $asset;
    }

    private function assetUrl(string $key): string
    {
        return (string) $this->asset($key)->url();
    }

    private function preferredAssetKey(string $preferred, string $fallback): string
    {
        return $this->assets->has($preferred) ? $preferred : $fallback;
    }

    private function preferredGalleryAssetKeys(array $preferred, array $fallback): array
    {
        $resolved = collect($preferred)
            ->filter(fn (string $key) => $this->assets->has($key))
            ->values()
            ->all();

        return $resolved !== [] ? $resolved : $fallback;
    }

    private function copyPublicImageAsset(string $key, string $sourceRelativePath, string $folderSlug, string $filename, string $title, string $altText, string $description): void
    {
        $source = base_path($sourceRelativePath);
        $contents = File::get($source);
        [$width, $height] = getimagesize($source) ?: [null, null];

        $this->storeAsset(
            key: $key,
            folderSlug: $folderSlug,
            filename: $filename,
            contents: $contents,
            mimeType: File::mimeType($source) ?: 'image/png',
            kind: Asset::KIND_IMAGE,
            title: $title,
            altText: $altText,
            description: $description,
            width: $width,
            height: $height,
        );
    }

    private function storeSvgAsset(string $key, string $folderSlug, string $filename, string $title, string $altText, string $description, string $accent, string $surface, int $width = 1600, int $height = 900, bool $logoMode = false): void
    {
        $contents = $this->buildSvg($title, $accent, $surface, $width, $height, $logoMode);

        $this->storeAsset(
            key: $key,
            folderSlug: $folderSlug,
            filename: $filename,
            contents: $contents,
            mimeType: 'image/svg+xml',
            kind: Asset::KIND_IMAGE,
            title: $title,
            altText: $altText,
            description: $description,
            width: $width,
            height: $height,
        );
    }

    private function storePdfAsset(string $key, string $folderSlug, string $filename, string $title, array $lines, string $description): void
    {
        $this->storeAsset(
            key: $key,
            folderSlug: $folderSlug,
            filename: $filename,
            contents: $this->buildPdf($lines),
            mimeType: 'application/pdf',
            kind: Asset::KIND_DOCUMENT,
            title: $title,
            altText: $title,
            description: $description,
        );
    }

    private function storeTextAsset(string $key, string $folderSlug, string $filename, string $contents, string $title, string $description, string $mimeType = 'text/plain'): void
    {
        $this->storeAsset(
            key: $key,
            folderSlug: $folderSlug,
            filename: $filename,
            contents: $contents,
            mimeType: $mimeType,
            kind: Asset::KIND_DOCUMENT,
            title: $title,
            altText: $title,
            description: $description,
        );
    }

    private function storeWavAsset(string $key, string $folderSlug, string $filename, string $title, string $description): void
    {
        $this->storeAsset(
            key: $key,
            folderSlug: $folderSlug,
            filename: $filename,
            contents: $this->buildWav(),
            mimeType: 'audio/wav',
            kind: Asset::KIND_OTHER,
            title: $title,
            altText: $title,
            description: $description,
            duration: 2,
        );
    }

    private function storeAsset(
        string $key,
        string $folderSlug,
        string $filename,
        string $contents,
        string $mimeType,
        string $kind,
        string $title,
        string $altText,
        string $description,
        ?int $width = null,
        ?int $height = null,
        ?int $duration = null,
    ): void {
        $path = 'media/showcase/'.$folderSlug.'/'.$filename;

        Storage::disk('public')->put($path, $contents);

        $asset = Asset::query()->updateOrCreate(
            ['path' => $path],
            [
                'folder_id' => $this->folders->get($folderSlug)?->id,
                'disk' => 'public',
                'filename' => basename($path),
                'original_name' => basename($filename),
                'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: null,
                'mime_type' => $mimeType,
                'size' => strlen($contents),
                'kind' => $kind,
                'visibility' => 'public',
                'title' => $title,
                'alt_text' => $altText,
                'caption' => $description,
                'description' => $description,
                'width' => $width,
                'height' => $height,
                'duration' => $duration,
                'uploaded_by' => $this->uploaderId,
            ],
        );

        $this->assets->put($key, $asset);
    }

    private function buildSvg(string $title, string $accent, string $surface, int $width, int $height, bool $logoMode): string
    {
        $safeTitle = e($title);
        $titleSize = $logoMode ? 64 : 60;
        $subtitle = $logoMode ? 'Demo partner asset' : 'WebBlocks CMS showcase asset';
        $heightMinus = max(220, $height - 320);
        $widthMinus = max(320, $width - 240);
        $widthMinusSmall = max(260, $width - 420);
        $titleY = $logoMode ? 210 : 400;
        $subtitleY = $logoMode ? 280 : 460;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-labelledby="title desc">
  <title id="title">{$safeTitle}</title>
  <desc id="desc">{$subtitle}</desc>
  <rect width="{$width}" height="{$height}" fill="{$surface}" rx="36" />
  <circle cx="140" cy="140" r="84" fill="{$accent}" opacity="0.95" />
  <rect x="120" y="{$heightMinus}" width="{$widthMinus}" height="120" rx="24" fill="{$accent}" opacity="0.18" />
  <rect x="120" y="220" width="{$widthMinus}" height="18" rx="9" fill="#ffffff" opacity="0.16" />
  <rect x="120" y="260" width="{$widthMinusSmall}" height="18" rx="9" fill="#ffffff" opacity="0.12" />
  <text x="120" y="{$titleY}" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="{$titleSize}" font-weight="700">{$safeTitle}</text>
  <text x="120" y="{$subtitleY}" fill="#e2e8f0" font-family="Arial, Helvetica, sans-serif" font-size="28">{$subtitle}</text>
</svg>
SVG;
    }

    private function buildPdf(array $lines): string
    {
        $contentLines = [];
        $y = 760;

        foreach ($lines as $line) {
            $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $contentLines[] = "BT /F1 14 Tf 56 {$y} Td ({$safe}) Tj ET";
            $y -= 26;
        }

        $stream = implode("\n", $contentLines);
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '4 0 obj << /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= 'xref'."\n";
        $pdf .= '0 '.(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref'."\n";
        $pdf .= $xrefPosition."\n%%EOF";

        return $pdf;
    }

    private function buildWav(): string
    {
        $sampleRate = 8000;
        $seconds = 2;
        $samples = $sampleRate * $seconds;
        $data = str_repeat(pack('v', 0), $samples);
        $dataSize = strlen($data);
        $riffSize = 36 + $dataSize;

        return 'RIFF'
            .pack('V', $riffSize)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
            .'data'
            .pack('V', $dataSize)
            .$data;
    }
}
