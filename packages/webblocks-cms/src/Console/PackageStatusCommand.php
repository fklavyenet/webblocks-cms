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

    protected $description = 'Show read-only WebBlocks CMS package transition status';

    public function handle(): int
    {
        $packageRoot = dirname(__DIR__, 2);
        $configFiles = $this->phpFiles($packageRoot.'/config');

        $this->line('Package: fklavyenet/webblocks-cms');
        $this->line('Mode: read-only diagnostic only');
        $this->newLine();

        $this->line('Package resource boundary status');
        $this->line('Package base path: '.$packageRoot);
        $this->line('Package src path present: '.$this->yesNo(is_dir($packageRoot.'/src')));
        $this->line('Package config path present: '.$this->yesNo(is_dir($packageRoot.'/config')));
        $this->line('Package config files present: '.($configFiles === [] ? 'none' : implode(', ', array_map('basename', $configFiles))));
        $this->line('Package routes path present: '.$this->yesNo(is_dir($packageRoot.'/routes')));
        $this->line('Package resources/views path present: '.$this->yesNo(is_dir($packageRoot.'/resources/views')));
        $this->line('Package database/migrations path present: '.$this->yesNo(is_dir($packageRoot.'/database/migrations')));
        $this->line('Package public path present: '.$this->yesNo(is_dir($packageRoot.'/public')));
        $this->line('Package stubs path present: '.$this->yesNo(is_dir($packageRoot.'/stubs')));
        $this->line('Package service provider loaded: '.$this->yesNo($this->laravel->providerIsLoaded(WebBlocksCmsServiceProvider::class)));
        $this->line('Root override config present: '.$this->rootOverrideSummary());
        $this->line('View namespace: '.WebBlocksCmsServiceProvider::VIEW_NAMESPACE);
        $this->newLine();

        $this->line('Transition note: root runtime remains authoritative unless a resource has been intentionally moved and wired.');
        $this->line('This command does not publish files, run migrations, clear cache, or mutate install state.');

        return self::SUCCESS;
    }

    protected function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
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
