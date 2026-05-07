<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\SlotType;
use App\Models\BlockType;
use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteDomainTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolving_a_cms_site_by_primary_domain_works(): void
    {
        [$site, $page] = $this->seedPublicSiteWithDomain('primary.example.test');

        $response = $this->get('http://primary.example.test/p/about');

        $response->assertOk();
        $response->assertSee('About');
        $this->assertSame('primary.example.test', $site->fresh()->canonicalDomain());
        $this->assertSame('https://primary.example.test/p/about', $page->fresh()->canonicalUrl());
    }

    #[Test]
    public function resolving_a_cms_site_by_alias_domain_works(): void
    {
        [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
        $site->siteDomains()->create([
            'domain' => 'alias.example.test',
            'is_primary' => false,
            'redirect_to_primary' => false,
            'status' => SiteDomain::STATUS_ACTIVE,
        ]);

        $response = $this->get('http://alias.example.test/p/about');

        $response->assertOk();
        $response->assertSee('About');
        $response->assertSee('<link rel="canonical" href="https://primary.example.test/p/about">', false);
        $response->assertSee('<meta property="og:url" content="https://primary.example.test/p/about">', false);
    }

    #[Test]
    public function unknown_production_host_does_not_render_the_default_site(): void
    {
        [$site] = $this->seedPublicSiteWithDomain('primary.example.test');

        config()->set('cms.multisite.unknown_host_fallback', false);

        $this->get('http://unknown.example.test/p/about')->assertNotFound();
        $this->assertSame('primary.example.test', $site->fresh()->canonicalDomain());
    }

    #[Test]
    public function local_fallback_for_unknown_host_remains_compatible_when_enabled(): void
    {
        $this->seedPublicSiteWithDomain('primary.example.test');

        config()->set('cms.multisite.unknown_host_fallback', true);

        $this->get('http://unknown.example.test/p/about')->assertOk()->assertSee('About');
    }

    #[Test]
    public function inactive_domain_does_not_resolve(): void
    {
        [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
        $site->siteDomains()->create([
            'domain' => 'inactive.example.test',
            'is_primary' => false,
            'redirect_to_primary' => false,
            'status' => SiteDomain::STATUS_INACTIVE,
        ]);

        config()->set('cms.multisite.unknown_host_fallback', false);

        $this->get('http://inactive.example.test/p/about')->assertNotFound();
    }

    #[Test]
    public function duplicate_domain_validation_fails(): void
    {
        $user = User::factory()->superAdmin()->create();
        [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
        $otherSite = Site::query()->create([
            'name' => 'Other Site',
            'handle' => 'other-site',
            'domain' => 'other.example.test',
            'is_primary' => false,
        ]);
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $otherSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $response = $this->actingAs($user)->post(route('admin.sites.domains.store', $otherSite), [
            'domain' => 'https://PRIMARY.example.test/landing',
            'status' => 'active',
            'redirect_to_primary' => 0,
            'is_primary' => 0,
        ]);

        $response->assertSessionHasErrors('domain');
    }

    #[Test]
    public function site_domains_admin_screen_can_manage_domains(): void
    {
        $user = User::factory()->superAdmin()->create();
        [$site] = $this->seedPublicSiteWithDomain('primary.example.test');

        $this->actingAs($user)->post(route('admin.sites.domains.store', $site), [
            'domain' => 'alias.example.test',
            'status' => 'active',
            'redirect_to_primary' => 1,
            'is_primary' => 0,
        ])->assertRedirect(route('admin.sites.domains.index', $site));

        $alias = $site->fresh()->siteDomains()->where('domain', 'alias.example.test')->firstOrFail();

        $this->actingAs($user)->post(route('admin.sites.domains.primary', ['site' => $site, 'domain' => $alias]))
            ->assertRedirect(route('admin.sites.domains.index', $site));

        $this->assertSame('alias.example.test', $site->fresh()->canonicalDomain());
    }

    #[Test]
    public function canonical_url_uses_primary_domain_even_when_requested_from_alias(): void
    {
        [$site, $page] = $this->seedPublicSiteWithDomain('primary.example.test');
        $site->siteDomains()->create([
            'domain' => 'docs.example.test',
            'is_primary' => false,
            'redirect_to_primary' => false,
            'status' => SiteDomain::STATUS_ACTIVE,
        ]);

        $response = $this->get('http://docs.example.test/p/about');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://primary.example.test/p/about">', false);
        $this->assertSame('https://primary.example.test/p/about', $page->fresh()->canonicalUrl());
        $this->assertSame('http://docs.example.test/p/about', $page->fresh()->currentHostPublicUrl());
    }

    #[Test]
    public function token_authenticated_internal_api_listing_works_and_unauthenticated_calls_fail(): void
    {
        $this->seedPublicSiteWithDomain('primary.example.test');

        config()->set('cms.multisite.unknown_host_fallback', false);
        putenv('WEBBLOCKS_CMS_INTERNAL_API_TOKEN=secret-token');
        $_ENV['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'] = 'secret-token';
        $_SERVER['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'] = 'secret-token';

        $this->getJson('/admin-api/sites')->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->getJson('/admin-api/sites')
            ->assertOk()
            ->assertJsonPath('sites.0.primary_domain', 'primary.example.test');
    }

    #[Test]
    public function internal_api_domain_attach_validates_conflicts_and_delete_removes_only_requested_domain(): void
    {
        putenv('WEBBLOCKS_CMS_INTERNAL_API_TOKEN=secret-token');
        $_ENV['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'] = 'secret-token';
        $_SERVER['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'] = 'secret-token';

        [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
        $otherSite = Site::query()->create([
            'name' => 'Other Site',
            'handle' => 'other-site',
            'domain' => 'other.example.test',
            'is_primary' => false,
        ]);
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $otherSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->postJson('/admin-api/sites/'.$otherSite->id.'/domains', [
                'domain' => 'primary.example.test',
                'status' => 'active',
                'is_primary' => false,
                'redirect_to_primary' => false,
            ])
            ->assertStatus(422);

        $create = $this->withHeader('Authorization', 'Bearer secret-token')
            ->postJson('/admin-api/sites/'.$otherSite->id.'/domains', [
                'domain' => 'alias.example.test',
                'status' => 'active',
                'is_primary' => false,
                'redirect_to_primary' => false,
            ]);

        $create->assertCreated();

        $domainId = $create->json('domain.id');

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->deleteJson('/admin-api/sites/'.$otherSite->id.'/domains/'.$domainId)
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('site_domains', ['id' => $domainId]);
        $this->assertDatabaseHas('site_domains', ['site_id' => $site->id, 'domain' => 'primary.example.test']);
    }

    private function seedPublicSiteWithDomain(string $domain): array
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $site->update(['domain' => $domain]);
        $locale = Locale::query()->where('is_default', true)->firstOrFail();
        $slotType = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $headerType = BlockType::query()->where('slug', 'header')->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $page->defaultTranslation()?->update([
            'name' => 'About',
            'slug' => 'about',
            'path' => '/p/about',
        ]);

        return [$site, $page, $locale, $slotType, $headerType];
    }
}
