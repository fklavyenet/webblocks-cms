<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Admin\SystemUpdateController as PackageSystemUpdateController;

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
    public function package_login_route_and_view_exist_after_install(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('login');
        $response->assertSee('email');
    }

    #[Test]
    public function admin_route_redirects_guests_to_the_login_page(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function first_super_admin_can_log_in_and_reach_the_admin_dashboard(): void
    {
        $user = User::query()->where('email', 'auth-admin@example.com')->firstOrFail();

        $loginResponse = $this->post(route('login'), [
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
            'admin.system.updates.index',
            'admin.reports.visitors.index',
            'admin.contact-messages.index',
        ] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
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
            ->assertSee('Update Summary')
            ->assertSee('Actions');
    }
}
