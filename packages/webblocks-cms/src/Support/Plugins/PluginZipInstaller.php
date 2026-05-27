<?php

namespace WebBlocks\Cms\Support\Plugins;

use RuntimeException;
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
  public function install(string $zipPath): array
  {
    $zip = new ZipArchive;

    if ($zip->open($zipPath) !== true) {
      throw new RuntimeException('The uploaded plugin package is not a readable ZIP archive.');
    }

    try {
      $entries = $this->safeEntries($zip);
      [$manifestPath, $stripPrefix] = $this->locateManifest($entries);
      $manifest = $this->manifest($zip, $manifestPath);
      $this->validateManifest($manifest);

      $handle = (string) $manifest['handle'];
      $version = (string) $manifest['version'];

      if ($this->plugins->hasHandle($handle)) {
        throw new RuntimeException('A plugin with this handle is already installed.');
      }

      $target = $this->plugins->rootPath().DIRECTORY_SEPARATOR.$handle.DIRECTORY_SEPARATOR.$version;

      if (file_exists($target)) {
        throw new RuntimeException('A plugin with this handle and version is already installed.');
      }

      $this->extract($zip, $entries, $stripPrefix, $target);

      return [
        'handle' => $handle,
        'version' => $version,
        'path' => $target,
      ];
    } finally {
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
      throw new RuntimeException('The uploaded plugin package is empty.');
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

    throw new RuntimeException('The plugin package must contain webblocks-plugin.json or manifest.json.');
  }

  /**
   * @return array<string, mixed>
   */
  private function manifest(ZipArchive $zip, string $path): array
  {
    $contents = $zip->getFromName($path);
    $manifest = is_string($contents) ? json_decode($contents, true) : null;

    if (! is_array($manifest)) {
      throw new RuntimeException('The plugin manifest is malformed JSON.');
    }

    return $manifest;
  }

  /**
   * @param  array<string, mixed>  $manifest
   */
  private function validateManifest(array $manifest): void
  {
    $handle = $manifest['handle'] ?? null;

    if (! is_string($handle) || ! PluginDefinition::isValidHandle($handle)) {
      throw new RuntimeException('The plugin manifest handle must be kebab-case.');
    }

    if (! is_string($manifest['label'] ?? null) || trim((string) $manifest['label']) === '') {
      throw new RuntimeException('The plugin manifest must declare a label.');
    }

    if (! is_string($manifest['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $manifest['version'])) {
      throw new RuntimeException('The plugin manifest must declare a semver-like version.');
    }

    if (! is_string($manifest['provider'] ?? null) || trim((string) $manifest['provider']) === '') {
      throw new RuntimeException('The plugin manifest must declare provider class metadata.');
    }

    $definition = PluginDefinition::make($handle)
      ->label((string) $manifest['label'])
      ->version((string) $manifest['version'])
      ->provider((string) $manifest['provider'])
      ->requiresCms($manifest['required_cms_version'] ?? null);

    if ($definition->requiredCmsVersion() === null || ! $this->compatibility->isCompatible($definition)) {
      throw new RuntimeException('The plugin package is not compatible with this CMS version.');
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
