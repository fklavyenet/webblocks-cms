<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use WebBlocks\Cms\Models\PluginLifecycle;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

class InstalledPluginRepository
{
  private bool $dependencyLockHeld = false;

  private function withDependencyLock(callable $action): void
  {
    if ($this->dependencyLockHeld) {
    $action();

    return;
    }
    Cache::lock('webblocks-plugin-dependencies-'.hash('sha256', $this->rootPath()), 180)->block(5, function () use ($action): void {
      $this->dependencyLockHeld = true;
      try {
      $action();
      } finally {
      $this->dependencyLockHeld = false;
      }
    });
  }

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
    if (is_file($this->rootPath().'/'.$handle.'/.disabled')) {
      return null;
    }
    try {
      if (Schema::hasTable('wbcms_plugin_lifecycles')) {
        $state = $this->findState($handle);
        if ($state !== null) {
          return $state->disabled_at ? null : ($state->enabled_version ?: null);
        }
      }
    } catch (\Throwable) {
      return null; // A database outage must never reactivate plugin code.
    }
    $path = $this->rootPath().'/'.$handle.'/enabled.json';
    $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    $version = is_array($state) ? ($state['version'] ?? null) : null;

    return is_string($version) && $version !== '' ? $version : null;
  }

  public function enable(string $handle, string $version): void
  {
    $this->withDependencyLock(fn () => $this->enableUnlocked($handle, $version));
  }

  private function enableUnlocked(string $handle, string $version): void
  {
    $this->assertValidCoordinates($handle, $version);
    $candidate = $this->findVersion($handle, $version);
    if ($candidate !== null) {
      app(PluginDependencyManager::class)->assertEnable($candidate['manifest']);
    }
    $state = $this->state($handle);
    $state->forceFill(['enabled_version' => $version, 'enabled_at' => $state->enabled_at ?? now()->toIso8601String(), 'disabled_at' => null])->save();
    $this->state($handle, $version)->forceFill(['failed_at' => null])->save();
    File::delete([$this->rootPath().'/'.$handle.'/.disabled', $this->rootPath().'/'.$handle.'/'.$version.'/.runtime-failure']);
  }

  /**
   * @param  array<string, mixed>  $result
   */
  public function recordSetupResult(string $handle, string $version, array $result): void
  {
    $this->assertValidCoordinates($handle, $version);
    $this->state($handle, $version)->forceFill(['setup_result' => array_merge($result, ['ran_at' => now()->toIso8601String()])])->save();
  }

  /** @return array<string, mixed> */
  public function setupResult(string $handle, string $version): array
  {
    $this->assertValidCoordinates($handle, $version);
    if (Schema::hasTable('wbcms_plugin_lifecycles')) {
      $state = $this->findState($handle, $version);
      if ($state !== null) {
        return is_array($state->setup_result) ? $state->setup_result : [];
      }
    }
    $path = $this->rootPath().'/'.$handle.'/'.$version.'/setup.json';
    $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    return is_array($state['setup'] ?? null) ? $state['setup'] : [];
  }

  public function disable(string $handle, bool $force = false): void
  {
    if ($force) {
    $this->disableUnlocked($handle, true);

    return;
    }
    $this->withDependencyLock(fn () => $this->disableUnlocked($handle, false));
  }

  private function disableUnlocked(string $handle, bool $force): void
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      throw PluginException::invalidHandle($handle);
    }
    if (! $force) {
      app(PluginDependencyManager::class)->assertRemoval($handle);
    }
    $directory = $this->rootPath().'/'.$handle;
    File::ensureDirectoryExists($directory);
    // Minimal plain-text emergency switch also works without a database.
    File::put($directory.'/.disabled', now()->toIso8601String());
    try {
      $this->state($handle)->forceFill(['enabled_version' => null, 'disabled_at' => now()->toIso8601String()])->save();
    } catch (\Throwable) {
      // The durable emergency switch already prevents booting this plugin.
    }
  }

  public function uninstall(string $handle, string $version): void
  {
    $this->withDependencyLock(fn () => $this->uninstallUnlocked($handle, $version));
  }

  private function uninstallUnlocked(string $handle, string $version): void
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
    if (! PluginDefinition::isValidHandle($handle)) {
      return false;
    }
    if (is_file($this->rootPath().'/'.$handle.'/.disabled')) {
      return true;
    }
    try {
      if (Schema::hasTable('wbcms_plugin_lifecycles')) {
        $state = $this->findState($handle);
        if ($state !== null) {
          return $state->disabled_at !== null;
        }
      }
    } catch (\Throwable) {
      return true;
    }

    return is_file($this->rootPath().'/'.$handle.'/disabled.json');
  }

  public function quarantine(string $handle, string $version): void
  {
    $this->assertValidCoordinates($handle, $version);
    $directory = $this->rootPath().'/'.$handle.'/'.$version;
    File::ensureDirectoryExists($directory);
    File::put($directory.'/.runtime-failure', now()->toIso8601String());
    $this->disable($handle, force: true);
    try {
      $this->state($handle, $version)->forceFill(['failed_at' => now()->toIso8601String()])->save();
    } catch (\Throwable) {
    }
  }

  public function runtimeFailed(string $handle, string $version): bool
  {
    $this->assertValidCoordinates($handle, $version);
    if (is_file($this->rootPath().'/'.$handle.'/'.$version.'/.runtime-failure')) {
      return true;
    }
    try {
      if (Schema::hasTable('wbcms_plugin_lifecycles')) {
        $state = $this->findState($handle, $version);
        if ($state !== null) {
          return $state->failed_at !== null;
        }
      }
    } catch (\Throwable) {
      return true;
    }

    return is_file($this->rootPath().'/'.$handle.'/'.$version.'/runtime-failure.json');
  }

  public function recordPrevious(string $handle, string $version, string $replacement, bool $rollbackSafe): void
  {
    $this->assertValidCoordinates($handle, $version);
    $this->assertValidCoordinates($handle, $replacement);
    $this->state($handle)->forceFill(['previous_state' => ['version' => $version, 'replacement' => $replacement, 'rollback_safe' => $rollbackSafe]])->save();
  }

  public function previous(string $handle): array
  {
    if (! PluginDefinition::isValidHandle($handle)) {
      return [];
    }
    if (Schema::hasTable('wbcms_plugin_lifecycles')) {
      $state = $this->findState($handle);
      if ($state !== null) {
        return is_array($state->previous_state) ? $state->previous_state : [];
      }
    }
    $path = $this->rootPath().'/'.$handle.'/previous.json';
    $value = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    return is_array($value) ? $value : [];
  }

  private function rootHash(): string
  {
    return hash('sha256', realpath($this->rootPath()) ?: $this->rootPath());
  }

  private function findState(string $handle, string $version = ''): ?PluginLifecycle
  {
    return PluginLifecycle::where('root_hash', $this->rootHash())->where('handle', $handle)->where('version', $version)->first();
  }

  private function state(string $handle, string $version = ''): PluginLifecycle
  {
    File::ensureDirectoryExists($this->rootPath().'/'.$handle);
    if (! app(PersistentDocuments::class)->available()) {
      PersistentSchema::ensure();
    }

    return $this->findState($handle, $version) ?? PluginLifecycle::create(['root_hash' => $this->rootHash(), 'handle' => $handle, 'version' => $version]);
  }

  public function migrateLegacyState(bool $remove = false): void
  {
    if (! is_dir($this->rootPath())) {
      return;
    }
    foreach (File::directories($this->rootPath()) as $directory) {
      $handle = basename($directory);
      if (! PluginDefinition::isValidHandle($handle) || is_link($directory)) {
        continue;
      }
      $main = $this->state($handle);
      $legacy = $main->legacy_state ?? [];
      foreach (['enabled', 'disabled', 'previous'] as $type) {
        $path = $directory.'/'.$type.'.json';
        if (! is_file($path)) {
        continue;
        }
        if (array_key_exists($type, $legacy)) {
          if ($remove) {
          File::delete($path);
          }

          continue;
        }
        $raw = (string) file_get_contents($path);
        $value = json_decode($raw, true);
        $legacy[$type] = json_last_error() === JSON_ERROR_NONE ? $value : $raw;
        if ($type === 'enabled' && is_array($value)) {
          $main->enabled_version = is_string($value['version'] ?? null) ? $value['version'] : null;
          $main->enabled_at = is_string($value['enabled_at'] ?? null) ? $value['enabled_at'] : null;
        } elseif ($type === 'disabled') {
          $main->disabled_at = is_string($value['disabled_at'] ?? null) ? $value['disabled_at'] : now()->toIso8601String();
        } elseif ($type === 'previous') {
          $main->previous_state = is_array($value) ? $value : [];
        }
      }
      $main->legacy_state = $legacy;
      $main->save();
      foreach (File::directories($directory) as $versionPath) {
        if (is_link($versionPath)) {
        continue;
        }
        $version = basename($versionPath);
        if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
        continue;
        }
        foreach (['setup', 'runtime-failure'] as $type) {
          $path = $versionPath.'/'.$type.'.json';
          if (! is_file($path)) {
          continue;
          }
          $raw = (string) file_get_contents($path);
          $value = json_decode($raw, true);
          $state = $this->state($handle, $version);
          if (array_key_exists($type, $state->legacy_state ?? [])) {
            if ($remove) {
            File::delete($path);
            }

            continue;
          }
          $state->legacy_state = array_replace($state->legacy_state ?? [], [$type => json_last_error() === JSON_ERROR_NONE ? $value : $raw]);
          if ($type === 'setup') {
            $state->setup_result = is_array($value['setup'] ?? null) ? $value['setup'] : [];
          } else {
            $state->failed_at = is_string($value['failed_at'] ?? null) ? $value['failed_at'] : now()->toIso8601String();
          }
          $state->save();
          if ($remove) {
          File::delete($path);
          }
        }
      }
      if ($remove) {
        foreach (['enabled', 'disabled', 'previous'] as $type) {
        File::delete($directory.'/'.$type.'.json');
        }
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
      : rtrim(realpath(dirname($path)) ?: dirname($path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($path);

    if ($candidate !== $root && ! str_starts_with($candidate, $root.DIRECTORY_SEPARATOR)) {
      throw new RuntimeException('Plugin install path is outside the configured plugin root.');
    }
  }
}
