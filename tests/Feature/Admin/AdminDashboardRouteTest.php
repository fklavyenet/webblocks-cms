<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Admin\ContactMessageController as PackageContactMessageController;
use WebBlocks\Cms\Http\Controllers\Admin\DashboardController as PackageDashboardController;
use WebBlocks\Cms\Http\Controllers\Admin\SlotTypeController as PackageSlotTypeController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSearchController as PackageSystemSearchController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSettingsController as PackageSystemSettingsController;
use WebBlocks\Cms\Http\Controllers\Admin\VisitorReportController as PackageVisitorReportController;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\WebBlocks;

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

    foreach (['admin.dashboard', 'admin.contact-messages.index', 'admin.reports.visitors.index', 'admin.slot-types.index', 'admin.system.search.index', 'admin.system.settings.edit'] as $routeName) {
      $middleware = app('router')->getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

      $this->assertContains('web', $middleware);
      $this->assertContains('install.required', $middleware);
      $this->assertContains('auth', $middleware);
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
    $this->assertStringContainsString('webblocks-cms::admin.dashboard', file_get_contents(resource_path('views/admin/dashboard.blade.php')));
    $this->assertStringContainsString('webblocks-cms::layouts.admin', file_get_contents(resource_path('views/layouts/admin.blade.php')));
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
  public function admin_dashboard_route_name_points_to_canonical_cms_path(): void
  {
    $this->assertSame('/cms', route('admin.dashboard', absolute: false));
  }

  #[Test]
  public function cms_root_opens_dashboard_for_authenticated_users(): void
  {
    $user = User::factory()->editor()->create();
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
      'path' => '/p/dashboard-landing',
      'session_key' => 'dashboard-session',
      'ip_hash' => 'dashboard-hash',
      'visited_at' => CarbonImmutable::today()->setTime(9, 0),
    ]);

    $response = $this->actingAs($user)->get('/cms');

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('WebBlocks CMS v'.WebBlocks::version());
    $response->assertSee('Visitor Summary');
    $response->assertSee('/p/dashboard-landing');
    $response->assertSee('Actions and Shortcuts');
    $response->assertSee('href="'.route('admin.pages.create').'"', false);
    $response->assertSee('New Page');
    $response->assertSee('href="'.route('admin.pages.index').'"', false);
    $response->assertSee('Pages');
    $response->assertDontSee('href="'.route('admin.system.updates.index').'"', false);
    $response->assertSee('Sites, backups, and system updates are available to super admins only.');

    $content = $response->getContent();

    $this->assertNotFalse($content);
    $this->assertLessThan(strpos($content, 'Recent Pages'), strpos($content, 'Actions and Shortcuts'));
    $this->assertLessThan(strpos($content, 'Recent Media'), strpos($content, 'Overview'));
    $this->assertLessThan(strpos($content, 'Visitor Summary'), strpos($content, 'Recent Pages'));
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
  public function cms_dashboard_path_redirects_to_canonical_cms_path(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/cms/dashboard');

    $response->assertRedirect(route('admin.dashboard', absolute: false));
  }

  #[Test]
  public function guests_are_redirected_to_login_from_canonical_cms_path(): void
  {
    $response = $this->get('/cms');

    $response->assertRedirect(route('login'));
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
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/cms');

    $response->assertOk();
    $response->assertSee('cms/js/admin/core.js', false);
    $response->assertSee('cms/css/admin.css', false);
    $response->assertDontSee('function resetAdminTransientUiState()', false);
    $response->assertDontSee("document.body.classList.remove('wb-overlay-lock', 'overflow-y-hidden');", false);
    $response->assertDontSee("window.addEventListener('pageshow'", false);
    $response->assertDontSee("document.querySelectorAll('[data-wb-sidebar-backdrop]')", false);
    $response->assertDontSee("overlayRoot.querySelector('.wb-overlay-layer--dialog > .wb-overlay-backdrop')", false);
    $response->assertDontSee("overlayRoot.querySelectorAll('[data-wb-overlay-runtime=\"true\"]')", false);
  }

  #[Test]
  public function admin_layout_uses_pinned_webblocks_ui_v275_assets_and_not_master_urls(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/cms');

    $response->assertOk();
    $response->assertSee(WebBlocks::uiCssUrl(), false);
    $response->assertSee(WebBlocks::iconsCssUrl(), false);
    $response->assertSee(WebBlocks::uiJsUrl(), false);
    $response->assertDontSee('cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master', false);
  }

  #[Test]
  public function admin_layout_places_sidebar_backdrop_inside_dashboard_shell_for_webblocks_ui_sidebar_close_behavior(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/cms');

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
  public function top_level_dashboard_redirect_uses_canonical_cms_path(): void
  {
    $user = User::factory()->editor()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('admin.dashboard', absolute: false));
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
