<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use ZipArchive;

class PluginZipInstaller
{
  private const MANIFEST_NAMES = ['webblocks-plugin.json', 'manifest.json'];

  public function __construct(
    private readonly InstalledPluginRepository $plugins = new InstalledPluginRepository,
    private readonly PluginCompatibility $compatibility = new PluginCompatibility,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function install(string $zipPath, ?ExtensionOperation $operation = null, ?string $expectedHandle = null, ?string $expectedVersion = null): array
  {
    return $this->installPackage($zipPath, $expectedHandle, operation: $operation, expectedVersion: $expectedVersion);
  }

  /**
   * @return array<string, mixed>
   */
  public function update(string $zipPath, string $expectedHandle, string $currentVersion, ?ExtensionOperation $operation = null, ?string $expectedVersion = null): array
  {
    return $this->installPackage($zipPath, $expectedHandle, $currentVersion, $operation, $expectedVersion);
  }

  /**
   * @return array<string, mixed>
   */
  private function installPackage(string $zipPath, ?string $expectedHandle = null, ?string $currentVersion = null, ?ExtensionOperation $operation = null, ?string $expectedVersion = null): array
  {
    $operation?->during('package');
    $zip = new ZipArchive;
    $installLock = null;

    if ($zip->open($zipPath) !== true) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_validation.plugin_zip_unreadable'));
    }

    try {
      $entries = $this->safeEntries($zip);
      $operation?->during('manifest');
      [$manifestPath, $stripPrefix] = $this->locateManifest($entries);
      $manifest = $this->manifest($zip, $manifestPath);
      $manifestHandle = is_string($manifest['handle'] ?? null) ? $manifest['handle'] : null;
      $manifestVersion = is_string($manifest['version'] ?? null) ? $manifest['version'] : null;
      if (($expectedHandle !== null && $manifestHandle !== $expectedHandle) || ($expectedVersion !== null && $manifestVersion !== $expectedVersion)) {
        $operation?->during('integrity');
        throw new RuntimeException('The catalog artifact does not match the selected plugin release.');
      }
      $operation?->identify($manifestHandle, $manifestVersion, is_string($manifest['label'] ?? null) ? $manifest['label'] : null);
      $this->validateManifest($manifest, $operation);
      $operation?->during('dependencies');
      PluginDependencies::normalize($manifest['dependencies'] ?? [], (string) $manifest['handle']);
      $operation?->during('manifest');
      app(PluginIconDeclaration::class)->validate($manifest);
      $operation?->during('translation');
      $this->validateAdminTranslations($entries, $stripPrefix);

      $handle = (string) $manifest['handle'];
      $version = (string) $manifest['version'];
      $installLock = Cache::lock('webblocks-plugin-install-'.hash('sha256', $this->plugins->rootPath().'/'.$handle), max(1, (int) config('webblocks-plugins.install.database_timeout_seconds', 120)) + 60);
      $operation?->during('busy');
      if (! $installLock->get()) {
        throw new RuntimeException(__('webblocks-cms::admin.system_plugins_show.database_update_busy'));
      }

      $operation?->during('package');
      if ($currentVersion !== null && version_compare($version, $currentVersion, '<=')) {
        throw new RuntimeException('The catalog artifact is not newer than the installed plugin version.');
      }

      if ($currentVersion === null && $this->plugins->hasHandle($handle)) {
        throw new RuntimeException('A plugin with this handle is already installed.');
      }

      if ($expectedHandle !== null && $currentVersion !== null) {
        $current = collect($this->plugins->installed())->first(fn (array $installed): bool => ($installed['manifest']['handle'] ?? null) === $expectedHandle);
        if (($current['manifest']['version'] ?? null) !== $currentVersion) {
          $operation?->during('busy');
          throw new RuntimeException(__('webblocks-cms::admin.system_plugins_show.database_update_busy'));
        }
      }

      $target = $this->plugins->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version;

      if (file_exists($target)) {
        throw new RuntimeException('A plugin with this handle and version is already installed.');
      }

      if ($currentVersion !== null) {
        $operation?->during('dependencies');
        app(PluginDependencyManager::class)->assertReplacement($manifest);
      }
      $operation?->during('package');
      $this->extract($zip, $entries, $stripPrefix, $target);

      $wasEnabled = $currentVersion !== null && ($this->plugins->enabledVersion($handle) === $currentVersion || (! $this->plugins->isDisabled($handle) && (bool) config("webblocks-plugins.enabled.{$handle}", false)));
      $setupStarted = false;
      $probePassed = false;
      try {
        $operation?->during('startup');
        $definition = app(InstalledPluginDefinitionFactory::class)->make($manifest, $target, false);
        app(PluginBootProbe::class)->check($definition);
        $probePassed = true;
        $operation?->during('database');
        if ($expectedHandle !== null && $currentVersion !== null) {
          $this->plugins->recordPrevious($handle, $currentVersion, $version, false);
          $this->plugins->disable($handle, force: true);
        }
        $setupStarted = true;
        $setup = app(PluginDatabaseSetup::class)->run($definition, verifyBoot: false, syncBlockCatalog: $wasEnabled);
        if ($wasEnabled) {
          $this->plugins->enable($handle, $version);
        } elseif ($currentVersion !== null) {
          app(PluginBlockTypeCatalogSyncer::class)->sync(plugin: $definition);
        }
        if ($expectedHandle !== null && $currentVersion !== null) {
          $this->plugins->recordPrevious($handle, $currentVersion, $version, ! $setup['ran']);
        }
      } catch (Throwable $exception) {
        if (! $setupStarted) {
          if (! $probePassed) {
            $operation?->preserved($currentVersion);
          }
          File::deleteDirectory($target);
        }
        throw $exception;
      }

      return [
        'handle' => $handle,
        'version' => $version,
        'path' => $target,
        'database_setup' => $setup,
      ];
    } finally {
      $installLock?->release();
      $zip->close();
    }
  }

  /**
   * @return array<int, string>
   */
  private function safeEntries(ZipArchive $zip): array
  {
    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
      $name = (string) $zip->getNameIndex($index);

      if ($name === '' || str_ends_with($name, '/')) {
        continue;
      }

      $this->assertSafePath($name);
      $this->assertNotSymlink($zip, $index);
      $entries[] = $name;
    }

    if ($entries === []) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_validation.plugin_zip_empty'));
    }

    return $entries;
  }

  /**
   * @param  array<int, string>  $entries
   * @return array{0: string, 1: string}
   */
  private function locateManifest(array $entries): array
  {
    foreach (self::MANIFEST_NAMES as $name) {
      if (in_array($name, $entries, true)) {
        return [$name, ''];
      }
    }

    $prefixes = array_unique(array_map(fn (string $entry): string => explode('/', $entry, 2)[0], $entries));

    if (count($prefixes) === 1) {
      $prefix = $prefixes[0].'/';

      foreach (self::MANIFEST_NAMES as $name) {
        if (in_array($prefix.$name, $entries, true)) {
          return [$prefix.$name, $prefix];
        }
      }
    }

    throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.plugin_manifest_missing'));
  }

  /**
   * @return array<string, mixed>
   */
  private function manifest(ZipArchive $zip, string $path): array
  {
    $contents = $zip->getFromName($path);
    $manifest = is_string($contents) ? json_decode($contents, true) : null;

    if (! is_array($manifest)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.plugin_manifest_invalid_json'));
    }

    return $manifest;
  }

  /**
   * @param  array<string, mixed>  $manifest
   */
  private function validateManifest(array $manifest, ?ExtensionOperation $operation): void
  {
    $handle = $manifest['handle'] ?? null;

    if (! is_string($handle) || ! PluginDefinition::isValidHandle($handle)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.plugin_manifest_invalid_handle'));
    }

    if (! is_string($manifest['label'] ?? null) || trim((string) $manifest['label']) === '') {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.plugin_manifest_label_required'));
    }

    if (! is_string($manifest['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $manifest['version'])) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.plugin_manifest_invalid_version'));
    }

    if (! is_string($manifest['provider'] ?? null) || trim((string) $manifest['provider']) === '') {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.plugin_manifest_provider_required'));
    }

    $definition = PluginDefinition::make($handle)
      ->label((string) $manifest['label'])
      ->version((string) $manifest['version'])
      ->provider((string) $manifest['provider'])
      ->requiresCms($manifest['required_cms_version'] ?? null);

    $operation?->during('compatibility');
    if ($definition->requiredCmsVersion() === null || ! $this->compatibility->isCompatible($definition)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_validation.plugin_zip_incompatible'));
    }
  }

  /**
   * Every plugin admin catalogue must cover every locale the CMS lets an
   * operator select. Matching the English catalogue filenames keeps the rule
   * extensible when a plugin splits its UI copy across several files.
   *
   * @param  array<int, string>  $entries
   */
  private function validateAdminTranslations(array $entries, string $stripPrefix): void
  {
    $relativeEntries = array_map(
      fn (string $entry): string => $stripPrefix !== '' && str_starts_with($entry, $stripPrefix)
        ? substr($entry, strlen($stripPrefix))
        : $entry,
      $entries,
    );

    $englishCatalogues = array_values(array_filter(
      $relativeEntries,
      fn (string $entry): bool => preg_match('#^resources/lang/en/[^/]+\.php$#', $entry) === 1,
    ));

    if ($englishCatalogues === []) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_validation.plugin_catalog_english_required'));
    }

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      foreach ($englishCatalogues as $englishCatalogue) {
        $required = preg_replace('#^resources/lang/en/#', 'resources/lang/'.$locale.'/', $englishCatalogue, 1);

        if (! is_string($required) || ! in_array($required, $relativeEntries, true)) {
          throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_validation.plugin_catalog_missing', ['locale' => $locale, 'file' => basename($englishCatalogue)]));
        }
      }
    }
  }

  private function assertSafePath(string $path): void
  {
    $normalized = str_replace('\\', '/', $path);

    if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized)) {
      throw new RuntimeException('Plugin package paths must be relative.');
    }

    foreach (explode('/', $normalized) as $segment) {
      if ($segment === '..' || $segment === '') {
        throw new RuntimeException('Plugin package paths must not contain traversal segments.');
      }
    }

    $top = explode('/', $normalized, 2)[0];

    if (in_array($top, ['app', 'packages', 'project', 'storage', 'vendor'], true) || str_starts_with($normalized, 'public/cms/')) {
      throw new RuntimeException('Plugin packages must not target CMS core, project, vendor, storage, or public/cms paths.');
    }
  }

  private function assertNotSymlink(ZipArchive $zip, int $index): void
  {
    $attributes = 0;
    $opsys = 0;

    if (method_exists($zip, 'getExternalAttributesIndex')) {
      $zip->getExternalAttributesIndex($index, $opsys, $attributes);
    }

    $mode = ($attributes >> 16) & 0170000;

    if ($mode === 0120000) {
      throw new RuntimeException('Plugin package symlinks are not allowed.');
    }
  }

  /**
   * @param  array<int, string>  $entries
   */
  private function extract(ZipArchive $zip, array $entries, string $stripPrefix, string $target): void
  {
    foreach ($entries as $entry) {
      $relative = $stripPrefix !== '' && str_starts_with($entry, $stripPrefix)
        ? substr($entry, strlen($stripPrefix))
        : $entry;

      $this->assertSafePath($relative);

      $destination = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
      $directory = dirname($destination);

      if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        throw new RuntimeException('The plugin package could not be installed.');
      }

      $contents = $zip->getFromName($entry);

      if (! is_string($contents) || file_put_contents($destination, $contents) === false) {
        throw new RuntimeException('The plugin package could not be installed.');
      }
    }
  }
}
