<?php

namespace WebBlocks\Cms\Support\Applications;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Sites\SiteHandle;
use ZipArchive;

class ApplicationPackageStore
{
  public const MAX_FILES = 2000;

  public const MAX_FILE_BYTES = 25 * 1024 * 1024;

  public const MAX_TOTAL_BYTES = 200 * 1024 * 1024;

  private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'htaccess'];

  public function install(Site $site, EmbeddedApplication $application, UploadedFile $upload): array
  {
    if (! class_exists(ZipArchive::class)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.zip_required'));
    }

    $version = $this->normalizeVersion((string) $application->version);
    $target = $this->releaseDirectory($site, $application, $version);

    if (is_dir($target)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.version_installed'));
    }

    $archive = new ZipArchive;
    if ($archive->open($upload->getRealPath()) !== true) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.zip_unreadable'));
    }

    $files = [];
    $totalBytes = 0;

    try {
      if ($archive->numFiles > self::MAX_FILES) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.too_many_files'));
      }

      for ($index = 0; $index < $archive->numFiles; $index++) {
        $stat = $archive->statIndex($index);
        $path = $this->normalizePath((string) ($stat['name'] ?? ''));

        if ($path === '' || str_ends_with($path, '/')) {
          continue;
        }

        $bytes = (int) ($stat['size'] ?? 0);
        if ($bytes > self::MAX_FILE_BYTES) {
          throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.file_too_large'));
        }

        $totalBytes += $bytes;
        if ($totalBytes > self::MAX_TOTAL_BYTES) {
          throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.package_too_large'));
        }

        $comparisonKey = strtolower($path);
        if (array_key_exists($comparisonKey, $files)) {
          throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.duplicate_paths'));
        }

        $files[$comparisonKey] = ['path' => $path, 'index' => $index];
      }

      if (! array_key_exists('index.html', $files)) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.entry_missing'));
      }

      $staging = $target.'.installing-'.bin2hex(random_bytes(6));
      File::ensureDirectoryExists($staging);

      try {
        foreach ($files as $file) {
          $path = $file['path'];
          $index = $file['index'];
          $stream = $archive->getStream((string) $archive->getNameIndex($index));
          if (! is_resource($stream)) {
            throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.read_failed', ['path' => $path]));
          }

          $destination = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
          File::ensureDirectoryExists(dirname($destination));
          $output = fopen($destination, 'wb');
          if (! is_resource($output)) {
            fclose($stream);
            throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.write_failed', ['path' => $path]));
          }

          stream_copy_to_stream($stream, $output);
          fclose($stream);
          fclose($output);
        }

        File::ensureDirectoryExists(dirname($target));
        if (! rename($staging, $target)) {
          throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.activate_failed'));
        }
      } catch (\Throwable $exception) {
        File::deleteDirectory($staging);
        throw $exception;
      }
    } finally {
      $archive->close();
    }

    $entry = $this->publicPath($application, $version, 'index.html');
    $application->forceFill(['render_mode' => 'iframe', 'entry_url' => $entry])->save();

    return ['version' => $version, 'entry_url' => $entry, 'files' => count($files), 'size' => $totalBytes];
  }

  public function read(Site $site, EmbeddedApplication $application, string $version, string $path): array
  {
    $version = $this->normalizeVersion($version);
    $path = $this->normalizePath($path);
    $absolute = $this->releaseDirectory($site, $application, $version).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    $root = $this->releaseDirectory($site, $application, $version).DIRECTORY_SEPARATOR;

    if (! str_starts_with($absolute, $root) || ! is_file($absolute)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.package_asset_missing'));
    }

    return ['path' => $path, 'absolute_path' => $absolute, 'size' => (int) filesize($absolute), 'checksum' => hash_file('sha256', $absolute)];
  }

  public function publicPath(EmbeddedApplication $application, string $version, string $path): string
  {
    return '/webblocks-applications/'.trim((string) $application->handle).'/'.$this->normalizeVersion($version).'/'.$this->normalizePath($path);
  }

  private function releaseDirectory(Site $site, EmbeddedApplication $application, string $version): string
  {
    $siteHandle = SiteHandle::normalize((string) $site->handle);
    $applicationHandle = trim((string) $application->handle);
    if ($siteHandle === '' || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $applicationHandle) !== 1) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.handles_required'));
    }

    return public_path('site/'.$siteHandle.'/applications/'.$applicationHandle.'/releases/'.$version);
  }

  private function normalizeVersion(string $version): string
  {
    $version = trim($version);
    if (preg_match('/^[0-9A-Za-z](?:[0-9A-Za-z._-]{0,62}[0-9A-Za-z])?$/', $version) !== 1) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.version_invalid'));
    }

    return $version;
  }

  private function normalizePath(string $path): string
  {
    $path = str_replace('\\', '/', trim($path));
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.package_path_invalid'));
    }

    $segments = explode('/', $path);
    foreach ($segments as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._@+ -]{0,127}$/', $segment) !== 1) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.package_path_unsafe'));
      }
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.server_file'));
    }

    return implode('/', $segments);
  }
}
