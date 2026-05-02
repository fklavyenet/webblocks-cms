<?php

namespace Project\Support\UiDocs;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Support\Blocks\BlockPayloadWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncUiDocsHomeMain
{
    private const SLOT = 'main';

    private const IMPORT_GROUP = 'webblocks-ui-docs-home-main';

    private const ANCHOR_LAYOUT = 'Read the system';

    public function __construct(private readonly BlockPayloadWriter $blockPayloadWriter) {}

    public function run(): void
    {
        $page = Page::query()
            ->with(['translations', 'blocks.blockType'])
            ->whereHas('translations', fn ($query) => $query->where('slug', 'home'))
            ->first();

        if (! $page) {
            throw new RuntimeException('Home page not found.');
        }

        $container = Block::query()
            ->where('page_id', $page->id)
            ->whereNull('parent_id')
            ->where('slot', self::SLOT)
            ->whereHas('blockType', fn ($query) => $query->where('slug', 'container'))
            ->orderBy('sort_order')
            ->first();

        if (! $container) {
            throw new RuntimeException('Home page main container not found.');
        }

        $blockTypeIds = BlockType::query()
            ->whereIn('slug', ['section', 'header', 'plain_text', 'grid', 'card', 'link-list', 'link-list-item'])
            ->pluck('id', 'slug');

        foreach (['section', 'header', 'plain_text', 'grid', 'card', 'link-list', 'link-list-item'] as $slug) {
            if (! $blockTypeIds->has($slug)) {
                throw new RuntimeException("Required block type [{$slug}] is missing.");
            }
        }

        DB::transaction(function () use ($page, $container, $blockTypeIds): void {
            $anchorSection = Block::query()
                ->where('page_id', $page->id)
                ->where('parent_id', $container->id)
                ->where('slot', self::SLOT)
                ->whereHas('blockType', fn ($query) => $query->where('slug', 'section'))
                ->get()
                ->first(fn (Block $block) => $block->setting('layout_name') === self::ANCHOR_LAYOUT);

            if (! $anchorSection) {
                throw new RuntimeException('Anchor section [Read the system] not found in Home page main slot.');
            }

            $existingImportedSections = Block::query()
                ->where('page_id', $page->id)
                ->where('parent_id', $container->id)
                ->where('slot', self::SLOT)
                ->whereHas('blockType', fn ($query) => $query->where('slug', 'section'))
                ->get()
                ->filter(fn (Block $block) => $block->setting('import_group') === self::IMPORT_GROUP)
                ->sortBy('sort_order')
                ->values();

            $baseSortOrder = (int) $anchorSection->sort_order;

            foreach ($existingImportedSections as $section) {
                $section->delete();
            }

            $siblingsToShift = Block::query()
                ->where('page_id', $page->id)
                ->where('parent_id', $container->id)
                ->where('slot', self::SLOT)
                ->where('sort_order', '>', $baseSortOrder)
                ->orderBy('sort_order')
                ->get();

            foreach ($siblingsToShift as $offset => $sibling) {
                $sibling->update(['sort_order' => $baseSortOrder + 4 + $offset]);
            }

            foreach ($this->sectionPayloads() as $sectionIndex => $sectionPayload) {
                $section = $this->createBlock(
                    page: $page,
                    parent: $container,
                    blockTypeId: $blockTypeIds['section'],
                    sortOrder: $baseSortOrder + 1 + $sectionIndex,
                    payload: [
                        'settings' => [
                            'layout_name' => $sectionPayload['layout_name'],
                            'import_group' => self::IMPORT_GROUP,
                            'import_key' => $sectionPayload['import_key'],
                        ],
                    ],
                );

                foreach ($sectionPayload['children'] as $childIndex => $childPayload) {
                    $child = $this->createBlock(
                        page: $page,
                        parent: $section,
                        blockTypeId: $blockTypeIds[$childPayload['type']],
                        sortOrder: $childIndex,
                        payload: Arr::except($childPayload, ['type', 'children']),
                    );

                    foreach ($childPayload['children'] ?? [] as $grandchildIndex => $grandchildPayload) {
                        $this->createBlock(
                            page: $page,
                            parent: $child,
                            blockTypeId: $blockTypeIds[$grandchildPayload['type']],
                            sortOrder: $grandchildIndex,
                            payload: Arr::except($grandchildPayload, ['type']),
                        );
                    }
                }
            }
        });
    }

    private function createBlock(Page $page, Block $parent, int $blockTypeId, int $sortOrder, array $payload): Block
    {
        $settings = $payload['settings'] ?? null;

        return $this->blockPayloadWriter->save(new Block, $page, [
            'page_id' => $page->id,
            'parent_id' => $parent->id,
            'slot' => self::SLOT,
            'slot_type_id' => $parent->slot_type_id,
            'sort_order' => $sortOrder,
            'block_type_id' => $blockTypeId,
            'status' => 'published',
            'is_system' => false,
            'title' => $payload['title'] ?? null,
            'subtitle' => $payload['subtitle'] ?? null,
            'content' => $payload['content'] ?? null,
            'url' => $payload['url'] ?? null,
            'variant' => $payload['variant'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'settings' => $settings === null ? null : json_encode($settings, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function sectionPayloads(): array
    {
        return [
            [
                'import_key' => 'start-from-real-shipped-example',
                'layout_name' => 'Start from a real shipped example',
                'children' => [
                    [
                        'type' => 'header',
                        'title' => 'Start from a real shipped example',
                        'variant' => 'h2',
                    ],
                    [
                        'type' => 'plain_text',
                        'content' => 'When the page job is already clear, copying the nearest pattern example from this guide is safer than rebuilding the screen from memory. Start with the whole structure, replace the content, then trim aggressively.',
                    ],
                    [
                        'type' => 'link-list',
                        'children' => [
                            [
                                'type' => 'link-list-item',
                                'title' => 'Admin dashboard',
                                'subtitle' => 'Sidebar, topbar, stats, tables',
                                'content' => 'Use this when the page is application-heavy and needs navigation, metrics, row actions, and scan-friendly data.',
                                'url' => 'pattern-dashboard-shell.html',
                            ],
                            [
                                'type' => 'link-list-item',
                                'title' => 'Admin settings',
                                'subtitle' => 'Longer forms and sectioned actions',
                                'content' => 'Use this when the page is driven by grouped settings, destructive actions, and slower form workflows.',
                                'url' => 'pattern-settings-shell.html',
                            ],
                            [
                                'type' => 'link-list-item',
                                'title' => 'Auth login',
                                'subtitle' => 'Focused entry flow',
                                'content' => 'Use this when the screen should stay narrow, quiet, and centered around a single form task.',
                                'url' => 'pattern-auth-shell.html',
                            ],
                            [
                                'type' => 'link-list-item',
                                'title' => 'Public home',
                                'subtitle' => 'Hero rhythm and promotional surfaces',
                                'content' => 'Use this when the page is marketing-led and needs calmer content flow instead of admin density.',
                                'url' => 'pattern-marketing.html',
                            ],
                            [
                                'type' => 'link-list-item',
                                'title' => 'Content shell',
                                'subtitle' => 'Docs, onboarding, and text-first flows',
                                'content' => 'Use this when the page needs structure, references, and readable progression rather than product chrome.',
                                'url' => 'pattern-content-shell.html',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'import_key' => 'useful-tools-while-you-build',
                'layout_name' => 'Useful tools while you build',
                'children' => [
                    [
                        'type' => 'header',
                        'title' => 'Useful tools while you build',
                        'variant' => 'h2',
                    ],
                    [
                        'type' => 'link-list',
                        'children' => [
                            [
                                'type' => 'link-list-item',
                                'title' => 'Playground',
                                'subtitle' => 'Live HTML testing with real assets',
                                'content' => 'Use this when you want to paste plain HTML, test wb-* classes quickly, and visually validate AI-generated snippets in an isolated preview.',
                                'url' => '../playground/',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'import_key' => 'core-principles',
                'layout_name' => 'Core principles',
                'children' => [
                    [
                        'type' => 'header',
                        'title' => 'Core principles',
                        'variant' => 'h2',
                    ],
                    [
                        'type' => 'grid',
                        'settings' => [
                            'layout_name' => 'Core principles grid',
                            'columns' => '3',
                        ],
                        'children' => [
                            [
                                'type' => 'card',
                                'title' => 'HTML stays HTML',
                                'content' => 'WebBlocks does not require framework wrappers to be useful. You write explicit markup and attach shipped classes.',
                            ],
                            [
                                'type' => 'card',
                                'title' => 'Pattern-first workflow',
                                'content' => 'Start from the page job, then inspect the surfaces and primitives used by that pattern, then refine with utilities.',
                            ],
                            [
                                'type' => 'card',
                                'title' => 'Small correct layers',
                                'content' => 'Tokens stay in foundation, structure stays in layout, controls stay in primitives, framed content regions stay in surfaces, and full screens stay in patterns.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
