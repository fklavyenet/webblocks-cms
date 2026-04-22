<?php

namespace App\Support\Sites\ExportImport;

use RuntimeException;

class SiteTransferPathGuard
{
    public function assertSafeRelativePath(string $path, string $label = 'Path'): void
    {
        if (! $this->isSafeRelativePath($path)) {
            throw new RuntimeException($label.' is invalid.');
        }
    }

    public function isSafeRelativePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return false;
        }

        $trimmed = rtrim($normalized, '/');
        $segments = explode('/', $trimmed === '' ? $normalized : $trimmed);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
