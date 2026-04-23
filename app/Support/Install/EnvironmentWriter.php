<?php

namespace App\Support\Install;

use RuntimeException;

class EnvironmentWriter
{
    public function path(): string
    {
        return (string) config('cms.install.environment_path', base_path('.env'));
    }

    public function examplePath(): string
    {
        return (string) config('cms.install.environment_example_path', base_path('.env.example'));
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function canWrite(): bool
    {
        $path = $this->path();

        if (is_file($path)) {
            return is_writable($path);
        }

        $directory = dirname($path);

        return is_dir($directory) && is_writable($directory);
    }

    public function ensureFileExists(): void
    {
        $path = $this->path();

        if (is_file($path)) {
            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('The environment file could not be created because its directory is not writable.');
        }

        $examplePath = $this->examplePath();
        $contents = is_file($examplePath)
            ? (string) file_get_contents($examplePath)
            : '';

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('The environment file could not be created.');
        }
    }

    public function write(array $values): void
    {
        $this->ensureFileExists();

        if (! $this->canWrite()) {
            throw new RuntimeException('The environment file is not writable.');
        }

        $path = $this->path();
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $replacement = $key.'='.$this->formatValue($value);
            $pattern = '/^\s*#?\s*'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $replacement, $contents, 1);

                continue;
            }

            $contents = rtrim($contents, "\r\n").PHP_EOL.$replacement.PHP_EOL;
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('The environment file could not be updated.');
        }
    }

    private function formatValue(mixed $value): string
    {
        $value = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => trim((string) $value),
        };

        if ($value === '') {
            return '';
        }

        if (! preg_match('/\s|#|"|\'|\$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
