<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\File;

class InstalledPluginRepository
{
  public function rootPath(): string
  {
    $configured = config('webblocks-plugins.install.root');

    return is_string($configured) && trim($configured) !== ''
      ? rtrim($configured, DIRECTORY_SEPARATOR)
      : storage_path('app/webblocks/plugins');
  }

  /**
   * @return array<int, array{manifest: array<string, mixed>, path: string, enabled: bool}>
   */
  public function installed(): array
  {
    if (! is_dir($this->rootPath())) {
      return [];
    }

    $plugins = [];

    foreach (File::directories($this->rootPath()) as $handlePath) {
      foreach (File::directories($handlePath) as $versionPath) {
        $manifestPath = $versionPath.DIRECTORY_SEPARATOR.'webblocks-plugin.json';

        if (! is_file($manifestPath)) {
          $manifestPath = $versionPath.DIRECTORY_SEPARATOR.'manifest.json';
        }

        if (! is_file($manifestPath)) {
          continue;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
          continue;
        }

        $plugins[] = [
          'manifest' => $manifest,
          'path' => $versionPath,
          'enabled' => $this->enabledVersion((string) ($manifest['handle'] ?? '')) === (string) ($manifest['version'] ?? ''),
        ];
      }
    }

    usort($plugins, fn (array $left, array $right): int => strcmp((string) $left['manifest']['label'], (string) $right['manifest']['label']));

    return $plugins;
  }

  public function hasHandle(string $handle): bool
  {
    foreach ($this->installed() as $plugin) {
      if (($plugin['manifest']['handle'] ?? null) === $handle) {
        return true;
      }
    }

    return false;
  }

  public function enabledVersion(string $handle): ?string
  {
    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'enabled.json';

    if (! is_file($path)) {
      return null;
    }

    $state = json_decode((string) file_get_contents($path), true);
    $version = is_array($state) ? ($state['version'] ?? null) : null;

    return is_string($version) && $version !== '' ? $version : null;
  }

  public function enable(string $handle, string $version): void
  {
    $directory = $this->rootPath().DIRECTORY_SEPARATOR.$handle;
    File::ensureDirectoryExists($directory);

    file_put_contents($directory.DIRECTORY_SEPARATOR.'enabled.json', json_encode([
      'version' => $version,
      'enabled_at' => now()->toIso8601String(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }
}
