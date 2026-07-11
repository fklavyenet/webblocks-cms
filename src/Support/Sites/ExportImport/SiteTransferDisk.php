<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SiteTransferDisk
{
  public const DISK = 'site-transfers';

  public static function defaultConfig(): array
  {
    return [
      'driver' => 'local',
      'root' => storage_path('app/site-transfers'),
      'throw' => false,
    ];
  }

  public static function registerDefaultConfigIfMissing(): void
  {
    if (config()->has('filesystems.disks.'.self::DISK)) {
      return;
    }

    config()->set('filesystems.disks.'.self::DISK, self::defaultConfig());
  }

  public function ensureReady(): FilesystemAdapter
  {
    self::registerDefaultConfigIfMissing();

    $configuredRoot = config('filesystems.disks.'.self::DISK.'.root');

    if (! is_string($configuredRoot) || trim($configuredRoot) === '') {
      throw new RuntimeException($this->adminErrorMessage());
    }

    try {
      if (is_file($configuredRoot)) {
        throw new RuntimeException('Configured site transfer storage path is a file.');
      }

      File::ensureDirectoryExists($configuredRoot);

      if (! is_dir($configuredRoot) || ! is_writable($configuredRoot)) {
        throw new RuntimeException('Configured site transfer storage path is not writable.');
      }

      return Storage::disk(self::DISK);
    } catch (Throwable $throwable) {
      throw new RuntimeException($this->adminErrorMessage(), previous: $throwable);
    }
  }

  private function adminErrorMessage(): string
  {
    return 'Site transfer storage is not ready. Check the site-transfers filesystem disk and make sure storage/app/site-transfers is writable.';
  }
}
