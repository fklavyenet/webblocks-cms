<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;

class CatalogPluginInstallBridge
{
  private const MAX_ZIP_BYTES = 20 * 1024 * 1024;

  public function __construct(
    private readonly PluginZipInstaller $installer,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function install(CatalogPlugin $plugin): array
  {
    $release = $plugin->latestCompatibleRelease;

    if (! $plugin->isCompatible()) {
      throw new RuntimeException('This catalog plugin is not compatible with this CMS installation.');
    }

    if ($release === null) {
      throw new RuntimeException('No compatible catalog release is available for this plugin.');
    }

    $downloadUrl = $plugin->firstDownloadUrl();
    $checksum = $release->checksumSha256;
    $filename = $release->artifactFilename;

    if ($downloadUrl === null || $checksum === null || $filename === null) {
      throw new RuntimeException('This catalog release is missing downloadable artifact metadata.');
    }

    $tempPath = null;

    try {
      $tempPath = $this->download($downloadUrl, $filename);
      $this->verifyChecksum($tempPath, $checksum);

      return $this->installer->install($tempPath);
    } finally {
      if ($tempPath !== null && is_file($tempPath)) {
        File::delete($tempPath);
      }
    }
  }

  private function download(string $url, string $filename): string
  {
    try {
      $response = Http::timeout((int) config('webblocks-plugins.catalog.timeout_seconds', 5))
        ->connectTimeout((int) config('webblocks-plugins.catalog.connect_timeout_seconds', 3))
        ->withHeaders([
          'Accept' => 'application/zip, application/octet-stream',
          'User-Agent' => 'WebBlocks-CMS-Plugin-Catalog-Installer',
        ])
        ->get($url);
    } catch (ConnectionException) {
      throw new RuntimeException('The catalog artifact could not be downloaded. Try again later.');
    }

    if (! $response->successful()) {
      throw new RuntimeException('The catalog artifact could not be downloaded. Try again later.');
    }

    $contentLength = $response->header('Content-Length');

    if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > self::MAX_ZIP_BYTES) {
      throw new RuntimeException('The catalog artifact is larger than the allowed plugin ZIP size.');
    }

    $body = $response->body();

    if ($body === '' || strlen($body) > self::MAX_ZIP_BYTES || ! $this->looksLikeZip($body, $filename)) {
      throw new RuntimeException('The catalog artifact is not a valid plugin ZIP archive.');
    }

    $directory = storage_path('app/webblocks/tmp/catalog-installs');
    File::ensureDirectoryExists($directory);

    $tempPath = tempnam($directory, 'catalog-plugin-');

    if ($tempPath === false || file_put_contents($tempPath, $body) === false) {
      throw new RuntimeException('The catalog artifact could not be prepared for installation.');
    }

    return $tempPath;
  }

  private function verifyChecksum(string $path, string $expected): void
  {
    $actual = hash_file('sha256', $path);

    if (! is_string($actual) || ! hash_equals(trim($expected), $actual)) {
      throw new RuntimeException('The downloaded catalog artifact failed SHA-256 verification.');
    }
  }

  private function looksLikeZip(string $body, string $filename): bool
  {
    return str_ends_with(strtolower($filename), '.zip')
      && (str_starts_with($body, "PK\x03\x04") || str_starts_with($body, "PK\x05\x06"));
  }
}
