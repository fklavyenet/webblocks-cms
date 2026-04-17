<?php

namespace App\Support\System;

use Illuminate\Filesystem\Filesystem;

class InstalledVersionStore
{
    public function __construct(private readonly Filesystem $files) {}

    public function update(string $version): void
    {
        $envPath = base_path('.env');

        if (! $this->files->exists($envPath)) {
            throw new \RuntimeException('The .env file is missing.');
        }

        $contents = $this->files->get($envPath);
        $line = 'APP_VERSION='.$version;

        if (preg_match('/^APP_VERSION=.*/m', $contents) === 1) {
            $updated = preg_replace('/^APP_VERSION=.*/m', $line, $contents, 1);
        } else {
            $separator = str_ends_with($contents, PHP_EOL) ? '' : PHP_EOL;
            $updated = $contents.$separator.$line.PHP_EOL;
        }

        if (! is_string($updated)) {
            throw new \RuntimeException('Failed to update APP_VERSION in .env.');
        }

        $this->files->put($envPath, $updated);
    }
}
