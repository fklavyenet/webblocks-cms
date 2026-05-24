<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LaravelWelcomeRouteCleaner
{
    private ?string $lastBackupPath = null;

    public function clean(?string $routePath = null): LaravelWelcomeRouteCleanupResult
    {
        $this->lastBackupPath = null;
        $routePath = $routePath ?: base_path('routes/web.php');

        if (! is_file($routePath)) {
            return LaravelWelcomeRouteCleanupResult::missing();
        }

        $contents = (string) File::get($routePath);
        $cleaned = $this->removeDefaultWelcomeRoute($contents);

        if ($cleaned === $contents) {
            return $this->containsWelcomeViewReference($contents)
                ? LaravelWelcomeRouteCleanupResult::custom()
                : LaravelWelcomeRouteCleanupResult::unchanged();
        }

        $backupPath = $this->backupPath($routePath);
        File::copy($routePath, $backupPath);
        File::put($routePath, $this->normalizeBlankLines($cleaned));

        $this->lastBackupPath = $backupPath;

        return LaravelWelcomeRouteCleanupResult::removed($backupPath);
    }

    public function lastBackupPath(): ?string
    {
        return $this->lastBackupPath;
    }

    private function removeDefaultWelcomeRoute(string $contents): string
    {
        foreach ($this->defaultWelcomeRoutePatterns() as $pattern) {
            $cleaned = preg_replace($pattern, "\n", $contents, 1);

            if (is_string($cleaned) && $cleaned !== $contents) {
                return $cleaned;
            }
        }

        return $contents;
    }

    private function defaultWelcomeRoutePatterns(): array
    {
        return [
            '/\R*Route::get\(\s*[\'"]\/[\'"]\s*,\s*function\s*\(\s*\)\s*\{\s*\R\s*return\s+view\(\s*[\'"]welcome[\'"]\s*\)\s*;\s*\R\s*\}\s*\)\s*;\s*\R*/m',
        ];
    }

    private function containsWelcomeViewReference(string $contents): bool
    {
        return preg_match('/view\(\s*[\'"]welcome[\'"]\s*\)/', $contents) === 1;
    }

    private function backupPath(string $routePath): string
    {
        return $routePath.'.webblocks-cms.'.now()->format('YmdHis').'.bak';
    }

    private function normalizeBlankLines(string $contents): string
    {
        $contents = preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $contents));
        $contents = is_string($contents) ? $contents : '';

        return Str::finish(rtrim($contents), "\n");
    }
}
