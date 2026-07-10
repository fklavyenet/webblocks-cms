<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;

class SiteDomainTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function resolving_a_cms_site_by_primary_domain_works(): void
  {
    [$site, $page] = $this->seedPublicSiteWithDomain('primary.example.test');

    $response = $this->get('http://primary.example.test/about');

    $response->assertOk();
    $response->assertSee('About');
    $this->assertSame('primary.example.test', $site->fresh()->canonicalDomain());
    $this->assertSame('https://primary.example.test/about', $page->fresh()->canonicalUrl());
  }

  #[Test]
  public function public_page_html_lang_uses_the_rendered_page_locale(): void
  {
    [, $page, $locale] = $this->seedPublicSiteWithDomain('primary.example.test');
    $locale->update([
      'code' => 'de',
      'name' => 'German',
    ]);
    $page->defaultTranslation()?->update(['name' => 'Ausstellungen']);

    $response = $this->get('http://primary.example.test/about');

    $response->assertOk();
    $response->assertSee('<html lang="de">', false);
    $response->assertSee('Ausstellungen');
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

    $response = $this->get('http://alias.example.test/about');

    $response->assertOk();
    $response->assertSee('About');
    $response->assertSee('<link rel="canonical" href="https://primary.example.test/about">', false);
    $response->assertSee('<meta property="og:url" content="https://primary.example.test/about">', false);
  }

  #[Test]
  public function unknown_production_host_does_not_render_the_default_site(): void
  {
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');

    config()->set('cms.multisite.unknown_host_fallback', false);

    $this->get('http://unknown.example.test/about')->assertNotFound();
    $this->assertSame('primary.example.test', $site->fresh()->canonicalDomain());
  }

  #[Test]
  public function local_fallback_for_unknown_host_remains_compatible_when_enabled(): void
  {
    $this->seedPublicSiteWithDomain('primary.example.test');

    config()->set('cms.multisite.unknown_host_fallback', true);

    $this->get('http://unknown.example.test/about')->assertOk()->assertSee('About');
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

    $this->get('http://inactive.example.test/about')->assertNotFound();
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
  public function site_domains_admin_screen_uses_assigned_domains_card_header_action_and_create_modal(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');

    $response = $this->actingAs($user)->get(route('admin.sites.domains.index', $site));

    $response->assertOk();
    $response->assertSee('<div class="wb-stack wb-gap-4">', false);
    $response->assertSee('<strong>Assigned Domains</strong>', false);
    $response->assertSee('aria-controls="siteDomainCreateModal"', false);
    $response->assertSee('href="'.route('admin.sites.domains.index', ['site' => $site, 'modal' => 'create-domain']).'"', false);
    $response->assertSee('id="siteDomainCreateModal"', false);
    $response->assertDontSee('<div class="wb-card-header"><strong>Add Domain</strong></div>', false);
  }

  #[Test]
  public function assigned_domains_table_uses_compact_actions_and_no_inline_row_forms(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => true,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($user)->get(route('admin.sites.domains.index', $site));

    $response->assertOk();
    $response->assertSee('class="wb-action-group"', false);
    $response->assertSee('aria-label="Manage domain settings"', false);
    $response->assertSee('aria-label="Remove domain"', false);
    $response->assertDontSee('id="domain_status_', false);
    $response->assertDontSee('>Save<', false);
    $response->assertDontSee('>Make Primary<', false);
    $response->assertDontSee('class="wb-cluster wb-cluster-2 wb-items-end wb-flex-wrap"', false);
    $response->assertDontSee('action="'.route('admin.sites.domains.update', ['site' => $site, 'domain' => $alias]).'"', false);
  }

  #[Test]
  public function manage_domain_modal_renders_status_redirect_and_primary_controls(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($user)->get(route('admin.sites.domains.index', [
      'site' => $site,
      'modal' => 'manage-domain',
      'site_domain' => $alias->id,
    ]));

    $response->assertOk();
    $response->assertSee('Manage Domain: alias.example.test');
    $response->assertSee('id="manage_domain_status_'.$alias->id.'"', false);
    $response->assertSee('Redirect alias to primary');
    $response->assertSee('Make primary domain');
    $response->assertSee('Active domains participate in host resolution.');
    $response->assertSee('The primary domain is used for canonical public URLs.');
  }

  #[Test]
  public function updating_domain_status_through_the_manage_modal_flow_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
      ->from(route('admin.sites.domains.index', ['site' => $site, 'modal' => 'manage-domain', 'site_domain' => $alias->id]))
      ->put(route('admin.sites.domains.update', ['site' => $site, 'domain' => $alias]), [
        '_site_domain_modal' => 'manage-domain',
        '_site_domain_id' => $alias->id,
        'domain' => $alias->domain,
        'status' => SiteDomain::STATUS_INACTIVE,
        'is_primary' => 0,
      ])
      ->assertRedirect(route('admin.sites.domains.index', $site));

    $this->assertDatabaseHas('wbcms_site_domains', [
      'id' => $alias->id,
      'status' => SiteDomain::STATUS_INACTIVE,
    ]);
  }

  #[Test]
  public function updating_domain_redirect_alias_behavior_through_the_manage_modal_flow_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
      ->from(route('admin.sites.domains.index', ['site' => $site, 'modal' => 'manage-domain', 'site_domain' => $alias->id]))
      ->put(route('admin.sites.domains.update', ['site' => $site, 'domain' => $alias]), [
        '_site_domain_modal' => 'manage-domain',
        '_site_domain_id' => $alias->id,
        'domain' => $alias->domain,
        'status' => SiteDomain::STATUS_ACTIVE,
        'redirect_to_primary' => 1,
        'is_primary' => 0,
      ])
      ->assertRedirect(route('admin.sites.domains.index', $site));

    $this->assertDatabaseHas('wbcms_site_domains', [
      'id' => $alias->id,
      'redirect_to_primary' => true,
    ]);
  }

  #[Test]
  public function making_an_alias_primary_through_the_manage_modal_flow_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
      ->from(route('admin.sites.domains.index', ['site' => $site, 'modal' => 'manage-domain', 'site_domain' => $alias->id]))
      ->put(route('admin.sites.domains.update', ['site' => $site, 'domain' => $alias]), [
        '_site_domain_modal' => 'manage-domain',
        '_site_domain_id' => $alias->id,
        'domain' => $alias->domain,
        'status' => SiteDomain::STATUS_ACTIVE,
        'redirect_to_primary' => 0,
        'is_primary' => 1,
      ])
      ->assertRedirect(route('admin.sites.domains.index', $site));

    $this->assertSame('alias.example.test', $site->fresh()->canonicalDomain());
    $this->assertDatabaseHas('wbcms_site_domains', [
      'id' => $alias->id,
      'is_primary' => true,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);
  }

  #[Test]
  public function remove_confirmation_modal_renders_and_removable_alias_delete_still_works(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($user)->get(route('admin.sites.domains.index', [
      'site' => $site,
      'modal' => 'remove-domain',
      'site_domain' => $alias->id,
    ]));

    $response->assertOk();
    $response->assertSee('Remove Domain: alias.example.test');
    $response->assertSee('Confirm whether this alias domain should be removed from the site.');
    $response->assertSeeInOrder([
      'data-admin-form-actions',
      'data-admin-form-actions-main',
      'Remove domain',
      'Cancel',
    ], false);
    $response->assertDontSee('data-admin-form-actions-danger', false);

    $this->actingAs($user)
      ->from(route('admin.sites.domains.index', ['site' => $site, 'modal' => 'remove-domain', 'site_domain' => $alias->id]))
      ->delete(route('admin.sites.domains.destroy', ['site' => $site, 'domain' => $alias]), [
        '_site_domain_modal' => 'remove-domain',
        '_site_domain_id' => $alias->id,
      ])
      ->assertRedirect(route('admin.sites.domains.index', $site));

    $this->assertDatabaseMissing('wbcms_site_domains', ['id' => $alias->id]);
  }

  #[Test]
  public function invalid_manage_modal_updates_redirect_back_to_the_same_modal_url(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $alias = $site->siteDomains()->create([
      'domain' => 'alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);
    $modalUrl = route('admin.sites.domains.index', ['site' => $site, 'modal' => 'manage-domain', 'site_domain' => $alias->id]);

    $this->actingAs($user)
      ->followingRedirects()
      ->from($modalUrl)
      ->put(route('admin.sites.domains.update', ['site' => $site, 'domain' => $alias]), [
        '_site_domain_modal' => 'manage-domain',
        '_site_domain_id' => $alias->id,
        'domain' => $alias->domain,
        'status' => 'broken',
        'is_primary' => 0,
      ])
      ->assertSee('Validation Error')
      ->assertSee('Manage Domain: alias.example.test');
  }

  #[Test]
  public function primary_domain_delete_validation_redirects_back_to_the_remove_modal(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $primary = $site->siteDomains()->where('is_primary', true)->firstOrFail();
    $modalUrl = route('admin.sites.domains.index', ['site' => $site, 'modal' => 'remove-domain', 'site_domain' => $primary->id]);

    $response = $this->actingAs($user)
      ->from($modalUrl)
      ->delete(route('admin.sites.domains.destroy', ['site' => $site, 'domain' => $primary]), [
        '_site_domain_modal' => 'remove-domain',
        '_site_domain_id' => $primary->id,
      ]);

    $response->assertRedirect($modalUrl);
    $response->assertSessionHasErrors('domain');
    $this->followRedirects($response)->assertSee('Removal Blocked');
  }

  #[Test]
  public function cross_site_domain_mutation_is_rejected(): void
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
    $otherDomain = $otherSite->siteDomains()->create([
      'domain' => 'other-alias.example.test',
      'is_primary' => false,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
      ->put(route('admin.sites.domains.update', ['site' => $site, 'domain' => $otherDomain]), [
        'domain' => $otherDomain->domain,
        'status' => SiteDomain::STATUS_ACTIVE,
        'is_primary' => 0,
      ])
      ->assertNotFound();
  }

  #[Test]
  public function site_domain_mutation_requires_system_access(): void
  {
    $user = User::factory()->editor()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');
    $domain = $site->siteDomains()->where('is_primary', true)->firstOrFail();

    $this->actingAs($user)
      ->put(route('admin.sites.domains.update', ['site' => $site, 'domain' => $domain]), [
        'domain' => $domain->domain,
        'status' => SiteDomain::STATUS_ACTIVE,
        'is_primary' => 1,
      ])
      ->assertForbidden();
  }

  #[Test]
  public function domains_landing_redirects_to_the_current_site_domain_screen_when_site_context_exists(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$site] = $this->seedPublicSiteWithDomain('primary.example.test');

    $response = $this->actingAs($user)
      ->withSession(['admin.pages.site' => (string) $site->id])
      ->get(route('admin.domains.index'));

    $response->assertRedirect(route('admin.sites.domains.index', $site));
  }

  #[Test]
  public function domains_landing_lists_accessible_sites_when_no_current_site_context_exists(): void
  {
    $user = User::factory()->superAdmin()->create();
    [$primarySite] = $this->seedPublicSiteWithDomain('primary.example.test');
    $otherSite = Site::query()->create([
      'name' => 'Docs Site',
      'handle' => 'docs-site',
      'domain' => 'docs.example.test',
      'is_primary' => false,
    ]);
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $otherSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

    $response = $this->actingAs($user)
      ->withSession(['admin.domains.site' => 'all'])
      ->get(route('admin.domains.index'));

    $response->assertOk();
    $response->assertSee('Select a site');
    $response->assertSee($primarySite->name);
    $response->assertSee($otherSite->name);
    $response->assertSee('href="'.route('admin.sites.domains.index', $primarySite).'"', false);
    $response->assertSee('href="'.route('admin.sites.domains.index', $otherSite).'"', false);
  }

  #[Test]
  public function domains_landing_requires_system_access(): void
  {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)
      ->get(route('admin.domains.index'))
      ->assertForbidden();
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

    $response = $this->get('http://docs.example.test/about');

    $response->assertOk();
    $response->assertSee('<link rel="canonical" href="https://primary.example.test/about">', false);
    $this->assertSame('https://primary.example.test/about', $page->fresh()->canonicalUrl());
    $this->assertSame('http://docs.example.test/about', $page->fresh()->currentHostPublicUrl());
  }

  #[Test]
  public function internal_api_domain_attach_validates_conflicts_and_delete_removes_only_requested_domain(): void
  {
    $this->createInternalApiToken('secret-token');

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

    $this->assertDatabaseMissing('wbcms_site_domains', ['id' => $domainId]);
    $this->assertDatabaseHas('wbcms_site_domains', ['site_id' => $site->id, 'domain' => 'primary.example.test']);
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
      'path' => '/about',
    ]);

    return [$site, $page, $locale, $slotType, $headerType];
  }

  private function createInternalApiToken(string $token): void
  {
    CmsApiToken::query()->create([
      'name' => 'Test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($token),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($token),
    ]);
  }
}
