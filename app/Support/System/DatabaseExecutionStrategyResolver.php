<?php

namespace App\Support\System;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseExecutionStrategyResolver
{
    public function resolveMysqlStrategy(): string
    {
        $configuredStrategy = Str::lower((string) config('cms.backup.execution', 'auto'));

        return match ($configuredStrategy) {
            'direct' => 'direct',
            'ddev' => 'ddev',
            'auto', '' => $this->shouldUseDdevStrategy() ? 'ddev' : 'direct',
            default => throw new RuntimeException('Invalid cms.backup.execution value ['.$configuredStrategy.']. Supported values: auto, direct, ddev.'),
        };
    }

    public function isDdevProject(): bool
    {
        return File::isDirectory(base_path('.ddev'));
    }

    public function isRunningInsideDdev(): bool
    {
        return ! empty($_SERVER['IS_DDEV_PROJECT'] ?? $_ENV['IS_DDEV_PROJECT'] ?? getenv('IS_DDEV_PROJECT'));
    }

    public function isDdevUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && str_ends_with(Str::lower($host), '.ddev.site');
    }

    private function shouldUseDdevStrategy(): bool
    {
        if (Str::lower((string) config('app.env')) !== 'local') {
            return false;
        }

        if ($this->isRunningInsideDdev()) {
            return false;
        }

        if (! $this->isDdevProject()) {
            return false;
        }

        return $this->isDdevUrl((string) config('app.url')) || $this->isDdevProject();
    }
}
