<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\WebBlocks;

class AdminSidebarNavigationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function dashboard_sidebar_renders_direct_editorial_links_and_the_new_system_groups(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));
    $content = $response->getContent();
    $domainsHref = 'href="'.route('admin.domains.index').'"';
    $reportsHref = 'href="'.route('admin.reports.visitors.index').'"';
    $settingsHref = 'href="'.route('admin.system.settings.edit').'"';
    $searchHref = 'href="'.route('admin.system.search.index').'"';
    $backupsHref = 'href="'.route('admin.system.backups.index').'"';
    $transfersHref = 'href="'.route('admin.site-transfers.exports.index').'"';
    $updatesHref = 'href="'.route('admin.system.updates.index').'"';
    $usersHref = 'href="'.route('admin.users.index').'"';
    $sitesHref = 'href="'.route('admin.sites.index').'"';
    $localesHref = 'href="'.route('admin.locales.index').'"';
    $pageLayoutsHref = 'href="'.route('admin.page-layouts.index').'"';
    $slotTypesHref = 'href="'.route('admin.slot-types.index').'"';
    $blockTypesHref = 'href="'.route('admin.block-types.index').'"';

    $response->assertOk();
    $response->assertSee('>Dashboard<', false);
    $response->assertSee('href="'.route('admin.dashboard').'"', false);
    $response->assertDontSee('href="'.url('/cms').'"', false);
    $response->assertSee('href="'.route('admin.pages.index').'"', false);
    $response->assertSee('href="'.route('admin.shared-slots.index').'"', false);
    $response->assertSee('href="'.route('admin.navigation.index').'"', false);
    $response->assertSee('href="'.route('admin.media.index').'"', false);
    $response->assertSee('href="'.route('admin.contact-messages.index').'"', false);
    $response->assertSee('>System<', false);
    $response->assertSee('>Maintenance<', false);
    $response->assertSee('href="'.route('admin.domains.index').'"', false);
    $response->assertSee('href="'.route('admin.reports.visitors.index').'"', false);
    $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
    $response->assertSee('href="'.route('admin.system.search.index').'"', false);
    $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
    $response->assertSee('href="'.route('admin.site-transfers.exports.index').'"', false);
    $response->assertSee('href="'.route('admin.system.updates.index').'"', false);
    $response->assertSee('href="'.route('admin.users.index').'"', false);
    $response->assertSee('href="'.route('admin.sites.index').'"', false);
    $response->assertSee('href="'.route('admin.locales.index').'"', false);
    $response->assertSee('href="'.route('admin.page-layouts.index').'"', false);
    $response->assertSee('href="'.route('admin.slot-types.index').'"', false);
    $response->assertSee('href="'.route('admin.block-types.index').'"', false);
    $response->assertDontSee('>Reports<', false);
    $response->assertDontSee('>Access<', false);
    $response->assertDontSee('>Structure<', false);
    $this->assertSame(1, substr_count($content, $usersHref));
    $response->assertSeeInOrder([
      'href="'.route('admin.dashboard').'"',
      $sitesHref,
      'href="'.route('admin.pages.index').'"',
    ], false);
    $this->assertTrue(
      strpos($content, $searchHref) < strpos($content, $backupsHref)
      && strpos($content, $backupsHref) < strpos($content, $transfersHref)
      && strpos($content, $transfersHref) < strpos($content, $updatesHref)
    );
    $this->assertTrue(
      strpos($content, $domainsHref) < strpos($content, $usersHref)
      && strpos($content, $usersHref) < strpos($content, $localesHref)
      && strpos($content, $localesHref) < strpos($content, $pageLayoutsHref)
      && strpos($content, $pageLayoutsHref) < strpos($content, $slotTypesHref)
      && strpos($content, $slotTypesHref) < strpos($content, $blockTypesHref)
      && strpos($content, $blockTypesHref) < strpos($content, $settingsHref)
      && strpos($content, $settingsHref) < strpos($content, $reportsHref)
    );
    $this->assertTrue(
      strpos($content, '>System<') < strpos($content, '>Maintenance<')
    );
    $this->assertTrue(strpos($content, $reportsHref) < strpos($content, '>Maintenance<'));
    $response->assertSeeText('Search Rebuild');
  }

  #[Test]
  public function settings_page_marks_system_group_and_settings_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.settings.edit'));

    $response->assertOk();
    $response->assertSee('wb-nav-group-toggle is-active', false);
    $response->assertSee('>System<', false);
    $response->assertSee('>Maintenance<', false);
    $response->assertSee('href="'.route('admin.system.settings.edit').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
  }

  #[Test]
  public function sidebar_brand_and_footer_stay_fixed_when_project_identity_changes(): void
  {
    $user = User::factory()->superAdmin()->create();
    $adminCss = File::get(public_path('cms/css/admin.css'));
    $packageAdminCss = File::get(base_path('packages/webblocks-cms/public/cms/css/admin.css'));

    SystemSetting::query()->updateOrCreate(['key' => 'system.project_name'], ['value' => 'Project Alpha']);
    SystemSetting::query()->updateOrCreate(['key' => 'system.project_tagline'], ['value' => 'Admin context only']);

    $response = $this->actingAs($user)->get(route('admin.dashboard'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('<a href="'.route('admin.dashboard').'" class="wb-sidebar-brand" aria-label="WebBlocks CMS">', false);
    $response->assertSee('<svg', false);
    $response->assertSee('class="wb-sidebar-brand-logo wb-sidebar-brand-logo-inline"', false);
    $response->assertSee('viewBox="0 0 128 128"', false);
    $response->assertSee('stroke="currentColor"', false);
    $response->assertDontSee('<img src="'.asset('cms/brand/logo-mark.svg'), false);
    $response->assertDontSee('wb-sidebar-brand-logo" src=', false);
    $response->assertSee('>WebBlocks CMS<', false);
    $response->assertSee('>A modern block-based CMS<', false);
    $this->assertSame($adminCss, $packageAdminCss);
    $this->assertStringContainsString('svg.wb-sidebar-brand-logo {', $adminCss);
    $this->assertStringContainsString('inline-size: 3rem;', $adminCss);
    $this->assertStringContainsString('block-size: 3rem;', $adminCss);
    $this->assertStringContainsString('color: var(--wb-accent);', $adminCss);
    $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/brand/logo-mark.svg'));
    $this->assertFileExists(public_path('cms/brand/logo-mark.svg'));
    $this->assertAdminSidebarBrandSvgHasNoDimensions($content);
    $response->assertSee('<div class="wb-text-sm wb-text-muted wb-text-center">WebBlocks CMS v'.WebBlocks::VERSION.'</div>', false);
    $response->assertSee('WebBlocks CMS v'.WebBlocks::VERSION);
  }

  #[Test]
  public function search_page_marks_maintenance_group_and_search_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.search.index'));

    $response->assertOk();
    $response->assertSee('wb-nav-group-toggle is-active', false);
    $response->assertSee('>Maintenance<', false);
    $response->assertSeeText('Search Rebuild');
    $response->assertSee('href="'.route('admin.system.search.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
  }

  #[Test]
  public function visitor_reports_page_marks_system_group_and_visitor_reports_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('>System<', false);
    $response->assertSee('>Visitor Reports<', false);
    $response->assertSee('href="'.route('admin.reports.visitors.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
    $this->assertMatchesRegularExpression('/wb-nav-group-toggle is-active.*?>\\s*<i[^>]*><\\/i>\\s*<span class="wb-nav-group-label">System<\\/span>/s', $content);
    $this->assertTrue(strpos($content, 'href="'.route('admin.reports.visitors.index').'"') < strpos($content, '>Maintenance<'));
  }

  #[Test]
  public function locales_page_marks_system_group_and_locales_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.locales.index'));

    $response->assertOk();
    $response->assertSee('>System<', false);
    $response->assertSee('href="'.route('admin.locales.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
  }

  #[Test]
  public function domains_page_marks_system_group_and_domains_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.domains.index', $site));

    $response->assertOk();
    $response->assertSee('>System<', false);
    $response->assertSee('href="'.route('admin.domains.index').'"', false);
    $response->assertSee('wb-nav-group-toggle is-active', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
    $response->assertDontSee('href="'.route('admin.sites.index').'" class="wb-sidebar-link is-active"', false);
  }

  #[Test]
  public function pages_page_is_a_direct_top_level_sidebar_item(): void
  {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.pages.index'));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.pages.index').'"', false);
    $response->assertSee('class="wb-sidebar-link is-active"', false);
  }

  #[Test]
  public function blocks_navigation_item_is_visible_only_to_super_admin_users(): void
  {
    $superAdmin = User::factory()->superAdmin()->create();
    $siteAdmin = User::factory()->siteAdmin()->create();
    $editor = User::factory()->editor()->create();

    $superAdminResponse = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
    $superAdminResponse->assertOk();
    $superAdminResponse->assertSee('href="'.route('admin.blocks.index').'"', false);

    $siteAdminResponse = $this->followingRedirects()->actingAs($siteAdmin)->get(route('admin.pages.index'));
    $siteAdminResponse->assertOk();
    $siteAdminResponse->assertDontSee('href="'.route('admin.blocks.index').'"', false);

    $editorResponse = $this->followingRedirects()->actingAs($editor)->get(route('admin.pages.index'));
    $editorResponse->assertOk();
    $editorResponse->assertDontSee('href="'.route('admin.blocks.index').'"', false);
  }

  private function assertAdminSidebarBrandSvgHasNoDimensions(string $html): void
  {
    preg_match('/<svg\b[^>]*class="[^"]*wb-sidebar-brand-logo[^"]*"[^>]*>/i', $html, $match);

    $this->assertNotEmpty($match);
    $this->assertStringNotContainsString(' width=', $match[0]);
    $this->assertStringNotContainsString(' height=', $match[0]);
  }

  #[Test]
  public function blocks_page_is_a_direct_top_level_sidebar_item_for_super_admins(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.blocks.index'));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.blocks.index').'"', false);
    $response->assertSee('class="wb-sidebar-link is-active"', false);
    $response->assertSee('wb-icon-box', false);
  }

  #[Test]
  public function shared_slots_page_is_a_direct_top_level_sidebar_item(): void
  {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.shared-slots.index'));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.shared-slots.index').'"', false);
    $response->assertSee('class="wb-sidebar-link is-active"', false);
    $response->assertSee('wb-icon-layers', false);
  }

  #[Test]
  public function visitor_reports_page_does_not_mark_maintenance_group_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertSee('class="wb-nav-group-item is-active"', false);
    $response->assertSee('>Maintenance<', false);
    $this->assertDoesNotMatchRegularExpression('/class="wb-nav-group-toggle is-active"[^>]*>\\s*<i class="wb-icon wb-icon-file wb-nav-group-icon"/', $content);
  }

  #[Test]
  public function admin_users_navigation_item_is_visible_only_to_super_admin_users(): void
  {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->editor()->create();

    $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
    $adminResponse->assertOk();
    $adminResponse->assertSee('href="'.route('admin.users.index').'"', false);
    $adminResponse->assertSee('>System<', false);
    $this->assertSame(1, substr_count($adminResponse->getContent(), 'href="'.route('admin.users.index').'"'));

    $userResponse = $this->followingRedirects()->actingAs($user)->get(route('admin.pages.index'));
    $userResponse->assertOk();
    $userResponse->assertDontSee('href="'.route('admin.users.index').'"', false);
  }

  #[Test]
  public function users_page_is_grouped_under_system_and_not_listed_as_a_top_level_item(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.users.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
    $response->assertSee('>System<', false);
    $response->assertDontSee('class="wb-sidebar-link is-active"', false);
    $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('admin.users.index').'"'));
  }

  #[Test]
  public function sites_page_is_a_top_level_item_and_not_listed_under_system(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.index'));

    $response->assertOk();
    $response->assertSee('>System<', false);
    $response->assertSee('href="'.route('admin.sites.index').'"', false);
    $response->assertSee('class="wb-sidebar-link is-active"', false);
    $response->assertDontSee('href="'.route('admin.sites.index').'" class="wb-nav-group-item', false);
    $response->assertSee($site->name);
  }

  #[Test]
  public function site_edit_page_keeps_sites_top_level_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.sites.edit', $site));

    $response->assertOk();
    $response->assertSee('class="wb-sidebar-link is-active"', false);
    $response->assertDontSee('class="wb-nav-group-item is-active"', false);
  }

  #[Test]
  public function inaccessible_system_links_are_hidden_for_users_without_system_access(): void
  {
    $siteAdmin = User::factory()->siteAdmin()->create();
    $editor = User::factory()->editor()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $siteAdmin->sites()->sync([$site->id]);
    $editor->sites()->sync([$site->id]);

    $siteAdminResponse = $this->followingRedirects()->actingAs($siteAdmin)->get(route('admin.pages.index'));
    $siteAdminResponse->assertOk();
    $siteAdminResponse->assertDontSee('href="'.route('admin.sites.index').'"', false);
    $siteAdminResponse->assertDontSee('href="'.route('admin.domains.index').'"', false);

    $editorResponse = $this->followingRedirects()->actingAs($editor)->get(route('admin.pages.index'));
    $editorResponse->assertOk();
    $editorResponse->assertDontSee('href="'.route('admin.sites.index').'"', false);
    $editorResponse->assertDontSee('href="'.route('admin.domains.index').'"', false);

    $this->actingAs($siteAdmin)->get(route('admin.sites.edit', $site))->assertOk();
    $this->actingAs($editor)->get(route('admin.sites.edit', $site))->assertOk();
  }

  #[Test]
  public function block_types_page_marks_system_group_and_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index'));

    $response->assertOk();
    $response->assertSee('>System<', false);
    $response->assertSee('href="'.route('admin.block-types.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
  }

  #[Test]
  public function icons_page_marks_system_group_and_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.icons.index'));

    $response->assertOk();
    $response->assertSee('>System<', false);
    $response->assertSee('href="'.route('admin.system.icons.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
  }

  #[Test]
  public function icons_navigation_item_is_visible_only_to_super_admin_users(): void
  {
    $superAdmin = User::factory()->superAdmin()->create();
    $siteAdmin = User::factory()->siteAdmin()->create();
    $editor = User::factory()->editor()->create();

    $superAdminResponse = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
    $superAdminResponse->assertOk();
    $superAdminResponse->assertSee('href="'.route('admin.system.icons.index').'"', false);

    $siteAdminResponse = $this->followingRedirects()->actingAs($siteAdmin)->get(route('admin.pages.index'));
    $siteAdminResponse->assertOk();
    $siteAdminResponse->assertDontSee('href="'.route('admin.system.icons.index').'"', false);

    $editorResponse = $this->followingRedirects()->actingAs($editor)->get(route('admin.pages.index'));
    $editorResponse->assertOk();
    $editorResponse->assertDontSee('href="'.route('admin.system.icons.index').'"', false);
  }

  #[Test]
  public function backups_page_marks_maintenance_group_and_item_active(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.system.backups.index'));

    $response->assertOk();
    $response->assertSee('>Maintenance<', false);
    $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
    $response->assertSee('class="wb-nav-group-item is-active"', false);
  }
}
