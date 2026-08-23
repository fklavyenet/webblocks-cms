<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Media\MediaTransformService;

class MaintenanceCleanup
{
  public const ASSET_REVISIONS = 'asset-revisions';

  public const MEDIA_VARIANTS = 'media-variants';

  public const TEMPORARY_WORKSPACES = 'temporary-workspaces';

  public const RUNNABLE = [self::ASSET_REVISIONS, self::MEDIA_VARIANTS, self::TEMPORARY_WORKSPACES];

  public function __construct(
    private readonly SystemSettings $settings,
    private readonly MediaTransformService $mediaTransforms,
  ) {}

  public function overview(): array
  {
    return [
      'asset_revisions' => $this->previewAssetRevisions(),
      'media_variants' => $this->previewMediaVariants(),
      'temporary_workspaces' => $this->previewTemporaryWorkspaces(),
      'page_revisions' => $this->tableCount('wbcms_page_revisions', PageRevision::class),
      'shared_slot_revisions' => $this->tableCount('wbcms_shared_slot_revisions', SharedSlotRevision::class),
      'transfer_packages' => $this->transferPackageCount(),
    ];
  }

  public function run(string $category): MaintenanceCleanupResult
  {
    return match ($category) {
      self::ASSET_REVISIONS => $this->cleanAssetRevisions(),
      self::MEDIA_VARIANTS => $this->cleanMediaVariants(),
      self::TEMPORARY_WORKSPACES => $this->cleanTemporaryWorkspaces(),
      default => throw new \InvalidArgumentException('Unsupported maintenance cleanup category.'),
    };
  }

  public function previewAssetRevisions(): MaintenanceCleanupResult
  {
    return $this->fileResult($this->assetRevisionCandidates());
  }

  public function previewTemporaryWorkspaces(): MaintenanceCleanupResult
  {
    return $this->fileResult($this->temporaryWorkspaceCandidates());
  }

  public function previewMediaVariants(): MaintenanceCleanupResult
  {
    $paths = [];

    $this->imageMedia()->each(function (Media $media) use (&$paths): void {
      $disk = Storage::disk($media->disk);
      $root = 'media/transforms/'.$media->getKey();
      $active = collect(config('media_transforms.variants', []))
        ->map(fn (array $definition) => substr($this->mediaTransforms->fingerprint($media, $definition), 0, 16))
        ->unique()->all();

      foreach ($disk->directories($root) as $directory) {
        if (! in_array(basename($directory), $active, true)) {
          $paths[] = [$media->disk, $directory];
        }
      }
    });

    $bytes = 0;
    foreach ($paths as [$diskName, $directory]) {
      $disk = Storage::disk($diskName);
      foreach ($disk->allFiles($directory) as $file) {
        $bytes += (int) $disk->size($file);
      }
    }

    return new MaintenanceCleanupResult(count($paths), $bytes);
  }

  private function cleanAssetRevisions(): MaintenanceCleanupResult
  {
    return $this->deleteFiles($this->assetRevisionCandidates());
  }

  private function cleanTemporaryWorkspaces(): MaintenanceCleanupResult
  {
    $candidates = $this->temporaryWorkspaceCandidates();
    $deleted = 0;
    $bytes = 0;
    $failures = [];

    foreach ($candidates as $path) {
      $size = $this->pathSize($path);
      if (File::deleteDirectory($path)) {
        $deleted++;
        $bytes += $size;
      } else {
        $failures[] = ['path' => basename($path), 'message' => 'Directory could not be deleted.'];
      }
    }

    return new MaintenanceCleanupResult(count($candidates), array_sum(array_map($this->pathSize(...), $candidates)), $deleted, $bytes, $failures);
  }

  private function cleanMediaVariants(): MaintenanceCleanupResult
  {
    $preview = $this->previewMediaVariants();
    $deleted = 0;
    $failures = [];

    $this->imageMedia()->each(function (Media $media) use (&$deleted, &$failures): void {
      try {
        $deleted += $this->mediaTransforms->prune($media);
      } catch (\Throwable $exception) {
        $failures[] = ['id' => (int) $media->getKey(), 'message' => $exception->getMessage()];
      }
    });

    return new MaintenanceCleanupResult($preview->candidateCount, $preview->candidateBytes, $deleted, $deleted > 0 ? $preview->candidateBytes : 0, $failures);
  }

  private function assetRevisionCandidates(): array
  {
    $policy = $this->settings->maintenanceCleanupSettings();
    $cutoff = now()->subDays($policy['asset_revision_days'])->getTimestamp();
    $keep = $policy['keep_latest_asset_revisions'];
    $groups = [];

    foreach ([storage_path('app/cms/site-assets'), storage_path('app/cms/application-assets')] as $root) {
      if (! is_dir($root)) {
        continue;
      }

      foreach (File::allFiles($root) as $file) {
        $path = $file->getPathname();
        if (! str_contains(str_replace('\\', '/', $path), '/revisions/') && str_contains($root, 'site-assets')) {
          continue;
        }
        $name = $file->getFilename();
        $original = preg_match('/^\d{14}-[a-f0-9]{64}-(.+)$/', $name, $matches) === 1 ? $matches[1] : pathinfo($name, PATHINFO_EXTENSION);
        $groups[$file->getPath().'/'.$original][] = $path;
      }
    }

    $candidates = [];
    foreach ($groups as $files) {
      usort($files, fn (string $left, string $right) => filemtime($right) <=> filemtime($left));
      foreach (array_slice($files, $keep) as $path) {
        if (filemtime($path) < $cutoff) {
          $candidates[] = $path;
        }
      }
    }

    return $candidates;
  }

  private function temporaryWorkspaceCandidates(): array
  {
    $cutoff = now()->subHours($this->settings->maintenanceCleanupSettings()['temporary_workspace_hours'])->getTimestamp();
    $paths = [];

    foreach ([storage_path('app/system-updates'), storage_path('app/webblocks/tmp/catalog-installs')] as $root) {
      if (! is_dir($root)) {
        continue;
      }
      foreach (File::directories($root) as $directory) {
        if (filemtime($directory) < $cutoff) {
          $paths[] = $directory;
        }
      }
    }

    return $paths;
  }

  private function deleteFiles(array $paths): MaintenanceCleanupResult
  {
    $candidateBytes = array_sum(array_map(fn (string $path): int => (int) filesize($path), $paths));
    $deleted = 0;
    $deletedBytes = 0;
    $failures = [];
    foreach ($paths as $path) {
      $size = (int) filesize($path);
      if (File::delete($path)) {
        $deleted++;
        $deletedBytes += $size;
      } else {
        $failures[] = ['path' => basename($path), 'message' => 'File could not be deleted.'];
      }
    }

    return new MaintenanceCleanupResult(count($paths), $candidateBytes, $deleted, $deletedBytes, $failures);
  }

  private function fileResult(array $paths): MaintenanceCleanupResult
  {
    return new MaintenanceCleanupResult(count($paths), array_sum(array_map($this->pathSize(...), $paths)));
  }

  private function pathSize(string $path): int
  {
    if (is_file($path)) {
      return (int) filesize($path);
    }

    return array_sum(array_map(fn ($file): int => (int) $file->getSize(), File::allFiles($path)));
  }

  private function imageMedia()
  {
    return Schema::hasTable('wbcms_media') ? Media::query()->where('kind', Media::KIND_IMAGE)->get() : collect();
  }

  private function tableCount(string $table, string $model): int
  {
    return Schema::hasTable($table) ? $model::query()->count() : 0;
  }

  private function transferPackageCount(): int
  {
    $exports = Schema::hasTable('wbcms_site_exports') ? SiteExport::query()->count() : 0;
    $imports = Schema::hasTable('wbcms_site_imports') ? SiteImport::query()->count() : 0;

    return $exports + $imports;
  }
}
