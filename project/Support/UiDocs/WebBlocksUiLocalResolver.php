<?php

namespace Project\Support\UiDocs;

use RuntimeException;

class WebBlocksUiLocalResolver
{
    public const REQUIRED_HOSTNAME = SetupWebBlocksUiDocsSite::CANONICAL_DOMAIN;

    public const CONFIG_PATH = '.ddev/config.webblocksui.local-resolver.yaml';

    public const BASE_DDEV_CONFIG_PATH = '.ddev/config.yaml';

    public function status(): array
    {
        $configPath = base_path(self::CONFIG_PATH);
        $hosts = $this->configuredHostsFromPaths([
            base_path(self::BASE_DDEV_CONFIG_PATH),
            $configPath,
        ]);

        return [
            'config_path' => $configPath,
            'config_exists' => is_file($configPath),
            'required_hosts' => [SetupWebBlocksUiDocsSite::LOCAL_DDEV_DOMAIN],
            'configured_hosts' => $hosts,
            'is_configured' => in_array(self::REQUIRED_HOSTNAME, $hosts, true),
        ];
    }

    public function ensure(): array
    {
        return $this->ensureAtPath(base_path(self::CONFIG_PATH));
    }

    public function ensureAtPath(string $configPath): array
    {
        $configDir = dirname($configPath);

        if (! is_dir($configDir) && ! @mkdir($configDir, 0777, true) && ! is_dir($configDir)) {
            throw new RuntimeException('Unable to create DDEV config directory ['.$configDir.'].');
        }

        if (! is_writable($configDir)) {
            throw new RuntimeException('DDEV config directory is not writable ['.$configDir.'].');
        }

        $existingHosts = $this->configuredHosts($configPath);
        $hosts = array_values(array_unique(array_merge($existingHosts, [self::REQUIRED_HOSTNAME])));
        $contents = $this->renderConfig($hosts);
        $current = is_file($configPath) ? file_get_contents($configPath) : false;

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to generate DDEV resolver config.');
        }

        if ($current === $contents) {
            return [
                'changed' => false,
                'restart_required' => false,
                'config_path' => $configPath,
                'hosts' => $hosts,
            ];
        }

        if (@file_put_contents($configPath, $contents) === false) {
            throw new RuntimeException('Unable to write DDEV resolver config ['.$configPath.'].');
        }

        return [
            'changed' => true,
            'restart_required' => true,
            'config_path' => $configPath,
            'hosts' => $hosts,
        ];
    }

    public function configuredHostsFromPaths(array $paths): array
    {
        $hosts = [];

        foreach ($paths as $path) {
            $hosts = array_merge($hosts, $this->configuredHosts((string) $path));
        }

        return array_values(array_unique($hosts));
    }

    public function configuredHosts(string $configPath): array
    {
        if (! is_file($configPath)) {
            return [];
        }

        $contents = file_get_contents($configPath);

        if (! is_string($contents) || trim($contents) === '') {
            return [];
        }

        return $this->extractAdditionalHostnames($contents);
    }

    public function renderConfig(array $hosts): string
    {
        $hosts = array_values(array_unique(array_filter(array_map(
            static fn ($host) => trim((string) $host),
            $hosts,
        ))));

        sort($hosts);

        $lines = [
            '# Managed by ddev artisan project:webblocksui-local-resolver',
            '# Adds project-specific local preview host routing for WebBlocks UI docs.',
            'additional_hostnames:',
        ];

        foreach ($hosts as $host) {
            $lines[] = '  - '.$host;
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function extractAdditionalHostnames(string $contents): array
    {
        $hosts = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $inAdditionalHostnames = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^additional_hostnames\s*:/', $trimmed) === 1) {
                $inAdditionalHostnames = true;

                continue;
            }

            if (! $inAdditionalHostnames) {
                continue;
            }

            if (preg_match('/^-\s*(.+)$/', $trimmed, $matches) === 1) {
                $host = trim($matches[1], " \t\n\r\0\x0B\"'");

                if ($host !== '') {
                    $hosts[] = $host;
                }

                continue;
            }

            if (preg_match('/^[A-Za-z0-9_]+\s*:/', $trimmed) === 1) {
                break;
            }
        }

        return array_values(array_unique($hosts));
    }
}
