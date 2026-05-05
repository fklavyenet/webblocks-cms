<?php

namespace Project\Support\UiDocs;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockPayloadWriter;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WebBlocksUiImporter
{
    private const SOURCE = 'webblocksui.com';

    private const STORAGE_ROOT = 'storage/project/webblocksui.com';

    private const IMPORT_GROUP = 'webblocksui-project-import';

    public function __construct(private readonly BlockPayloadWriter $blockPayloadWriter) {}

    public function run(string $key): array
    {
        $manifest = $this->loadJson(base_path(self::STORAGE_ROOT.'/manifest.json'));
        $payloadMeta = Arr::get($manifest, 'payloads.'.$key);

        if (! is_array($payloadMeta)) {
            throw new RuntimeException("Unknown WebBlocks UI payload key [{$key}].");
        }

        $payloadFile = trim((string) ($payloadMeta['file'] ?? ''));

        if ($payloadFile === '') {
            throw new RuntimeException("Manifest entry for [{$key}] is missing a payload file.");
        }

        $payload = $this->loadJson(base_path(self::STORAGE_ROOT.'/'.$payloadFile));
        $pagePayload = $payload['page'] ?? null;

        if (! is_array($pagePayload) || ($pagePayload['key'] ?? null) !== $key) {
            throw new RuntimeException("Payload [{$payloadFile}] does not match requested key [{$key}].");
        }

        $sourceUrl = trim((string) ($pagePayload['source_url'] ?? ''));

        if ($sourceUrl !== 'https://webblocksui.com/docs/architecture.html') {
            throw new RuntimeException("Payload [{$key}] does not point at the verified WebBlocks UI Architecture source page.");
        }

        $site = $this->resolveSite($payload['site'] ?? []);
        $localeMap = $this->resolveLocales($site, $pagePayload['translations'] ?? []);
        $defaultLocaleCode = $this->defaultLocaleCode($pagePayload['translations'] ?? []);
        $requiredBlockTypes = $this->resolveRequiredBlockTypes($pagePayload);
        $slotTypes = $this->resolveSlotTypes($pagePayload);
        $docsHomePage = $this->resolveDocsHomePage($site);

        if (! $this->docsSidebarNavigationBlocks($site)->count()) {
            throw new RuntimeException('Docs sidebar navigation block not found for the target site. Import the docs home/sidebar dependency before importing this page.');
        }

        $result = [];

        DB::transaction(function () use (
            $site,
            $pagePayload,
            $key,
            $defaultLocaleCode,
            $localeMap,
            $requiredBlockTypes,
            $slotTypes,
            $docsHomePage,
            &$result,
            $payload
        ): void {
            $page = $this->syncPage($site, $pagePayload, $key, $localeMap);
            $pageRefs = $this->resolvePageRefs($site, $docsHomePage, $page, $key);

            $this->syncSlots($page, $pagePayload, $slotTypes, $site);
            $this->syncPageBlocks($page, $pagePayload, $defaultLocaleCode, $localeMap, $requiredBlockTypes, $slotTypes);
            $navigationCount = $this->syncNavigation($site, $payload['navigation'] ?? [], $pageRefs);
            $sidebarNavigationBlocks = $this->syncSidebarNavigationBlocks($site);

            $result[] = 'Imported payload key: '.$key;
            $result[] = 'Canonical site domain: '.SetupWebBlocksUiDocsSite::canonicalDomain();
            $result[] = 'Resolved local site domain: '.$site->domain;
            $result[] = 'Page: '.($page->defaultTranslation()?->name ?? $page->id).' ('.$page->publicPath().')';
            $result[] = 'Navigation items synced: '.$navigationCount;
            $result[] = 'Sidebar navigation blocks updated: '.$sidebarNavigationBlocks;
            if ($key === 'docs-architecture') {
                $result[] = 'Architecture local preview URL: '.$this->localPreviewUrl($site, $page->publicPath() ?? SetupWebBlocksUiDocsSite::ARCHITECTURE_PATH);
            }
        });

        return $result;
    }

    private function syncPage(Site $site, array $pagePayload, string $key, array $localeMap): Page
    {
        $page = Page::query()
            ->with('translations')
            ->where('site_id', $site->id)
            ->get()
            ->first(function (Page $candidate) use ($key, $pagePayload): bool {
                if ($candidate->setting('project_source') === self::SOURCE && $candidate->setting('project_page_key') === $key) {
                    return true;
                }

                return collect($pagePayload['translations'] ?? [])->contains(function (array $translation) use ($candidate): bool {
                    $slug = trim((string) ($translation['slug'] ?? ''));

                    return $slug !== '' && $candidate->translations->contains('slug', $slug);
                });
            }) ?? new Page;

        $page->fill([
            'site_id' => $site->id,
            'page_type' => $pagePayload['page_type'] ?? Page::TYPE_DEFAULT,
            'status' => $pagePayload['status'] ?? Page::STATUS_PUBLISHED,
            'settings' => array_merge(
                is_array($page->settings) ? $page->settings : [],
                [
                    'public_shell' => $pagePayload['public_shell'] ?? 'docs',
                    'project_source' => self::SOURCE,
                    'project_page_key' => $key,
                    'requested_public_path' => $pagePayload['requested_public_path'] ?? null,
                    'current_public_path' => $pagePayload['current_public_path'] ?? null,
                    'source_url' => $pagePayload['source_url'] ?? null,
                ],
            ),
        ]);

        if ($page->status === Page::STATUS_PUBLISHED && ! $page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        foreach ($pagePayload['translations'] ?? [] as $localeCode => $translation) {
            $locale = $localeMap[$localeCode] ?? null;

            if (! $locale instanceof Locale) {
                continue;
            }

            $name = trim((string) ($translation['name'] ?? ''));
            $slug = trim((string) ($translation['slug'] ?? ''));

            if ($name === '' || $slug === '') {
                throw new RuntimeException("Page translation [{$localeCode}] is missing a name or slug.");
            }

            PageTranslation::query()->updateOrCreate(
                [
                    'page_id' => $page->id,
                    'locale_id' => $locale->id,
                ],
                [
                    'site_id' => $site->id,
                    'name' => $name,
                    'slug' => $slug,
                    'path' => PageTranslation::pathFromSlug($slug),
                ],
            );
        }

        return $page->fresh(['translations', 'slots.slotType']);
    }

    private function syncSlots(Page $page, array $pagePayload, array $slotTypes, Site $site): void
    {
        foreach ($pagePayload['slots'] ?? [] as $slotSlug => $slotPayload) {
            $slotType = $slotTypes[$slotSlug] ?? null;

            if (! $slotType instanceof SlotType) {
                throw new RuntimeException("Slot type [{$slotSlug}] is missing.");
            }

            $duplicateSlots = PageSlot::query()
                ->where('page_id', $page->id)
                ->where('slot_type_id', $slotType->id)
                ->orderBy('id')
                ->get();

            $slot = $duplicateSlots->shift() ?? new PageSlot;

            foreach ($duplicateSlots as $duplicateSlot) {
                $duplicateSlot->delete();
            }

            $sourceType = PageSlot::normalizeRuntimeSourceType($slotPayload['source_type'] ?? PageSlot::SOURCE_TYPE_PAGE);
            $sharedSlotId = null;

            if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
                $sharedSlotHandle = trim((string) ($slotPayload['shared_slot_handle'] ?? ''));

                if ($sharedSlotHandle === '') {
                    throw new RuntimeException("Page slot [{$slotSlug}] requires a shared_slot_handle.");
                }

                $sharedSlotId = SharedSlot::query()
                    ->where('site_id', $site->id)
                    ->where('handle', $sharedSlotHandle)
                    ->value('id');

                if (! $sharedSlotId) {
                    throw new RuntimeException("Shared slot [{$sharedSlotHandle}] was not found for slot [{$slotSlug}].");
                }
            }

            $slot->fill([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'source_type' => $sourceType,
                'shared_slot_id' => $sharedSlotId,
                'sort_order' => (int) ($slotPayload['sort_order'] ?? 0),
                'settings' => PageSlot::sanitizeSettings($slotPayload['settings'] ?? null),
            ]);
            $slot->save();
        }
    }

    private function syncPageBlocks(Page $page, array $pagePayload, string $defaultLocaleCode, array $localeMap, array $blockTypeIds, array $slotTypes): void
    {
        foreach ($pagePayload['slots'] ?? [] as $slotSlug => $slotPayload) {
            $slotType = $slotTypes[$slotSlug] ?? null;

            if (! $slotType instanceof SlotType) {
                continue;
            }

            $existingImportedBlocks = Block::query()
                ->where('page_id', $page->id)
                ->whereNull('parent_id')
                ->where('slot_type_id', $slotType->id)
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (Block $block) => $block->setting('project_source') === self::SOURCE && $block->setting('project_page_key') === $pagePayload['key'])
                ->values();

            foreach ($existingImportedBlocks as $block) {
                $this->deleteBlockTree($block);
            }

            $nextSortOrder = Block::query()
                ->where('page_id', $page->id)
                ->whereNull('parent_id')
                ->where('slot_type_id', $slotType->id)
                ->max('sort_order');
            $nextSortOrder = $nextSortOrder === null ? 0 : ((int) $nextSortOrder + 1);

            foreach (array_values($slotPayload['blocks'] ?? []) as $index => $blockPayload) {
                $this->createPayloadTree(
                    page: $page,
                    parent: null,
                    slotSlug: $slotSlug,
                    slotTypeId: $slotType->id,
                    blockTypeIds: $blockTypeIds,
                    payload: $blockPayload,
                    defaultLocaleCode: $defaultLocaleCode,
                    localeMap: $localeMap,
                    sortOrder: $nextSortOrder + $index,
                    pageKey: (string) $pagePayload['key'],
                );
            }
        }
    }

    private function createPayloadTree(
        Page $page,
        ?Block $parent,
        string $slotSlug,
        int $slotTypeId,
        array $blockTypeIds,
        array $payload,
        string $defaultLocaleCode,
        array $localeMap,
        int $sortOrder,
        string $pageKey,
    ): Block {
        $type = trim((string) ($payload['type'] ?? ''));
        $blockKey = trim((string) ($payload['key'] ?? ''));

        if ($type === '' || ! isset($blockTypeIds[$type])) {
            throw new RuntimeException('Project payload references an unknown block type.');
        }

        if ($blockKey === '') {
            throw new RuntimeException('Project payload contains a block without a stable key.');
        }

        $defaultTranslation = $payload['translations'][$defaultLocaleCode] ?? [];
        $settings = array_merge(
            is_array($payload['settings'] ?? null) ? $payload['settings'] : [],
            [
                'project_source' => self::SOURCE,
                'project_import_group' => self::IMPORT_GROUP,
                'project_page_key' => $pageKey,
                'project_block_key' => $blockKey,
            ],
        );

        $block = $this->blockPayloadWriter->save(new Block, $page, array_filter([
            'page_id' => $page->id,
            'parent_id' => $parent?->id,
            'slot' => $slotSlug,
            'slot_type_id' => $slotTypeId,
            'sort_order' => $sortOrder,
            'block_type_id' => $blockTypeIds[$type],
            'status' => $payload['status'] ?? Block::query()->getModel()->getAttribute('status') ?? 'published',
            'is_system' => false,
            'variant' => $payload['variant'] ?? null,
            'url' => $payload['url'] ?? null,
            'meta' => $defaultTranslation['meta'] ?? ($payload['meta'] ?? null),
            'title' => $defaultTranslation['title'] ?? null,
            'subtitle' => $defaultTranslation['subtitle'] ?? null,
            'content' => $defaultTranslation['content'] ?? null,
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
            'type' => $type,
        ], fn ($value, $field) => ! ($field === 'variant' && $value === null), ARRAY_FILTER_USE_BOTH), $defaultLocaleCode);

        foreach ($payload['translations'] ?? [] as $localeCode => $translation) {
            if ($localeCode === $defaultLocaleCode) {
                continue;
            }

            if (! isset($localeMap[$localeCode])) {
                throw new RuntimeException("Block translation locale [{$localeCode}] is not enabled for the target site.");
            }

            $this->blockPayloadWriter->save($block, $page, [
                'type' => $type,
                'title' => $translation['title'] ?? null,
                'subtitle' => $translation['subtitle'] ?? null,
                'content' => $translation['content'] ?? null,
                'meta' => $translation['meta'] ?? null,
            ], $localeCode);
        }

        foreach (array_values($payload['children'] ?? []) as $index => $childPayload) {
            $this->createPayloadTree(
                page: $page,
                parent: $block,
                slotSlug: $slotSlug,
                slotTypeId: $slotTypeId,
                blockTypeIds: $blockTypeIds,
                payload: $childPayload,
                defaultLocaleCode: $defaultLocaleCode,
                localeMap: $localeMap,
                sortOrder: $index,
                pageKey: $pageKey,
            );
        }

        return $block;
    }

    private function syncNavigation(Site $site, array $navigationPayload, array $pageRefs): int
    {
        $menuKey = trim((string) ($navigationPayload['menu_key'] ?? NavigationItem::MENU_DOCS));
        $items = $navigationPayload['items'] ?? null;

        if (! is_array($items)) {
            throw new RuntimeException('Navigation payload is missing items.');
        }

        NavigationItem::query()->forSite($site->id)->forMenu($menuKey)->delete();

        $count = 0;

        foreach (array_values($items) as $index => $itemPayload) {
            $this->createNavigationItem($site, null, $menuKey, $itemPayload, $pageRefs, $count, $index);
        }

        return $count;
    }

    private function createNavigationItem(
        Site $site,
        ?NavigationItem $parent,
        string $menuKey,
        array $payload,
        array $pageRefs,
        int &$count,
        int $defaultPosition,
    ): NavigationItem {
        $linkType = trim((string) ($payload['link_type'] ?? NavigationItem::LINK_CUSTOM_URL));
        $pageId = null;

        if ($linkType === NavigationItem::LINK_PAGE) {
            $pageRef = trim((string) ($payload['page_ref'] ?? ''));
            $pageId = $pageRefs[$pageRef] ?? null;

            if (! $pageId) {
                throw new RuntimeException("Navigation item [{$payload['title']}] references missing page ref [{$pageRef}].");
            }
        }

        $item = NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => $menuKey,
            'parent_id' => $parent?->id,
            'page_id' => $pageId,
            'title' => $payload['title'] ?? null,
            'link_type' => $linkType,
            'url' => $payload['url'] ?? null,
            'target' => $payload['target'] ?? null,
            'position' => $payload['position'] ?? ($defaultPosition + 1),
            'visibility' => $payload['visibility'] ?? NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
        ]);

        if (isset($payload['icon'])) {
            $item->forceFill(['icon' => $payload['icon']])->save();
        }

        $count++;

        foreach (array_values($payload['children'] ?? []) as $index => $childPayload) {
            $this->createNavigationItem($site, $item, $menuKey, $childPayload, $pageRefs, $count, $index);
        }

        return $item;
    }

    private function syncSidebarNavigationBlocks(Site $site): int
    {
        $blocks = $this->docsSidebarNavigationBlocks($site);

        foreach ($blocks as $block) {
            $settings = $block->settings;
            $settings = is_array($settings) ? $settings : [];
            $settings['menu_key'] = NavigationItem::MENU_DOCS;
            $settings['show_icons'] = true;
            $settings['active_matching'] = 'current-page';

            $block->forceFill([
                'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
            ])->save();
        }

        return $blocks->count();
    }

    private function docsSidebarNavigationBlocks(Site $site)
    {
        return Block::query()
            ->where('type', 'sidebar-navigation')
            ->whereHas('page', fn ($query) => $query->where('site_id', $site->id))
            ->get();
    }

    private function resolveDocsHomePage(Site $site): Page
    {
        $page = Page::query()
            ->with(['translations', 'blocks'])
            ->where('site_id', $site->id)
            ->get()
            ->first(fn (Page $candidate) => $candidate->publicShellPreset() === 'docs' && $candidate->translations->contains('slug', 'home'));

        if (! $page) {
            throw new RuntimeException('Docs Home page not found for the target site. Import the docs home page before importing Architecture.');
        }

        return $page;
    }

    private function resolvePageRefs(Site $site, Page $docsHomePage, Page $importedPage, string $pageKey): array
    {
        $refs = [
            'home' => $docsHomePage->id,
            $pageKey => $importedPage->id,
        ];

        $gettingStartedPageId = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))
            ->value('id');

        if (! $gettingStartedPageId) {
            throw new RuntimeException('Getting Started page not found for the target site. Import the docs Getting Started page before importing Architecture.');
        }

        $refs['getting-started'] = (int) $gettingStartedPageId;

        return $refs;
    }

    private function resolveSite(array $sitePayload): Site
    {
        $handle = trim((string) ($sitePayload['handle'] ?? ''));
        $domain = trim((string) ($sitePayload['domain'] ?? ''));

        $site = null;

        if ($handle !== '') {
            $site = Site::query()->where('handle', $handle)->first();
        }

        if (! $site && $domain !== '') {
            $site = Site::query()->where('domain', $domain)->first();
        }

        if (! $site && app()->environment('local') && $domain !== '') {
            $site = Site::query()->where('domain', $this->localAliasDomainFor($domain))->first();
        }

        if (! $site) {
            $label = $handle !== '' ? $handle : $domain;
            throw new RuntimeException('Target site could not be resolved for WebBlocks UI import ['.$label.'].');
        }

        return $site;
    }

    private function resolveLocales(Site $site, array $translations): array
    {
        $map = [];

        foreach (array_keys($translations) as $localeCode) {
            $locale = Locale::query()->where('code', Locale::normalizeCode((string) $localeCode))->first();

            if (! $locale) {
                throw new RuntimeException("Locale [{$localeCode}] is not available.");
            }

            if (! $site->hasEnabledLocale($locale)) {
                throw new RuntimeException("Locale [{$localeCode}] is not enabled for site [{$site->domain}].");
            }

            $map[$locale->code] = $locale;
        }

        return $map;
    }

    private function defaultLocaleCode(array $translations): string
    {
        $defaultLocaleCode = Locale::query()->where('is_default', true)->value('code');

        if (! is_string($defaultLocaleCode) || $defaultLocaleCode === '') {
            throw new RuntimeException('Default locale is not configured.');
        }

        if (! array_key_exists($defaultLocaleCode, $translations)) {
            throw new RuntimeException('Payload is missing the default locale translation.');
        }

        return $defaultLocaleCode;
    }

    private function resolveRequiredBlockTypes(array $pagePayload): array
    {
        $slugs = collect($pagePayload['slots'] ?? [])
            ->flatMap(fn (array $slotPayload) => $this->collectBlockTypeSlugs($slotPayload['blocks'] ?? []))
            ->push('sidebar-navigation')
            ->unique()
            ->values();

        $blockTypes = BlockType::query()
            ->whereIn('slug', $slugs->all())
            ->get(['id', 'slug'])
            ->pluck('id', 'slug')
            ->all();

        foreach ($slugs as $slug) {
            if (! isset($blockTypes[$slug])) {
                throw new RuntimeException("Required block type [{$slug}] is missing.");
            }
        }

        return $blockTypes;
    }

    private function collectBlockTypeSlugs(array $blocks): array
    {
        $slugs = [];

        foreach ($blocks as $block) {
            $slug = trim((string) ($block['type'] ?? ''));

            if ($slug !== '') {
                $slugs[] = $slug;
            }

            $slugs = array_merge($slugs, $this->collectBlockTypeSlugs($block['children'] ?? []));
        }

        return $slugs;
    }

    private function resolveSlotTypes(array $pagePayload): array
    {
        $slotSlugs = array_keys($pagePayload['slots'] ?? []);
        $slotTypes = SlotType::query()->whereIn('slug', $slotSlugs)->get()->keyBy('slug');

        foreach ($slotSlugs as $slotSlug) {
            if (! $slotTypes->has($slotSlug)) {
                throw new RuntimeException("Required slot type [{$slotSlug}] is missing.");
            }
        }

        return $slotTypes->all();
    }

    private function loadJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Project import file not found: '.$path);
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException('Project import file is empty: '.$path);
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Project import file is not valid JSON: '.$path);
        }

        return $decoded;
    }

    private function deleteBlockTree(Block $block): void
    {
        $block->loadMissing('children');

        foreach ($block->children as $child) {
            $this->deleteBlockTree($child);
        }

        $block->delete();
    }

    private function localAliasDomainFor(string $domain): string
    {
        return Str::finish($domain, '.ddev.site');
    }

    private function localPreviewUrl(Site $site, string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return 'https://'.((string) $site->domain).$path;
    }
}
