<?php

namespace WebBlocks\Cms\Support\Theme;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use WebBlocks\Cms\Support\ExtensionsCatalog;
use WebBlocks\Cms\Support\WebBlocks;

final class ThemeCatalog
{
  public const HANDLE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

  public const VERSION_PATTERN = '(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[a-z0-9]+(?:[.-][a-z0-9]+)*)?';

  public function __construct(private readonly ThemePackage $packages, private readonly PublicThemes $themes) {}

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

    if (! is_array($result['data'] ?? null) || ! array_is_list($result['data']) || count($result['data']) > 24) {
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

    $installed = $this->themes->installedCatalogVersions(array_keys($themes));
    foreach ($themes as $handle => $theme) {
      $version = $theme['release']['version'] ?? null;
      $versions = $installed[$handle] ?? [];
      $alreadyInstalled = $version !== null && collect($versions)->contains(
        fn (string $existing) => version_compare($existing, $version, '>=')
      );
      $themes[$handle]['install_action'] = $version === null || $alreadyInstalled
        ? null
        : ($versions === [] ? 'install' : 'update');
    }

    return ['themes' => array_values($themes), 'hasMore' => ($result['next_page'] ?? null) === $page + 1];
  }

  public function install(string $handle, string $version): void
  {
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
      $package = $this->packages->read($filename)['version'];

      if ($package['handle'] !== $handle || $package['version'] !== $version) {
        $this->invalid('catalog_download_failed');
      }
      if (! ThemePackage::compatible((object) $package)) {
        $this->invalid('incompatible');
      }

      $this->themes->install($filename);
    } finally {
      fclose($file);
    }
  }

  private function download(string $path, mixed $file, int $size, string $checksum): void
  {
    try {
      $deadline = hrtime(true) + 15000000000;
      $response = $this->http()->withOptions(['stream' => true, 'read_timeout' => 5])->get(ExtensionsCatalog::themeBaseUrl().$path);
      $body = $response->toPsrResponse()->getBody();
      try {
        if (! $response->successful()) {
          $this->invalid('catalog_download_failed');
        }
        $bytes = 0;
        $hash = hash_init('sha256');
        while (! $body->eof()) {
          $chunk = $body->read(min(65536, $size - $bytes + 1));
          $bytes += strlen($chunk);
          if ($chunk === '' || $bytes > $size || hrtime(true) > $deadline || fwrite($file, $chunk) !== strlen($chunk)) {
            $this->invalid('catalog_download_failed');
          }
          hash_update($hash, $chunk);
        }
        if ($bytes !== $size || ! hash_equals($checksum, hash_final($hash)) || ! fflush($file)) {
          $this->invalid('catalog_download_failed');
        }
      } finally {
        $body->close();
      }
    } catch (ConnectionException|RuntimeException) {
      $this->invalid('catalog_download_failed');
    }
  }

  private function json(string $path, array $query): array
  {
    try {
      $response = $this->http()->acceptJson()->withOptions([
        'progress' => static function ($total, $downloaded): void {
          if ($total > 524288 || $downloaded > 524288) {
            throw new RuntimeException('Theme catalog response exceeded the size limit.');
          }
        },
      ])->get(ExtensionsCatalog::themeBaseUrl().$path, $query);
      $json = $response->successful() && strlen($response->body()) <= 524288 ? $response->json() : null;
    } catch (ConnectionException|RuntimeException) {
      $this->invalid('catalog_unavailable');
    }

    if (! is_array($json)) {
      $this->invalid('catalog_unavailable');
    }

    return $json;
  }

  private function http(): PendingRequest
  {
    return Http::connectTimeout(3)->timeout(15)->withoutRedirecting()->withHeaders(['Accept-Encoding' => 'identity']);
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
