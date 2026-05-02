<?php

namespace Project\Support\UiDocs;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncUiDocsNavigation
{
    private const MENU_KEY = NavigationItem::MENU_DOCS;

    public function run(): void
    {
        $homePage = Page::query()
            ->with(['site', 'translations', 'blocks'])
            ->get()
            ->first(fn (Page $page) => $page->publicShellPreset() === 'docs' && $page->translations->contains('slug', 'home'));

        if (! $homePage) {
            throw new RuntimeException('Docs Home page not found.');
        }

        $siteId = (int) $homePage->site_id;

        DB::transaction(function () use ($homePage, $siteId): void {
            NavigationItem::query()
                ->forSite($siteId)
                ->forMenu(self::MENU_KEY)
                ->delete();

            $gettingStartedPageId = Page::query()
                ->where('site_id', $siteId)
                ->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))
                ->value('id');

            $guides = NavigationItem::query()->create([
                'site_id' => $siteId,
                'menu_key' => self::MENU_KEY,
                'title' => 'Guides',
                'link_type' => NavigationItem::LINK_GROUP,
                'icon' => 'layers',
                'position' => 1,
                'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            ]);

            if ($gettingStartedPageId) {
                NavigationItem::query()->create([
                    'site_id' => $siteId,
                    'menu_key' => self::MENU_KEY,
                    'parent_id' => $guides->id,
                    'title' => 'Getting Started',
                    'link_type' => NavigationItem::LINK_PAGE,
                    'page_id' => $gettingStartedPageId,
                    'icon' => 'rocket',
                    'position' => 1,
                    'visibility' => NavigationItem::VISIBILITY_VISIBLE,
                ]);
            }

            NavigationItem::query()->create([
                'site_id' => $siteId,
                'menu_key' => self::MENU_KEY,
                'parent_id' => $guides->id,
                'title' => 'Patterns',
                'link_type' => NavigationItem::LINK_CUSTOM_URL,
                'url' => 'patterns.html',
                'icon' => 'grid',
                'position' => 2,
                'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            ]);

            NavigationItem::query()->create([
                'site_id' => $siteId,
                'menu_key' => self::MENU_KEY,
                'parent_id' => $guides->id,
                'title' => 'Playground',
                'link_type' => NavigationItem::LINK_CUSTOM_URL,
                'url' => '../playground/',
                'icon' => 'code',
                'position' => 3,
                'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            ]);

            NavigationItem::query()->create([
                'site_id' => $siteId,
                'menu_key' => self::MENU_KEY,
                'title' => 'Home',
                'link_type' => NavigationItem::LINK_PAGE,
                'page_id' => $homePage->id,
                'icon' => 'home',
                'position' => 2,
                'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            ]);

            $sidebarNavigations = Block::query()
                ->where('page_id', $homePage->id)
                ->where('type', 'sidebar-navigation')
                ->get();

            foreach ($sidebarNavigations as $block) {
                $settings = json_decode((string) $block->getRawOriginal('settings'), true);
                $settings = is_array($settings) ? $settings : [];
                $settings['menu_key'] = self::MENU_KEY;
                $settings['show_icons'] = true;
                $settings['active_matching'] = 'current-page';

                $block->forceFill([
                    'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
                ])->save();
            }
        });
    }
}
