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
        if ($this->routeFiles() === []) {
            return;
        }

        foreach ($this->routeFiles() as $file) {
            $this->loadRoutesFrom($file);
        }
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
        if ($this->migrationFiles() === []) {
            return;
        }

        $this->loadMigrationsFrom($this->migrationsPath());
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
        if (! $this->directoryHasRealFiles($this->publicPath())) {
            return;
        }

        $this->publishes([
            $this->publicPath() => public_path('vendor/'.self::PACKAGE_NAME),
        ], self::ASSETS_PUBLISH_TAG);
    }

    protected function bootStubPublishing(): void
    {
        if (! $this->directoryHasRealFiles($this->stubsPath())) {
            return;
        }

        $this->publishes([
            $this->stubsPath() => base_path('stubs/vendor/'.self::PACKAGE_NAME),
        ], self::STUBS_PUBLISH_TAG);
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
