<?php

namespace WebBlocks\Cms\Support\Theme;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\ExtensionsCatalog;
use WebBlocks\Cms\Support\ExtensionsCatalogHttp;
use WebBlocks\Cms\Support\WebBlocks;

final class ThemeCatalog
{
  public const PAGE_SIZE = 24;

  public const HANDLE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

  public const VERSION_PATTERN = '(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[a-z0-9]+(?:[.-][a-z0-9]+)*)?';

  public function __construct(
    private readonly ThemePackage $packages,
    private readonly PublicThemes $themes,
    private readonly ExtensionsCatalogHttp $http,
  ) {}

  public function enabled(): bool
  {
    return ExtensionsCatalog::themeBaseUrl() !== null;
  }

  public function browse(int $page): array
  {
    $result = $this->json('/api/themes', [
      'page' => $page,
      'cms' => WebBlocks::VERSION,
      'ui' => ltrim(WebBlocks::UI_VERSION, 'v'),
    ]);

    if (! is_array($result['data'] ?? null) || ! array_is_list($result['data']) || count($result['data']) > self::PAGE_SIZE) {
      $this->invalid('catalog_unavailable');
    }

    $themes = [];
    foreach ($result['data'] as $theme) {
      if (! is_array($theme) || ! $this->token($theme['handle'] ?? null, self::HANDLE_PATTERN, 63)
        || ! is_string($theme['name'] ?? null) || mb_strlen($theme['name']) > 100
        || (isset($theme['description']) && (! is_string($theme['description']) || mb_strlen($theme['description']) > 3000))) {
        $this->invalid('catalog_unavailable');
      }
      $release = $theme['release'] ?? null;
      if ($release !== null && (! is_array($release) || ! $this->validRelease($release, $theme['handle']))) {
        $this->invalid('catalog_unavailable');
      }
      $themes[$theme['handle']] = ['release' => $release] + $theme;
    }

    $installed = $this->themes->installedCatalogChecksums(array_keys($themes));
    foreach ($themes as $handle => $theme) {
      $release = $theme['release'];
      $version = $release['version'] ?? null;
      $versions = $installed[$handle] ?? [];
      $themes[$handle]['state'] = match (true) {
        $release === null => 'incompatible',
        isset($versions[$version]) => hash_equals($versions[$version], $release['sha256']) ? 'installed' : 'conflict',
        collect(array_keys($versions))->contains(fn (string $existing) => version_compare($existing, $version, '>')) => 'newer_installed',
        $versions !== [] => 'update',
        default => 'install',
      };
    }

    return ['themes' => array_values($themes), 'hasMore' => ($result['next_page'] ?? null) === $page + 1];
  }

  public function install(string $handle, string $version, ?ExtensionOperation $operation = null): void
  {
    if ($operation !== null) {
      $installed = $this->themes->installedCatalogChecksums([$handle])[$handle] ?? [];
      if ($installed !== [] && ! isset($installed[$version]) && ! collect(array_keys($installed))->contains(fn (string $existing): bool => version_compare($existing, $version, '>'))) {
        $operation->operation('update');
      }
    }
    $result = $this->json('/api/themes/'.$handle.'/releases/'.$version, []);
    $release = $result['data'] ?? null;
    if (! is_array($release) || ! $this->validRelease($release, $handle) || $release['version'] !== $version) {
      $this->invalid('catalog_unavailable');
    }

    $file = tmpfile();
    if ($file === false) {
      $this->invalid('catalog_download_failed');
    }
    try {
      $path = '/themes/'.$handle.'/releases/'.$version.'/download';
      $this->download($path, $file, $release['size'], $release['sha256']);
      $filename = stream_get_meta_data($file)['uri'];
      $operation?->during('manifest');
      $package = $this->packages->read($filename);
      $manifest = $package['version'];
      $archiveLayouts = $package['layouts'];
      $catalogLayouts = $release['layouts'];
      sort($archiveLayouts);
      sort($catalogLayouts);

      $operation?->during('integrity');
      if ($manifest['handle'] !== $handle || $manifest['version'] !== $version || $archiveLayouts !== $catalogLayouts) {
        $this->invalid('catalog_download_failed');
      }
      $operation?->identify($handle, $version, $manifest['name']);
      $operation?->during('compatibility');
      if (! ThemePackage::compatible((object) $manifest)) {
        $this->invalid('incompatible');
      }

      $this->themes->install($filename, $operation);
    } finally {
      fclose($file);
    }
  }

  private function download(string $path, mixed $file, int $size, string $checksum): void
  {
    try {
      $this->http->download($path, $file, $size, $checksum);
    } catch (ConnectionException|RuntimeException) {
      $this->invalid('catalog_download_failed');
    }
  }

  private function json(string $path, array $query): array
  {
    try {
      return $this->http->json($path, $query, 524288);
    } catch (ConnectionException|RuntimeException) {
      $this->invalid('catalog_unavailable');
    }
  }

  private function validRelease(array $release, string $handle): bool
  {
    return ($release['handle'] ?? null) === $handle
      && $this->token($release['version'] ?? null, self::VERSION_PATTERN, 64)
      && $this->token($release['sha256'] ?? null, '[a-f0-9]{64}', 64)
      && is_int($release['size'] ?? null) && $release['size'] > 0 && $release['size'] <= ThemePackage::MAX_KILOBYTES * 1024
      && is_array($release['layouts'] ?? null) && array_is_list($release['layouts']) && count($release['layouts']) <= 16
      && collect($release['layouts'])->every(fn ($layout) => $this->token($layout, self::HANDLE_PATTERN, 63));
  }

  private function token(mixed $value, string $pattern, int $max): bool
  {
    return is_string($value) && strlen($value) <= $max && preg_match('/^'.$pattern.'$/D', $value) === 1;
  }

  private function invalid(string $key): never
  {
    throw ValidationException::withMessages(['catalog' => __('webblocks-cms::themes.'.$key)]);
  }
}
