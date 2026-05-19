<?php

namespace WebBlocks\Cms\Console;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageStatusCommand extends Command
{
    protected $signature = 'webblocks:package-status
    {--view-check : Render the package diagnostic view through the package namespace}';

    protected $description = 'Show read-only WebBlocks CMS package transition status';

    public function handle(): int
    {
        $packageRoot = dirname(__DIR__, 2);
        $rootComposer = $this->composerJson(base_path('composer.json'));
        $packageComposer = $this->composerJson($packageRoot.'/composer.json');
        $diagnosticView = 'diagnostics.package-status';
        $namespacedDiagnosticView = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$diagnosticView;
        $diagnosticRouteFile = WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_FILE;
        $diagnosticRouteName = WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_NAME;
        $diagnosticRoutePath = WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_PATH;
        $adminRouteFile = WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_FILE;
        $adminRouteName = WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_NAME;
        $adminRoutePath = WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_PATH;
        $publicRouteFile = WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_FILE;
        $publicRouteName = WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_NAME;
        $publicRoutePath = WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_PATH;
        $packageMigrationLoadingConfig = WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG;
        $configFiles = $this->phpFiles($packageRoot.'/config');
        $routeFiles = $this->resourceFiles($packageRoot.'/routes');
        $viewFiles = $this->resourceFiles($packageRoot.'/resources/views');
        $migrationFiles = $this->resourceFiles($packageRoot.'/database/migrations');
        $seederFiles = $this->resourceFiles($packageRoot.'/database/seeders');
        $publicFiles = $this->resourceFiles($packageRoot.'/public');
        $stubFiles = $this->resourceFiles($packageRoot.'/stubs');
        $shouldCheckView = (bool) $this->option('view-check');

        $providerDiscoveryPresent = $this->composerProviderDiscoveryPresent($packageComposer, WebBlocksCmsServiceProvider::class);
        $packageComposerNamePresent = $this->composerPackageNamePresent(
            $packageComposer,
            WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME
        );
        $rootDevPathDependencyPresent = $this->rootComposerDependencyPresent(
            $rootComposer,
            WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME
        );

        $this->line('Package: '.WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME);
        $this->line('Mode: read-only diagnostic only');
        $this->newLine();

        $this->line('Package resource boundary status');
        $this->line('Package base path: '.$packageRoot);
        $this->line('Package src path present: '.$this->yesNo(is_dir($packageRoot.'/src')));
        $this->line('Package config path present: '.$this->yesNo(is_dir($packageRoot.'/config')));
        $this->line('Package config files present: '.($configFiles === [] ? 'none' : implode(', ', array_map('basename', $configFiles))));
        $this->line('Expected package config defaults:');

        foreach (WebBlocksCmsServiceProvider::PACKAGE_CONFIG_DEFAULTS as $file) {
            $this->line(sprintf(
                '- %s: package default=%s, root override=%s',
                $file,
                $this->yesNo(is_file($packageRoot.'/config/'.$file)),
                $this->yesNo(is_file(config_path($file)))
            ));
        }

        $this->line('Package routes path present: '.$this->yesNo(is_dir($packageRoot.'/routes')));
        $this->line('Package route files status: '.$this->resourceStatus($routeFiles));
        $this->line('Package route Composer readiness: '.$this->yesNo($providerDiscoveryPresent && $this->expectedFilesPresent(
            $packageRoot.'/routes',
            WebBlocksCmsServiceProvider::PACKAGE_ROUTE_FILES
        )).' (provider discovery plus guarded route files)');
        $this->line('Expected package diagnostic route file exists: '.$this->yesNo(is_file($packageRoot.'/routes/'.$diagnosticRouteFile)).' ('.$diagnosticRouteFile.')');
        $this->line('Package diagnostic route loading guard enabled: '.$this->yesNo($this->diagnosticRouteLoadingEnabled()).' ('.WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG.')');
        $this->line('Package diagnostic route loaded in active runtime: '.$this->yesNo($this->routeIsRegistered($diagnosticRouteName, $diagnosticRoutePath)).' ('.$diagnosticRouteName.' at '.$diagnosticRoutePath.')');
        $this->line('Expected package admin route file exists: '.$this->yesNo(is_file($packageRoot.'/routes/'.$adminRouteFile)).' ('.$adminRouteFile.')');
        $this->line('Package admin slice loading guard enabled: '.$this->yesNo($this->packageAdminRouteLoadingEnabled()).' ('.WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG.')');
        $this->line('Package admin slice loaded in active runtime: '.$this->yesNo($this->routeIsRegistered($adminRouteName, $adminRoutePath)).' ('.$adminRouteName.' at '.$adminRoutePath.')');
        $this->line('Expected package public route file exists: '.$this->yesNo(is_file($packageRoot.'/routes/'.$publicRouteFile)).' ('.$publicRouteFile.')');
        $this->line('Package public slice loading guard enabled: '.$this->yesNo($this->packagePublicRouteLoadingEnabled()).' ('.WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG.')');
        $this->line('Package public slice loaded in active runtime: '.$this->yesNo($this->routeIsRegistered($publicRouteName, $publicRoutePath)).' ('.$publicRouteName.' at '.$publicRoutePath.')');
        $this->line('Active runtime route loading remains root-authoritative: yes');
        $this->line('Root route compatibility state: root routes remain authoritative outside reserved package paths.');
        $this->line('Package resources/views path present: '.$this->yesNo(is_dir($packageRoot.'/resources/views')));
        $this->line('Package view files status: '.$this->resourceStatus($viewFiles));
        $this->line('Package view Composer readiness: '.$this->yesNo($providerDiscoveryPresent && $this->expectedFilesPresent(
            $packageRoot.'/resources/views',
            WebBlocksCmsServiceProvider::PACKAGE_VIEW_FILES
        )).' (provider discovery plus package view namespace)');
        $this->line('Package admin slice view exists: '.$this->yesNo(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status')).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.runtime-status)');
        $this->line('Package public slice view exists: '.$this->yesNo(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status')).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::public.runtime-status)');
        $this->line('Root view compatibility state: root views remain authoritative outside the package namespace.');
        $this->line('Package database/migrations path present: '.$this->yesNo(is_dir($packageRoot.'/database/migrations')));
        $this->line('Package migration boundary status: '.$this->resourceBoundaryStatus($migrationFiles));
        $this->line('Package migration files status: '.$this->resourceStatus($migrationFiles));
        $this->line('Package migration loading guard enabled: '.$this->yesNo($this->packageMigrationLoadingEnabled()).' ('.$packageMigrationLoadingConfig.')');
        $this->line('Package migrations loaded in active runtime: no');
        $this->line('Legacy root migration compatibility state: yes (root database/migrations remains authoritative).');
        $this->line('Future package migration Composer readiness: reserved boundary only (no schema-changing package migrations are active yet).');
        $this->line('Package database/seeders path present: '.$this->yesNo(is_dir($packageRoot.'/database/seeders')));
        $this->line('Package seeder boundary status: '.$this->resourceBoundaryStatus($seederFiles));
        $this->line('Package seeder files status: '.$this->resourceStatus($seederFiles));
        $this->line('Package catalog seeders present: '.$this->expectedFilesStatus(
            $packageRoot.'/database/seeders',
            WebBlocksCmsServiceProvider::PACKAGE_SEEDER_FILES
        ));
        $this->line('Root catalog seeder compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
            base_path('database/seeders'),
            WebBlocksCmsServiceProvider::PACKAGE_SEEDER_FILES
        ));
        $this->line('Package public path present: '.$this->yesNo(is_dir($packageRoot.'/public')));
        $this->line('Package public asset boundary status: '.$this->resourceBoundaryStatus($publicFiles));
        $this->line('Package public assets status: '.$this->resourceStatus($publicFiles));
        $this->line('Package public asset publish readiness: no (tag '.WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TAG.' remains inert until real package assets exist)');
        $this->line('Legacy root public asset compatibility state: yes (root public/cms and install-owned public/site remain authoritative).');
        $this->line('Future package public asset Composer readiness: reserved boundary only (current WebBlocks UI CDN pinning and root asset flow stay unchanged).');
        $this->line('Package stubs path present: '.$this->yesNo(is_dir($packageRoot.'/stubs')));
        $this->line('Package stub boundary status: '.$this->resourceBoundaryStatus($stubFiles));
        $this->line('Package stubs status: '.$this->resourceStatus($stubFiles));
        $this->line('Package stub publish readiness: no (tag '.WebBlocksCmsServiceProvider::STUBS_PUBLISH_TAG.' remains inert until real package stubs exist)');
        $this->line('Starter stub readiness: reserved only (no publishable starter stubs are intentionally shipped yet).');
        $this->line('Package service provider loaded: '.$this->yesNo($this->laravel->providerIsLoaded(WebBlocksCmsServiceProvider::class)));
        $this->line('Package view namespace registered: '.$this->yesNo($this->viewNamespaceIsRegistered()).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.')');
        $this->line('Package diagnostic view exists: '.$this->yesNo(view()->exists($namespacedDiagnosticView)).' ('.$namespacedDiagnosticView.')');
        $this->line('Package low-risk runtime support moves present: '.$this->expectedFilesStatus(
            $packageRoot.'/src/Support',
            WebBlocksCmsServiceProvider::LOW_RISK_RUNTIME_SUPPORT_FILES
        ));
        $this->line('Root runtime support compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
            base_path('app/Support'),
            WebBlocksCmsServiceProvider::LOW_RISK_RUNTIME_SUPPORT_FILES
        ));
        $this->line('Package icon runtime moves present: '.$this->expectedFilesStatus(
            $packageRoot.'/src',
            WebBlocksCmsServiceProvider::ICON_RUNTIME_FILES
        ));
        $this->line('Root icon runtime compatibility wrappers present: '.$this->rootCompatibilityFilesStatus(
            base_path('app'),
            WebBlocksCmsServiceProvider::ICON_RUNTIME_FILES
        ));
        $this->line('Package diagnostic view render check: '.$this->diagnosticViewRenderStatus(
            $shouldCheckView,
            $namespacedDiagnosticView,
            $packageRoot
        ));
        $this->line('Package Composer package name present: '.$this->yesNo($packageComposerNamePresent).' ('.WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME.')');
        $this->line('Package Composer provider discovery present: '.$this->yesNo($providerDiscoveryPresent).' ('.WebBlocksCmsServiceProvider::class.')');
        $this->line('Package Composer seeder autoload present: '.$this->yesNo($this->composerSeederAutoloadPresent($packageComposer)).' (WebBlocks\\Cms\\Database\\Seeders\\)');
        $this->line('Root Composer development path dependency present: '.$this->yesNo($rootDevPathDependencyPresent).' ('.WebBlocksCmsServiceProvider::PACKAGE_COMPOSER_NAME.')');
        $this->line('Root Composer path repository present: '.$this->yesNo($this->pathRepositoryPresent($rootComposer, 'packages/webblocks-cms')).' (packages/webblocks-cms)');
        $this->line('Target Composer install flow: '.WebBlocksCmsServiceProvider::TARGET_INSTALL_COMMAND.' (future starter or package-consumer target only; current root install flow remains authoritative).');
        $this->line('Target Composer update flow: '.WebBlocksCmsServiceProvider::TARGET_UPDATE_COMMAND.' followed by migrations, catalog sync, block-types:sync-core, cache clear, asset publish or sync when needed, package diagnostics, and installed-version sync when release state is real.');
        $this->line('Starter foundation readiness: partial (package metadata, provider discovery, path-repository development wiring, and documented target install or update flow are present; '.WebBlocksCmsServiceProvider::STARTER_PACKAGE_NAME.' is intentionally not created yet).');
        $this->line('Composer-managed update target note: future Composer-managed package updates remain the target boundary, while current root Composer and runtime update flow stay authoritative.');
        $this->newLine();

        $this->line('Transition note: root runtime remains authoritative unless a resource has been intentionally moved and wired.');
        $this->line('This command performs no publishing, migrations, cache clearing, file writes, database writes, install-state changes, or update-state changes.');

        return self::SUCCESS;
    }

    protected function diagnosticViewRenderStatus(bool $shouldCheckView, string $viewName, string $packageRoot): string
    {
        if (! $shouldCheckView) {
            return 'not run (use --view-check)';
        }

        if (! view()->exists($viewName)) {
            return 'failed (diagnostic view missing)';
        }

        try {
            view($viewName, [
                'viewNamespace' => WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
                'packageBasePath' => $packageRoot,
            ])->render();
        } catch (\Throwable $exception) {
            return 'failed ('.$exception::class.': '.$exception->getMessage().')';
        }

        return 'success';
    }

    protected function diagnosticRouteLoadingEnabled(): bool
    {
        return (bool) config(WebBlocksCmsServiceProvider::DIAGNOSTIC_ROUTE_LOADING_CONFIG, false);
    }

    protected function packageMigrationLoadingEnabled(): bool
    {
        return (bool) config(WebBlocksCmsServiceProvider::PACKAGE_MIGRATION_LOADING_CONFIG, false);
    }

    protected function packageAdminRouteLoadingEnabled(): bool
    {
        return (bool) config(WebBlocksCmsServiceProvider::PACKAGE_ADMIN_ROUTE_LOADING_CONFIG, false);
    }

    protected function packagePublicRouteLoadingEnabled(): bool
    {
        return (bool) config(WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ROUTE_LOADING_CONFIG, false);
    }

    protected function routeIsRegistered(string $name, string $path): bool
    {
        $route = app('router')->getRoutes()->getByName($name);

        if ($route === null) {
            return false;
        }

        return '/'.$route->uri() === $path;
    }

    protected function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    protected function resourceStatus(array $files): string
    {
        if ($files === []) {
            return 'reserved only';
        }

        return 'package files present ('.implode(', ', $files).')';
    }

    protected function resourceBoundaryStatus(array $files): string
    {
        if ($files === []) {
            return 'reserved boundary only';
        }

        return 'pilot files present';
    }

    protected function viewNamespaceIsRegistered(): bool
    {
        return array_key_exists(
            WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
            view()->getFinder()->getHints()
        );
    }

    /**
     * @param  array<int, string>  $expectedFiles
     */
    protected function expectedFilesStatus(string $basePath, array $expectedFiles): string
    {
        $missingFiles = $this->missingExpectedFiles($basePath, $expectedFiles);

        if ($missingFiles !== []) {
            return 'no (missing '.implode(', ', $missingFiles).')';
        }

        return 'yes ('.implode(', ', array_map(
            fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
            $expectedFiles
        )).')';
    }

    /**
     * @param  array<int, string>  $expectedFiles
     */
    protected function rootCompatibilityFilesStatus(string $basePath, array $expectedFiles): string
    {
        $missingFiles = $this->missingExpectedFiles($basePath, $expectedFiles);

        if ($missingFiles !== []) {
            return 'no (missing '.implode(', ', $missingFiles).')';
        }

        return 'yes';
    }

    protected function composerSeederAutoloadPresent(array $composer): bool
    {
        return ($composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null) === 'database/seeders/';
    }

    protected function composerPackageNamePresent(array $composer, string $packageName): bool
    {
        return ($composer['name'] ?? null) === $packageName;
    }

    protected function composerProviderDiscoveryPresent(array $composer, string $providerClass): bool
    {
        return in_array($providerClass, $composer['extra']['laravel']['providers'] ?? [], true);
    }

    protected function rootComposerDependencyPresent(array $composer, string $packageName): bool
    {
        return array_key_exists($packageName, $composer['require'] ?? [])
            || array_key_exists($packageName, $composer['require-dev'] ?? []);
    }

    /**
     * @param  array<int, string>  $expectedFiles
     * @return array<int, string>
     */
    protected function missingExpectedFiles(string $basePath, array $expectedFiles): array
    {
        return array_values(array_filter(
            $expectedFiles,
            fn (string $file): bool => ! is_file($basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file))
        ));
    }

    /**
     * @param  array<int, string>  $expectedFiles
     */
    protected function expectedFilesPresent(string $basePath, array $expectedFiles): bool
    {
        return $this->missingExpectedFiles($basePath, $expectedFiles) === [];
    }

    protected function pathRepositoryPresent(array $composer, string $path): bool
    {
        foreach (($composer['repositories'] ?? []) as $repository) {
            if (! is_array($repository)) {
                continue;
            }

            if (($repository['type'] ?? null) !== 'path') {
                continue;
            }

            if (($repository['url'] ?? null) === $path) {
                return true;
            }
        }

        return false;
    }

    protected function composerJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
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
     * @return array<int, string>
     */
    protected function resourceFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        foreach ($this->packageFiles($path) as $file) {
            $files[] = ltrim(str_replace($path, '', $file->getPathname()), DIRECTORY_SEPARATOR);
        }

        sort($files);

        return $files;
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
