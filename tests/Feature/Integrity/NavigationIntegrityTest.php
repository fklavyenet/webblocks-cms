<?php

namespace Tests\Feature\Integrity;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Navigation\NavigationTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function createLocale(string $code): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'is_default' => false,
            'is_enabled' => true,
        ]);
    }

    private function createSite(string $handle, string $domain): Site
    {
        $site = Site::query()->create([
            'name' => ucfirst($handle),
            'handle' => $handle,
            'domain' => $domain,
            'is_primary' => false,
        ]);

        $site->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

        return $site;
    }

    private function slotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'header'],
            ['name' => 'Header', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function navigationBlockType(): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => 'navigation-auto'],
            ['name' => 'Navigation Auto', 'source_type' => 'static', 'status' => 'published'],
        );
    }

    private function createPage(Site $site, string $title, string $slug): Page
    {
        return Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    private function addNavigationBlock(Page $page): void
    {
        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
        ]);

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'navigation-auto',
            'block_type_id' => $this->navigationBlockType()->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => true,
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_PRIMARY], JSON_UNESCAPED_SLASHES),
        ]);
    }

    #[Test]
    public function navigation_items_cannot_link_to_pages_from_another_site(): void
    {
        $firstSite = Site::query()->where('is_primary', true)->firstOrFail();
        $secondSite = $this->createSite('campaign', 'campaign.example.test');
        $foreignPage = $this->createPage($secondSite, 'Campaign About', 'about');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Linked page must belong to the same site as the navigation item.');

        NavigationItem::query()->create([
            'site_id' => $firstSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Broken Link',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $foreignPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);
    }

    #[Test]
    public function navigation_items_respect_site_scope_in_public_rendering(): void
    {
        $primarySite = Site::query()->where('is_primary', true)->firstOrFail();
        $primarySite->update(['domain' => 'primary.example.test']);
        $campaignSite = $this->createSite('campaign', 'campaign.example.test');

        $primaryPage = $this->createPage($primarySite, 'Primary About', 'about');
        $campaignPage = $this->createPage($campaignSite, 'Campaign About', 'about');
        $this->addNavigationBlock($campaignPage);

        NavigationItem::query()->create([
            'site_id' => $primarySite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Primary Link',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $primaryPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);
        NavigationItem::query()->create([
            'site_id' => $campaignSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Campaign Link',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $campaignPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        $response = $this->get('http://campaign.example.test/p/about');

        $response->assertOk();
        $response->assertSee('Campaign Link');
        $response->assertDontSee('Primary Link');
    }

    #[Test]
    public function deleting_a_page_cleans_navigation_safely_without_orphan_render_crashes(): void
    {
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $site->update(['domain' => 'primary.example.test']);
        $page = $this->createPage($site, 'About', 'about');
        $this->addNavigationBlock($page);

        $linkedPage = $this->createPage($site, 'Linked', 'linked');
        $item = NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Linked Page',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $linkedPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        $linkedPage->delete();

        $this->assertNull($item->fresh()->page_id);
        $this->get('http://primary.example.test/p/about')->assertOk()->assertSee('Linked Page');
    }

    #[Test]
    public function localized_navigation_uses_translation_based_slugs_for_the_current_locale(): void
    {
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $site->update(['domain' => 'primary.example.test']);
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $homePage = $this->createPage($site, 'Home', 'home');
        $homePage->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Ana Sayfa',
            'slug' => 'home',
            'path' => '/',
        ]);

        $linkedPage = $this->createPage($site, 'About', 'about');
        $linkedPage->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $this->addNavigationBlock($homePage);

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'About',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $linkedPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        $response = $this->get('http://primary.example.test/tr');

        $response->assertOk();
        $response->assertSee('/tr/p/hakkinda', false);
        $response->assertDontSee('href="/p/about"', false);
    }

    #[Test]
    public function invalid_cross_site_navigation_rows_inserted_directly_do_not_break_site_scoped_tree_queries(): void
    {
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $otherSite = $this->createSite('campaign', 'campaign.example.test');
        $foreignPage = $this->createPage($otherSite, 'Campaign About', 'about');

        DB::table('navigation_items')->insert([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'parent_id' => null,
            'page_id' => $foreignPage->id,
            'title' => 'Broken Link',
            'link_type' => NavigationItem::LINK_PAGE,
            'url' => null,
            'target' => null,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = app(NavigationTree::class)->buildMenuTree(NavigationItem::MENU_PRIMARY, $site);

        $this->assertCount(1, $items);
        $this->assertSame('Broken Link', $items->first()->resolvedTitle());
    }

    #[Test]
    public function cross_site_page_links_are_rejected_at_request_level(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $otherSite = $this->createSite('campaign', 'campaign.example.test');
        $foreignPage = $this->createPage($otherSite, 'Campaign About', 'about');

        $response = $this->actingAs($user)
            ->from(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_PRIMARY]))
            ->post(route('admin.navigation.store'), [
                'site_id' => $site->id,
                'menu_key' => NavigationItem::MENU_PRIMARY,
                'title' => 'Broken Link',
                'link_type' => NavigationItem::LINK_PAGE,
                'page_id' => $foreignPage->id,
                'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            ]);

        $response->assertRedirect(route('admin.navigation.index', ['site_id' => $site->id, 'menu_key' => NavigationItem::MENU_PRIMARY]));
        $response->assertSessionHasErrors(['page_id' => 'Selected page does not belong to this site.']);
        $this->assertSame(0, NavigationItem::query()->where('site_id', $site->id)->count());
    }
}
