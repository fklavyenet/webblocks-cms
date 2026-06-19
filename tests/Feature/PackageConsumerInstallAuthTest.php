<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Admin\SiteExportController as PackageSiteExportController;
use WebBlocks\Cms\Http\Controllers\Admin\SiteImportController as PackageSiteImportController;
use WebBlocks\Cms\Http\Controllers\Admin\SitePromotionController as PackageSitePromotionController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemUpdateController as PackageSystemUpdateController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\WebBlocks;

class PackageConsumerInstallAuthTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->artisan('webblocks:install', [
      '--name' => 'Auth Admin',
      '--email' => 'auth-admin@example.com',
      '--password' => 'secret-password',
      '--site-name' => 'Auth Site',
      '--site-handle' => 'auth-site',
      '--no-interaction' => true,
      '--force' => true,
    ])->assertExitCode(0);
  }

  #[Test]
  public function package_owned_cms_login_route_and_view_exist_after_install(): void
  {
    $response = $this->get(route('webblocks.auth.login'));

    $response->assertOk();
    $response->assertSee('class="wb-auth-shell wb-auth-split"', false);
    $response->assertSee('class="wb-auth-panel wb-bg-primary"', false);
    $response->assertSee('class="wb-auth-card"', false);
    $response->assertSee('<svg', false);
    $response->assertSee('viewBox="0 0 128 128"', false);
    $response->assertSee('stroke="currentColor"', false);
    $response->assertDontSee('cms/brand/logo-mark.svg', false);
    $response->assertDontSee('cms/brand/logo-mark-dark.svg', false);
    $response->assertDontSee('cms/brand/logo-mark-on-accent.svg', false);
    $response->assertDontSee('cms/brand/logo-mark-mask.svg', false);
    $response->assertDontSee('wb-auth-brand-mark-mask', false);
    $response->assertSee('wb-auth-brand-mark-on-accent', false);
    $response->assertSee('wb-auth-brand-mark-on-surface', false);
    $response->assertDontSee('wb-auth-brand-logo', false);
    $response->assertDontSee('wb-auth-brand-mark-light', false);
    $response->assertDontSee('wb-auth-brand-mark-dark', false);
    $response->assertDontSee('<img', false);
    $this->assertDoesNotMatchRegularExpression('/<(?:picture|source)\b/i', $response->getContent());
    $response->assertDontSee('prefers-color-scheme', false);
    $response->assertSee(WebBlocks::name());
    $response->assertSee(WebBlocks::slogan());
    $response->assertSee(WebBlocks::uiCssUrl(), false);
    $response->assertSee(WebBlocks::iconsCssUrl(), false);
    $response->assertSee(WebBlocks::uiJsUrl(), false);
    $response->assertSee('<script src="'.WebBlocks::uiJsUrl().'" defer></script>', false);
    $response->assertSee('webblocks-ui@v2.7.12', false);
    $response->assertSee('webblocks-ui.css', false);
    $response->assertSee('webblocks-icons.css', false);
    $response->assertSee('webblocks-ui.js', false);
    $response->assertDontSee('raw.githubusercontent.com/fklavyenet/webblocks-ui', false);
    $response->assertDontSee('@b43f92b', false);
    $response->assertDontSee('webblocks-ui.min.css', false);
    $response->assertDontSee('webblocks-icons.min.css', false);
    $response->assertDontSee('webblocks-ui.min.js', false);
    $response->assertSee('cms/css/guest.css', false);
    $response->assertSee('action="'.route('webblocks.auth.login').'"', false);
    $response->assertSee('name="email"', false);
    $response->assertSee('name="password"', false);
    $response->assertSee('name="remember"', false);
    $response->assertSee('Forgot password');
    $response->assertSee('href="'.route('webblocks.auth.password.request').'"', false);
    $response->assertDontSee('href="'.route('password.request').'"', false);
    $response->assertDontSee('Need an account?');
    $response->assertDontSee('href="'.route('register').'"', false);
    $response->assertSee('data-password-toggle', false);
    $this->assertFileExists(public_path('cms/css/guest.css'));
    $this->assertFileExists(public_path('cms/brand/logo-mark.svg'));
    $this->assertFileExists(public_path('cms/brand/logo-mark-dark.svg'));
    $this->assertFileExists(public_path('cms/brand/logo-mark-on-accent.svg'));
    $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/css/guest.css'));
    $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/brand/logo-mark.svg'));
    $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/brand/logo-mark-dark.svg'));
    $this->assertFileExists(base_path('packages/webblocks-cms/public/cms/brand/logo-mark-on-accent.svg'));

    $this->assertAuthBrandSvgsHaveNoDimensions($response->getContent());
  }

  #[Test]
  public function package_owned_cms_login_shell_uses_package_safe_views_and_assets(): void
  {
    $loginView = File::get(base_path('packages/webblocks-cms/resources/views/auth/login.blade.php'));
    $forgotPasswordView = File::get(base_path('packages/webblocks-cms/resources/views/auth/forgot-password.blade.php'));
    $resetPasswordView = File::get(base_path('packages/webblocks-cms/resources/views/auth/reset-password.blade.php'));
    $guestLayout = File::get(base_path('packages/webblocks-cms/resources/views/layouts/guest.blade.php'));
    $brandMarkComponent = File::get(base_path('packages/webblocks-cms/resources/views/components/brand-mark.blade.php'));
    $guestCss = File::get(public_path('cms/css/guest.css'));
    $packageGuestCss = File::get(base_path('packages/webblocks-cms/public/cms/css/guest.css'));
    $authViews = $loginView."\n".$forgotPasswordView."\n".$resetPasswordView;

    $this->assertStringContainsString('webblocks-cms::layouts.guest', $loginView);
    $this->assertStringContainsString('webblocks-cms::partials.head-meta', $guestLayout);
    $this->assertStringContainsString('<x-webblocks-cms::brand-mark', $authViews);
    $this->assertStringNotContainsString('asset(\'cms/brand/logo-mark.svg\')', $authViews);
    $this->assertStringNotContainsString('asset(\'cms/brand/logo-mark-dark.svg\')', $authViews);
    $this->assertStringNotContainsString('asset(\'cms/brand/logo-mark-on-accent.svg\')', $authViews);
    $this->assertStringNotContainsString('logo-mark-mask.svg', $authViews);
    $this->assertStringNotContainsString('wb-auth-brand-mark-mask', $authViews);
    $this->assertStringContainsString('wb-auth-brand-mark-on-accent', $authViews);
    $this->assertStringContainsString('wb-auth-brand-mark-on-surface', $authViews);
    $this->assertStringNotContainsString('wb-auth-brand-logo', $authViews);
    $this->assertStringNotContainsString('wb-auth-brand-mark-light', $authViews);
    $this->assertStringNotContainsString('wb-auth-brand-mark-dark', $authViews);
    $this->assertStringNotContainsString('<img', $authViews);
    $this->assertDoesNotMatchRegularExpression('/<(?:picture|source)\b/i', $authViews);
    $this->assertStringNotContainsString('prefers-color-scheme', $authViews);
    $this->assertStringContainsString('viewBox="0 0 128 128"', $brandMarkComponent);
    $this->assertStringContainsString('stroke="currentColor"', $brandMarkComponent);
    $componentMarkup = View::make('webblocks-cms::components.brand-mark', [
      'attributes' => new ComponentAttributeBag(['class' => 'wb-auth-brand-mark']),
    ])->render();
    $this->assertAuthBrandSvgsHaveNoDimensions($componentMarkup, 1);
    $this->assertStringNotContainsString('<script', $brandMarkComponent);
    $this->assertStringNotContainsString('foreignObject', $brandMarkComponent);
    $this->assertStringNotContainsString('style=', $brandMarkComponent);
    $this->assertDoesNotMatchRegularExpression('/<svg\b[^>]*\s(?:width|height)=/i', $brandMarkComponent);
    $this->assertStringContainsString('asset(\'cms/css/guest.css\')', $guestLayout);
    $this->assertStringContainsString('WebBlocks::uiCssUrl()', $guestLayout);
    $this->assertStringContainsString('WebBlocks::iconsCssUrl()', $guestLayout);
    $this->assertStringContainsString('WebBlocks::uiJsUrl()', $guestLayout);
    $this->assertStringNotContainsString('<x-guest-layout', $loginView);
    $this->assertStringNotContainsString('<x-auth-shell', $loginView);
    $this->assertStringNotContainsString("@extends('layouts.guest'", $loginView);
    $this->assertStringNotContainsString('@extends("layouts.guest"', $loginView);
    $this->assertStringNotContainsString('@include(\'partials.head-meta\'', $guestLayout);
    $this->assertSame($guestCss, $packageGuestCss);
    $this->assertStringContainsString('.wb-auth-brand {', $guestCss);
    $this->assertStringContainsString('display: inline-flex;', $guestCss);
    $this->assertStringContainsString('align-items: center;', $guestCss);
    $this->assertStringContainsString('gap: var(--wb-space-3, 0.75rem);', $guestCss);
    $this->assertStringContainsString('svg.wb-auth-brand-mark {', $guestCss);
    $this->assertStringContainsString('inline-size: 3rem;', $guestCss);
    $this->assertStringContainsString('block-size: 3rem;', $guestCss);
    $this->assertStringContainsString('inline-size: 100%;', $guestCss);
    $this->assertStringContainsString('block-size: 100%;', $guestCss);
    $this->assertStringContainsString('svg.wb-auth-brand-mark-sm {', $guestCss);
    $this->assertStringContainsString('inline-size: 2rem;', $guestCss);
    $this->assertStringContainsString('block-size: 2rem;', $guestCss);
    $this->assertStringContainsString('color: var(--wb-accent-on);', $guestCss);
    $this->assertStringContainsString('color: var(--wb-accent);', $guestCss);
    $this->assertStringNotContainsString('mask-image:', $guestCss);
    $this->assertStringNotContainsString('-webkit-mask-image:', $guestCss);
    $this->assertStringNotContainsString('prefers-color-scheme', $guestCss);
  }

  #[Test]
  public function cms_dashboard_redirects_guests_to_the_login_page(): void
  {
    $this->assertSame('/webadmin', route('admin.dashboard', absolute: false));
    $this->assertSame('/webadmin/login', route('webblocks.auth.login', absolute: false));

    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('webblocks.auth.login'));
  }

  #[Test]
  public function cms_auth_uses_package_owned_route_names_when_a_host_owns_login(): void
  {
    $quizTemLoginRoute = Route::get('/quiztem/login', fn () => response('QuizTem login screen'))->name('login');
    Route::post('/quiztem/login', fn () => response('QuizTem login submit', 418))->name('quiztem.login.store');
    $this->forceNamedRouteLookup('login', $quizTemLoginRoute);

    $this->assertSame('/quiztem/login', route('login', absolute: false));
    $this->assertSame('/webadmin/login', route('webblocks.auth.login', absolute: false));

    $this->get('/webadmin')->assertRedirect(route('webblocks.auth.login'));

    $this->get(route('webblocks.auth.login'))
      ->assertOk()
      ->assertSee('action="'.route('webblocks.auth.login').'"', false)
      ->assertDontSee('action="'.route('login').'"', false);

    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    $this->post(route('webblocks.auth.login'), [
      'email' => $user->email,
      'password' => 'secret-password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
  }

  #[Test]
  public function package_owned_auth_routes_use_cms_namespace(): void
  {
    $routeFile = (string) file_get_contents(base_path('packages/webblocks-cms/routes/auth.php'));

    $this->assertStringContainsString('/webadmin/login', $routeFile);
    $this->assertStringContainsString('/webadmin/logout', $routeFile);
    $this->assertStringContainsString('/webadmin/forgot-password', $routeFile);
    $this->assertStringContainsString('/webadmin/reset-password/{token}', $routeFile);
    $this->assertStringContainsString("name('webblocks.auth.login')", $routeFile);
    $this->assertStringContainsString("name('webblocks.auth.logout')", $routeFile);
    $this->assertStringNotContainsString("name('login')", $routeFile);
    $this->assertStringNotContainsString("name('logout')", $routeFile);
    $this->assertStringNotContainsString('/cms/login', $routeFile);
    $this->assertStringNotContainsString('/cms/logout', $routeFile);
    $this->assertStringNotContainsString('/admin/login', $routeFile);
    $this->assertStringNotContainsString('/admin/logout', $routeFile);
  }

  #[Test]
  public function package_routes_do_not_register_cms_admin_entry_paths(): void
  {
    foreach (app('router')->getRoutes() as $route) {
      $this->assertNotSame('cms', $route->uri());
      $this->assertFalse(str_starts_with($route->uri(), 'cms/'), $route->uri());
    }
  }

  #[Test]
  public function package_routes_do_not_register_legacy_admin_entry_paths(): void
  {
    foreach (app('router')->getRoutes() as $route) {
      $this->assertNotSame('admin', $route->uri());
      $this->assertFalse(str_starts_with($route->uri(), 'admin/'), $route->uri());
    }
  }

  #[Test]
  public function cms_auth_does_not_expose_legacy_admin_or_cms_login_aliases(): void
  {
    $this->get('/admin/login')->assertNotFound();
    $this->get('/cms/login')->assertNotFound();
  }

  #[Test]
  public function first_super_admin_can_log_in_and_reach_the_cms_dashboard(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    $loginResponse = $this->post(route('webblocks.auth.login'), [
      'email' => $user->email,
      'password' => 'secret-password',
    ]);

    $loginResponse->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);

    $dashboard = $this->actingAs($user)->get(route('admin.dashboard'));

    $dashboard->assertOk();
    $dashboard->assertSee('Dashboard');
  }

  #[Test]
  public function public_home_route_renders_after_install(): void
  {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('Install WebBlocks CMS');
    $response->assertDontSee('Laravel');
  }

  #[Test]
  public function admin_sites_and_navigation_routes_render_after_install(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    $this->actingAs($user)->get(route('admin.sites.index'))
      ->assertOk();

    $this->actingAs($user)->get(route('admin.navigation.index'))
      ->assertOk();
  }

  #[Test]
  public function admin_users_index_and_create_routes_render_after_install(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    $this->actingAs($user)->get(route('admin.users.index'))
      ->assertOk();

    $this->actingAs($user)->get(route('admin.users.create'))
      ->assertOk();
  }

  #[Test]
  public function broader_package_routed_admin_surfaces_render_after_install(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    foreach ([
      'admin.dashboard',
      'admin.sites.index',
      'admin.sites.create',
      'admin.users.index',
      'admin.users.create',
      'admin.locales.index',
      'admin.pages.index',
      'admin.blocks.index',
      'admin.media.index',
      'admin.navigation.index',
      'admin.shared-slots.index',
      'admin.system.settings.edit',
      'admin.block-types.index',
      'admin.page-layouts.index',
      'admin.slot-types.index',
      'admin.system.icons.index',
      'admin.system.search.index',
      'admin.system.backups.index',
      'admin.site-transfers.exports.index',
      'admin.site-transfers.imports.index',
      'admin.site-transfers.imports.create',
      'admin.sites.promote',
      'admin.system.updates.index',
      'admin.reports.visitors.index',
      'admin.contact-messages.index',
    ] as $routeName) {
      $this->actingAs($user)->get(route($routeName))->assertOk();
    }
  }

  #[Test]
  public function representative_package_owned_admin_routes_render_after_install_without_root_admin_view_dependencies(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    foreach ([
      'admin.dashboard',
      'admin.pages.index',
      'admin.pages.create',
      'admin.blocks.index',
      'admin.media.index',
      'admin.navigation.index',
      'admin.shared-slots.index',
      'admin.block-types.index',
      'admin.page-layouts.index',
      'admin.slot-types.index',
      'admin.system.settings.edit',
    ] as $routeName) {
      $this->actingAs($user)->get(route($routeName))->assertOk();
    }
  }

  #[Test]
  public function site_transfer_routes_render_after_install_through_the_package_view_boundary(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();
    $exportRoute = app('router')->getRoutes()->getByName('admin.site-transfers.exports.index');
    $importRoute = app('router')->getRoutes()->getByName('admin.site-transfers.imports.index');
    $promotionRoute = app('router')->getRoutes()->getByName('admin.sites.promote');
    $exportControllerSource = (string) file_get_contents(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SiteExportController.php'));
    $importControllerSource = (string) file_get_contents(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SiteImportController.php'));
    $promotionControllerSource = (string) file_get_contents(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SitePromotionController.php'));
    $site = Site::query()->firstOrFail();
    $siteExport = SiteExport::query()->create([
      'site_id' => $site->id,
      'user_id' => $user->id,
      'status' => SiteExport::STATUS_COMPLETED,
      'includes_media' => false,
      'archive_disk' => 'site-exports',
      'archive_path' => 'consumer-export.zip',
      'archive_name' => 'consumer-export.zip',
      'archive_size_bytes' => 1024,
    ]);
    $siteImport = SiteImport::query()->create([
      'user_id' => $user->id,
      'status' => SiteImport::STATUS_VALIDATED,
      'source_archive_name' => 'consumer-import.zip',
      'archive_disk' => 'site-transfers',
      'archive_path' => 'consumer-import.zip',
    ]);

    $this->assertNotNull($exportRoute);
    $this->assertNotNull($importRoute);
    $this->assertNotNull($promotionRoute);
    $this->assertStringStartsWith(PackageSiteExportController::class.'@', (string) $exportRoute?->getAction('controller'));
    $this->assertStringStartsWith(PackageSiteImportController::class.'@', (string) $importRoute?->getAction('controller'));
    $this->assertStringStartsWith(PackageSitePromotionController::class.'@', (string) $promotionRoute?->getAction('controller'));
    $this->assertStringContainsString("view('webblocks-cms::admin.site-transfers.exports.index'", $exportControllerSource);
    $this->assertStringContainsString("view('webblocks-cms::admin.site-transfers.imports.index'", $importControllerSource);
    $this->assertStringContainsString("view('webblocks-cms::admin.sites.promote'", $promotionControllerSource);
    $this->assertStringNotContainsString("view('admin/site-transfers", $exportControllerSource);
    $this->assertStringNotContainsString("view('admin.site-transfers", $exportControllerSource);
    $this->assertStringNotContainsString("view('admin/site-transfers", $importControllerSource);
    $this->assertStringNotContainsString("view('admin.site-transfers", $importControllerSource);

    $this->actingAs($user)->get(route('admin.site-transfers.exports.index'))
      ->assertOk()
      ->assertSee('Site Exports')
      ->assertSee('Site Imports');

    $this->actingAs($user)->get(route('admin.site-transfers.imports.index'))
      ->assertOk()
      ->assertSee('Export / Import')
      ->assertSee('Open Export / Import');

    $this->actingAs($user)->get(route('admin.site-transfers.imports.create'))
      ->assertOk()
      ->assertSee('Run Import');

    $this->actingAs($user)->get(route('admin.site-transfers.exports.show', $siteExport))
      ->assertOk()
      ->assertSee('Export Details')
      ->assertDontSee('admin.site-transfers.exports.show');

    $this->actingAs($user)->get(route('admin.site-transfers.imports.show', $siteImport))
      ->assertOk()
      ->assertSee('Import Details')
      ->assertDontSee('admin.site-transfers.imports.show');

    $this->actingAs($user)->get(route('admin.sites.promote'))
      ->assertOk()
      ->assertSee('Site Promotion');
  }

  #[Test]
  public function system_updates_route_renders_after_install_through_the_package_view_boundary(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();
    $route = app('router')->getRoutes()->getByName('admin.system.updates.index');
    $controller = (string) $route?->getAction('controller');
    $controllerSource = (string) file_get_contents(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/SystemUpdateController.php'));

    $this->assertNotNull($route);
    $this->assertStringStartsWith(PackageSystemUpdateController::class.'@', $controller);
    $this->assertStringContainsString("view('webblocks-cms::admin.system.updates'", $controllerSource);
    $this->assertStringNotContainsString("view('admin.system.updates'", $controllerSource);
    $this->assertStringNotContainsString("View::make('admin.system.updates'", $controllerSource);
    $this->assertStringNotContainsString("response()->view('admin.system.updates'", $controllerSource);

    $this->actingAs($user)->get(route('admin.system.updates.index'))
      ->assertOk()
      ->assertSee('System Updates')
      ->assertSee('Install Update')
      ->assertSee('Update Details')
      ->assertDontSee('<strong>Actions</strong>', false);
  }

  #[Test]
  public function fresh_consumer_install_can_create_a_pre_update_backup_without_root_backups_disk_config(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

    config()->set('filesystems.disks.backups', null);
    File::deleteDirectory(storage_path('app/backups'));

    $backup = app(SystemBackupManager::class)->createPreUpdateBackup($user->id, 'Fresh consumer pre-update backup');

    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);
    $this->assertFileExists(storage_path('app/backups/'.$backup->archive_path));
  }

  #[Test]
  public function fallback_block_type_admin_form_renders_after_install_through_the_package_view_boundary(): void
  {
    $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();
    $page = Page::query()->firstOrFail();
    $slotType = SlotType::query()->where('slug', 'main')->firstOrFail();
    $pageSlot = PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $slotType->id)->firstOrFail();
    $blockType = BlockType::query()->create([
      'name' => 'Consumer Fallback Block',
      'slug' => 'consumer-fallback-block',
      'category' => 'content',
      'description' => 'Uses the generic fallback admin form.',
      'source_type' => 'static',
      'status' => 'published',
      'sort_order' => 9990,
      'is_system' => false,
      'is_container' => false,
    ]);
    $block = new Block([
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'slot' => $slotType->slug,
      'source_type' => 'static',
      'status' => 'draft',
    ]);

    $this->assertSame('webblocks-cms::admin.blocks.types.fallback', $block->adminFormView());

    $this->actingAs($user)->get(route('admin.pages.slots.blocks', [
      'page' => $page,
      'slot' => $pageSlot,
      'picker' => 1,
      'block_type_id' => $blockType->id,
    ]))
      ->assertOk()
      ->assertSee('Add Block: Consumer Fallback Block')
      ->assertSee('Generic Block Form')
      ->assertSee('The safe fallback form is being used.')
      ->assertSee('name="title"', false)
      ->assertSee('name="content"', false);
  }

  #[Test]
  public function inline_fallback_block_type_admin_form_renders_after_install_through_the_package_view_boundary(): void
  {
    $page = Page::query()->firstOrFail();
    $blockType = BlockType::query()->create([
      'name' => 'Consumer Inline Fallback Block',
      'slug' => 'consumer-inline-fallback-block',
      'category' => 'content',
      'description' => 'Uses the generic fallback inline admin form.',
      'source_type' => 'static',
      'status' => 'published',
      'sort_order' => 9991,
      'is_system' => false,
      'is_container' => false,
    ]);
    $slotTypes = SlotType::query()->orderBy('sort_order')->get();
    $block = new Block([
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'page_id' => $page->id,
      'slot_type_id' => $slotTypes->firstOrFail()->id,
      'slot' => $slotTypes->firstOrFail()->slug,
      'source_type' => 'static',
      'status' => 'draft',
    ]);

    $rendered = View::make('webblocks-cms::admin.pages.partials.inline-block-fields', [
      'block' => $block,
      'index' => 0,
      'selectedBlockType' => $blockType,
      'slotTypes' => $slotTypes,
    ])->render();

    $this->assertStringContainsString('Generic Block Form', $rendered);
    $this->assertStringContainsString('The safe fallback form is being used.', $rendered);
    $this->assertStringContainsString('name="blocks[0][title]"', $rendered);
    $this->assertStringContainsString('name="blocks[0][content]"', $rendered);
  }

  private function assertAuthBrandSvgsHaveNoDimensions(string $html, int $expectedCount = 2): void
  {
    preg_match_all('/<svg\b[^>]*class="[^"]*wb-auth-brand-mark[^"]*"[^>]*>/i', $html, $matches);

    $this->assertCount($expectedCount, $matches[0]);

    foreach ($matches[0] as $svg) {
      $this->assertStringNotContainsString(' width=', $svg);
      $this->assertStringNotContainsString(' height=', $svg);
    }
  }

  private function forceNamedRouteLookup(string $name, \Illuminate\Routing\Route $route): void
  {
    $routes = Route::getRoutes();
    $property = new \ReflectionProperty($routes, 'nameList');
    $property->setAccessible(true);

    $nameList = $property->getValue($routes);
    $nameList[$name] = $route;

    $property->setValue($routes, $nameList);
  }
}
