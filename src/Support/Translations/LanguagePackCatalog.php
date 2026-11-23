<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\ExtensionsCatalog;
use WebBlocks\Cms\Support\ExtensionsCatalogHttp;
use WebBlocks\Cms\Support\WebBlocks;

final class LanguagePackCatalog
{
  public const VERSION_PATTERN = '(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)';

  public const FORMAT = 'native';

  public function __construct(
    private readonly PanelLanguageSource $source,
    private readonly NativeLanguagePacks $nativePacks,
    private readonly ExtensionsCatalogHttp $http,
  ) {}

  public function enabled(): bool
  {
    return ExtensionsCatalog::secureBaseUrl() !== null && $this->nativePacks->ready();
  }

  public function browse(): array
  {
    try {
      $result = $this->http->json('/api/language-packs', ['cms' => WebBlocks::VERSION], 131072);
      $data = $result['data'] ?? null;
    } catch (ConnectionException|RuntimeException) {
      $this->invalid('extensions_unavailable');
    }

    if (! is_array($data) || ! array_is_list($data) || count($data) > 250) {
      $this->invalid('extensions_unavailable');
    }

    $releases = [];
    foreach ($data as $row) {
      // Older catalog servers can include retired panel-only artifacts.
      if (is_array($row) && ($row['format'] ?? null) === 'panel') {
        continue;
      }
      if (! is_array($row) || ! $this->validRelease($row)) {
        $this->invalid('extensions_unavailable');
      }

      $code = $row['code'];
      if (! in_array($code, AdminLocaleResolver::SUPPORTED_LOCALES, true)
        && (! isset($releases[$code]) || version_compare($row['version'], $releases[$code]['version'], '>'))) {
        $releases[$code] = $row;
      }
    }

    return $releases;
  }

  public function install(string $code, string $version, string $checksum, ?ExtensionOperation $operation = null): void
  {
    if ($operation !== null && $this->nativePacks->installed()->has($code)) {
      $operation->operation('update');
    }
    $release = $this->browse()[$code] ?? null;
    if ($release === null || $release['version'] !== $version || ! hash_equals($release['sha256'], $checksum)) {
      $operation?->during('integrity');
      $this->invalid('extensions_release_changed');
    }

    $operation?->identify($code, $version, $release['name']);
    $file = tmpfile();
    if ($file === false) {
      $this->invalid('extensions_download_failed');
    }

    try {
      $this->download('/language-packs/'.$code.'/releases/'.$version.'/'.self::FORMAT.'/download', $file, $release);
      $this->nativePacks->installArchive(stream_get_meta_data($file)['uri'], $operation, $code, $version);
    } finally {
      fclose($file);
    }
  }

  private function download(string $path, mixed $file, array $release): void
  {
    try {
      $this->http->download($path, $file, $release['size'], $release['sha256']);
    } catch (ConnectionException|RuntimeException) {
      $this->invalid('extensions_download_failed');
    }
  }

  private function validRelease(array $row): bool
  {
    return ($row['format'] ?? null) === self::FORMAT && is_string($row['code'] ?? null) && $this->source->validCode($row['code'])
      && is_string($row['name'] ?? null) && trim($row['name']) !== '' && mb_strlen($row['name']) <= 100 && $this->source->plainText($row['name'])
      && is_string($row['version'] ?? null) && strlen($row['version']) <= 32 && preg_match('/\A'.self::VERSION_PATTERN.'\z/', $row['version'])
      && ($row['cms'] ?? null) === WebBlocks::VERSION
      && is_string($row['sha256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $row['sha256'])
      && is_int($row['size'] ?? null) && $row['size'] > 0
      && $row['size'] <= NativeLanguageArchive::MAX_BYTES;
  }

  private function invalid(string $key): never
  {
    throw ValidationException::withMessages(['package' => __('webblocks-cms::panel-languages.'.$key)]);
  }
}
