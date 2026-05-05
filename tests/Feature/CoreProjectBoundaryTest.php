<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class CoreProjectBoundaryTest extends TestCase
{
    #[Test]
    public function core_paths_do_not_contain_website_specific_sync_or_import_references(): void
    {
        foreach ($this->corePaths() as $path) {
            foreach ($this->filesForBoundaryScan($path) as $file) {
                $contents = $this->contentsForBoundaryScan($file);

                foreach ($this->bannedTokens() as $token) {
                    $this->assertStringNotContainsString(
                        $token,
                        $contents,
                        sprintf('Found banned token [%s] in [%s].', $token, $file)
                    );
                }
            }
        }
    }

    private function corePaths(): array
    {
        return [
            base_path('app'),
            base_path('bootstrap'),
            base_path('config'),
            base_path('database'),
            base_path('docs'),
            base_path('public/assets/webblocks-cms'),
            base_path('resources'),
            base_path('routes'),
            base_path('tests'),
            base_path('README.md'),
            base_path('CHANGELOG.md'),
            base_path('.github/workflows'),
        ];
    }

    private function bannedTokens(): array
    {
        return [
            implode(':', ['webblocks', implode('-', ['sync', 'ui', 'docs'])]),
            implode('', ['Sync', 'Ui', 'Docs']),
            implode('-', ['sync', 'ui', 'docs', 'getting', 'started']),
            'webblocksui.com/docs/'.implode('-', ['getting', 'started']),
            implode('-', ['getting', 'started']).'.html',
        ];
    }

    private function filesForBoundaryScan(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        if (is_file($path)) {
            return [$path];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($this->shouldSkipBoundaryFile($file->getPathname())) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function contentsForBoundaryScan(string $path): string
    {
        if (basename($path) !== 'CHANGELOG.md') {
            return (string) file_get_contents($path);
        }

        $contents = (string) file_get_contents($path);

        if (! preg_match('/^## \[Unreleased\]\R(.*?)(?=^##\s)/ms', $contents, $matches)) {
            return $contents;
        }

        return $matches[1];
    }

    private function shouldSkipBoundaryFile(string $path): bool
    {
        if (str_contains($path, DIRECTORY_SEPARATOR.'project'.DIRECTORY_SEPARATOR)) {
            return true;
        }

        if (str_contains($path, DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)) {
            return true;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'zip', 'gz', 'mp4', 'mp3', 'pdf'], true);
    }
}
