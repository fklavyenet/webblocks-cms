<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use App\Support\System\InstalledVersionStore;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageRuntimeSlicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSiteLocaleSeeder::class);
        app(InstalledVersionStore::class)->persist('1.32.0');
    }

    #[Test]
    public function package_runtime_routes_are_not_exposed_by_default(): void
    {
        $this->get(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH)->assertNotFound();
        $this->get(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH)->assertNotFound();

        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH)
            ->assertNotFound();
    }

    #[Test]
    public function guarded_package_diagnostics_route_uses_the_package_handler_and_view_reference_when_enabled(): void
    {
        config()->set(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, true);
        $this->bootPackageRoutes();

        $response = $this->get(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH);

        $response->assertOk();
        $response->assertSee('WebBlocks CMS package diagnostic view');
        $response->assertSee('View namespace: webblocks-cms');
        $response->assertSee('Root runtime remains authoritative for install, auth, profile, migrations, and compatibility wrappers, while active public page or search shells now render from the package view namespace.');
    }

    #[Test]
    public function guarded_package_admin_slice_renders_only_when_enabled_and_does_not_override_root_admin_dashboard(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        config()->set(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG, true);
        $this->bootPackageRoutes();

        $response = $this->actingAs($superAdmin)->get(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH);

        $response->assertOk();
        $response->assertSee('Package Admin Runtime Status');
        $response->assertSee('This screen is package-owned and available only when the dedicated package admin route guard is enabled.');
        $response->assertSee('data-webblocks-cms-package-admin-slice="status"', false);

        $dashboard = $this->actingAs($superAdmin)->get(route('admin.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Dashboard');
        $dashboard->assertDontSee('Package Admin Runtime Status');
    }

    #[Test]
    public function guarded_package_public_slice_renders_only_when_enabled_and_does_not_override_root_public_routes(): void
    {
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG, true);
        $this->bootPackageRoutes();

        $response = $this->get(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH);

        $response->assertOk();
        $response->assertSee('Package Public Runtime Status');
        $response->assertSee('the main public layout, page shell, and search views now render from the package namespace too.');
        $response->assertSee('data-webblocks-cms-package-public-slice="status"', false);

        $site = Site::query()->where('is_primary', true)->firstOrFail();
        $this->assertSame('Default Site', $site->name);

        $home = $this->get('/');

        $home->assertOk();
        $home->assertDontSee('Package Public Runtime Status');
    }

    private function bootPackageRoutes(): void
    {
        $provider = new class($this->app) extends WebBlocksCmsServiceProvider
        {
            public function bootRoutesForTest(): void
            {
                $this->bootRoutes();
            }
        };

        $provider->bootRoutesForTest();
    }
}
