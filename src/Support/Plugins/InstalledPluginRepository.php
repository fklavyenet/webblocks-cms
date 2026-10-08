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

    $selected = [];
    foreach ($plugins as $plugin) {
      $handle = (string) $plugin['manifest']['handle'];
      $current = $selected[$handle] ?? null;
      if ($current === null || $plugin['enabled'] || (! $current['enabled'] && version_compare((string) $plugin['manifest']['version'], (string) $current['manifest']['version'], '>'))) {
        $selected[$handle] = $plugin;
      }
    }
    $plugins = array_values($selected);

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

    $this->writeState($path, array_merge($state, [
      'version' => $version,
      'enabled_at' => $state['enabled_at'] ?? now()->toIso8601String(),
    ]));
    File::delete($directory.DIRECTORY_SEPARATOR.'disabled.json');
    File::delete($directory.DIRECTORY_SEPARATOR.$version.DIRECTORY_SEPARATOR.'runtime-failure.json');
  }

  /**
   * @param  array<string, mixed>  $result
   */
  public function recordSetupResult(string $handle, string $version, array $result): void
  {
    $this->assertValidCoordinates($handle, $version);

    $directory = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version;
    File::ensureDirectoryExists($directory);
    $path = $directory.DIRECTORY_SEPARATOR.'setup.json';

    $this->writeState($path, [
      'version' => $version,
      'setup' => array_merge($result, [
        'ran_at' => now()->toIso8601String(),
      ]),
    ]);
  }

  /** @return array<string, mixed> */
  public function setupResult(string $handle, string $version): array
  {
    $this->assertValidCoordinates($handle, $version);
    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version.DIRECTORY_SEPARATOR.'setup.json';
    $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    return is_array($state) && is_array($state['setup'] ?? null) ? $state['setup'] : [];
  }

  public function disable(string $handle): void
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      throw PluginException::invalidHandle($handle);
    }

    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'enabled.json';

    $this->writeState(dirname($path).DIRECTORY_SEPARATOR.'disabled.json', ['disabled_at' => now()->toIso8601String()]);
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

    if (is_dir($pluginPath)) {
      $this->assertPathInsideRoot($pluginPath, $root);
      if (is_link($pluginPath)) {
        throw new RuntimeException('Plugin install path is not a removable plugin directory.');
      }
      foreach (File::directories($pluginPath) as $directory) {
        $this->assertPathInsideRoot($directory, $root);
        if (is_link($directory)) {
          throw new RuntimeException('Plugin install path is not a removable plugin directory.');
        }
      }
      // An uninstall removes retained code packages too, never database tables.
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

      // Keep the last working package available for recovery.
      return;
    }
  }

  public function findVersion(string $handle, string $version): ?array
  {
    $this->assertValidCoordinates($handle, $version);
    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version;
    $this->assertPathInsideRoot($path, $this->canonicalRootPath());
    foreach (['webblocks-plugin.json', 'manifest.json'] as $filename) {
      if (! is_file($path.DIRECTORY_SEPARATOR.$filename)) {
        continue;
      }
      $manifest = json_decode((string) file_get_contents($path.DIRECTORY_SEPARATOR.$filename), true);
      if (is_array($manifest) && ($manifest['handle'] ?? null) === $handle && ($manifest['version'] ?? null) === $version) {
        return ['manifest' => $manifest, 'path' => $path];
      }
    }

    return null;
  }

  public function isDisabled(string $handle): bool
  {
    return PluginDefinition::isValidHandle($handle) && is_file($this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'disabled.json');
  }

  public function quarantine(string $handle, string $version): void
  {
    $this->assertValidCoordinates($handle, $version);
    $this->writeState($this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version.DIRECTORY_SEPARATOR.'runtime-failure.json', ['failed_at' => now()->toIso8601String()]);
    $this->disable($handle);
  }

  public function runtimeFailed(string $handle, string $version): bool
  {
    $this->assertValidCoordinates($handle, $version);

    return is_file($this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version.DIRECTORY_SEPARATOR.'runtime-failure.json');
  }

  public function recordPrevious(string $handle, string $version, string $replacement, bool $rollbackSafe): void
  {
    $this->assertValidCoordinates($handle, $version);
    $this->assertValidCoordinates($handle, $replacement);
    $this->writeState($this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'previous.json', ['version' => $version, 'replacement' => $replacement, 'rollback_safe' => $rollbackSafe]);
  }

  public function previous(string $handle): array
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      return [];
    }
    $path = $this->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.'previous.json';
    $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

    return is_array($state) ? $state : [];
  }

  private function writeState(string $path, array $state): void
  {
    File::ensureDirectoryExists(dirname($path));
    $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
    try {
      if (file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false || ! rename($temporary, $path)) {
        throw new RuntimeException('Unable to write plugin lifecycle state.');
      }
    } finally {
      if (is_file($temporary)) {
        File::delete($temporary);
      }
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
