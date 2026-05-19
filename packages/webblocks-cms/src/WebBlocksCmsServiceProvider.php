<?php

namespace WebBlocks\Cms;

use FilesystemIterator;
use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebBlocks\Cms\Console\PackageStatusCommand;
use WebBlocks\Cms\Console\SyncWebBlocksUiIconsCommand;

class WebBlocksCmsServiceProvider extends ServiceProvider
{
    public const PACKAGE_NAME = 'webblocks-cms';

    public const VIEW_NAMESPACE = 'webblocks-cms';

    public const DIAGNOSTIC_ROUTE_FILE = 'diagnostics.php';

    public const PACKAGE_ADMIN_ROUTE_FILE = 'admin.php';

    public const PACKAGE_PUBLIC_ROUTE_FILE = 'public.php';

    public const DIAGNOSTIC_ROUTE_NAME = 'webblocks-cms.diagnostics.package-status';

    public const PACKAGE_ADMIN_ROUTE_NAME = 'admin.webblocks-cms.runtime-status';

    public const PACKAGE_PUBLIC_ROUTE_NAME = 'webblocks-cms.public.runtime-status';

    public const DIAGNOSTIC_ROUTE_PATH = '/_webblocks-cms/diagnostics/package-status';

    public const PACKAGE_ADMIN_ROUTE_PATH = '/admin/_webblocks-cms/runtime-status';

    public const PACKAGE_PUBLIC_ROUTE_PATH = '/_webblocks-cms/runtime-status';

    public const DIAGNOSTIC_ROUTE_LOADING_CONFIG = 'webblocks-cms.diagnostics.load_routes';

    public const PACKAGE_ADMIN_ROUTE_LOADING_CONFIG = 'webblocks-cms.admin.load_routes';

    public const PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG = 'webblocks-cms.admin.load_status_route';

    public const PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG = 'webblocks-cms.public.load_routes';

    public const PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG = 'webblocks-cms.public.load_status_route';

    public const PACKAGE_MIGRATION_LOADING_CONFIG = 'webblocks-cms.boundaries.load_migrations';

    public const PACKAGE_CONFIG_DEFAULTS = [
        'webblocks-cms.php',
        'cms.php',
        'contact.php',
        'demo_media.php',
        'webblocks-updates.php',
    ];

    public const PACKAGE_ROUTE_FILES = [
        'admin.php',
        'diagnostics.php',
        'public.php',
    ];

    public const PACKAGE_VIEW_FILES = [
        'admin/runtime-status.blade.php',
        'admin/system/icons/index.blade.php',
        'admin/system/icons/partials/edit-modal.blade.php',
        'diagnostics/package-status.blade.php',
        'layouts/public.blade.php',
        'pages/show.blade.php',
        'pages/partials/block.blade.php',
        'pages/partials/slot.blade.php',
        'public/pages/show.blade.php',
        'search/show.blade.php',
        'search/partials/modal.blade.php',
        'public/search/show.blade.php',
        'public/runtime-status.blade.php',
    ];

    public const PUBLIC_RENDERING_RUNTIME_FILES = [
        'Support/Blocks/PublicBodyEndRegistry.php',
        'Support/Blocks/PublicOverlayRegistry.php',
        'Support/Blocks/TrustedHtmlOverlayExtractor.php',
        'Support/Pages/PageRouteResolver.php',
        'Support/Pages/PublicPagePresenter.php',
        'Support/Pages/PublicSharedSlotResolver.php',
        'Support/PublicRendering/SiteAssetResolver.php',
        'Support/PublicRendering/SlotWrapperResolver.php',
        'Support/Search/PublicSearchQuery.php',
        'Support/Sites/ResolvedSite.php',
        'Support/Sites/SiteResolver.php',
        'Support/Visitors/VisitorEventLogger.php',
    ];

    public const ROOT_PUBLIC_RENDERING_RUNTIME_WRAPPER_FILES = [
        'Support/Blocks/PublicBodyEndRegistry.php',
        'Support/Blocks/PublicOverlayRegistry.php',
        'Support/Blocks/TrustedHtmlOverlayExtractor.php',
        'Support/Pages/PageRouteResolver.php',
        'Support/Pages/PublicPagePresenter.php',
        'Support/Pages/PublicSharedSlotResolver.php',
        'Support/PublicRendering/SiteAssetResolver.php',
        'Support/PublicRendering/SlotWrapperResolver.php',
        'Support/Search/PublicSearchQuery.php',
        'Support/Sites/ResolvedSite.php',
        'Support/Sites/SiteResolver.php',
        'Support/Visitors/VisitorEventLogger.php',
    ];

    public const ICON_VIEW_FILES = [
        'admin/system/icons/index.blade.php',
        'admin/system/icons/partials/edit-modal.blade.php',
    ];

    public const ROOT_ICON_VIEW_WRAPPER_FILES = [
        'admin/system/icons/index.blade.php',
        'admin/system/icons/partials/edit-modal.blade.php',
    ];

    public const PACKAGE_SEEDER_FILES = [
        'CoreCatalogSeeder.php',
        'IconCatalogSeeder.php',
        'PageTypeSeeder.php',
        'LayoutTypeSeeder.php',
        'SlotTypeSeeder.php',
    ];

    public const PACKAGE_PUBLIC_ASSET_FILES = [
        'cms/package-boundary.json',
    ];

    public const PACKAGE_STUB_FILES = [
        'starter/README.md',
        'starter/composer.json.stub',
        'starter/env.example.stub',
    ];

    public const LOW_RISK_RUNTIME_SUPPORT_FILES = [
        'Admin/AdminPagination.php',
        'BlockTypes/BlockTypeIndexState.php',
        'Media/MediaIndexState.php',
        'Pages/PageIndexState.php',
    ];

    public const ICON_RUNTIME_FILES = [
        'Console/SyncWebBlocksUiIconsCommand.php',
        'Http/Controllers/Admin/IconCatalogController.php',
        'Http/Requests/Admin/IconCatalogItemUpdateRequest.php',
        'Support/Icons/IconCatalog.php',
        'Support/Icons/WebBlocksIconManifestSyncer.php',
    ];

    public const ROOT_ICON_RUNTIME_WRAPPER_FILES = [
        'Console/Commands/SyncWebBlocksUiIconsCommand.php',
        'Http/Controllers/Admin/IconCatalogController.php',
        'Http/Requests/Admin/IconCatalogItemUpdateRequest.php',
        'Support/Icons/IconCatalog.php',
        'Support/Icons/WebBlocksIconManifestSyncer.php',
    ];

    public const ICON_ADMIN_INDEX_ROUTE_NAME = 'admin.system.icons.index';

    public const ICON_ADMIN_UPDATE_ROUTE_NAME = 'admin.system.icons.update';

    public const ICON_SYNC_COMMAND_NAME = 'icons:sync-webblocks-ui';

    public const CONFIG_PUBLISH_TAG = 'webblocks-cms-config';

    public const ASSETS_PUBLISH_TAG = 'webblocks-cms-assets';

    public const STUBS_PUBLISH_TAG = 'webblocks-cms-stubs';

    public const PACKAGE_COMPOSER_NAME = 'fklavyenet/webblocks-cms';

    public const STARTER_PACKAGE_NAME = 'fklavyenet/webblocks-cms-starter';

    public const TARGET_INSTALL_COMMAND = 'composer require fklavyenet/webblocks-cms';

    public const TARGET_UPDATE_COMMAND = 'composer update fklavyenet/webblocks-cms';

    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {
        $this->bootCommands();
        $this->bootRoutes();
        $this->bootViews();
        $this->bootMigrations();
        $this->bootPublishing();
    }

    protected function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            PackageStatusCommand::class,
            SyncWebBlocksUiIconsCommand::class,
        ]);
    }

    protected function registerConfig(): void
    {
        foreach ($this->configFiles() as $file) {
            $this->mergeConfigFrom($file, pathinfo($file, PATHINFO_FILENAME));
        }
    }

    protected function bootRoutes(): void
    {
        $this->loadGuardedRouteFiles(
            $this->diagnosticRoutesShouldLoad(),
            $this->diagnosticRouteFiles()
        );

        $this->loadGuardedRouteFiles(
            $this->packageAdminRoutesShouldLoad(),
            $this->adminRouteFiles()
        );

        $this->loadGuardedRouteFiles(
            $this->packagePublicRoutesShouldLoad(),
            $this->publicRouteFiles()
        );
    }

    protected function diagnosticRoutesShouldLoad(): bool
    {
        return (bool) config(self::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false);
    }

    protected function packageAdminRoutesShouldLoad(): bool
    {
        if (! (bool) config(self::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, false)) {
            return false;
        }

        if ((bool) config(self::PACKAGE_ADMIN_STATUS_ROUTE_LOADING_CONFIG, false)
            && app('router')->getRoutes()->getByName(self::PACKAGE_ADMIN_ROUTE_NAME) === null) {
            return true;
        }

        return app('router')->getRoutes()->getByName(self::ICON_ADMIN_INDEX_ROUTE_NAME) === null;
    }

    protected function packagePublicRoutesShouldLoad(): bool
    {
        if (! (bool) config(self::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, false)) {
            return false;
        }

        if ((bool) config(self::PACKAGE_PUBLIC_STATUS_ROUTE_LOADING_CONFIG, false)
            && app('router')->getRoutes()->getByName(self::PACKAGE_PUBLIC_ROUTE_NAME) === null) {
            return true;
        }

        return app('router')->getRoutes()->getByName('home') === null;
    }

    /**
     * @return array<int, string>
     */
    protected function diagnosticRouteFiles(): array
    {
        return $this->namedRouteFiles(self::DIAGNOSTIC_ROUTE_FILE);
    }

    /**
     * @return array<int, string>
     */
    protected function adminRouteFiles(): array
    {
        return $this->namedRouteFiles(self::PACKAGE_ADMIN_ROUTE_FILE);
    }

    /**
     * @return array<int, string>
     */
    protected function publicRouteFiles(): array
    {
        return $this->namedRouteFiles(self::PACKAGE_PUBLIC_ROUTE_FILE);
    }

    protected function bootViews(): void
    {
        if (! is_dir($this->viewsPath())) {
            return;
        }

        $this->loadViewsFrom($this->viewsPath(), self::VIEW_NAMESPACE);
    }

    protected function bootMigrations(): void
    {
        if (! $this->packageMigrationsShouldLoad()) {
            return;
        }

        if ($this->migrationFiles() === []) {
            return;
        }

        $this->loadMigrationsFrom($this->migrationsPath());
    }

    protected function packageMigrationsShouldLoad(): bool
    {
        return (bool) config(self::PACKAGE_MIGRATION_LOADING_CONFIG, false);
    }

    protected function bootPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->bootConfigPublishing();
        $this->bootAssetPublishing();
        $this->bootStubPublishing();
    }

    protected function bootConfigPublishing(): void
    {
        foreach ($this->configFiles() as $file) {
            $this->publishes([
                $file => config_path(basename($file)),
            ], self::CONFIG_PUBLISH_TAG);
        }
    }

    protected function bootAssetPublishing(): void
    {
        if (! $this->packageAssetsArePublishable()) {
            return;
        }

        $this->publishes([
            $this->publicPath() => public_path('vendor/'.self::PACKAGE_NAME),
        ], self::ASSETS_PUBLISH_TAG);
    }

    protected function bootStubPublishing(): void
    {
        if (! $this->packageStubsArePublishable()) {
            return;
        }

        $this->publishes([
            $this->stubsPath() => base_path('stubs/vendor/'.self::PACKAGE_NAME),
        ], self::STUBS_PUBLISH_TAG);
    }

    protected function packageAssetsArePublishable(): bool
    {
        return $this->directoryHasRealFiles($this->publicPath());
    }

    protected function packageStubsArePublishable(): bool
    {
        return $this->directoryHasRealFiles($this->stubsPath());
    }

    protected function packagePath(string $path = ''): string
    {
        $packagePath = dirname(__DIR__);

        if ($path === '') {
            return $packagePath;
        }

        return $packagePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }

    protected function configPath(): string
    {
        return $this->packagePath('config');
    }

    protected function routesPath(): string
    {
        return $this->packagePath('routes');
    }

    protected function viewsPath(): string
    {
        return $this->packagePath('resources/views');
    }

    protected function migrationsPath(): string
    {
        return $this->packagePath('database/migrations');
    }

    protected function publicPath(): string
    {
        return $this->packagePath('public');
    }

    protected function stubsPath(): string
    {
        return $this->packagePath('stubs');
    }

    /**
     * @return array<int, string>
     */
    protected function configFiles(): array
    {
        $files = [];

        foreach (self::PACKAGE_CONFIG_DEFAULTS as $file) {
            $path = $this->configPath().DIRECTORY_SEPARATOR.$file;

            if (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    protected function routeFiles(): array
    {
        return $this->phpFiles($this->routesPath());
    }

    /**
     * @return array<int, string>
     */
    protected function migrationFiles(): array
    {
        return $this->phpFiles($this->migrationsPath());
    }

    /**
     * @return array<int, string>
     */
    protected function phpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        foreach ($this->packageFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @param  array<int, string>  $files
     */
    protected function loadGuardedRouteFiles(bool $shouldLoad, array $files): void
    {
        if (! $shouldLoad) {
            return;
        }

        foreach ($files as $file) {
            $this->loadRoutesFrom($file);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function namedRouteFiles(string $fileName): array
    {
        return array_values(array_filter(
            $this->routeFiles(),
            static fn (string $file): bool => basename($file) === $fileName
        ));
    }

    protected function directoryHasRealFiles(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        foreach ($this->packageFiles($path) as $file) {
            if ($file->isFile()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Generator<int, SplFileInfo>
     */
    protected function packageFiles(string $path): \Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($this->isPlaceholderFile($file)) {
                continue;
            }

            yield $file;
        }
    }

    protected function isPlaceholderFile(SplFileInfo $file): bool
    {
        return in_array($file->getFilename(), ['.gitkeep', '.DS_Store', 'README.md'], true)
            || str_starts_with($file->getFilename(), '.');
    }
}
