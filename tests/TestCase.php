<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Tests\Support\HtmlStructureAssertions;

abstract class TestCase extends BaseTestCase
{
    use HtmlStructureAssertions;

    protected array $trackedCreatedPublicSitePaths = [];

    protected function tearDown(): void
    {
        $this->cleanupTrackedPublicSitePaths();

        parent::tearDown();
    }

    protected function putTrackedPublicSiteFile(string $relativePath, string $contents): string
    {
        $relativePath = ltrim($relativePath, '/');
        $path = public_path($relativePath);

        if (! str_starts_with($path, public_path('site').DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('Tracked public site file must stay under public/site.');
        }

        $this->trackCreatedPublicSitePath($path);

        $directory = dirname($path);

        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        File::put($path, $contents);

        return $path;
    }

    private function trackCreatedPublicSitePath(string $path): void
    {
        $current = $path;

        while (str_starts_with($current, public_path('site').DIRECTORY_SEPARATOR)) {
            if (! file_exists($current)) {
                $this->trackedCreatedPublicSitePaths[$current] = true;
            }

            $parent = dirname($current);

            if ($parent === $current || $parent === public_path('site')) {
                break;
            }

            $current = $parent;
        }
    }

    private function cleanupTrackedPublicSitePaths(): void
    {
        $paths = array_keys($this->trackedCreatedPublicSitePaths);
        usort($paths, fn (string $left, string $right) => strlen($right) <=> strlen($left));

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);

                continue;
            }

            if (is_dir($path) && $this->isEmptyDirectory($path)) {
                @rmdir($path);
            }
        }

        $this->trackedCreatedPublicSitePaths = [];
    }

    private function isEmptyDirectory(string $path): bool
    {
        $entries = @scandir($path);

        return is_array($entries) && count(array_diff($entries, ['.', '..'])) === 0;
    }
}
