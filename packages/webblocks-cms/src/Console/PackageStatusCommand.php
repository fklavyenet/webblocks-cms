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
        $diagnosticView = 'diagnostics.package-status';
        $namespacedDiagnosticView = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::'.$diagnosticView;
        $configFiles = $this->phpFiles($packageRoot.'/config');
        $routeFiles = $this->resourceFiles($packageRoot.'/routes');
        $viewFiles = $this->resourceFiles($packageRoot.'/resources/views');
        $migrationFiles = $this->resourceFiles($packageRoot.'/database/migrations');
        $publicFiles = $this->resourceFiles($packageRoot.'/public');
        $stubFiles = $this->resourceFiles($packageRoot.'/stubs');
        $shouldCheckView = (bool) $this->option('view-check');

        $this->line('Package: fklavyenet/webblocks-cms');
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
        $this->line('Package resources/views path present: '.$this->yesNo(is_dir($packageRoot.'/resources/views')));
        $this->line('Package view files status: '.$this->resourceStatus($viewFiles));
        $this->line('Package database/migrations path present: '.$this->yesNo(is_dir($packageRoot.'/database/migrations')));
        $this->line('Package migration files status: '.$this->resourceStatus($migrationFiles));
        $this->line('Package public path present: '.$this->yesNo(is_dir($packageRoot.'/public')));
        $this->line('Package public assets status: '.$this->resourceStatus($publicFiles));
        $this->line('Package stubs path present: '.$this->yesNo(is_dir($packageRoot.'/stubs')));
        $this->line('Package stubs status: '.$this->resourceStatus($stubFiles));
        $this->line('Package service provider loaded: '.$this->yesNo($this->laravel->providerIsLoaded(WebBlocksCmsServiceProvider::class)));
        $this->line('Package view namespace registered: '.$this->yesNo($this->viewNamespaceIsRegistered()).' ('.WebBlocksCmsServiceProvider::VIEW_NAMESPACE.')');
        $this->line('Package diagnostic view exists: '.$this->yesNo(view()->exists($namespacedDiagnosticView)).' ('.$namespacedDiagnosticView.')');
        $this->line('Package diagnostic view render check: '.$this->diagnosticViewRenderStatus(
            $shouldCheckView,
            $namespacedDiagnosticView,
            $packageRoot
        ));
        $this->newLine();

        $this->line('Transition note: root runtime remains authoritative unless a resource has been intentionally moved and wired.');
        $this->line('This command performs no publishing, migrations, cache clearing, file writes, database writes, or install-state changes.');

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

    protected function viewNamespaceIsRegistered(): bool
    {
        return array_key_exists(
            WebBlocksCmsServiceProvider::VIEW_NAMESPACE,
            view()->getFinder()->getHints()
        );
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
