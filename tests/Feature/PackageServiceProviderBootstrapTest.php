<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\IconCatalogController;
use App\Http\Requests\Admin\IconCatalogItemUpdateRequest;
use App\Support\Admin\AdminPagination;
use App\Support\BlockTypes\BlockTypeIndexState;
use App\Support\Icons\IconCatalog;
use App\Support\Icons\WebBlocksIconManifestSyncer;
use App\Support\Media\MediaIndexState;
use App\Support\Pages\PageIndexState;
use App\Support\WebBlocks;
use Database\Seeders\CoreCatalogSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\IconCatalogSeeder;
use Database\Seeders\LayoutTypeSeeder;
use Database\Seeders\PageTypeSeeder;
use Database\Seeders\SlotTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Database\Seeders\CoreCatalogSeeder as PackageCoreCatalogSeeder;
use WebBlocks\Cms\Database\Seeders\IconCatalogSeeder as PackageIconCatalogSeeder;
use WebBlocks\Cms\Database\Seeders\LayoutTypeSeeder as PackageLayoutTypeSeeder;
use WebBlocks\Cms\Database\Seeders\PageTypeSeeder as PackagePageTypeSeeder;
use WebBlocks\Cms\Database\Seeders\SlotTypeSeeder as PackageSlotTypeSeeder;
use WebBlocks\Cms\Http\Controllers\Admin\IconCatalogController as PackageIconCatalogController;
use WebBlocks\Cms\Http\Requests\Admin\IconCatalogItemUpdateRequest as PackageIconCatalogItemUpdateRequest;
use WebBlocks\Cms\Support\Admin\AdminPagination as PackageAdminPagination;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeIndexState as PackageBlockTypeIndexState;
use WebBlocks\Cms\Support\Icons\IconCatalog as PackageIconCatalog;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer as PackageWebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\Media\MediaIndexState as PackageMediaIndexState;
use WebBlocks\Cms\Support\Pages\PageIndexState as PackagePageIndexState;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageServiceProviderBootstrapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function discovered_package_provider_loads_without_changing_current_root_runtime_behavior(): void
    {
        $this->assertTrue(class_exists(WebBlocksCmsServiceProvider::class));
        $this->assertTrue($this->app->providerIsLoaded(WebBlocksCmsServiceProvider::class));

        $router = $this->app['router'];
        $viewHints = view()->getFinder()->getHints();

        $this->assertFileExists(base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_FILE));
        $this->assertFileExists(base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_FILE));
        $this->assertFileExists(base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_FILE));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/migrations/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/CoreCatalogSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/IconCatalogSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/PageTypeSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/LayoutTypeSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/database/seeders/SlotTypeSeeder.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/public/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/stubs/README.md'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Admin/AdminPagination.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/BlockTypes/BlockTypeIndexState.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Controllers/Admin/IconCatalogController.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Http/Requests/Admin/IconCatalogItemUpdateRequest.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Icons/IconCatalog.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Icons/WebBlocksIconManifestSyncer.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Media/MediaIndexState.php'));
        $this->assertFileExists(base_path('packages/webblocks-cms/src/Support/Pages/PageIndexState.php'));
        $this->assertFileExists(base_path('database/seeders/CoreCatalogSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/IconCatalogSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/PageTypeSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/LayoutTypeSeeder.php'));
        $this->assertFileExists(base_path('database/seeders/SlotTypeSeeder.php'));
        $this->assertFileExists(base_path('app/Support/Admin/AdminPagination.php'));
        $this->assertFileExists(base_path('app/Support/BlockTypes/BlockTypeIndexState.php'));
        $this->assertFileExists(base_path('app/Http/Controllers/Admin/IconCatalogController.php'));
        $this->assertFileExists(base_path('app/Http/Requests/Admin/IconCatalogItemUpdateRequest.php'));
        $this->assertFileExists(base_path('app/Support/Icons/IconCatalog.php'));
        $this->assertFileExists(base_path('app/Support/Icons/WebBlocksIconManifestSyncer.php'));
        $this->assertFileExists(base_path('app/Support/Media/MediaIndexState.php'));
        $this->assertFileExists(base_path('app/Support/Pages/PageIndexState.php'));
        $this->assertNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull($router->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertArrayHasKey(WebBlocksCmsServiceProvider::VIEW_NAMESPACE, $viewHints);
        $this->assertContains(
            base_path('packages/webblocks-cms/resources/views'),
            $viewHints[WebBlocksCmsServiceProvider::VIEW_NAMESPACE]
        );
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::diagnostics.package-status'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status'));
        $this->assertTrue(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status'));
        $this->assertFalse(view()->exists('diagnostics.package-status'));
        $this->assertTrue(view()->exists('welcome'));
        $this->assertSame([], config('webblocks-cms', []));
        $this->assertSame('fklavyenet/webblocks-cms', WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME);
        $this->assertSame('fklavyenet/webblocks-cms-starter', WebBlocksCmsServiceProvider::STARTER_PACKAGE_NAME);
        $this->assertSame('composer require fklavyenet/webblocks-cms', WebBlocksCmsServiceProvider::TARGET_INSTALL_COMMAND);
        $this->assertSame('composer update fklavyenet/webblocks-cms', WebBlocksCmsServiceProvider::TARGET_UPDATE_COMMAND);
    }

    #[Test]
    public function package_diagnostic_view_renders_through_the_package_namespace_without_overriding_root_view_resolution(): void
    {
        $rendered = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::diagnostics.package-status', [
            'viewNamespace' => WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
            'packageBasePath' => base_path('packages/webblocks-cms'),
        ])->render();

        $welcomeViewPath = app('view.finder')->find('welcome');

        $this->assertStringContainsString('WebBlocks CMS package diagnostic view', $rendered);
        $this->assertStringContainsString('View namespace: webblocks-cms', $rendered);
        $this->assertStringContainsString('Package base path:', $rendered);
        $this->assertStringContainsString('Root runtime remains authoritative for active admin and public views.', $rendered);
        $this->assertSame(resource_path('views/welcome.blade.php'), $welcomeViewPath);
    }

    #[Test]
    public function package_admin_and_public_views_render_through_the_package_namespace_without_overriding_root_views(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $adminRendered = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status', [
            'packageRouteName' => WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME,
            'packageRoutePath' => WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH,
        ])->render();

        $publicRendered = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status', [
            'packageRouteName' => WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME,
            'packageRoutePath' => WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH,
        ])->render();

        $this->assertStringContainsString('Package Admin Runtime Status', $adminRendered);
        $this->assertStringContainsString('Root admin pages such as Pages, Media, Blocks, Sites, Updates, Backups, and Export / Import remain root-owned.', $adminRendered);
        $this->assertStringContainsString('Package Public Runtime Status', $publicRendered);
        $this->assertStringContainsString('root public page rendering, multisite routing, search, and block rendering remain authoritative.', $publicRendered);
        $this->assertSame(resource_path('views/welcome.blade.php'), app('view.finder')->find('welcome'));
    }

    #[Test]
    public function package_diagnostic_route_is_explicitly_guarded_and_not_loaded_in_normal_runtime(): void
    {
        $this->assertFalse(config(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false));
        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, false));
        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, false));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH));
    }

    #[Test]
    public function guarded_package_diagnostic_route_can_be_loaded_explicitly_without_conflicting_with_root_runtime_routes(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH));

        config()->set(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, true);
        config()->set(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, true);

        $provider = new class($this->app) extends WebBlocksCmsServiceProvider
        {
            /**
             * @var array<int, string>
             */
            public array $loadedRouteFiles = [];

            public function bootPackageRoutesForTest(): void
            {
                $this->bootRoutes();
            }

            protected function loadRoutesFrom($path): void
            {
                $this->loadedRouteFiles[] = $path;
            }
        };

        $provider->bootPackageRoutesForTest();

        $this->assertSame([
            base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_FILE),
            base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_FILE),
            base_path('packages/webblocks-cms/routes/'.WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_FILE),
        ], $provider->loadedRouteFiles);
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME));
        $this->assertNull(app('router')->getRoutes()->getByName(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH));
        $this->assertFalse($this->routePathExists(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH));
        $this->assertSame(resource_path('views/welcome.blade.php'), app('view.finder')->find('welcome'));
    }

    #[Test]
    public function package_migrations_assets_and_stubs_remain_inert_until_a_later_phase_intentionally_wires_them(): void
    {
        $provider = new class($this->app) extends WebBlocksCmsServiceProvider
        {
            /**
             * @var array<int, string>
             */
            public array $loadedMigrationPaths = [];

            /**
             * @var array<int, array{paths: array<string, string>, group: string|null}>
             */
            public array $publishCalls = [];

            public function bootMigrationBoundaryForTest(): void
            {
                $this->bootMigrations();
            }

            public function bootPublishingBoundariesForTest(): void
            {
                $this->bootAssetPublishing();
                $this->bootStubPublishing();
            }

            protected function loadMigrationsFrom($paths): void
            {
                foreach ((array) $paths as $path) {
                    $this->loadedMigrationPaths[] = $path;
                }
            }

            public function publishes(array $paths, $groups = null): void
            {
                $this->publishCalls[] = [
                    'paths' => $paths,
                    'group' => $groups,
                ];
            }
        };

        $provider->bootMigrationBoundaryForTest();
        $provider->bootPublishingBoundariesForTest();

        $this->assertFalse(config(WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG, false));
        $this->assertSame([], $provider->loadedMigrationPaths);
        $this->assertSame([], $provider->publishCalls);
    }

    #[Test]
    public function package_seeders_and_runtime_support_classes_keep_root_compatibility_entrypoints(): void
    {
        $this->assertTrue(class_exists(PackageCoreCatalogSeeder::class));
        $this->assertTrue(class_exists(PackageIconCatalogSeeder::class));
        $this->assertTrue(class_exists(PackagePageTypeSeeder::class));
        $this->assertTrue(class_exists(PackageLayoutTypeSeeder::class));
        $this->assertTrue(class_exists(PackageSlotTypeSeeder::class));
        $this->assertTrue(class_exists(PackageAdminPagination::class));
        $this->assertTrue(class_exists(PackageBlockTypeIndexState::class));
        $this->assertTrue(class_exists(PackageIconCatalogController::class));
        $this->assertTrue(class_exists(PackageIconCatalogItemUpdateRequest::class));
        $this->assertTrue(class_exists(PackageIconCatalog::class));
        $this->assertTrue(class_exists(PackageWebBlocksIconManifestSyncer::class));
        $this->assertTrue(class_exists(PackageMediaIndexState::class));
        $this->assertTrue(class_exists(PackagePageIndexState::class));

        $this->assertTrue(is_subclass_of(CoreCatalogSeeder::class, PackageCoreCatalogSeeder::class));
        $this->assertTrue(is_subclass_of(IconCatalogSeeder::class, PackageIconCatalogSeeder::class));
        $this->assertTrue(is_subclass_of(PageTypeSeeder::class, PackagePageTypeSeeder::class));
        $this->assertTrue(is_subclass_of(LayoutTypeSeeder::class, PackageLayoutTypeSeeder::class));
        $this->assertTrue(is_subclass_of(SlotTypeSeeder::class, PackageSlotTypeSeeder::class));
        $this->assertTrue(is_subclass_of(AdminPagination::class, PackageAdminPagination::class));
        $this->assertTrue(is_subclass_of(BlockTypeIndexState::class, PackageBlockTypeIndexState::class));
        $this->assertTrue(is_subclass_of(IconCatalogController::class, PackageIconCatalogController::class));
        $this->assertTrue(is_subclass_of(IconCatalogItemUpdateRequest::class, PackageIconCatalogItemUpdateRequest::class));
        $this->assertTrue(is_subclass_of(IconCatalog::class, PackageIconCatalog::class));
        $this->assertTrue(is_subclass_of(WebBlocksIconManifestSyncer::class, PackageWebBlocksIconManifestSyncer::class));
        $this->assertTrue(is_subclass_of(MediaIndexState::class, PackageMediaIndexState::class));
        $this->assertTrue(is_subclass_of(PageIndexState::class, PackagePageIndexState::class));
    }

    protected function routePathExists(string $path): bool
    {
        $expectedPath = ltrim($path, '/');

        foreach (app('router')->getRoutes() as $route) {
            if ($route->uri() === $expectedPath) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function package_default_update_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/webblocks-updates.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame('https://updates.webblocksui.com', config('webblocks-updates.server_url'));
        $this->assertSame('stable', config('webblocks-updates.channel'));
        $this->assertSame('1', config('webblocks-updates.api_version'));
        $this->assertSame(WebBlocks::HANDLE, config('webblocks-updates.product'));
        $this->assertSame(WebBlocks::VERSION, config('webblocks-updates.current_version'));
    }

    #[Test]
    public function package_default_contact_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/contact.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame(3, config('contact.minimum_submit_seconds'));
        $this->assertSame(5, config('contact.rate_limit_per_minute'));
        $this->assertSame(
            'Thanks for your message. We will get back to you soon.',
            config('contact.success_message')
        );
    }

    #[Test]
    public function package_default_demo_media_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/demo_media.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertCount(9, config('demo_media.items', []));
        $this->assertSame('home-hero-01', config('demo_media.items.0.key'));
        $this->assertSame('gallery-04', config('demo_media.items.8.key'));
    }

    #[Test]
    public function package_default_cms_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/cms.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame('DISABLED', config('cms.install.git_protection.disabled_push_url'));
        $this->assertSame(15, config('cms.install.git_protection.timeout_seconds'));
        $this->assertSame('auto', config('cms.backup.execution'));
        $this->assertSame('webblocks_visitor_consent', config('cms.visitor_reports.consent_cookie_name'));
        $this->assertContains('googleother', config('cms.visitor_reports.ignored_user_agents', []));
    }

    #[Test]
    public function root_update_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('webblocks-updates.php'));

        config()->set('webblocks-updates.server_url', 'https://override.example.test');
        config()->set('webblocks-updates.installer.lock_name', 'custom-system-updates-lock');

        $this->assertSame('https://override.example.test', config('webblocks-updates.server_url'));
        $this->assertSame('custom-system-updates-lock', config('webblocks-updates.installer.lock_name'));
        $this->assertSame(WebBlocks::VERSION, config('webblocks-updates.current_version'));
    }

    #[Test]
    public function root_contact_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('contact.php'));

        config()->set('contact.rate_limit_per_minute', 9);
        config()->set('contact.success_message', 'Custom success message');

        $this->assertSame(9, config('contact.rate_limit_per_minute'));
        $this->assertSame('Custom success message', config('contact.success_message'));
    }

    #[Test]
    public function root_demo_media_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('demo_media.php'));

        config()->set('demo_media.items', [
            [
                'key' => 'custom-demo-item',
                'topic' => 'custom',
                'title' => 'Custom demo item',
                'folder' => 'Custom',
                'source_url' => 'https://example.test/custom-demo-item.jpg',
                'alt' => 'Custom demo item',
            ],
        ]);

        $this->assertCount(1, config('demo_media.items', []));
        $this->assertSame('custom-demo-item', config('demo_media.items.0.key'));
    }

    #[Test]
    public function root_cms_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('cms.php'));

        config()->set('cms.backup.execution', 'mysqldump');
        config()->set('cms.visitor_reports.enabled', false);
        config()->set('cms.install.git_protection.disabled_push_url', 'NO-PUSH');

        $this->assertSame('mysqldump', config('cms.backup.execution'));
        $this->assertFalse(config('cms.visitor_reports.enabled'));
        $this->assertSame('NO-PUSH', config('cms.install.git_protection.disabled_push_url'));
    }
}
