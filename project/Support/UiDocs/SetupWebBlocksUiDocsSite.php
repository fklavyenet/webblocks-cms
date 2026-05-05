<?php

namespace Project\Support\UiDocs;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SetupWebBlocksUiDocsSite
{
    public const CANONICAL_DOMAIN = 'ui.docs.webblocksui.com';

    public const LOCAL_DDEV_DOMAIN = 'ui.docs.webblocksui.com.ddev.site';

    public const ARCHITECTURE_PATH = '/p/architecture';

    public function run(): array
    {
        $defaultLocale = Locale::query()->where('is_default', true)->first();

        if (! $defaultLocale) {
            throw new RuntimeException('Default locale is not configured.');
        }

        $requiredBlockTypes = BlockType::query()
            ->whereIn('slug', ['sidebar-navigation'])
            ->pluck('id', 'slug');

        if (! $requiredBlockTypes->has('sidebar-navigation')) {
            throw new RuntimeException('Required block type [sidebar-navigation] is missing.');
        }

        $headerSlotType = $this->slotType('header', 'Header', 0);
        $sidebarSlotType = $this->slotType('sidebar', 'Sidebar', 1);
        $mainSlotType = $this->slotType('main', 'Main', 2);

        return DB::transaction(function () use ($defaultLocale, $requiredBlockTypes, $headerSlotType, $sidebarSlotType, $mainSlotType): array {
            $site = $this->resolveTargetSite();

            $site->locales()->syncWithoutDetaching([
                $defaultLocale->id => ['is_enabled' => true],
            ]);

            $homePage = $this->firstOrCreateDocsPage($site, 'Home', 'home', '/');
            $gettingStartedPage = $this->firstOrCreateDocsPage($site, 'Getting Started', 'getting-started', '/p/getting-started');

            $this->ensureSlots($homePage, $headerSlotType, $sidebarSlotType, $mainSlotType);
            $this->ensureSlots($gettingStartedPage, $headerSlotType, $sidebarSlotType, $mainSlotType);
            $this->ensureSidebarNavigationBlock($homePage, $requiredBlockTypes['sidebar-navigation'], $sidebarSlotType->id, $defaultLocale->id);

            return [
                'Target site: '.$site->name.' ('.$site->handle.')',
                'Ensured page: Home',
                'Ensured page: Getting Started',
                'Ensured docs sidebar navigation dependency on Home page',
                'Architecture local preview URL: '.self::previewUrlForPath(self::ARCHITECTURE_PATH, $site),
            ];
        });
    }

    public static function canonicalDomain(): string
    {
        return self::CANONICAL_DOMAIN;
    }

    public static function localDdevDomain(): string
    {
        return self::LOCAL_DDEV_DOMAIN;
    }

    public static function architecturePreviewUrl(): string
    {
        return self::previewUrlForPath(self::ARCHITECTURE_PATH);
    }

    public static function previewUrlForPath(string $path, ?Site $site = null): string
    {
        return rtrim(self::previewBaseUrl($site), '/').'/'.ltrim($path, '/');
    }

    private function firstOrCreateDocsPage(Site $site, string $name, string $slug, string $path): Page
    {
        $page = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', $slug))
            ->first() ?? new Page;

        $page->fill([
            'site_id' => $site->id,
            'page_type' => Page::TYPE_DEFAULT,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => array_merge(is_array($page->settings) ? $page->settings : [], ['public_shell' => 'docs']),
        ]);

        if ($page->status === Page::STATUS_PUBLISHED && ! $page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
            ['site_id' => $site->id, 'name' => $name, 'slug' => $slug, 'path' => $path],
        );

        return $page->fresh(['translations', 'slots']);
    }

    private function ensureSlots(Page $page, SlotType $headerSlotType, SlotType $sidebarSlotType, SlotType $mainSlotType): void
    {
        foreach ([
            [$headerSlotType, 0],
            [$sidebarSlotType, 1],
            [$mainSlotType, 2],
        ] as [$slotType, $sortOrder]) {
            $slot = PageSlot::query()->firstOrNew([
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
            ]);

            $slot->fill([
                'source_type' => PageSlot::SOURCE_TYPE_PAGE,
                'shared_slot_id' => null,
                'sort_order' => $sortOrder,
                'settings' => null,
            ]);
            $slot->save();
        }
    }

    private function ensureSidebarNavigationBlock(Page $page, int $blockTypeId, int $sidebarSlotTypeId, int $localeId): void
    {
        $block = Block::query()->firstOrNew([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotTypeId,
            'type' => 'sidebar-navigation',
            'parent_id' => null,
        ]);

        $block->fill([
            'block_type_id' => $blockTypeId,
            'source_type' => 'static',
            'slot' => 'sidebar',
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
            'settings' => json_encode([
                'menu_key' => 'docs',
                'show_icons' => true,
                'active_matching' => 'current-page',
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $block->save();

        $block->textTranslations()->updateOrCreate(
            ['locale_id' => $localeId],
            ['title' => 'Documentation navigation'],
        );
    }

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function resolveTargetSite(): Site
    {
        $site = Site::primary();

        if (! $site) {
            $site = Site::query()->firstOrCreate(
                ['handle' => 'default'],
                ['name' => 'Default Site', 'domain' => null, 'is_primary' => true],
            );
        }

        if (! $site->is_primary) {
            $site->forceFill(['is_primary' => true])->save();
        }

        return $site->fresh();
    }

    private static function previewBaseUrl(?Site $site = null): string
    {
        $host = trim((string) ($site?->domain ?? Site::primary()?->domain ?? ''));
        $appUrl = trim((string) config('app.url', 'http://localhost'));
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);

        if ($host !== '') {
            return (($scheme ?: 'https').'://'.$host);
        }

        if ($appUrl !== '') {
            return $appUrl;
        }

        return 'http://localhost';
    }
}
