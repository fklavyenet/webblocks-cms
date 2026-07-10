<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Admin\ContactMessageController as PackageContactMessageController;
use WebBlocks\Cms\Http\Controllers\Admin\DashboardController as PackageDashboardController;
use WebBlocks\Cms\Http\Controllers\Admin\ProfileController as PackageProfileController;
use WebBlocks\Cms\Http\Controllers\Admin\SlotTypeController as PackageSlotTypeController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSearchController as PackageSystemSearchController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSettingsController as PackageSystemSettingsController;
use WebBlocks\Cms\Http\Controllers\Admin\VisitorReportController as PackageVisitorReportController;
use WebBlocks\Cms\Http\Middleware\UseCmsAuthenticationRedirect;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\System\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class AdminDashboardRouteTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function operational_admin_runtime_routes_use_package_controllers_and_views_without_root_app_wrappers(): void
  {
    $this->assertRouteUsesPackageController('admin.dashboard', PackageDashboardController::class);
    $this->assertRouteUsesPackageController('admin.contact-messages.index', PackageContactMessageController::class);
    $this->assertRouteUsesPackageController('admin.reports.visitors.index', PackageVisitorReportController::class);
    $this->assertRouteUsesPackageController('admin.slot-types.index', PackageSlotTypeController::class);
    $this->assertRouteUsesPackageController('admin.system.search.index', PackageSystemSearchController::class);
    $this->assertRouteUsesPackageController('admin.system.settings.edit', PackageSystemSettingsController::class);
    $this->assertRouteUsesPackageController('admin.profile.edit', PackageProfileController::class);

    foreach (['admin.dashboard', 'admin.contact-messages.index', 'admin.reports.visitors.index', 'admin.slot-types.index', 'admin.system.search.index', 'admin.system.settings.edit', 'admin.profile.edit'] as $routeName) {
      $middleware = app('router')->getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

      $this->assertContains('web', $middleware);
      $this->assertContains('install.required', $middleware);
      $this->assertContains(UseCmsAuthenticationRedirect::class, $middleware);
      $this->assertContains('admin.access', $middleware);
    }

    $this->assertContains('can:access-system', app('router')->getRoutes()->getByName('admin.slot-types.index')?->gatherMiddleware() ?? []);
    $this->assertContains('can:access-system', app('router')->getRoutes()->getByName('admin.system.search.index')?->gatherMiddleware() ?? []);
    $this->assertContains('can:access-system', app('router')->getRoutes()->getByName('admin.system.settings.edit')?->gatherMiddleware() ?? []);
    $this->assertTrue(view()->exists('webblocks-cms::admin.dashboard'));
    $this->assertTrue(view()->exists('webblocks-cms::layouts.admin'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.contact-messages.index'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.reports.visitors.index'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.slot-types.index'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.system.search'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.system.settings'));
    $this->assertTrue(view()->exists('webblocks-cms::admin.profile.edit'));
    $this->assertFalse(view()->exists('layouts.admin'));
    $this->assertStringContainsString('webblocks-cms::admin.dashboard', file_get_contents(resource_path('views/admin/dashboard.blade.php')));
    $this->assertStringContainsString('webblocks-cms::admin.contact-messages.index', file_get_contents(resource_path('views/admin/contact-messages/index.blade.php')));
    $this->assertStringContainsString('webblocks-cms::admin.reports.visitors.index', file_get_contents(resource_path('views/admin/reports/visitors/index.blade.php')));
    $this->assertStringContainsString('webblocks-cms::admin.slot-types.index', file_get_contents(resource_path('views/admin/slot-types/index.blade.php')));
    $this->assertStringContainsString('webblocks-cms::admin.system.search', file_get_contents(resource_path('views/admin/system/search.blade.php')));
    $this->assertStringContainsString('webblocks-cms::admin.system.settings', file_get_contents(resource_path('views/admin/system/settings.blade.php')));

    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\DashboardController'));
    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\ContactMessageController'));
    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\VisitorReportController'));
    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\SlotTypeController'));
    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\SystemSearchController'));
    $this->assertFalse(class_exists('App\\Http\\Controllers\\Admin\\SystemSettingsController'));
    $this->assertFalse(class_exists('App\\Support\\Visitors\\VisitorReportsQuery'));
    $this->assertFalse(class_exists('App\\Support\\Search\\PublicSearchSchema'));
  }

  #[Test]
  public function admin_dashboard_route_name_points_to_canonical_webadmin_path(): void
  {
    $this->assertSame('/webadmin', route('admin.dashboard', absolute: false));
  }

  #[Test]
  public function webadmin_root_opens_dashboard_for_authenticated_super_admins(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    app(InstalledVersionStore::class)->persist('0.1.4');

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Dashboard Landing',
      'slug' => 'dashboard-landing',
      'status' => 'published',
    ]);

    VisitorEvent::query()->create([
      'site_id' => $page->site_id,
      'page_id' => $page->id,
      'path' => '/dashboard-landing',
      'session_key' => 'dashboard-session',
      'ip_hash' => 'dashboard-hash',
      'visited_at' => CarbonImmutable::today()->setTime(9, 0),
    ]);

    $response = $this->actingAs($user)->get('/webadmin');

    $response->assertOk();
    $response->assertSee('<title>Dashboard - WebBlocks CMS</title>', false);
    $response->assertSee('Dashboard');
    $response->assertSee('WebBlocks CMS v'.WebBlocks::version());
    $response->assertSee('Visitor Summary');
    $response->assertSee('/dashboard-landing');
    $response->assertSee('Actions and Shortcuts');
    $response->assertSee('href="'.route('admin.pages.create').'"', false);
    $response->assertSee('New Page');
    $response->assertSee('href="'.route('admin.pages.index').'"', false);
    $response->assertSee('Pages');
    $response->assertSee('href="'.route('admin.sites.index').'"', false);
    $response->assertSee('href="'.route('admin.system.updates.index').'"', false);
    $response->assertDontSee('Sites, backups, and system updates are available to super admins only.');

    $content = $response->getContent();

    $this->assertNotFalse($content);
    $this->assertLessThan(strpos($content, 'Recent Pages'), strpos($content, 'Actions and Shortcuts'));
    $this->assertLessThan(strpos($content, 'Recent Media'), strpos($content, 'Overview'));
    $this->assertLessThan(strpos($content, 'Visitor Summary'), strpos($content, 'Recent Pages'));
  }

  #[Test]
  public function admin_dashboard_uses_configured_admin_locale_copy(): void
  {
    SystemSetting::query()->updateOrCreate(
      ['key' => SystemSettings::ADMIN_LOCALE],
      ['value' => 'tr'],
    );
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->get('/webadmin')
      ->assertOk()
      ->assertSee('Pano')
      ->assertSee('Aksiyonlar ve Kısayollar')
      ->assertSee('Son Sayfalar')
      ->assertSee('Ziyaretçi Özeti');
  }

  #[Test]
  public function admin_layout_adds_product_suffix_to_listing_browser_titles_once(): void
  {
    $user = User::factory()->superAdmin()->create();

    $sitesResponse = $this->actingAs($user)->get(route('admin.sites.index'));
    $pagesResponse = $this->actingAs($user)->get(route('admin.pages.index'));

    $sitesResponse->assertOk();
    $sitesResponse->assertSee('<title>Sites - WebBlocks CMS</title>', false);
    $sitesResponse->assertDontSee('<title>Sites</title>', false);
    $sitesResponse->assertDontSee('<title>Sites - WebBlocks CMS - WebBlocks CMS</title>', false);

    $pagesResponse->assertOk();
    $pagesResponse->assertSee('<title>Pages - WebBlocks CMS</title>', false);
    $pagesResponse->assertDontSee('<title>Pages</title>', false);
    $pagesResponse->assertDontSee('<title>Pages - WebBlocks CMS - WebBlocks CMS</title>', false);
  }

  #[Test]
  public function admin_layout_does_not_duplicate_existing_product_title_suffix(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user);

    $html = view('webblocks-cms::layouts.admin', [
      'title' => 'Already Suffixed - WebBlocks CMS',
      'heading' => 'Already Suffixed',
    ])->render();

    $this->assertStringContainsString('<title>Already Suffixed - WebBlocks CMS</title>', $html);
    $this->assertStringNotContainsString('<title>Already Suffixed - WebBlocks CMS - WebBlocks CMS</title>', $html);
  }

  #[Test]
  public function site_scoped_admin_users_are_redirected_from_webadmin_root_to_allowed_admin_landing_page(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $siteAdmin = User::factory()->siteAdmin()->create();
    $editor = User::factory()->editor()->create();
    $siteAdmin->sites()->sync([$site->id]);
    $editor->sites()->sync([$site->id]);

    $siteAdminResponse = $this->actingAs($siteAdmin)->get('/webadmin');
    $editorResponse = $this->actingAs($editor)->get('/webadmin/');

    $siteAdminResponse->assertRedirect(route('admin.pages.index', absolute: false));
    $editorResponse->assertRedirect(route('admin.pages.index', absolute: false));

    $this->followingRedirects()->actingAs($siteAdmin)->get(route('admin.pages.index'))->assertOk();
    $this->followingRedirects()->actingAs($editor)->get(route('admin.pages.index'))->assertOk();
  }

  #[Test]
  public function reported_root_case_does_not_forbid_a_user_who_can_open_sites(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/webadmin/sites')->assertOk();
    $this->actingAs($user)->get('/webadmin')->assertOk();
    $this->actingAs($user)->get('/webadmin/')->assertOk();
  }

  #[Test]
  public function dashboard_shortcuts_include_system_links_for_super_admins(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Actions and Shortcuts');
    $response->assertSee('href="'.route('admin.pages.create').'"', false);
    $response->assertSee('New Page');
    $response->assertSee('href="'.route('admin.pages.index').'"', false);
    $response->assertSee('Pages');
    $response->assertSee('href="'.route('admin.sites.index').'"', false);
    $response->assertSee('Sites');
    $response->assertSee('href="'.route('admin.system.backups.index').'"', false);
    $response->assertSee('Backups');
    $response->assertSee('href="'.route('admin.system.updates.index').'"', false);
    $response->assertSee('Update');
  }

  #[Test]
  public function admin_navbar_renders_hidden_update_indicator_shell_for_super_admins_only(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $superAdmin = User::factory()->superAdmin()->create();
    $siteAdmin = User::factory()->siteAdmin()->create();
    $siteAdmin->sites()->sync([$site->id]);

    $superAdminResponse = $this->actingAs($superAdmin)->get(route('admin.dashboard'));

    $superAdminResponse->assertOk();
    $superAdminResponse->assertSee('data-wb-update-indicator', false);
    $superAdminResponse->assertSee('data-wb-update-indicator-url="'.route('admin.system.updates.indicator').'"', false);
    $superAdminResponse->assertSee('data-wb-update-indicator-state="unknown"', false);
    $superAdminResponse->assertSee('href="'.route('admin.system.updates.index').'"', false);
    $superAdminResponse->assertSee('class="wb-icon wb-icon-download"', false);
    $superAdminResponse->assertSee('aria-label="System Updates"', false);
    $superAdminResponse->assertSee('title="System Updates"', false);
    $superAdminResponse->assertSee('hidden', false);

    $siteAdminResponse = $this->actingAs($siteAdmin)->get(route('admin.pages.index'));

    $siteAdminResponse->assertOk();
    $siteAdminResponse->assertDontSee('data-wb-update-indicator', false);
  }

  #[Test]
  public function admin_update_indicator_endpoint_returns_available_update_payload_and_caches_result(): void
  {
    $user = User::factory()->superAdmin()->create();
    Cache::forget(AdminUpdateIndicator::CACHE_KEY);

    $this->mock(UpdateServerClient::class, function ($mock): void {
      $mock->shouldReceive('check')->once()->andReturn(new UpdateCheckResult(
        state: 'update_available',
        label: 'Update available',
        message: 'A newer published release is available from the configured update server.',
        badgeClass: 'wb-status-info',
        serverReachable: true,
        apiVersion: '1',
        serverUrl: 'https://updates.example.test',
        product: 'webblocks-cms',
        channel: 'stable',
        installedVersion: WebBlocks::version(),
        latestVersion: '99.0.0',
        updateAvailable: true,
        compatibility: ['status' => 'compatible', 'reasons' => []],
        release: ['download_url' => 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip'],
        errorCode: null,
        errorMessage: null,
        checkedAt: CarbonImmutable::now(),
      ));
    });

    $response = $this->actingAs($user)->getJson(route('admin.system.updates.indicator'));

    $response->assertOk();
    $response->assertJsonPath('visible', true);
    $response->assertJsonPath('state', 'update_available');
    $response->assertJsonPath('label', 'Update 99.0.0 available');
    $response->assertJsonPath('latest_version', '99.0.0');
    $response->assertJsonPath('url', route('admin.system.updates.index'));

    $cachedResponse = $this->actingAs($user)->getJson(route('admin.system.updates.indicator'));

    $cachedResponse->assertOk();
    $cachedResponse->assertJsonPath('visible', true);
    $cachedResponse->assertJsonPath('latest_version', '99.0.0');
  }

  #[Test]
  public function admin_update_indicator_refreshes_inactive_statuses_quickly(): void
  {
    $user = User::factory()->superAdmin()->create();
    Cache::forget(AdminUpdateIndicator::CACHE_KEY);
    config()->set('webblocks-updates.indicator_inactive_cache_ttl_seconds', 60);
    config()->set('webblocks-updates.indicator_cache_ttl_seconds', 3600);

    $this->mock(UpdateServerClient::class, function ($mock): void {
      $mock->shouldReceive('check')->once()->andReturn(new UpdateCheckResult(
        state: 'up_to_date',
        label: 'Already up to date',
        message: 'This install is already on the latest published release.',
        badgeClass: 'wb-status-active',
        serverReachable: true,
        apiVersion: '1',
        serverUrl: 'https://updates.example.test',
        product: 'webblocks-cms',
        channel: 'stable',
        installedVersion: WebBlocks::version(),
        latestVersion: WebBlocks::version(),
        updateAvailable: false,
        compatibility: ['status' => 'compatible', 'reasons' => []],
        release: null,
        errorCode: null,
        errorMessage: null,
        checkedAt: CarbonImmutable::now(),
      ));

      $mock->shouldReceive('check')->once()->andReturn(new UpdateCheckResult(
        state: 'update_available',
        label: 'Update available',
        message: 'A newer published release is available from the configured update server.',
        badgeClass: 'wb-status-info',
        serverReachable: true,
        apiVersion: '1',
        serverUrl: 'https://updates.example.test',
        product: 'webblocks-cms',
        channel: 'stable',
        installedVersion: WebBlocks::version(),
        latestVersion: '99.0.0',
        updateAvailable: true,
        compatibility: ['status' => 'compatible', 'reasons' => []],
        release: ['download_url' => 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip'],
        errorCode: null,
        errorMessage: null,
        checkedAt: CarbonImmutable::now()->addSeconds(61),
      ));
    });

    $initialResponse = $this->actingAs($user)->getJson(route('admin.system.updates.indicator'));

    $initialResponse->assertOk();
    $initialResponse->assertJsonPath('visible', false);
    $initialResponse->assertJsonPath('state', 'up_to_date');

    $this->travel(61)->seconds();

    $refreshedResponse = $this->actingAs($user)->getJson(route('admin.system.updates.indicator'));

    $refreshedResponse->assertOk();
    $refreshedResponse->assertJsonPath('visible', true);
    $refreshedResponse->assertJsonPath('state', 'update_available');
    $refreshedResponse->assertJsonPath('latest_version', '99.0.0');
  }

  #[Test]
  public function admin_update_indicator_endpoint_requires_system_access(): void
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $siteAdmin = User::factory()->siteAdmin()->create();
    $siteAdmin->sites()->sync([$site->id]);

    $this->actingAs($siteAdmin)
      ->getJson(route('admin.system.updates.indicator'))
      ->assertForbidden();
  }

  #[Test]
  public function webadmin_dashboard_path_redirects_to_canonical_webadmin_path(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/webadmin/dashboard');

    $response->assertRedirect(route('admin.dashboard', absolute: false));
  }

  #[Test]
  public function guests_are_redirected_to_login_from_canonical_webadmin_path(): void
  {
    $response = $this->get('/webadmin');

    $response->assertRedirect(route('webblocks.auth.login'));
  }

  #[Test]
  public function cms_admin_routes_are_absent_and_static_asset_prefix_remains_separate(): void
  {
    foreach (app('router')->getRoutes() as $route) {
      $this->assertNotSame('cms', $route->uri());
      $this->assertFalse(str_starts_with($route->uri(), 'cms/'), $route->uri());
    }

    $adminPrefix = strtok(ltrim(route('admin.dashboard', absolute: false), '/'), '/');
    $assetPrefix = basename(WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TARGET);

    $this->assertSame('webadmin', $adminPrefix);
    $this->assertSame('cms', $assetPrefix);
    $this->assertNotSame($assetPrefix, $adminPrefix);
    $this->assertFileExists(public_path('cms/css/admin.css'));
    $this->assertFileExists(public_path('cms/js/admin/core.js'));
    $this->assertFileExists(public_path('cms/brand/logo-mark.svg'));
    $this->assertFileDoesNotExist(public_path('cms/index.php'));
    $this->assertFileDoesNotExist(base_path('packages/webblocks-cms/public/cms/index.php'));
  }

  #[Test]
  public function cms_routes_do_not_claim_the_host_admin_namespace(): void
  {
    foreach (app('router')->getRoutes() as $route) {
      $this->assertNotSame('admin', $route->uri());
      $this->assertFalse(str_starts_with($route->uri(), 'admin/'), $route->uri());
    }

    $this->get('/admin')->assertNotFound();
  }

  #[Test]
  public function admin_layout_resets_transient_overlay_and_sidebar_state_on_restore(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get('/webadmin');

    $response->assertOk();
    $response->assertSee('cms/js/admin/core.js', false);
    $response->assertSee('cms/css/admin.css', false);
    $response->assertDontSee('cms/js/admin/password-fields.js', false);
    $response->assertDontSee('cms/js/admin/asset-picker.js', false);
    $response->assertDontSee('cms/js/admin/media-copy.js', false);
    $response->assertDontSee('cms/js/admin-sortable-list.js', false);
    $response->assertDontSee('cms/js/admin/inline-block-builder.js', false);
    $response->assertDontSee('cms/js/admin/builder-items.js', false);
    $response->assertDontSee('cms/js/admin/page-builder-modals.js', false);
    $response->assertDontSee('cms/js/admin/slot-block-delete-modal.js', false);
    $response->assertDontSee('cms/js/admin/page-slot-source-modals.js', false);
    $response->assertDontSee('cms/js/admin/page-assets.js', false);
    $response->assertDontSee('cms/js/admin/gallery-items.js', false);
    $response->assertDontSee('cms/js/admin/rich-text-editor.js', false);
    $response->assertDontSee('function resetAdminTransientUiState()', false);
    $response->assertDontSee("document.body.classList.remove('wb-overlay-lock', 'overflow-y-hidden');", false);
    $response->assertDontSee("window.addEventListener('pageshow'", false);
    $response->assertDontSee("document.querySelectorAll('[data-wb-sidebar-backdrop]')", false);
    $response->assertDontSee("overlayRoot.querySelector('.wb-overlay-layer--dialog > .wb-overlay-backdrop')", false);
    $response->assertDontSee("overlayRoot.querySelectorAll('[data-wb-overlay-runtime=\"true\"]')", false);
  }

  #[Test]
  public function admin_layout_uses_pinned_webblocks_ui_standard_dist_assets_and_not_master_or_minified_urls(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get('/webadmin');

    $response->assertOk();
    $response->assertSee(WebBlocks::uiCssUrl(), false);
    $response->assertSee(WebBlocks::iconsCssUrl(), false);
    $response->assertSee(WebBlocks::uiJsUrl(), false);
    $response->assertSee('webblocks-ui.css', false);
    $response->assertSee('webblocks-icons.css', false);
    $response->assertSee('webblocks-ui.js', false);
    $response->assertSee('<script src="'.WebBlocks::uiJsUrl().'" defer></script>', false);
    $response->assertSee('webblocks-ui@'.WebBlocks::UI_VERSION, false);
    $response->assertDontSee('raw.githubusercontent.com/fklavyenet/webblocks-ui', false);
    $response->assertDontSee('@b43f92b', false);
    $response->assertDontSee('webblocks-ui.min.css', false);
    $response->assertDontSee('webblocks-icons.min.css', false);
    $response->assertDontSee('webblocks-ui.min.js', false);
    $response->assertDontSee('cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master', false);
  }

  #[Test]
  public function admin_layout_places_sidebar_backdrop_inside_dashboard_shell_for_webblocks_ui_sidebar_close_behavior(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get('/webadmin');

    $response->assertOk();
    $response->assertSeeInOrder([
      '<div class="wb-dashboard-shell">',
      '<div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>',
      '<aside class="wb-sidebar" id="admin-sidebar">',
      '<div class="wb-dashboard-body">',
    ], false);
  }

  #[Test]
  public function package_owned_admin_views_extend_the_package_layout_namespace(): void
  {
    $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/dashboard.blade.php')));
    $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/pages/index.blade.php')));
    $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/slot-types/index.blade.php')));
    $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/system/search.blade.php')));
    $this->assertStringContainsString("@extends('webblocks-cms::layouts.admin'", file_get_contents(base_path('packages/webblocks-cms/resources/views/admin/system/settings.blade.php')));
  }

  #[Test]
  public function top_level_dashboard_path_is_not_claimed_by_cms(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    // /dashboard is the host convenience route that redirects to the CMS admin,
    // not a CMS public page. The redirect-manager catch-all must not shadow it.
    $response->assertRedirect('/webadmin');
  }

  private function assertRouteUsesPackageController(string $routeName, string $controllerClass): void
  {
    $route = app('router')->getRoutes()->getByName($routeName);

    $this->assertNotNull($route, 'Route '.$routeName.' should be registered.');

    $controller = (string) $route->getAction('controller');

    if ($routeName === 'admin.dashboard') {
      $this->assertSame($controllerClass, $controller);

      return;
    }

    $this->assertStringStartsWith($controllerClass.'@', $controller);
  }
}
