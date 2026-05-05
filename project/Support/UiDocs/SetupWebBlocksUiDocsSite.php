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
            $site = Site::query()->firstOrCreate(
                ['handle' => 'ui-docs-webblocksui-com'],
                ['name' => 'WebBlocks UI Docs', 'domain' => 'ui.docs.webblocksui.com', 'is_primary' => false],
            );

            if (! $site->domain) {
                $site->forceFill(['domain' => 'ui.docs.webblocksui.com'])->save();
            }

            $site->locales()->syncWithoutDetaching([
                $defaultLocale->id => ['is_enabled' => true],
            ]);

            $homePage = $this->firstOrCreateDocsPage($site, 'Home', 'home', '/');
            $gettingStartedPage = $this->firstOrCreateDocsPage($site, 'Getting Started', 'getting-started', '/p/getting-started');

            $this->ensureSlots($homePage, $headerSlotType, $sidebarSlotType, $mainSlotType);
            $this->ensureSlots($gettingStartedPage, $headerSlotType, $sidebarSlotType, $mainSlotType);
            $this->ensureSidebarNavigationBlock($homePage, $requiredBlockTypes['sidebar-navigation'], $sidebarSlotType->id, $defaultLocale->id);

            return [
                'Site: '.$site->domain,
                'Ensured page: Home',
                'Ensured page: Getting Started',
                'Ensured docs sidebar navigation dependency on Home page',
            ];
        });
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
}
