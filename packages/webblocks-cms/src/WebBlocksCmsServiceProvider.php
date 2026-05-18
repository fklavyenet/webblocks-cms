<?php

namespace WebBlocks\Cms;

use FilesystemIterator;
use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebBlocks\Cms\Console\PackageStatusCommand;

class WebBlocksCmsServiceProvider extends ServiceProvider
{
    public const PACKAGE_NAME = 'webblocks-cms';

    public const VIEW_NAMESPACE = 'webblocks-cms';

    public const DIAGNOSTIC_ROUTE_FILE = 'diagnostics.php';

    public const DIAGNOSTIC_ROUTE_NAME = 'webblocks-cms.diagnostics.package-status';

    public const DIAGNOSTIC_ROUTE_PATH = '/_webblocks-cms/diagnostics/package-status';

    public const DIAGNOSTIC_ROUTE_LOADING_CONFIG = 'webblocks-cms.diagnostics.load_routes';

    public const PACKAGE_MIGRATION_LOADING_CONFIG = 'webblocks-cms.boundaries.load_migrations';

    public const PACKAGE_CONFIG_DEFAULTS = [
        'cms.php',
        'contact.php',
        'demo_media.php',
        'webblocks-updates.php',
    ];

    public const CONFIG_PUBLISH_TAG = 'webblocks-cms-config';

    public const ASSETS_PUBLISH_TAG = 'webblocks-cms-assets';

    public const STUBS_PUBLISH_TAG = 'webblocks-cms-stubs';

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
        if (! $this->diagnosticRoutesShouldLoad()) {
            return;
        }

        foreach ($this->diagnosticRouteFiles() as $file) {
            $this->loadRoutesFrom($file);
        }
    }

    protected function diagnosticRoutesShouldLoad(): bool
    {
        return (bool) config(self::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false);
    }

    /**
     * @return array<int, string>
     */
    protected function diagnosticRouteFiles(): array
    {
        return array_values(array_filter(
            $this->routeFiles(),
            static fn (string $file): bool => basename($file) === self::DIAGNOSTIC_ROUTE_FILE
        ));
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
