<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferDisk;

/** Read-only physical file measurements, never directory creation or reconciliation. */
final class StorageUsageReport
{
  public function overview(): array
  {
    $public = $this->publicMeasurements();

    return [
      'measured_at' => now()->toIso8601String(),
      'categories' => [
        'backups' => $this->backups(),
        'transfer_packages' => $this->disk(SiteTransferDisk::DISK, storage_path('app/site-transfers')),
        'public_uploads' => $public['uploads'],
        'media_variants' => $public['variants'],
        'temporary_workspaces' => $this->directories([
          storage_path(trim((string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'), '/')),
          storage_path('app/webblocks/tmp/catalog-installs'),
          storage_path('app/temp'),
        ]),
        'asset_revisions' => $this->directories([storage_path('app/cms/site-assets'), storage_path('app/cms/application-assets')]),
        'site_public' => $this->directories([public_path('site')]),
        'logs' => $this->directories([storage_path('logs')]),
      ],
    ];
  }

  /** Partition uploads and generated variants in a single physical traversal. */
  private function publicMeasurements(): array
  {
    $result = ['uploads' => $this->empty(), 'variants' => $this->empty()];
    $config = config('filesystems.disks.public');
    if (! is_array($config)) {
      return ['uploads' => $this->empty('unavailable'), 'variants' => $this->empty('unavailable')];
    }
    try {
      if (($config['driver'] ?? null) === 'local') {
        $root = isset($config['root']) ? realpath($config['root']) : false;
        if ($root === false || ! is_dir($root)) {
          return ['uploads' => $this->empty('missing'), 'variants' => $this->empty('missing')];
        }
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($entries as $file) {
          if ($file->isLink()) {
            $result['uploads']['status'] = $result['variants']['status'] = 'partial';
          } elseif ($file->isFile()) {
            $path = substr($file->getPathname(), strlen(rtrim($root, '/')) + 1);
            $key = $this->included($path, true) ? 'variants' : 'uploads';
            $result[$key]['files']++;
            $result[$key]['bytes'] += $file->getSize();
          }
        }
      } else {
        foreach (Storage::disk('public')->listContents('', true) as $file) {
          if ($file->isFile()) {
            $key = $this->included($file->path(), true) ? 'variants' : 'uploads';
            $result[$key]['files']++;
            if ($file->fileSize() === null) {
              $result[$key]['status'] = 'partial';
            } else {
              $result[$key]['bytes'] += $file->fileSize();
            }
          }
        }
      }
    } catch (Throwable) {
      foreach ($result as &$measurement) {
        $measurement['status'] = $measurement['files'] > 0 ? 'partial' : 'unavailable';
      }
    }

    return $result;
  }

  public function backups(): array
  {
    return $this->disk('backups', storage_path('app/backups'));
  }

  private function disk(string $name, ?string $defaultRoot, ?bool $variants = null): array
  {
    $config = config('filesystems.disks.'.$name);
    if (! is_array($config)) {
      return $defaultRoot === null ? $this->empty('unavailable') : $this->directories([$defaultRoot]);
    }
    if (($config['driver'] ?? null) === 'local') {
      $root = $config['root'] ?? null;

      return is_string($root) && $root !== '' ? $this->directories([$root], $variants) : $this->empty('unavailable');
    }
    $result = $this->empty();
    try {
      foreach (Storage::disk($name)->listContents('', true) as $entry) {
        if ($entry->isFile() && $this->included($entry->path(), $variants)) {
          $result['files']++;
          $size = $entry->fileSize();
          if ($size === null) {
            $result['status'] = 'partial';

            continue;
          }
          $result['bytes'] += $size;
        }
      }
    } catch (Throwable) {
      $result['status'] = $result['files'] > 0 ? 'partial' : 'unavailable';
    }

    return $result;
  }

  private function directories(array $roots, ?bool $variants = null): array
  {
    $result = $this->empty();
    $found = false;
    foreach (array_unique($roots) as $root) {
      if (is_link($root)) {
        $resolved = realpath($root);
        if ($resolved === false) {
          $result['status'] = 'unavailable';

          continue;
        }
        $root = $resolved;
      }
      if (! file_exists($root)) {
        continue;
      }
      $found = true;
      try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
          if ($file->isLink()) {
            $result['status'] = 'partial';

            continue;
          }
          if ($file->isFile() && $this->included(substr($file->getPathname(), strlen(rtrim($root, '/')) + 1), $variants)) {
            $result['files']++;
            $result['bytes'] += $file->getSize();
          }
        }
      } catch (Throwable) {
        $result['status'] = $result['files'] > 0 ? 'partial' : 'unavailable';
      }
    }
    if ($result['status'] === 'unavailable' && $result['files'] > 0) {
      $result['status'] = 'partial';
    }
    if (! $found && $result['status'] === 'available') {
      $result['status'] = 'missing';
    }

    return $result;
  }

  private function included(string $path, ?bool $variants): bool
  {
    return $variants === null || str_starts_with(str_replace('\\', '/', $path), 'media/transforms/') === $variants;
  }

  private function empty(string $status = 'available'): array
  {
    return ['files' => 0, 'bytes' => 0, 'status' => $status];
  }
}
