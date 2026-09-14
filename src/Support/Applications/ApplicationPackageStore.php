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
      throw new RuntimeException('ZIP support is required to install an application package.');
    }

    $version = $this->normalizeVersion((string) $application->version);
    $target = $this->releaseDirectory($site, $application, $version);

    if (is_dir($target)) {
      throw new RuntimeException('This application version is already installed. Increase the application version before uploading a new package.');
    }

    $archive = new ZipArchive;
    if ($archive->open($upload->getRealPath()) !== true) {
      throw new RuntimeException('The uploaded application package is not a readable ZIP archive.');
    }

    $files = [];
    $totalBytes = 0;

    try {
      if ($archive->numFiles > self::MAX_FILES) {
        throw new RuntimeException('The application package contains too many files.');
      }

      for ($index = 0; $index < $archive->numFiles; $index++) {
        $stat = $archive->statIndex($index);
        $path = $this->normalizePath((string) ($stat['name'] ?? ''));

        if ($path === '' || str_ends_with($path, '/')) {
          continue;
        }

        $bytes = (int) ($stat['size'] ?? 0);
        if ($bytes > self::MAX_FILE_BYTES) {
          throw new RuntimeException('An application package file exceeds the 25 MB limit.');
        }

        $totalBytes += $bytes;
        if ($totalBytes > self::MAX_TOTAL_BYTES) {
          throw new RuntimeException('The expanded application package exceeds the 200 MB limit.');
        }

        $comparisonKey = strtolower($path);
        if (array_key_exists($comparisonKey, $files)) {
          throw new RuntimeException('The application package contains duplicate file paths.');
        }

        $files[$comparisonKey] = ['path' => $path, 'index' => $index];
      }

      if (! array_key_exists('index.html', $files)) {
        throw new RuntimeException('The application package must contain index.html at its root.');
      }

      $staging = $target.'.installing-'.bin2hex(random_bytes(6));
      File::ensureDirectoryExists($staging);

      try {
        foreach ($files as $file) {
          $path = $file['path'];
          $index = $file['index'];
          $stream = $archive->getStream((string) $archive->getNameIndex($index));
          if (! is_resource($stream)) {
            throw new RuntimeException('CMS could not read '.$path.' from the application package.');
          }

          $destination = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
          File::ensureDirectoryExists(dirname($destination));
          $output = fopen($destination, 'wb');
          if (! is_resource($output)) {
            fclose($stream);
            throw new RuntimeException('CMS could not write '.$path.' from the application package.');
          }

          stream_copy_to_stream($stream, $output);
          fclose($stream);
          fclose($output);
        }

        File::ensureDirectoryExists(dirname($target));
        if (! rename($staging, $target)) {
          throw new RuntimeException('CMS could not activate the application package.');
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
      throw new RuntimeException('Application package asset was not found.');
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
      throw new RuntimeException('Valid site and application handles are required.');
    }

    return public_path('site/'.$siteHandle.'/applications/'.$applicationHandle.'/releases/'.$version);
  }

  private function normalizeVersion(string $version): string
  {
    $version = trim($version);
    if (preg_match('/^[0-9A-Za-z](?:[0-9A-Za-z._-]{0,62}[0-9A-Za-z])?$/', $version) !== 1) {
      throw new RuntimeException('Application version must be safe for a public package URL.');
    }

    return $version;
  }

  private function normalizePath(string $path): string
  {
    $path = str_replace('\\', '/', trim($path));
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
      throw new RuntimeException('Application package contains an invalid path.');
    }

    $segments = explode('/', $path);
    foreach ($segments as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._@+ -]{0,127}$/', $segment) !== 1) {
        throw new RuntimeException('Application package contains an unsafe path.');
      }
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
      throw new RuntimeException('Application package contains a server-executable file type.');
    }

    return implode('/', $segments);
  }
}
