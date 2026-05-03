<?php

namespace Project\Support\UiDocs;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\SlotType;
use App\Support\Blocks\BlockPayloadWriter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncUiDocsGettingStarted
{
    private const IMPORT_GROUP = 'webblocks-ui-docs-getting-started';

    public function __construct(private readonly BlockPayloadWriter $blockPayloadWriter) {}

    public function run(): void
    {
        $homePage = Page::query()
            ->with(['site', 'translations'])
            ->get()
            ->first(fn (Page $page) => $page->publicShellPreset() === 'docs' && $page->translations->contains('slug', 'home'));

        if (! $homePage) {
            throw new RuntimeException('Docs Home page not found.');
        }

        $page = Page::query()
            ->with(['translations', 'slots.slotType'])
            ->where('site_id', $homePage->site_id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', Page::defaultLocaleId())
                ->where('slug', 'getting-started'))
            ->first();

        if (! $page) {
            throw new RuntimeException('Getting Started page not found.');
        }

        $mainSlot = $page->slots->first(fn ($slot) => $slot->slotType?->slug === 'main');

        if (! $mainSlot) {
            throw new RuntimeException('Getting Started page main slot not found.');
        }

        $requiredBlockTypeIds = BlockType::query()
            ->whereIn('slug', [
                'section',
                'container',
                'content_header',
                'header',
                'plain_text',
                'button_link',
                'card',
                'alert',
                'code',
                'link-list',
                'link-list-item',
                'grid',
                'cluster',
            ])
            ->get(['id', 'slug', 'status'])
            ->keyBy('slug');

        foreach ([
            'section',
            'container',
            'content_header',
            'header',
            'plain_text',
            'button_link',
            'card',
            'alert',
            'code',
            'link-list',
            'link-list-item',
            'grid',
            'cluster',
        ] as $slug) {
            if (! $requiredBlockTypeIds->has($slug)) {
                throw new RuntimeException("Required block type [{$slug}] is missing.");
            }
        }

        if ($requiredBlockTypeIds['code']->status !== 'published') {
            throw new RuntimeException('Required published block type [code] is missing or inactive.');
        }

        DB::transaction(function () use ($page, $mainSlot, $requiredBlockTypeIds): void {
            $existingImportedBlocks = Block::query()
                ->where('page_id', $page->id)
                ->whereNull('parent_id')
                ->where('slot_type_id', $mainSlot->slot_type_id)
                ->get()
                ->filter(fn (Block $block) => $block->setting('import_group') === self::IMPORT_GROUP)
                ->values();

            foreach ($existingImportedBlocks as $block) {
                $this->deleteBlockTree($block);
            }

            $baseSortOrder = (int) Block::query()
                ->where('page_id', $page->id)
                ->whereNull('parent_id')
                ->where('slot_type_id', $mainSlot->slot_type_id)
                ->max('sort_order');

            $nextSortOrder = Block::query()
                ->where('page_id', $page->id)
                ->whereNull('parent_id')
                ->where('slot_type_id', $mainSlot->slot_type_id)
                ->exists()
                    ? $baseSortOrder + 1
                    : 0;

            foreach ($this->sectionPayloads() as $sectionIndex => $sectionPayload) {
                $this->createPayloadTree(
                    page: $page,
                    parent: null,
                    slotTypeId: $mainSlot->slot_type_id,
                    blockTypeIds: $requiredBlockTypeIds->map(fn ($blockType) => $blockType->id)->all(),
                    sortOrder: $nextSortOrder + $sectionIndex,
                    payload: $sectionPayload,
                );
            }
        });
    }

    private function createPayloadTree(Page $page, ?Block $parent, int $slotTypeId, array $blockTypeIds, int $sortOrder, array $payload): Block
    {
        $type = (string) ($payload['type'] ?? '');

        if ($type === '' || ! isset($blockTypeIds[$type])) {
            throw new RuntimeException('Invalid block payload type.');
        }

        $block = $this->blockPayloadWriter->save(new Block, $page, [
            'page_id' => $page->id,
            'parent_id' => $parent?->id,
            'slot' => SlotType::query()->whereKey($slotTypeId)->value('slug') ?? 'main',
            'slot_type_id' => $slotTypeId,
            'sort_order' => $sortOrder,
            'block_type_id' => $blockTypeIds[$type],
            'status' => 'published',
            'is_system' => false,
            'title' => $payload['title'] ?? null,
            'subtitle' => $payload['subtitle'] ?? null,
            'content' => $payload['content'] ?? null,
            'url' => $payload['url'] ?? null,
            'variant' => $payload['variant'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'settings' => isset($payload['settings']) ? json_encode($payload['settings'], JSON_UNESCAPED_SLASHES) : null,
        ]);

        foreach (array_values($payload['children'] ?? []) as $childIndex => $childPayload) {
            $this->createPayloadTree(
                page: $page,
                parent: $block,
                slotTypeId: $slotTypeId,
                blockTypeIds: $blockTypeIds,
                sortOrder: $childIndex,
                payload: $childPayload,
            );
        }

        return $block;
    }

    private function deleteBlockTree(Block $block): void
    {
        $block->loadMissing('children');

        foreach ($block->children as $child) {
            $this->deleteBlockTree($child);
        }

        $block->delete();
    }

    private function importSection(string $importKey, string $layoutName, array $children): array
    {
        return [
            'type' => 'section',
            'settings' => [
                'import_group' => self::IMPORT_GROUP,
                'import_key' => $importKey,
                'layout_name' => $layoutName,
            ],
            'children' => [
                [
                    'type' => 'container',
                    'settings' => [
                        'width' => 'lg',
                        'import_group' => self::IMPORT_GROUP,
                        'import_key' => $importKey.'-container',
                        'layout_name' => $layoutName.' container',
                    ],
                    'children' => $children,
                ],
            ],
        ];
    }

    private function sectionPayloads(): array
    {
        $assetIncludes = <<<'HTML'
<link rel="stylesheet" href="/packages/webblocks/dist/webblocks-ui.css">
<link rel="stylesheet" href="/packages/webblocks/dist/webblocks-icons.css">
<script src="/packages/webblocks/dist/webblocks-ui.js" defer></script>
HTML;

        $rootThemeSnippet = <<<'HTML'
<html data-mode="auto"
      data-accent="royal"
      data-preset="corporate"
      data-radius="soft"
      data-density="compact">
HTML;

        $markupSnippet = <<<'HTML'
<button class="wb-btn wb-btn-primary">Save</button>
<div class="wb-card">...</div>
<table class="wb-table wb-table-hover">...</table>
HTML;

        $themeSnippet = <<<'HTML'
<button data-wb-mode-set="dark">Dark</button>
<button data-wb-accent-set="forest">Forest</button>
<button data-wb-preset-set="minimal">Minimal</button>
HTML;

        $behaviorSnippet = <<<'HTML'
<button data-wb-toggle="modal" data-wb-target="#demoModal">Open dialog</button>
<button data-wb-toggle="modal" data-wb-target="#mediaViewer">Open viewer modal</button>
<button data-wb-toggle="drawer" data-wb-target="#demoDrawer">Filters</button>
<button data-wb-toggle="cmd" data-wb-target="#siteCmd">Search</button>
HTML;

        return [
            $this->importSection('content-header', 'Getting Started intro', [
                [
                    'type' => 'content_header',
                    'title' => 'Getting Started',
                    'subtitle' => 'Include the built assets, set root theme attributes, and begin from patterns instead of guessing from isolated primitives.',
                    'variant' => 'h1',
                ],
            ]),
            $this->importSection('include-package-files', 'Include the package files', [
                [
                    'type' => 'header',
                    'title' => '1. Include the package files',
                    'variant' => 'h2',
                ],
                [
                    'type' => 'plain_text',
                    'content' => 'The smallest correct setup includes the main CSS and JS bundles. Add the icon CSS only if you want class-based icon usage with <i class="wb-icon wb-icon-*">. The docs and playground in this repo load the same built files from the local published dist path so the examples stay aligned with the shipped package output.',
                ],
                [
                    'type' => 'code',
                    'content' => $assetIncludes,
                    'settings' => ['language' => 'html'],
                ],
                [
                    'type' => 'alert',
                    'title' => 'No extra layer required',
                    'content' => 'You do not need framework wrappers, a custom starter stylesheet, or a separate component runtime to use the package correctly.',
                    'settings' => ['variant' => 'info'],
                ],
                [
                    'type' => 'alert',
                    'title' => 'Mask-image icon path',
                    'content' => 'This guide uses the shipped class-based icon path with webblocks-icons.css and <i class="wb-icon wb-icon-...">. Keep that stylesheet included if you want the same icon behavior.',
                    'settings' => ['variant' => 'info'],
                ],
            ]),
            $this->importSection('set-root-theme-attributes', 'Set root theme attributes', [
                [
                    'type' => 'header',
                    'title' => '2. Set root theme attributes',
                    'variant' => 'h2',
                ],
                [
                    'type' => 'plain_text',
                    'content' => 'Theme axes live on the html element. The package theme module reads and updates these attributes for you.',
                ],
                [
                    'type' => 'code',
                    'content' => $rootThemeSnippet,
                    'settings' => ['language' => 'html'],
                ],
                [
                    'type' => 'grid',
                    'settings' => ['columns' => '2', 'gap' => '4'],
                    'children' => [
                        [
                            'type' => 'card',
                            'title' => 'Mode',
                            'content' => '`light`, `dark`, or `auto`',
                        ],
                        [
                            'type' => 'card',
                            'title' => 'Accent and preset',
                            'content' => 'Accent changes the color system. Preset applies a named bundle of axis values.',
                        ],
                    ],
                ],
            ]),
            $this->importSection('start-from-a-pattern', 'Start from a pattern', [
                [
                    'type' => 'header',
                    'title' => '3. Start from a pattern',
                    'variant' => 'h2',
                ],
                [
                    'type' => 'plain_text',
                    'content' => 'The fastest correct path is not to browse every primitive first. Start from the page job, then inspect the surfaces and primitives that the pattern uses.',
                ],
                [
                    'type' => 'link-list',
                    'children' => [
                        [
                            'type' => 'link-list-item',
                            'title' => 'Dashboard shell',
                            'subtitle' => 'Admin and data-heavy screens',
                            'content' => 'Use this when the page needs sidebar navigation, a topbar, and structured main content.',
                            'url' => 'patterns.html',
                        ],
                        [
                            'type' => 'link-list-item',
                            'title' => 'Content shell',
                            'subtitle' => 'Docs, onboarding, long-form content',
                            'content' => 'Use this when prose, hierarchy, and reading width matter more than dense application chrome.',
                            'url' => 'patterns.html',
                        ],
                    ],
                ],
            ]),
            $this->importSection('copy-the-nearest-example', 'Copy the nearest shipped example', [
                [
                    'type' => 'header',
                    'title' => '4. Copy the nearest shipped example',
                    'variant' => 'h2',
                ],
                [
                    'type' => 'plain_text',
                    'content' => 'The pattern example pages already combine shell choice, hierarchy, surfaces, primitives, and behavior hooks in one place without leaving the docs navigation.',
                ],
                [
                    'type' => 'link-list',
                    'children' => [
                        [
                            'type' => 'link-list-item',
                            'title' => 'Admin dashboard',
                            'subtitle' => 'Topbar, sidebar, stats, tables',
                            'content' => 'Good first copy when the page is an application surface with navigation and dense operational content.',
                            'url' => 'pattern-dashboard-shell.html',
                        ],
                        [
                            'type' => 'link-list-item',
                            'title' => 'Admin settings',
                            'subtitle' => 'Settings sections and destructive flows',
                            'content' => 'Good first copy when the page is a slower settings workflow with grouped fields and secondary descriptions.',
                            'url' => 'pattern-settings-shell.html',
                        ],
                        [
                            'type' => 'link-list-item',
                            'title' => 'Auth login',
                            'subtitle' => 'Narrow, single-task entry screens',
                            'content' => 'Good first copy when the page needs visual quiet and should stay centered around one form task.',
                            'url' => 'pattern-auth-shell.html',
                        ],
                        [
                            'type' => 'link-list-item',
                            'title' => 'Public home',
                            'subtitle' => 'Hero, promo, calmer page rhythm',
                            'content' => 'Good first copy when the page is marketing-led, editorial, or public-facing rather than application-heavy.',
                            'url' => 'pattern-marketing.html',
                        ],
                        [
                            'type' => 'link-list-item',
                            'title' => 'Playground',
                            'subtitle' => 'Test HTML before you integrate',
                            'content' => 'Use this when you already have markup, want to try AI-generated snippets, or need to preview real WebBlocks assets without wiring a full page first.',
                            'url' => '../playground/',
                        ],
                    ],
                ],
            ]),
            $this->importSection('keep-responsibilities-clear', 'Keep responsibilities clear', [
                [
                    'type' => 'header',
                    'title' => '5. Keep responsibilities clear',
                    'variant' => 'h2',
                ],
                [
                    'type' => 'alert',
                    'title' => 'CSS and JS should keep their own jobs',
                    'content' => 'Layout is CSS-driven. Overlays, tooltips, tabs, drawers, theme switching, and command palette behavior are JavaScript-driven through shipped hooks. Do not add JS to fake layout, and do not add CSS to fake missing behavior.',
                    'settings' => ['variant' => 'warning'],
                ],
                [
                    'type' => 'header',
                    'title' => 'Markup',
                    'variant' => 'h3',
                ],
                [
                    'type' => 'code',
                    'title' => 'Structural markup',
                    'content' => $markupSnippet,
                    'settings' => ['language' => 'html'],
                ],
                [
                    'type' => 'header',
                    'title' => 'Theme',
                    'variant' => 'h3',
                ],
                [
                    'type' => 'code',
                    'title' => 'Theme hooks',
                    'content' => $themeSnippet,
                    'settings' => ['language' => 'html'],
                ],
                [
                    'type' => 'header',
                    'title' => 'Behavior',
                    'variant' => 'h3',
                ],
                [
                    'type' => 'code',
                    'title' => 'Behavior hooks',
                    'content' => $behaviorSnippet,
                    'settings' => ['language' => 'html'],
                ],
            ]),
        ];
    }
}
