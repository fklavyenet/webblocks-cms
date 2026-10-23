<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\Revisions\RevisionCleanupQuery;
use WebBlocks\Cms\Support\Revisions\RevisionPayloadSizes;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;

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
    $pageRevisionTotals = $this->revisionStorageBySite('wbcms_page_revisions');
    $sharedSlotRevisionTotals = $this->revisionStorageBySite('wbcms_shared_slot_revisions');
    $pageRevisionBytes = (int) $pageRevisionTotals->sum('bytes');
    $sharedSlotRevisionBytes = (int) $sharedSlotRevisionTotals->sum('bytes');
    $blockStorage = $this->blockStorageOverview();
    $mediaStorage = $this->mediaStorageOverview();

    return [
      'asset_revisions' => $this->preview(self::ASSET_REVISIONS),
      'media_variants' => $this->preview(self::MEDIA_VARIANTS),
      'temporary_workspaces' => $this->preview(self::TEMPORARY_WORKSPACES),
      'page_revision_cleanup' => $this->preview(self::PAGE_REVISIONS),
      'shared_slot_revision_cleanup' => $this->preview(self::SHARED_SLOT_REVISIONS),
      'page_revisions' => (int) $pageRevisionTotals->sum('count'),
      'page_revision_bytes' => $pageRevisionBytes,
      'shared_slot_revisions' => (int) $sharedSlotRevisionTotals->sum('count'),
      'shared_slot_revision_bytes' => $sharedSlotRevisionBytes,
      'block_storage' => $blockStorage,
      'media_storage' => $mediaStorage,
      'site_growth' => $this->siteGrowthOverview($pageRevisionTotals, $sharedSlotRevisionTotals),
      'capacity_warnings' => $this->capacityWarnings($pageRevisionBytes + $sharedSlotRevisionBytes, $blockStorage, $mediaStorage),
      'transfer_packages' => $this->transferPackageCount(),
      'storage_report' => app(StorageUsageReport::class)->overview(),
      'automation' => [
        'enabled' => app(SystemSettings::class)->automaticMaintenanceSettings(),
        'scheduler' => app(SchedulerHealth::class)->snapshot(),
        ...app(MaintenanceCleanupState::class)->overview(),
      ],
    ];
  }

  public function preview(string $category): MaintenanceCleanupResult
  {
    if (! in_array($category, self::RUNNABLE, true)) {
      throw new \InvalidArgumentException('Unsupported maintenance cleanup category.');
    }
    try {
      return match ($category) {
        self::ASSET_REVISIONS => $this->previewAssetRevisions(),
        self::MEDIA_VARIANTS => $this->previewMediaVariants(),
        self::TEMPORARY_WORKSPACES => $this->previewTemporaryWorkspaces(),
        self::PAGE_REVISIONS => $this->previewPageRevisions(),
        self::SHARED_SLOT_REVISIONS => $this->previewSharedSlotRevisions(),
      };
    } catch (\Throwable) {
      return new MaintenanceCleanupResult(0, 0, failures: [['code' => 'preview_unavailable']]);
    }
  }

  public function run(string $category, string $source = 'manual'): MaintenanceCleanupResult
  {
    if (! in_array($category, self::RUNNABLE, true)) {
      throw new \InvalidArgumentException('Unsupported maintenance cleanup category.');
    }
    $state = app(MaintenanceCleanupState::class);
    if (! $state->ready()) {
      return new MaintenanceCleanupResult(0, 0, failures: [['code' => 'schema_missing']]);
    }
    $lock = Cache::lock('cms:maintenance-cleanup', 86400);
    if (! $lock->get()) {
      return new MaintenanceCleanupResult(0, 0, failures: [['code' => 'busy']]);
    }
    try {
      $state->start($category, $source === 'scheduled' ? 'scheduled' : 'manual');
      try {
        $result = $this->execute($category);
      } catch (\Throwable $exception) {
        $result = new MaintenanceCleanupResult(0, 0, failures: [['code' => 'failed']]);
        Log::warning('Maintenance cleanup failed.', ['category' => $category, 'exception' => $exception::class]);
      }
      $state->finish($category, $result);

      return $result;
    } finally {
      $lock->release();
    }
  }

  private function execute(string $category): MaintenanceCleanupResult
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
    $count = 0;
    $bytes = 0;
    foreach ($this->imageMedia() as $media) {
      $disk = Storage::disk($media->disk);
      $root = 'media/transforms/'.$media->getKey();
      $active = collect(config('media_transforms.variants', []))
        ->map(fn (array $definition) => substr($this->mediaTransforms->fingerprint($media, $definition), 0, 16))
        ->unique()->all();
      foreach ($disk->listContents($root, false) as $entry) {
        if (! $entry->isDir() || in_array(basename($entry->path()), $active, true)) {
          continue;
        }
        $count++;
        foreach ($disk->listContents($entry->path(), true) as $file) {
          if ($file->isFile()) {
            $bytes += $file->fileSize() ?? (int) $disk->size($file->path());
          }
        }
      }
    }

    return new MaintenanceCleanupResult($count, $bytes);
  }

  public function previewPageRevisions(): MaintenanceCleanupResult
  {
    return $this->revisionPreview(self::PAGE_REVISIONS);
  }

  public function previewSharedSlotRevisions(): MaintenanceCleanupResult
  {
    return $this->revisionPreview(self::SHARED_SLOT_REVISIONS);
  }

  private function cleanAssetRevisions(): MaintenanceCleanupResult
  {
    return $this->deleteFiles($this->assetRevisionCandidates());
  }

  private function cleanTemporaryWorkspaces(): MaintenanceCleanupResult
  {
    $candidates = $this->temporaryWorkspaceCandidates();
    $candidateBytes = array_sum(array_map($this->pathSize(...), $candidates));
    $deleted = 0;
    $bytes = 0;
    $failures = [];

    $cutoff = now()->subHours($this->settings->maintenanceCleanupSettings()['temporary_workspace_hours'])->getTimestamp();
    foreach ($candidates as $path) {
      if (is_link($path) || ! file_exists($path) || filemtime($path) >= $cutoff) {
        continue;
      }
      if (is_dir($path) && ($this->updatesActive() || $this->latestModification($path) >= $cutoff)) {
        continue;
      }
      $size = $this->pathSize($path);
      if (is_file($path) ? $this->deleteTemporaryFile($path) : File::deleteDirectory($path)) {
        $deleted++;
        $bytes += $size;
      } else {
        $failures[] = ['path' => basename($path), 'message' => 'Directory could not be deleted.'];
      }
    }

    return new MaintenanceCleanupResult(count($candidates), $candidateBytes, $deleted, $bytes, $failures);
  }

  private function cleanMediaVariants(): MaintenanceCleanupResult
  {
    $preview = $this->previewMediaVariants();
    $deleted = 0;
    $failures = [];

    $deletedBytes = 0;
    foreach ($this->imageMedia() as $media) {
      try {
        $result = $this->mediaTransforms->pruneWithResult($media);
        $deleted += $result['deleted'];
        $deletedBytes += $result['bytes'];
        foreach ($result['failures'] as $failure) {
          $failures[] = ['id' => (int) $media->getKey(), 'message' => $failure];
        }
      } catch (\Throwable) {
        $failures[] = ['id' => (int) $media->getKey(), 'code' => 'failed'];
      }
    }

    return new MaintenanceCleanupResult($preview->candidateCount, $preview->candidateBytes, $deleted, $deletedBytes, $failures);
  }

  private function cleanPageRevisions(): MaintenanceCleanupResult
  {
    return $this->cleanRevisions(self::PAGE_REVISIONS);
  }

  private function cleanSharedSlotRevisions(): MaintenanceCleanupResult
  {
    return $this->cleanRevisions(self::SHARED_SLOT_REVISIONS);
  }

  private function revisionConfig(string $category): array
  {
    $policy = $this->settings->maintenanceCleanupSettings();

    return $category === self::PAGE_REVISIONS
      ? [PageRevision::class, 'page_id', $policy['page_revision_days'], $policy['keep_latest_page_revisions'], Page::class]
      : [SharedSlotRevision::class, 'shared_slot_id', $policy['shared_slot_revision_days'], $policy['keep_latest_shared_slot_revisions'], SharedSlot::class];
  }

  private function revisionPreview(string $category): MaintenanceCleanupResult
  {
    [$model, $ownerKey, $days, $keep] = $this->revisionConfig($category);
    $count = 0;
    $bytes = 0;
    foreach ((new RevisionCleanupQuery)->batches($model, $ownerKey, $days, $keep, $category === self::PAGE_REVISIONS) as $batch) {
      $count += count($batch);
      $bytes += array_sum(array_column($batch, 'bytes'));
    }

    return new MaintenanceCleanupResult($count, $bytes);
  }

  private function cleanRevisions(string $category): MaintenanceCleanupResult
  {
    [$model, $ownerKey, $days, $keep, $ownerModel] = $this->revisionConfig($category);
    $queries = new RevisionCleanupQuery;
    $count = 0;
    $bytes = 0;
    $deleted = 0;
    $deletedBytes = 0;
    foreach ($queries->batches($model, $ownerKey, $days, $keep, $category === self::PAGE_REVISIONS) as $batch) {
      $count += count($batch);
      $bytes += array_sum(array_column($batch, 'bytes'));
      [$removed, $freed] = DB::transaction(function () use ($batch, $model, $ownerModel, $ownerKey, $days, $keep, $category, $queries): array {
        $owners = array_values(array_unique(array_column($batch, 'owner_id')));
        sort($owners);
        $ownerModel::query()->whereIn('id', $owners)->orderBy('id')->lockForUpdate()->get(['id']);
        $eligible = collect($batch)->keyBy('id');
        // Recheck the newest-version floor and active restore sources under owner locks.
        foreach ($owners as $ownerId) {
          $protected = $model::query()->where($ownerKey, $ownerId)->orderByDesc('created_at')->orderByDesc('id')->limit($keep)->pluck('id');
          $eligible = $eligible->except($protected->all());
        }
        if ($category === self::PAGE_REVISIONS) {
          $eligible = $eligible->except($queries->protectedIds($eligible->keys()->all()));
        }
        if ($eligible->isEmpty()) {
          return [0, 0];
        }
        $ids = $model::query()->whereIn('id', $eligible->keys()->all())
          ->where(fn ($old) => $old->where('created_at', '<', now()->subDays($days))->orWhereNull('created_at'))
          ->lockForUpdate()->pluck('id');
        $freed = $eligible->only($ids->all())->sum('bytes');
        $removed = $model::query()->whereIn('id', $ids->all())->delete();

        return [$removed, $removed === $ids->count() ? $freed : 0];
      });
      $deleted += $removed;
      $deletedBytes += $freed;
    }

    return new MaintenanceCleanupResult($count, $bytes, $deleted, $deletedBytes);
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

    $updateRoot = storage_path(trim((string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'), '/'));
    if (! $this->updatesActive() && is_dir($updateRoot) && ! is_link($updateRoot)) {
      foreach (File::directories($updateRoot) as $directory) {
        if (! is_link($directory) && filemtime($directory) < $cutoff && $this->latestModification($directory) < $cutoff) {
          $paths[] = $directory;
        }
      }
    }
    $catalogRoot = storage_path('app/webblocks/tmp/catalog-installs');
    if (is_dir($catalogRoot) && ! is_link($catalogRoot)) {
      // The catalog downloads flat temporary files, not child directories.
      foreach (File::files($catalogRoot) as $file) {
        if (! $file->isLink() && str_starts_with($file->getFilename(), 'catalog-plugin-') && $file->getMTime() < $cutoff && $this->temporaryFileAvailable($file->getPathname())) {
          $paths[] = $file->getPathname();
        }
      }
    }

    return $paths;
  }

  private function updatesActive(): bool
  {
    if (Schema::hasTable('wbcms_system_update_runs') && DB::table('wbcms_system_update_runs')->whereIn('status', ['running', 'pending'])->exists()) {
      return true;
    }
    $lock = Cache::lock((string) config('webblocks-updates.installer.lock_name', 'system-updates:run'), 1);
    if (! $lock->get()) {
      return true;
    }
    $lock->release();

    return false;
  }

  private function latestModification(string $directory): int
  {
    $latest = (int) filemtime($directory);
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
      // Never traverse a workspace containing external symlinks.
      if ($file->isLink()) {
        return PHP_INT_MAX;
      }
      $latest = max($latest, $file->getMTime());
    }

    return $latest;
  }

  private function temporaryFileAvailable(string $path): bool
  {
    $handle = @fopen($path, 'r');
    if ($handle === false) {
      return false;
    }
    try {
      return flock($handle, LOCK_EX | LOCK_NB);
    } finally {
      fclose($handle);
    }
  }

  private function deleteTemporaryFile(string $path): bool
  {
    $handle = fopen($path, 'r');
    if ($handle === false) {
      return false;
    }
    try {
      return flock($handle, LOCK_EX | LOCK_NB) && File::delete($path);
    } finally {
      fclose($handle);
    }
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
    return Schema::hasTable('wbcms_media') ? Media::query()->where('kind', Media::KIND_IMAGE)->lazyById(100) : collect();
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
    $measurement = 'available';

    foreach (Media::query()->where('kind', Media::KIND_IMAGE)->distinct()->pluck('disk') as $diskName) {
      try {
        $disk = Storage::disk($diskName);
        foreach ($disk->listContents('media/transforms', true) as $file) {
          if ($file->isFile()) {
            $variantFiles++;
            $variantBytes += $file->fileSize() ?? (int) $disk->size($file->path());
          }
        }
      } catch (\Throwable) {
        $measurement = 'partial';
      }
    }

    return [
      'items' => (int) $summary->items,
      'images' => (int) $summary->images,
      'original_bytes' => (int) $summary->original_bytes,
      'variant_files' => $variantFiles,
      'variant_bytes' => $variantBytes,
      'variant_measurement_status' => $measurement,
    ];
  }

  private function siteGrowthOverview(Collection $pageRevisionTotals, Collection $sharedSlotRevisionTotals): array
  {
    if (! Schema::hasTable('wbcms_sites')) {
      return [];
    }

    $pages = Schema::hasTable('wbcms_pages')
      ? DB::table('wbcms_pages')->select('site_id')->selectRaw('COUNT(*) as count')->groupBy('site_id')->get()->keyBy('site_id') : collect();
    $blocks = Schema::hasTable('wbcms_blocks') && Schema::hasTable('wbcms_pages')
      ? DB::table('wbcms_blocks')->join('wbcms_pages', 'wbcms_pages.id', '=', 'wbcms_blocks.page_id')
        ->select('wbcms_pages.site_id')->selectRaw('COUNT(*) as count')->groupBy('wbcms_pages.site_id')->get()->keyBy('site_id') : collect();
    $revisionTotals = ['pages' => $pageRevisionTotals, 'shared_slots' => $sharedSlotRevisionTotals];

    return Site::query()->orderBy('id')->get(['id', 'name', 'handle'])->map(fn (Site $site): array => [
      'id' => (int) $site->id,
      'name' => $site->name,
      'handle' => $site->handle,
      'pages' => (int) ($pages->get($site->id)?->count ?? 0),
      'blocks' => (int) ($blocks->get($site->id)?->count ?? 0),
      'page_revisions' => (int) ($revisionTotals['pages']->get($site->id)?->count ?? 0),
      'page_revision_bytes' => (int) ($revisionTotals['pages']->get($site->id)?->bytes ?? 0),
      'shared_slot_revisions' => (int) ($revisionTotals['shared_slots']->get($site->id)?->count ?? 0),
      'shared_slot_revision_bytes' => (int) ($revisionTotals['shared_slots']->get($site->id)?->bytes ?? 0),
    ])->all();
  }

  private function revisionStorageBySite(string $table): Collection
  {
    if (! Schema::hasTable($table)) {
      return collect();
    }
    $hasBytes = Schema::hasColumn($table, 'snapshot_bytes');
    $totals = DB::table($table)->select('site_id')->selectRaw('COUNT(*) as count, '.($hasBytes ? 'COALESCE(SUM(snapshot_bytes), 0)' : '0').' as bytes')
      ->groupBy('site_id')->get()->keyBy('site_id');
    $unmeasured = DB::table($table)->select(['id', 'site_id']);
    if ($hasBytes) {
      $unmeasured->whereNull('snapshot_bytes');
    }
    $unmeasured->chunkById(500, function ($rows) use ($table, $totals): void {
      $sizes = RevisionPayloadSizes::forIds($table, $rows->pluck('id'));
      foreach ($rows as $row) {
        $totals->get($row->site_id)->bytes += (int) ($sizes[$row->id] ?? 0);
      }
    });

    return $totals;
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
