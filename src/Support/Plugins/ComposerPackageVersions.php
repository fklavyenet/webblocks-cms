<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Plugins;

use Composer\InstalledVersions;
use JsonException;
use Throwable;

/**
 * Reads Composer package versions from the host's on-disk registry first.
 *
 * Composer\InstalledVersions keeps process-local static data. Long-running PHP
 * workers can therefore retain the version that existed before a package-scoped
 * update. installed.json is ordinary data and reflects the latest completed
 * update without requiring a worker restart.
 */
final class ComposerPackageVersions
{
  public function __construct(
    private readonly ?string $installedJsonPath = null,
  ) {}

  /** @return array{installed: bool, version: ?string} */
  public function find(string $package): array
  {
    $fromDisk = $this->fromInstalledJson($package);

    if ($fromDisk !== null) {
      return $fromDisk;
    }

    try {
      if (! InstalledVersions::isInstalled($package)) {
        return ['installed' => false, 'version' => null];
      }

      $version = InstalledVersions::getVersion($package)
        ?? InstalledVersions::getPrettyVersion($package);

      return [
        'installed' => true,
        'version' => is_string($version) && $version !== '' ? $version : null,
      ];
    } catch (Throwable) {
      return ['installed' => false, 'version' => null];
    }
  }

  /** @return array{installed: bool, version: ?string}|null */
  private function fromInstalledJson(string $package): ?array
  {
    $path = $this->installedJsonPath ?? base_path('vendor/composer/installed.json');

    if (! is_file($path) || ! is_readable($path)) {
      return null;
    }

    try {
      $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      return null;
    }

    if (! is_array($document)) {
      return null;
    }

    $packages = array_is_list($document) ? $document : ($document['packages'] ?? null);

    if (! is_array($packages)) {
      return null;
    }

    foreach ($packages as $metadata) {
      if (! is_array($metadata) || ($metadata['name'] ?? null) !== $package) {
        continue;
      }

      $version = $metadata['version'] ?? $metadata['pretty_version'] ?? null;

      if (is_string($version) && preg_match('/^v\d/i', $version) === 1) {
        $version = substr($version, 1);
      }

      return [
        'installed' => true,
        'version' => is_string($version) && $version !== '' ? $version : null,
      ];
    }

    return ['installed' => false, 'version' => null];
  }
}
