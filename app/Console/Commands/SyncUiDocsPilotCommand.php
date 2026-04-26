<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockPayloadWriter;
use App\Support\Blocks\BlockTranslationRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncUiDocsPilotCommand extends Command
{
    private const TARGET_DOMAIN = 'ui.docs.webblocksui.com';

    protected $signature = 'webblocks:sync-ui-docs-pilot';

    protected $description = 'Rebuild the UI docs migration pilot pages inside the existing ui.docs.webblocksui.com CMS site';

    public function __construct(
        private readonly BlockPayloadWriter $blockPayloadWriter,
        private readonly BlockTranslationRegistry $blockTranslationRegistry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $site = Site::query()->where('domain', self::TARGET_DOMAIN)->first();

        if (! $site) {
            $this->error('Site not found for domain '.self::TARGET_DOMAIN.'. This command only operates on the existing ui.docs.webblocksui.com site.');

            return self::FAILURE;
        }

        $summary = DB::transaction(function () use ($site): array {
            $locales = $this->ensureTargetLocales($site);
            $mainSlot = $this->slotType('main', 'Main', 1, true);
            $summary = [
                'site' => $site,
                'locales' => $locales,
                'pages_synced' => 0,
                'pages_created' => 0,
                'pages_updated' => 0,
                'blocks_removed' => 0,
                'blocks_written' => 0,
                'pages' => [],
            ];

            foreach ($this->pageDefinitions() as $definition) {
                $page = $this->locatePilotPage($site, $definition);
                $wasCreated = false;

                if (! $page) {
                    $page = Page::query()->create([
                        'site_id' => $site->id,
                        'title' => $definition['title'],
                        'slug' => $definition['slug'],
                        'page_type' => $definition['page_type'],
                        'status' => Page::STATUS_PUBLISHED,
                    ]);
                    $wasCreated = true;
                } else {
                    $page->forceFill([
                        'site_id' => $site->id,
                        'page_type' => $definition['page_type'],
                        'status' => Page::STATUS_PUBLISHED,
                    ]);

                    if (! $page->published_at) {
                        $page->published_at = now();
                    }

                    $page->save();
                }

                $this->syncPageTranslations($page, $site, $locales, $definition['title'], $definition['slug']);

                $removedBlocks = Block::query()->where('page_id', $page->id)->count();
                Block::query()->where('page_id', $page->id)->delete();
                PageSlot::query()->where('page_id', $page->id)->delete();
                PageSlot::query()->create([
                    'page_id' => $page->id,
                    'slot_type_id' => $mainSlot->id,
                    'sort_order' => 0,
                ]);

                $writtenBlocks = $this->writeBlockTree($page, $mainSlot, $locales, $definition['blocks']);

                $summary['pages_synced']++;
                $summary['pages_created'] += $wasCreated ? 1 : 0;
                $summary['pages_updated'] += $wasCreated ? 0 : 1;
                $summary['blocks_removed'] += $removedBlocks;
                $summary['blocks_written'] += $writtenBlocks;
                $summary['pages'][] = $definition['slug'];
            }

            return $summary;
        });

        $this->line('target site: '.$summary['site']->name.' | '.($summary['site']->domain ?: 'no domain').' (#'.$summary['site']->id.')');
        $this->line('enabled locales: '.$summary['locales']->pluck('code')->implode(', '));
        $this->line('pages synced: '.$summary['pages_synced']);
        $this->line('pages created: '.$summary['pages_created']);
        $this->line('pages updated: '.$summary['pages_updated']);
        $this->line('blocks removed: '.$summary['blocks_removed']);
        $this->line('blocks written: '.$summary['blocks_written']);
        $this->line('pages rebuilt: '.implode(', ', $summary['pages']));
        $this->line('known gaps: docs home uses section composition instead of a dedicated docs hero; pattern preview remains static; Code Example Block needed for richer preview/source documentation pairs.');

        return self::SUCCESS;
    }

    private function ensureTargetLocales(Site $site): Collection
    {
        $defaultLocale = Locale::query()->where('is_default', true)->first();

        if (! $defaultLocale) {
            $defaultLocale = Locale::query()->create([
                'code' => 'en',
                'name' => 'English',
                'is_default' => true,
                'is_enabled' => true,
            ]);
        }

        $englishLocale = Locale::query()->firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'is_default' => $defaultLocale->code === 'en', 'is_enabled' => true],
        );

        if (! $englishLocale->is_enabled) {
            $englishLocale->forceFill(['is_enabled' => true])->save();
        }

        $site->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
            $englishLocale->id => ['is_enabled' => true],
        ]);

        return $site->enabledLocales()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function locatePilotPage(Site $site, array $definition): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', function ($query) use ($site, $definition) {
                $query
                    ->where('site_id', $site->id)
                    ->where(function ($translationQuery) use ($definition) {
                        $translationQuery->where('slug', $definition['slug']);

                        if ($definition['slug'] === 'home') {
                            $translationQuery->orWhere('path', '/');
                        }
                    });
            })
            ->first();
    }

    private function syncPageTranslations(Page $page, Site $site, Collection $locales, string $title, string $slug): void
    {
        foreach ($locales as $locale) {
            PageTranslation::query()->updateOrCreate(
                [
                    'page_id' => $page->id,
                    'locale_id' => $locale->id,
                ],
                [
                    'site_id' => $site->id,
                    'name' => $title,
                    'slug' => $slug,
                    'path' => PageTranslation::pathFromSlug($slug),
                ],
            );
        }
    }

    private function writeBlockTree(Page $page, SlotType $slotType, Collection $locales, array $blocks, ?Block $parent = null): int
    {
        $written = 0;

        foreach (array_values($blocks) as $sortOrder => $definition) {
            $type = $definition['type'];
            $blockType = $this->blockType(
                slug: $type,
                name: $definition['block_type_name'] ?? str($type)->replace('-', ' ')->title()->toString(),
                sortOrder: (int) ($definition['block_type_sort_order'] ?? ($sortOrder + 1)),
                sourceType: $definition['source_type'] ?? 'static',
                isSystem: (bool) ($definition['is_system'] ?? false),
                isContainer: array_key_exists('children', $definition),
            );

            $payload = [
                'page_id' => $page->id,
                'parent_id' => $parent?->id,
                'type' => $type,
                'block_type_id' => $blockType->id,
                'source_type' => $definition['source_type'] ?? ($blockType->source_type ?: 'static'),
                'slot' => $parent?->slot ?? $slotType->slug,
                'slot_type_id' => $slotType->id,
                'sort_order' => $sortOrder,
                'title' => $definition['title'] ?? null,
                'subtitle' => $definition['subtitle'] ?? null,
                'content' => $definition['content'] ?? null,
                'url' => $definition['url'] ?? null,
                'variant' => $definition['variant'] ?? null,
                'meta' => $definition['meta'] ?? null,
                'settings' => $this->encodeSettings($definition['settings'] ?? null),
                'status' => Page::STATUS_PUBLISHED,
                'is_system' => (bool) ($definition['is_system'] ?? false),
            ];

            $block = $this->blockPayloadWriter->save(new Block(), $page, $payload, $locales->first()?->code);

            if ($this->blockTranslationRegistry->isTranslatable($type)) {
                foreach ($locales->slice(1) as $locale) {
                    $this->blockPayloadWriter->save($block, $page, $payload, $locale->code);
                }
            }

            $written++;

            if (! empty($definition['children'])) {
                $written += $this->writeBlockTree($page, $slotType, $locales, $definition['children'], $block);
            }
        }

        return $written;
    }

    private function slotType(string $slug, string $name, int $sortOrder, bool $isSystem = false): SlotType
    {
        return SlotType::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'sort_order' => $sortOrder,
                'status' => 'published',
                'is_system' => $isSystem,
            ],
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder, string $sourceType = 'static', bool $isSystem = false, bool $isContainer = false): BlockType
    {
        return BlockType::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'source_type' => $sourceType,
                'sort_order' => $sortOrder,
                'status' => 'published',
                'is_system' => $isSystem,
                'is_container' => $isContainer,
            ],
        );
    }

    private function encodeSettings(?array $settings): ?string
    {
        if ($settings === null || $settings === []) {
            return null;
        }

        return json_encode($settings, JSON_UNESCAPED_SLASHES);
    }

    private function pageDefinitions(): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'WebBlocks UI',
                'page_type' => 'docs',
                'blocks' => $this->homeBlocks(),
            ],
            [
                'slug' => 'getting-started',
                'title' => 'Getting Started',
                'page_type' => 'docs',
                'blocks' => $this->gettingStartedBlocks(),
            ],
            [
                'slug' => 'cookie-consent',
                'title' => 'Cookie Consent',
                'page_type' => 'docs',
                'blocks' => $this->cookieConsentBlocks(),
            ],
        ];
    }

    private function homeBlocks(): array
    {
        return [
            [
                'type' => 'section',
                'children' => [
                    [
                        'type' => 'heading',
                        'variant' => 'h1',
                        'title' => 'Build consistent interfaces with WebBlocks UI',
                    ],
                    [
                        'type' => 'rich-text',
                        'content' => "WebBlocks UI ships practical primitives and patterns for public sites, auth flows, dashboards, and documentation. This pilot rebuilds that docs direction inside WebBlocks CMS with aligned blocks instead of custom page code.",
                    ],
                    [
                        'type' => 'button',
                        'title' => 'Start with Getting Started',
                        'url' => '/p/getting-started',
                        'variant' => 'primary',
                    ],
                ],
            ],
            [
                'type' => 'section',
                'title' => 'Why this migration pilot matters',
                'content' => 'The docs site should use the same CMS authoring model that product teams will use later, without relying on one-off HTML and CSS.',
                'children' => [
                    [
                        'type' => 'columns',
                        'title' => 'Pilot coverage',
                        'variant' => 'cards',
                        'children' => [
                            [
                                'type' => 'column_item',
                                'title' => 'Real CMS composition',
                                'content' => 'Pages are rebuilt from sections, headings, text, buttons, callouts, and aligned columns blocks.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => 'Design-system-safe output',
                                'content' => 'Public rendering stays inside shipped WebBlocks UI primitives instead of hand-rolled docs markup.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => 'Idempotent rebuild flow',
                                'content' => 'The pilot command clears only the target page block trees and recreates them cleanly on every run.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'callout',
                'title' => 'Known gap',
                'variant' => 'info',
                'content' => 'The docs home page currently uses section composition instead of a dedicated docs-focused hero block.',
            ],
            [
                'type' => 'related-content',
                'title' => 'Continue with the pilot',
                'subtitle' => 'Use the rebuilt docs pages as the CMS migration baseline.',
                'content' => "Getting Started | /p/getting-started | Guide | Load the CDN assets and build the first aligned docs page.\nCookie Consent | /p/cookie-consent | Pattern | Review a pattern page rebuilt without custom preview systems.",
            ],
        ];
    }

    private function gettingStartedBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'variant' => 'h1',
                'title' => 'Getting Started',
            ],
            [
                'type' => 'rich-text',
                'content' => "Start with the shipped CDN assets, then compose pages from stable WebBlocks UI primitives. This migration pilot keeps the content editorial and the output predictable.",
            ],
            [
                'type' => 'callout',
                'title' => 'CDN usage',
                'variant' => 'info',
                'content' => 'Use the public WebBlocks UI assets directly when wiring a CMS-rendered page shell or a standalone preview.',
            ],
            [
                'type' => 'code',
                'title' => 'Load WebBlocks UI from jsDelivr',
                'settings' => [
                    'language' => 'html',
                ],
                'content' => "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css\">\n<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.css\">\n<script src=\"https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js\"></script>",
            ],
            [
                'type' => 'section',
                'title' => 'Build the first page',
                'content' => 'Keep the initial page simple: add a heading, short copy, and one clear action before layering in more patterns.',
                'children' => [
                    [
                        'type' => 'columns',
                        'variant' => 'plain',
                        'children' => [
                            [
                                'type' => 'column_item',
                                'title' => '1. Load CSS and icons',
                                'content' => 'Add the shared stylesheet and icon bundle once in the page shell.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => '2. Mount JavaScript once',
                                'content' => 'Load the shared script before using any interactive patterns such as cookie consent.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => '3. Compose with blocks',
                                'content' => 'Use CMS sections, callouts, buttons, and columns blocks instead of freeform custom markup.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'code',
                'title' => 'Minimal starter markup',
                'settings' => [
                    'language' => 'html',
                ],
                'content' => "<section class=\"wb-section\">\n  <div class=\"wb-stack wb-gap-3\">\n    <h1>Ship the first docs page</h1>\n    <p>Start with stable primitives, then add richer patterns only when the content needs them.</p>\n    <a href=\"/p/cookie-consent\" class=\"wb-btn wb-btn-primary\">Review Cookie Consent</a>\n  </div>\n</section>",
            ],
            [
                'type' => 'related-content',
                'title' => 'Next docs pages',
                'content' => "WebBlocks UI Home | / | Overview | Return to the docs landing page.\nCookie Consent | /p/cookie-consent | Pattern | Inspect a real pattern page built with aligned blocks.",
            ],
        ];
    }

    private function cookieConsentBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'variant' => 'h1',
                'title' => 'Cookie Consent',
            ],
            [
                'type' => 'rich-text',
                'content' => 'Cookie Consent combines a bottom banner, a shared preference center, and a predictable browser-side storage model. In this CMS pilot the page documents the pattern with aligned content blocks instead of a custom docs app.',
            ],
            [
                'type' => 'section',
                'title' => 'Usage',
                'content' => 'Use the pattern when a public page needs a clear consent choice, a way to reopen preferences later, and a stable event contract for analytics or privacy integrations.',
                'children' => [
                    [
                        'type' => 'columns',
                        'variant' => 'plain',
                        'children' => [
                            [
                                'type' => 'column_item',
                                'title' => 'Banner first',
                                'content' => 'Present a bottom banner with accept, reject, and preferences actions.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => 'Shared modal',
                                'content' => 'Reopen the same preference center from the footer or another persistent control.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => 'Stable storage keys',
                                'content' => 'Keep browser state predictable so backend consent sync stays aligned with the UI pattern.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'section',
                'title' => 'Preview',
                'variant' => 'muted',
                'content' => 'This pilot keeps the preview static on purpose. It explains the expected states without mounting a separate documentation framework or a second consent system inside page content.',
                'children' => [
                    [
                        'type' => 'columns',
                        'variant' => 'cards',
                        'children' => [
                            [
                                'type' => 'column_item',
                                'title' => 'Banner state',
                                'content' => 'Bottom banner with Accept all, Reject, and Save preferences actions.',
                            ],
                            [
                                'type' => 'column_item',
                                'title' => 'Preference center',
                                'content' => 'Shared modal reopened from the footer so users can revisit their choice later.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'callout',
                'title' => 'Implementation notes',
                'variant' => 'info',
                'content' => 'The current pilot documents the storage keys and interaction model, but the preview remains a static representation instead of a live embedded pattern.',
            ],
            [
                'type' => 'code',
                'title' => 'Browser state keys',
                'settings' => [
                    'language' => 'text',
                ],
                'content' => "localStorage: wb-cookie-consent\nlocalStorage: wb-cookie-consent-preferences\npattern event: wb:cookie-consent:change",
            ],
            [
                'type' => 'related-content',
                'title' => 'Related links',
                'content' => "Getting Started | /p/getting-started | Guide | Load the assets and compose the first page.\nWebBlocks UI Home | / | Overview | Return to the migration pilot landing page.",
            ],
        ];
    }
}
