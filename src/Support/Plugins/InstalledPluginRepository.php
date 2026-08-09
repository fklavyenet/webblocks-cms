<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\File;
use RuntimeException;

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
    $this->assertValidCoordinates($handle, $version);

    $directory = $this->rootPath().DIRECTORY_SEPARATOR.$handle;
    File::ensureDirectoryExists($directory);

    $path = $directory.DIRECTORY_SEPARATOR.'enabled.json';
    $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    $state = is_array($state) ? $state : [];

    file_put_contents($path, json_encode(array_merge($state, [
      'version' => $version,
      'enabled_at' => $state['enabled_at'] ?? now()->toIso8601String(),
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * @param  array<string, mixed>  $result
   */
  public function recordSetupResult(string $handle, string $version, array $result): void
  {
    $this->assertValidCoordinates($handle, $version);

    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'enabled.json';
    $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    $state = is_array($state) ? $state : [];

    file_put_contents($path, json_encode(array_merge($state, [
      'version' => $version,
      'setup' => array_merge($result, [
        'ran_at' => now()->toIso8601String(),
      ]),
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  public function disable(string $handle): void
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      throw PluginException::invalidHandle($handle);
    }

    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'enabled.json';

    if (is_file($path)) {
      File::delete($path);
    }
  }

  public function uninstall(string $handle, string $version): void
  {
    $this->assertValidCoordinates($handle, $version);

    $root = $this->canonicalRootPath();
    $pluginPath = $this->rootPath().DIRECTORY_SEPARATOR.$handle;
    $versionPath = $pluginPath.DIRECTORY_SEPARATOR.$version;

    $this->assertPathInsideRoot($versionPath, $root);
    $this->disable($handle);

    if (file_exists($versionPath)) {
      if (! is_dir($versionPath) || is_link($versionPath)) {
        throw new RuntimeException('Plugin install path is not a removable plugin directory.');
      }

      File::deleteDirectory($versionPath);
    }

    if (is_dir($pluginPath) && count(File::allFiles($pluginPath)) === 0 && count(File::directories($pluginPath)) === 0) {
      $this->assertPathInsideRoot($pluginPath, $root);
      File::deleteDirectory($pluginPath);
    }

    /*
     * The published copy lives in the document root rather than under the plugin
     * root, so removing the package does not remove it. Left behind, the site would
     * keep serving the scripts of a plugin that is no longer installed, from a path
     * whose name still claims it is.
     */
    app(PluginAssetPublisher::class)->unpublish($handle);
  }

  public function replaceVersion(string $handle, string $oldVersion, string $newVersion): void
  {
    $this->assertValidCoordinates($handle, $oldVersion);
    $this->assertValidCoordinates($handle, $newVersion);

    $root = $this->canonicalRootPath();
    $pluginPath = $this->rootPath().DIRECTORY_SEPARATOR.$handle;
    $oldVersionPath = $pluginPath.DIRECTORY_SEPARATOR.$oldVersion;
    $newVersionPath = $pluginPath.DIRECTORY_SEPARATOR.$newVersion;
    $enabledVersion = $this->enabledVersion($handle);

    $this->assertPathInsideRoot($newVersionPath, $root);
    $this->assertPathInsideRoot($oldVersionPath, $root);

    if ($enabledVersion === $oldVersion) {
      $this->enable($handle, $newVersion);
    }

    if ($oldVersion !== $newVersion && file_exists($oldVersionPath)) {
      if (! is_dir($oldVersionPath) || is_link($oldVersionPath)) {
        throw new RuntimeException('Plugin install path is not a removable plugin directory.');
      }

      File::deleteDirectory($oldVersionPath);
    }
  }

  private function assertValidCoordinates(string $handle, string $version): void
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      throw PluginException::invalidHandle($handle);
    }

    if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
      throw PluginException::invalidVersion($handle, $version);
    }
  }

  private function canonicalRootPath(): string
  {
    File::ensureDirectoryExists($this->rootPath());

    $root = realpath($this->rootPath());

    if ($root === false) {
      throw new RuntimeException('Plugin install root is not available.');
    }

    return rtrim($root, DIRECTORY_SEPARATOR);
  }

  private function assertPathInsideRoot(string $path, string $root): void
  {
    $real = realpath($path);
    $candidate = $real !== false
      ? rtrim($real, DIRECTORY_SEPARATOR)
      : rtrim(dirname($path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($path);

    if ($candidate !== $root && ! str_starts_with($candidate, $root.DIRECTORY_SEPARATOR)) {
      throw new RuntimeException('Plugin install path is outside the configured plugin root.');
    }
  }
}
