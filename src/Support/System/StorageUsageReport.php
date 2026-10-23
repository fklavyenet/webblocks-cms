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
    return [
      'measured_at' => now()->toIso8601String(),
      'categories' => [
        'backups' => $this->disk('backups', storage_path('app/backups')),
        'transfer_packages' => $this->disk(SiteTransferDisk::DISK, storage_path('app/site-transfers')),
        'public_uploads' => $this->disk('public', null, false),
        'media_variants' => $this->disk('public', null, true),
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
