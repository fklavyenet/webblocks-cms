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
    protected $signature = 'webblocks:package-status';

    protected $description = 'Show read-only WebBlocks CMS package bootstrap status';

    public function handle(): int
    {
        $packageRoot = dirname(__DIR__, 2);
        $configFiles = $this->phpFiles($packageRoot.'/config');
        $routeFiles = $this->phpFiles($packageRoot.'/routes');
        $migrationFiles = $this->phpFiles($packageRoot.'/database/migrations');
        $hasViews = $this->directoryHasRealFiles($packageRoot.'/resources/views');

        $this->line('Package: fklavyenet/webblocks-cms');
        $this->line('Package root: '.$packageRoot);
        $this->line('Config files present: '.($configFiles !== [] ? 'yes' : 'no'));
        $this->line('Config file count: '.count($configFiles));
        $this->line('Config files: '.($configFiles === [] ? 'none' : implode(', ', array_map('basename', $configFiles))));
        $this->line('Routes contain real files: '.($routeFiles !== [] ? 'yes' : 'no'));
        $this->line('Views contain real files: '.($hasViews ? 'yes' : 'no'));
        $this->line('Migrations contain real files: '.($migrationFiles !== [] ? 'yes' : 'no'));
        $this->line('Root override config present: '.$this->rootOverrideSummary());
        $this->line('View namespace: '.WebBlocksCmsServiceProvider::VIEW_NAMESPACE);

        return self::SUCCESS;
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

    protected function rootOverrideSummary(): string
    {
        $expectedFiles = [
            'cms.php',
            'contact.php',
            'demo_media.php',
            'webblocks-updates.php',
        ];

        $present = [];

        foreach ($expectedFiles as $file) {
            if (is_file(config_path($file))) {
                $present[] = $file;
            }
        }

        if ($present === []) {
            return 'none';
        }

        return sprintf('%d/%d (%s)', count($present), count($expectedFiles), implode(', ', $present));
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
        return in_array($file->getFilename(), ['.gitkeep', '.DS_Store'], true)
            || str_starts_with($file->getFilename(), '.');
    }
}
