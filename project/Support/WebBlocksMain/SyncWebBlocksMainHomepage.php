<?php

namespace Project\Support\WebBlocksMain;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockPayloadWriter;
use Illuminate\Support\Facades\DB;
use Project\Support\UiDocs\SetupWebBlocksUiDocsSite;
use RuntimeException;

class SyncWebBlocksMainHomepage
{
    private const SITE_DOMAIN = 'webblocksui.com';

    private const SITE_NAME = 'webblocksui.com';

    private const SITE_HANDLE = 'webblocksuicom';

    private const DOCS_SITE_DOMAIN = 'ui.webblocksui.com';

    private const DOCS_SITE_NAME = 'ui.webblocksui.com';

    private const DOCS_SITE_HANDLE = 'uiwebblocksuicom';

    private const PAGE_KEY = 'webblocks-main-home';

    private const PROJECT_SOURCE = 'webblocks-main-site';

    private const SLOT_MAIN = 'main';

    private const IMPORT_GROUP = 'webblocks-main-homepage';

    public function __construct(private readonly BlockPayloadWriter $blockPayloadWriter) {}

    public function run(): array
    {
        $defaultLocale = Locale::query()->where('is_default', true)->first();

        if (! $defaultLocale) {
            throw new RuntimeException('Default locale is not configured.');
        }

        $site = $this->resolveTargetSite();
        $docsSite = $this->resolveDocsSite();
        $mainSlotType = $this->resolveMainSlotType();
        $blockTypeIds = $this->resolveBlockTypeIds();
        $docsVisiblePageCountBefore = $this->visiblePageCount($docsSite);

        $result = DB::transaction(function () use ($defaultLocale, $site, $mainSlotType, $blockTypeIds): array {
            $site->locales()->syncWithoutDetaching([
                $defaultLocale->id => ['is_enabled' => true],
            ]);

            $page = $this->syncPage($site, $defaultLocale);
            $slot = $this->syncMainSlot($page, $mainSlotType);
            $createdBlocks = $this->syncBlocks($page, $slot, $blockTypeIds);
            $navigationCreated = $this->syncNavigation($site, $page);

            return [
                'page' => $page->fresh(['translations', 'slots.slotType', 'site']),
                'created_blocks' => $createdBlocks,
                'navigation_created' => $navigationCreated,
            ];
        });

        $page = $result['page'];
        $docsVisiblePageCountAfter = $this->visiblePageCount($docsSite);

        if ($docsVisiblePageCountBefore !== $docsVisiblePageCountAfter) {
            throw new RuntimeException('The docs site page count changed unexpectedly during homepage sync.');
        }

        return [
            'Target site: '.$site->id.' '.$site->name.' ('.$site->handle.')',
            'Homepage page: '.$page->id.' '.$page->defaultTranslation()?->name,
            'Homepage status: '.$page->status,
            'Homepage path: '.($page->publicPath() ?? '/'),
            'Homepage local preview URL: '.$this->localPreviewUrl($site, $page->publicPath() ?? '/'),
            'Blocks synced: '.$result['created_blocks'],
            'Primary navigation synced: '.($result['navigation_created'] ? 'yes' : 'no'),
            'Docs site visible pages unchanged: '.$docsVisiblePageCountAfter,
        ];
    }

    private function resolveTargetSite(): Site
    {
        $candidates = Site::query()
            ->where(function ($query): void {
                $query->where('domain', self::SITE_DOMAIN)
                    ->orWhere('name', self::SITE_NAME)
                    ->orWhere('handle', self::SITE_HANDLE);
            })
            ->orderByRaw('case when domain = ? then 0 when name = ? then 1 when handle = ? then 2 else 3 end', [self::SITE_DOMAIN, self::SITE_NAME, self::SITE_HANDLE])
            ->get();

        if ($candidates->isEmpty()) {
            throw new RuntimeException('Site [webblocksui.com] was not found.');
        }

        $exact = $candidates->filter(fn (Site $site) => $site->domain === self::SITE_DOMAIN || $site->name === self::SITE_NAME || $site->handle === self::SITE_HANDLE);

        if ($exact->count() > 1) {
            throw new RuntimeException('Multiple site records could match [webblocksui.com]. Refusing to write content to an ambiguous site.');
        }

        return $exact->first() ?? $candidates->firstOrFail();
    }

    private function resolveDocsSite(): Site
    {
        $site = Site::query()
            ->where(function ($query): void {
                $query->where('domain', self::DOCS_SITE_DOMAIN)
                    ->orWhere('name', self::DOCS_SITE_NAME)
                    ->orWhere('handle', self::DOCS_SITE_HANDLE);
            })
            ->first();

        if (! $site) {
            throw new RuntimeException('Docs site [ui.webblocksui.com] was not found.');
        }

        return $site;
    }

    private function resolveMainSlotType(): SlotType
    {
        $slotType = SlotType::query()->where('slug', self::SLOT_MAIN)->first();

        if (! $slotType) {
            throw new RuntimeException('Required slot type [main] is missing.');
        }

        return $slotType;
    }

    private function resolveBlockTypeIds(): array
    {
        $slugs = ['section', 'container', 'grid', 'header', 'plain_text', 'button_link', 'card', 'link-list', 'link-list-item'];
        $ids = BlockType::query()->whereIn('slug', $slugs)->pluck('id', 'slug')->all();

        foreach ($slugs as $slug) {
            if (! array_key_exists($slug, $ids)) {
                throw new RuntimeException("Required block type [{$slug}] is missing.");
            }
        }

        return $ids;
    }

    private function syncPage(Site $site, Locale $defaultLocale): Page
    {
        $page = Page::query()
            ->with('translations')
            ->where('site_id', $site->id)
            ->get()
            ->first(function (Page $candidate): bool {
                if ($candidate->setting('project_source') === self::PROJECT_SOURCE && $candidate->setting('project_page_key') === self::PAGE_KEY) {
                    return true;
                }

                return $candidate->translations->contains(function (PageTranslation $translation): bool {
                    return $translation->slug === 'home' || $translation->path === '/';
                });
            }) ?? new Page;

        $page->fill([
            'site_id' => $site->id,
            'page_type' => Page::TYPE_DEFAULT,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => array_merge(
                is_array($page->settings) ? $page->settings : [],
                [
                    'public_shell' => 'default',
                    'project_source' => self::PROJECT_SOURCE,
                    'project_page_key' => self::PAGE_KEY,
                ],
            ),
        ]);

        if (! $page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        PageTranslation::query()->updateOrCreate(
            [
                'page_id' => $page->id,
                'locale_id' => $defaultLocale->id,
            ],
            [
                'site_id' => $site->id,
                'name' => 'WebBlocks',
                'slug' => 'home',
                'path' => '/',
            ],
        );

        return $page->fresh(['translations', 'slots.slotType']);
    }

    private function syncMainSlot(Page $page, SlotType $slotType): PageSlot
    {
        $duplicates = PageSlot::query()
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slotType->id)
            ->orderBy('id')
            ->get();

        $slot = $duplicates->shift() ?? new PageSlot;

        foreach ($duplicates as $duplicate) {
            $duplicate->delete();
        }

        $slot->fill([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'shared_slot_id' => null,
            'sort_order' => 0,
            'settings' => null,
        ]);
        $slot->save();

        return $slot;
    }

    private function syncBlocks(Page $page, PageSlot $slot, array $blockTypeIds): int
    {
        $existingRoots = Block::query()
            ->where('page_id', $page->id)
            ->whereNull('parent_id')
            ->where('slot', self::SLOT_MAIN)
            ->where('slot_type_id', $slot->slot_type_id)
            ->get();

        foreach ($existingRoots as $block) {
            $group = (string) $block->setting('import_group', '');
            $isManaged = $group === self::IMPORT_GROUP || $block->setting('project_page_key') === self::PAGE_KEY;

            if (! $isManaged) {
                throw new RuntimeException('The target homepage already has unmanaged main-slot content. Refusing to overwrite it.');
            }
        }

        foreach ($existingRoots as $block) {
            $block->delete();
        }

        foreach ($this->blockTree() as $index => $payload) {
            $this->createBlock(
                page: $page,
                parent: null,
                slotTypeId: $slot->slot_type_id,
                blockTypeId: $blockTypeIds[$payload['type']],
                sortOrder: $index,
                payload: $payload
            );
        }

        return count($this->flattenTree($this->blockTree()));
    }

    private function syncNavigation(Site $site, Page $page): bool
    {
        NavigationItem::query()
            ->forSite($site)
            ->forMenu(NavigationItem::MENU_PRIMARY)
            ->where(function ($query) use ($page): void {
                $query->where('is_system', true)
                    ->orWhere('url', 'https://ui.webblocksui.com')
                    ->orWhere('url', 'https://cms.webblocksui.com')
                    ->orWhere('page_id', $page->id ?? 0);
            })
            ->delete();

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Home',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $page->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => true,
        ]);

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'WebBlocks UI',
            'link_type' => NavigationItem::LINK_CUSTOM_URL,
            'url' => 'https://ui.webblocksui.com',
            'target' => '_blank',
            'position' => 2,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => true,
        ]);

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'WebBlocks CMS',
            'link_type' => NavigationItem::LINK_CUSTOM_URL,
            'url' => 'https://cms.webblocksui.com',
            'target' => '_blank',
            'position' => 3,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => true,
        ]);

        return true;
    }

    private function createBlock(Page $page, ?Block $parent, int $slotTypeId, int $blockTypeId, int $sortOrder, array $payload): Block
    {
        $settings = [
            'import_group' => self::IMPORT_GROUP,
            'project_source' => self::PROJECT_SOURCE,
            'project_page_key' => self::PAGE_KEY,
        ];

        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $settings = array_merge($settings, $payload['settings']);
        }

        $block = $this->blockPayloadWriter->save(new Block, $page, [
            'type' => $payload['type'] ?? null,
            'page_id' => $page->id,
            'parent_id' => $parent?->id,
            'slot' => self::SLOT_MAIN,
            'slot_type_id' => $slotTypeId,
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
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
        ]);

        foreach (($payload['children'] ?? []) as $index => $childPayload) {
            $this->createBlock(
                page: $page,
                parent: $block,
                slotTypeId: $slotTypeId,
                blockTypeId: BlockType::query()->where('slug', $childPayload['type'])->value('id'),
                sortOrder: $index,
                payload: $childPayload,
            );
        }

        return $block;
    }

    private function blockTree(): array
    {
        return [
            [
                'type' => 'container',
                'settings' => ['layout_name' => 'Homepage content'],
                'children' => [
                    [
                        'type' => 'section',
                        'settings' => ['layout_name' => 'Hero'],
                        'children' => [
                            [
                                'type' => 'header',
                                'subtitle' => 'WebBlocks',
                                'title' => 'A shared foundation for modern web products.',
                                'variant' => 'h1',
                            ],
                            [
                                'type' => 'plain_text',
                                'content' => 'WebBlocks brings together UI patterns, CMS tooling, hosting workflows, publishing infrastructure, and product documentation under one ecosystem.',
                            ],
                            [
                                'type' => 'grid',
                                'settings' => ['layout_name' => 'Hero actions', 'columns' => '2'],
                                'children' => [
                                    [
                                        'type' => 'button_link',
                                        'title' => 'WebBlocks UI Documentation',
                                        'settings' => ['url' => 'https://ui.webblocksui.com', 'target' => '_blank'],
                                    ],
                                    [
                                        'type' => 'button_link',
                                        'title' => 'WebBlocks CMS',
                                        'settings' => ['url' => 'https://cms.webblocksui.com', 'target' => '_blank'],
                                        'variant' => 'secondary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'settings' => ['layout_name' => 'Reorganization status'],
                        'children' => [
                            [
                                'type' => 'header',
                                'title' => 'WebBlocks is being reorganized.',
                                'variant' => 'h2',
                            ],
                            [
                                'type' => 'plain_text',
                                'content' => 'The main WebBlocks site is moving to this CMS. During the transition, the WebBlocks UI documentation is available at ui.webblocksui.com.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'settings' => ['layout_name' => 'Products'],
                        'children' => [
                            [
                                'type' => 'header',
                                'title' => 'Products in the WebBlocks ecosystem',
                                'variant' => 'h2',
                            ],
                            [
                                'type' => 'grid',
                                'settings' => ['layout_name' => 'Product grid', 'columns' => '3'],
                                'children' => [
                                    [
                                        'type' => 'card',
                                        'title' => 'WebBlocks UI',
                                        'content' => 'CSS, JavaScript, icon primitives, and interface patterns for building consistent WebBlocks-powered products.',
                                        'meta' => 'Open site',
                                        'settings' => ['url' => 'https://ui.webblocksui.com', 'target' => '_blank'],
                                    ],
                                    [
                                        'type' => 'card',
                                        'title' => 'WebBlocks CMS',
                                        'content' => 'A multisite, multilingual, block-based CMS for managing WebBlocks sites, pages, shared slots, media, navigation, search, and publishing workflows.',
                                        'meta' => 'Open site',
                                        'settings' => ['url' => 'https://cms.webblocksui.com', 'target' => '_blank'],
                                    ],
                                    [
                                        'type' => 'card',
                                        'title' => 'Herne Panel',
                                        'content' => 'Server and hosting control panel work for managing projects, domains, deployment, and operational settings.',
                                        'meta' => 'Coming soon',
                                        'settings' => ['url' => '#'],
                                    ],
                                    [
                                        'type' => 'card',
                                        'title' => 'WebBlocks Publisher',
                                        'content' => 'Release and update publishing tools for distributing WebBlocks product packages.',
                                        'meta' => 'Coming soon',
                                        'settings' => ['url' => '#'],
                                    ],
                                    [
                                        'type' => 'card',
                                        'title' => 'Wesend',
                                        'content' => 'Email delivery and control-plane work for domains, verification, delivery status, retries, and webhooks.',
                                        'meta' => 'Coming soon',
                                        'settings' => ['url' => '#'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'settings' => ['layout_name' => 'Docs'],
                        'children' => [
                            [
                                'type' => 'header',
                                'title' => 'Start with WebBlocks UI',
                                'variant' => 'h2',
                            ],
                            [
                                'type' => 'plain_text',
                                'content' => 'The UI documentation is the first public WebBlocks site being migrated into the CMS. It remains available while the main WebBlocks site is prepared.',
                            ],
                            [
                                'type' => 'button_link',
                                'title' => 'Open UI Docs',
                                'settings' => ['url' => 'https://ui.webblocksui.com', 'target' => '_blank'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'settings' => ['layout_name' => 'Footer'],
                        'children' => [
                            [
                                'type' => 'plain_text',
                                'content' => 'WebBlocks is maintained by Fklavye.',
                            ],
                            [
                                'type' => 'link-list',
                                'children' => [
                                    [
                                        'type' => 'link-list-item',
                                        'title' => 'WebBlocks UI',
                                        'url' => 'https://ui.webblocksui.com',
                                    ],
                                    [
                                        'type' => 'link-list-item',
                                        'title' => 'Fklavye',
                                        'url' => 'https://fklavye.net',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function flattenTree(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[] = $node;

            if (is_array($node['children'] ?? null)) {
                array_push($flat, ...$this->flattenTree($node['children']));
            }
        }

        return $flat;
    }

    private function visiblePageCount(Site $site): int
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->visibleInAdmin()
            ->count();
    }

    private function localPreviewUrl(Site $site, string $path): string
    {
        return SetupWebBlocksUiDocsSite::previewUrlForPath($path, $site);
    }
}
