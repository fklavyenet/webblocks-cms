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
            ['name' => 'WebBlocks', 'domain' => 'webblocksui.com.ddev.site', 'is_primary' => true],
        );

        $uiSite = Site::query()->updateOrCreate(
            ['handle' => 'ui'],
            ['name' => 'WebBlocks UI', 'domain' => 'ui.webblocksui.com.ddev.site', 'is_primary' => false],
        );

        $uiDocsSite = Site::query()->updateOrCreate(
            ['handle' => 'ui-docs'],
            ['name' => 'WebBlocks UI Docs', 'domain' => 'ui.docs.webblocksui.com.ddev.site', 'is_primary' => false],
        );

        $cmsSite = Site::query()->updateOrCreate(
            ['handle' => 'cms'],
            ['name' => 'WebBlocks CMS', 'domain' => 'cms.webblocksui.com.ddev.site', 'is_primary' => false],
        );

        $cmsDocsSite = Site::query()->updateOrCreate(
            ['handle' => 'cms-docs'],
            ['name' => 'WebBlocks CMS Docs', 'domain' => 'cms.docs.webblocksui.com.ddev.site', 'is_primary' => false],
        );

        Site::query()->whereNotIn('id', [$rootSite->id, $uiSite->id, $uiDocsSite->id, $cmsSite->id, $cmsDocsSite->id])->delete();

        foreach ([$rootSite, $uiSite, $uiDocsSite, $cmsSite, $cmsDocsSite] as $site) {
            $site->locales()->sync([
                $english->id => ['is_enabled' => true],
                $turkish->id => ['is_enabled' => true],
            ]);
        }

        $rootHome = $this->createPage($rootSite, 'home', 'WebBlocks');
        $rootAbout = $this->createPage($rootSite, 'about', 'About');
        $rootContact = $this->createPage($rootSite, 'contact', 'Contact');
        $uiHome = $this->createPage($uiSite, 'home', 'WebBlocks UI');
        $uiDocsHome = $this->createPage($uiDocsSite, 'home', 'WebBlocks UI Docs');
        $cmsHome = $this->createPage($cmsSite, 'home', 'WebBlocks CMS');
        $cmsDocsHome = $this->createPage($cmsDocsSite, 'home', 'WebBlocks CMS Docs');

        $this->translatePage($rootHome, $english, 'WebBlocks', 'home');
        $this->translatePage($rootHome, $turkish, 'WebBlocks Ekosistemi', 'home');
        $this->translatePage($rootAbout, $english, 'About', 'about');
        $this->translatePage($rootAbout, $turkish, 'Hakkinda', 'hakkinda');
        $this->translatePage($rootContact, $english, 'Contact', 'contact');
        $this->translatePage($rootContact, $turkish, 'Iletisim', 'iletisim');
        $this->translatePage($uiHome, $english, 'WebBlocks UI', 'home');
        $this->translatePage($uiHome, $turkish, 'WebBlocks UI', 'home');
        $this->translatePage($uiDocsHome, $english, 'WebBlocks UI Docs', 'home');
        $this->translatePage($uiDocsHome, $turkish, 'WebBlocks UI Dokumantasyon', 'home');
        $this->translatePage($cmsHome, $english, 'WebBlocks CMS', 'home');
        $this->translatePage($cmsHome, $turkish, 'WebBlocks CMS', 'home');
        $this->translatePage($cmsDocsHome, $english, 'WebBlocks CMS Docs', 'home');
        $this->translatePage($cmsDocsHome, $turkish, 'WebBlocks CMS Dokumantasyon', 'home');

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
            [NavigationItem::MENU_PRIMARY, 'Ecosystem', 'https://webblocksui.com.ddev.site/', 1],
            [NavigationItem::MENU_PRIMARY, 'UI', 'https://ui.webblocksui.com.ddev.site/', 2],
            [NavigationItem::MENU_PRIMARY, 'UI Docs', 'https://ui.docs.webblocksui.com.ddev.site/', 3],
            [NavigationItem::MENU_PRIMARY, 'CMS', 'https://cms.webblocksui.com.ddev.site/', 4],
            [NavigationItem::MENU_PRIMARY, 'CMS Docs', 'https://cms.docs.webblocksui.com.ddev.site/', 5],
            [NavigationItem::MENU_PRIMARY, 'About', 'https://webblocksui.com.ddev.site/p/about', 6],
            [NavigationItem::MENU_MOBILE, 'Ecosystem', 'https://webblocksui.com.ddev.site/', 1],
            [NavigationItem::MENU_MOBILE, 'UI', 'https://ui.webblocksui.com.ddev.site/', 2],
            [NavigationItem::MENU_MOBILE, 'UI Docs', 'https://ui.docs.webblocksui.com.ddev.site/', 3],
            [NavigationItem::MENU_MOBILE, 'CMS', 'https://cms.webblocksui.com.ddev.site/', 4],
            [NavigationItem::MENU_MOBILE, 'CMS Docs', 'https://cms.docs.webblocksui.com.ddev.site/', 5],
            [NavigationItem::MENU_MOBILE, 'Contact', 'https://webblocksui.com.ddev.site/p/contact', 6],
            [NavigationItem::MENU_FOOTER, 'WebBlocks', 'https://webblocksui.com.ddev.site/', 1],
            [NavigationItem::MENU_FOOTER, 'WebBlocks UI', 'https://ui.webblocksui.com.ddev.site/', 2],
            [NavigationItem::MENU_FOOTER, 'WebBlocks UI Docs', 'https://ui.docs.webblocksui.com.ddev.site/', 3],
            [NavigationItem::MENU_FOOTER, 'WebBlocks CMS', 'https://cms.webblocksui.com.ddev.site/', 4],
            [NavigationItem::MENU_FOOTER, 'WebBlocks CMS Docs', 'https://cms.docs.webblocksui.com.ddev.site/', 5],
            [NavigationItem::MENU_FOOTER, 'Contact', 'https://webblocksui.com.ddev.site/p/contact', 6],
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
