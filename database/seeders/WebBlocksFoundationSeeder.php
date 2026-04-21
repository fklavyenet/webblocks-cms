<?php

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use Database\Seeders\Concerns\GuardsInitializedSites;
use Illuminate\Database\Seeder;

class WebBlocksFoundationSeeder extends Seeder
{
    use GuardsInitializedSites;

    public function run(): void
    {
        $this->ensureSiteIsNotInitialized(self::class);

        $english = Locale::query()->updateOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'is_default' => true, 'is_enabled' => true],
        );

        $turkish = Locale::query()->updateOrCreate(
            ['code' => 'tr'],
            ['name' => 'Turkish', 'is_default' => false, 'is_enabled' => true],
        );

        $rootSite = Site::query()->updateOrCreate(
            ['handle' => 'root'],
            ['name' => 'WebBlocks', 'domain' => 'webblocksui.lvh.me', 'is_primary' => true],
        );

        $uiSite = Site::query()->updateOrCreate(
            ['handle' => 'ui'],
            ['name' => 'WebBlocks UI', 'domain' => 'ui.webblocksui.lvh.me', 'is_primary' => false],
        );

        $cmsSite = Site::query()->updateOrCreate(
            ['handle' => 'cms'],
            ['name' => 'WebBlocks CMS', 'domain' => 'cms.webblocksui.lvh.me', 'is_primary' => false],
        );

        Site::query()->whereNotIn('id', [$rootSite->id, $uiSite->id, $cmsSite->id])->delete();

        foreach ([$rootSite, $uiSite, $cmsSite] as $site) {
            $site->locales()->sync([
                $english->id => ['is_enabled' => true],
                $turkish->id => ['is_enabled' => true],
            ]);
        }

        $rootHome = $this->createPage($rootSite, 'home', 'WebBlocks');
        $rootAbout = $this->createPage($rootSite, 'about', 'About');
        $rootContact = $this->createPage($rootSite, 'contact', 'Contact');
        $uiHome = $this->createPage($uiSite, 'home', 'WebBlocks UI');
        $cmsHome = $this->createPage($cmsSite, 'home', 'WebBlocks CMS');

        $this->translatePage($rootHome, $english, 'WebBlocks', 'home');
        $this->translatePage($rootHome, $turkish, 'WebBlocks Ekosistemi', 'home');
        $this->translatePage($rootAbout, $english, 'About', 'about');
        $this->translatePage($rootAbout, $turkish, 'Hakkinda', 'hakkinda');
        $this->translatePage($rootContact, $english, 'Contact', 'contact');
        $this->translatePage($rootContact, $turkish, 'Iletisim', 'iletisim');
        $this->translatePage($uiHome, $english, 'WebBlocks UI', 'home');
        $this->translatePage($uiHome, $turkish, 'WebBlocks UI', 'home');
        $this->translatePage($cmsHome, $english, 'WebBlocks CMS', 'home');
        $this->translatePage($cmsHome, $turkish, 'WebBlocks CMS', 'home');

        $this->seedGlobalNavigation();
    }

    private function createPage(Site $site, string $slug, string $title): Page
    {
        return Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'page_type' => 'default',
            'status' => 'published',
        ]);
    }

    private function translatePage(Page $page, Locale $locale, string $name, string $slug): void
    {
        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $locale->id],
            ['name' => $name, 'slug' => $slug, 'path' => PageTranslation::pathFromSlug($slug)],
        );
    }

    private function seedGlobalNavigation(): void
    {
        $items = [
            [NavigationItem::MENU_PRIMARY, 'Ecosystem', 'http://webblocksui.lvh.me:8000/', 1],
            [NavigationItem::MENU_PRIMARY, 'UI', 'http://ui.webblocksui.lvh.me:8000/', 2],
            [NavigationItem::MENU_PRIMARY, 'CMS', 'http://cms.webblocksui.lvh.me:8000/', 3],
            [NavigationItem::MENU_PRIMARY, 'About', 'http://webblocksui.lvh.me:8000/p/about', 4],
            [NavigationItem::MENU_MOBILE, 'Ecosystem', 'http://webblocksui.lvh.me:8000/', 1],
            [NavigationItem::MENU_MOBILE, 'UI', 'http://ui.webblocksui.lvh.me:8000/', 2],
            [NavigationItem::MENU_MOBILE, 'CMS', 'http://cms.webblocksui.lvh.me:8000/', 3],
            [NavigationItem::MENU_MOBILE, 'Contact', 'http://webblocksui.lvh.me:8000/p/contact', 4],
            [NavigationItem::MENU_FOOTER, 'WebBlocks', 'http://webblocksui.lvh.me:8000/', 1],
            [NavigationItem::MENU_FOOTER, 'WebBlocks UI', 'http://ui.webblocksui.lvh.me:8000/', 2],
            [NavigationItem::MENU_FOOTER, 'WebBlocks CMS', 'http://cms.webblocksui.lvh.me:8000/', 3],
            [NavigationItem::MENU_FOOTER, 'Contact', 'http://webblocksui.lvh.me:8000/p/contact', 4],
            [NavigationItem::MENU_LEGAL, 'GitHub', 'https://github.com/fklavyenet/webblocks-cms', 1],
        ];

        foreach ($items as [$menuKey, $title, $url, $position]) {
            NavigationItem::query()->create([
                'menu_key' => $menuKey,
                'parent_id' => null,
                'page_id' => null,
                'title' => $title,
                'link_type' => NavigationItem::LINK_CUSTOM_URL,
                'url' => $url,
                'target' => '_blank',
                'position' => $position,
                'visibility' => NavigationItem::VISIBILITY_VISIBLE,
                'is_system' => false,
            ]);
        }
    }
}
