<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Media\MediaTransformService;

class MaintenanceCleanup
{
  private const REVISION_STORAGE_WARNING_BYTES = 104857600;

  public const ASSET_REVISIONS = 'asset-revisions';

  public const MEDIA_VARIANTS = 'media-variants';

  public const PAGE_REVISIONS = 'page-revisions';

  public const SHARED_SLOT_REVISIONS = 'shared-slot-revisions';

  public const TEMPORARY_WORKSPACES = 'temporary-workspaces';

  public const RUNNABLE = [self::ASSET_REVISIONS, self::MEDIA_VARIANTS, self::TEMPORARY_WORKSPACES, self::PAGE_REVISIONS, self::SHARED_SLOT_REVISIONS];

  public function __construct(
    private readonly SystemSettings $settings,
    private readonly MediaTransformService $mediaTransforms,
  ) {}

  public function overview(): array
  {
    $pageRevisionBytes = $this->snapshotBytes('wbcms_page_revisions');
    $sharedSlotRevisionBytes = $this->snapshotBytes('wbcms_shared_slot_revisions');
    $blockStorage = $this->blockStorageOverview();
    $mediaStorage = $this->mediaStorageOverview();

    return [
      'asset_revisions' => $this->previewAssetRevisions(),
      'media_variants' => $this->previewMediaVariants(),
      'temporary_workspaces' => $this->previewTemporaryWorkspaces(),
      'page_revision_cleanup' => $this->previewPageRevisions(),
      'shared_slot_revision_cleanup' => $this->previewSharedSlotRevisions(),
      'page_revisions' => $this->tableCount('wbcms_page_revisions', PageRevision::class),
      'page_revision_bytes' => $pageRevisionBytes,
      'shared_slot_revisions' => $this->tableCount('wbcms_shared_slot_revisions', SharedSlotRevision::class),
      'shared_slot_revision_bytes' => $sharedSlotRevisionBytes,
      'block_storage' => $blockStorage,
      'media_storage' => $mediaStorage,
      'site_growth' => $this->siteGrowthOverview(),
      'capacity_warnings' => $this->capacityWarnings($pageRevisionBytes + $sharedSlotRevisionBytes, $blockStorage, $mediaStorage),
      'transfer_packages' => $this->transferPackageCount(),
    ];
  }

  public function run(string $category): MaintenanceCleanupResult
  {
    return match ($category) {
      self::ASSET_REVISIONS => $this->cleanAssetRevisions(),
      self::MEDIA_VARIANTS => $this->cleanMediaVariants(),
      self::TEMPORARY_WORKSPACES => $this->cleanTemporaryWorkspaces(),
      self::PAGE_REVISIONS => $this->cleanPageRevisions(),
      self::SHARED_SLOT_REVISIONS => $this->cleanSharedSlotRevisions(),
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

  public function previewPageRevisions(): MaintenanceCleanupResult
  {
    return $this->revisionResult($this->pageRevisionCandidates());
  }

  public function previewSharedSlotRevisions(): MaintenanceCleanupResult
  {
    return $this->revisionResult($this->sharedSlotRevisionCandidates());
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

  private function cleanPageRevisions(): MaintenanceCleanupResult
  {
    return $this->deleteRevisionCandidates(PageRevision::class, $this->pageRevisionCandidates());
  }

  private function cleanSharedSlotRevisions(): MaintenanceCleanupResult
  {
    return $this->deleteRevisionCandidates(SharedSlotRevision::class, $this->sharedSlotRevisionCandidates());
  }

  private function pageRevisionCandidates(): array
  {
    if (! Schema::hasTable('wbcms_page_revisions')) {
      return [];
    }

    $policy = $this->settings->maintenanceCleanupSettings();
    $protected = Schema::hasTable('wbcms_page_revision_candidates')
      ? PageRevisionCandidate::query()->where('status', PageRevisionCandidate::STATUS_READY)->pluck('page_revision_id')->all()
      : [];

    return $this->revisionCandidates(
      PageRevision::query(),
      'page_id',
      $policy['page_revision_days'],
      $policy['keep_latest_page_revisions'],
      $protected,
    );
  }

  private function sharedSlotRevisionCandidates(): array
  {
    if (! Schema::hasTable('wbcms_shared_slot_revisions')) {
      return [];
    }

    $policy = $this->settings->maintenanceCleanupSettings();

    return $this->revisionCandidates(
      SharedSlotRevision::query(),
      'shared_slot_id',
      $policy['shared_slot_revision_days'],
      $policy['keep_latest_shared_slot_revisions'],
    );
  }

  private function revisionCandidates($query, string $ownerKey, int $days, int $keep, array $protected = []): array
  {
    $cutoff = now()->subDays($days);
    $seen = [];
    $protected = array_fill_keys(array_map('intval', $protected), true);
    $candidates = [];

    $query->select(['id', $ownerKey, 'created_at'])
      ->selectRaw('LENGTH(snapshot) as snapshot_bytes')
      ->orderBy($ownerKey)
      ->orderByDesc('created_at')
      ->orderByDesc('id')
      ->each(function ($revision) use (&$seen, &$candidates, $ownerKey, $keep, $cutoff, $protected): void {
        $ownerId = (int) $revision->{$ownerKey};
        $position = $seen[$ownerId] ?? 0;
        $seen[$ownerId] = $position + 1;

        if ($position < $keep || $revision->created_at?->gte($cutoff) || isset($protected[(int) $revision->id])) {
          return;
        }

        $candidates[] = ['id' => (int) $revision->id, 'bytes' => (int) $revision->snapshot_bytes];
      });

    return $candidates;
  }

  private function revisionResult(array $candidates): MaintenanceCleanupResult
  {
    return new MaintenanceCleanupResult(count($candidates), array_sum(array_column($candidates, 'bytes')));
  }

  private function deleteRevisionCandidates(string $model, array $candidates): MaintenanceCleanupResult
  {
    $preview = $this->revisionResult($candidates);
    $ids = array_column($candidates, 'id');
    $deleted = 0;

    foreach (array_chunk($ids, 500) as $chunk) {
      $deleted += $model::query()->whereKey($chunk)->delete();
    }

    return new MaintenanceCleanupResult($preview->candidateCount, $preview->candidateBytes, $deleted, $deleted === count($ids) ? $preview->candidateBytes : 0);
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

  private function snapshotBytes(string $table): int
  {
    return Schema::hasTable($table)
      ? (int) DB::table($table)->selectRaw('COALESCE(SUM(LENGTH(snapshot)), 0) as bytes')->value('bytes')
      : 0;
  }

  private function blockStorageOverview(): array
  {
    if (! Schema::hasTable('wbcms_blocks')) {
      return ['live_count' => 0, 'highest_id' => 0, 'id_gap' => 0];
    }

    $metrics = Block::query()->selectRaw('COUNT(*) as live_count, COALESCE(MIN(id), 0) as lowest_id, COALESCE(MAX(id), 0) as highest_id')->first();
    $live = (int) $metrics->live_count;
    $lowest = (int) $metrics->lowest_id;
    $highest = (int) $metrics->highest_id;

    return ['live_count' => $live, 'highest_id' => $highest, 'id_gap' => max(0, ($highest - $lowest + 1) - $live)];
  }

  private function mediaStorageOverview(): array
  {
    if (! Schema::hasTable('wbcms_media')) {
      return ['items' => 0, 'images' => 0, 'original_bytes' => 0, 'variant_files' => 0, 'variant_bytes' => 0];
    }

    $summary = Media::query()
      ->selectRaw('COUNT(*) as items, COALESCE(SUM(size), 0) as original_bytes')
      ->selectRaw('SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END) as images', [Media::KIND_IMAGE])
      ->first();
    $variantFiles = 0;
    $variantBytes = 0;

    $this->imageMedia()->groupBy('disk')->each(function ($mediaItems, string $diskName) use (&$variantFiles, &$variantBytes): void {
      $disk = Storage::disk($diskName);
      foreach ($disk->allFiles('media/transforms') as $path) {
        $variantFiles++;
        $variantBytes += (int) $disk->size($path);
      }
    });

    return [
      'items' => (int) $summary->items,
      'images' => (int) $summary->images,
      'original_bytes' => (int) $summary->original_bytes,
      'variant_files' => $variantFiles,
      'variant_bytes' => $variantBytes,
    ];
  }

  private function siteGrowthOverview(): array
  {
    if (! Schema::hasTable('wbcms_sites')) {
      return [];
    }

    $hasPages = Schema::hasTable('wbcms_pages');
    $hasBlocks = Schema::hasTable('wbcms_blocks');
    $hasPageRevisions = Schema::hasTable('wbcms_page_revisions');
    $hasSharedSlotRevisions = Schema::hasTable('wbcms_shared_slot_revisions');

    return Site::query()->orderBy('id')->get(['id', 'name', 'handle'])->map(function (Site $site) use ($hasPages, $hasBlocks, $hasPageRevisions, $hasSharedSlotRevisions): array {
      $pageIds = $hasPages ? DB::table('wbcms_pages')->where('site_id', $site->id)->select('id') : null;

      return [
        'id' => (int) $site->id,
        'name' => $site->name,
        'handle' => $site->handle,
        'pages' => $hasPages ? (int) DB::table('wbcms_pages')->where('site_id', $site->id)->count() : 0,
        'blocks' => $hasBlocks && $pageIds ? (int) DB::table('wbcms_blocks')->whereIn('page_id', $pageIds)->count() : 0,
        'page_revisions' => $hasPageRevisions ? (int) DB::table('wbcms_page_revisions')->where('site_id', $site->id)->count() : 0,
        'page_revision_bytes' => $hasPageRevisions ? (int) DB::table('wbcms_page_revisions')->where('site_id', $site->id)->selectRaw('COALESCE(SUM(LENGTH(snapshot)), 0) as bytes')->value('bytes') : 0,
        'shared_slot_revisions' => $hasSharedSlotRevisions ? (int) DB::table('wbcms_shared_slot_revisions')->where('site_id', $site->id)->count() : 0,
        'shared_slot_revision_bytes' => $hasSharedSlotRevisions ? (int) DB::table('wbcms_shared_slot_revisions')->where('site_id', $site->id)->selectRaw('COALESCE(SUM(LENGTH(snapshot)), 0) as bytes')->value('bytes') : 0,
      ];
    })->all();
  }

  private function capacityWarnings(int $revisionBytes, array $blockStorage, array $mediaStorage): array
  {
    $warnings = [];

    if ($revisionBytes >= self::REVISION_STORAGE_WARNING_BYTES) {
      $warnings[] = 'revision_storage';
    }
    if ($blockStorage['live_count'] > 0 && $blockStorage['id_gap'] >= $blockStorage['live_count'] * 5) {
      $warnings[] = 'block_churn';
    }
    if ($mediaStorage['original_bytes'] > 0 && $mediaStorage['variant_bytes'] >= $mediaStorage['original_bytes'] * 2) {
      $warnings[] = 'media_variants';
    }

    return $warnings;
  }

  private function transferPackageCount(): int
  {
    $exports = Schema::hasTable('wbcms_site_exports') ? SiteExport::query()->count() : 0;
    $imports = Schema::hasTable('wbcms_site_imports') ? SiteImport::query()->count() : 0;

    return $exports + $imports;
  }
}
