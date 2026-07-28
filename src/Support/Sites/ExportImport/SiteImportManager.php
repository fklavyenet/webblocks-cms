<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Sites\SiteDeleteResult;
use WebBlocks\Cms\Support\Sites\SiteDeleteService;
use ZipArchive;

class SiteImportManager
{
  public const ARCHIVE_DISK = SiteTransferDisk::DISK;

  public function __construct(
    private readonly ImportArchiveInspector $archiveInspector,
    private readonly ImportDataMapper $dataMapper,
    private readonly SiteTransferPathGuard $pathGuard,
    private readonly SiteTransferDisk $siteTransferDisk,
    private readonly SiteDeleteService $siteDeleteService,
  ) {}

  public function inspectUpload(UploadedFile $file, ?int $userId = null): SiteImport
  {
    $import = SiteImport::query()->create([
      'user_id' => $userId,
      'status' => SiteImport::STATUS_RUNNING,
      'source_archive_name' => $file->getClientOriginalName(),
      'archive_disk' => self::ARCHIVE_DISK,
    ]);

    $output = [];

    try {
      $originalName = trim($file->getClientOriginalName());
      $archiveName = Str::lower(Str::random(8)).'-'.($originalName !== '' ? $originalName : 'import-package.zip');
      $disk = $this->siteTransferDisk->ensureReady();
      $archivePath = $file->storeAs(now()->format('imports/Y/m/d'), $archiveName, self::ARCHIVE_DISK);
      $inspection = $this->archiveInspector->inspect($disk->path($archivePath));

      $output[] = 'Import package validated successfully.';

      $import->forceFill([
        'status' => SiteImport::STATUS_VALIDATED,
        'archive_path' => $archivePath,
        'manifest_json' => $inspection->manifest,
        'summary_json' => $inspection->counts(),
        'output_log' => implode(PHP_EOL, $output),
        'failure_message' => null,
      ])->save();

      return $import->fresh(['targetSite', 'user']);
    } catch (Throwable $throwable) {
      $output[] = 'Import validation failed: '.$throwable->getMessage();
      $import->forceFill([
        'status' => SiteImport::STATUS_FAILED,
        'output_log' => implode(PHP_EOL, $output),
        'failure_message' => $throwable->getMessage(),
      ])->save();

      throw new RuntimeException($throwable->getMessage(), previous: $throwable);
    }
  }

  /**
   * Run the import to completion, one step at a time.
   *
   * The loop is the whole implementation: everything a caller used to get from
   * a single transaction it now gets from a sequence of committed steps, so a
   * CLI or API caller keeps the same contract and picks up resumability with
   * it.
   */
  public function import(SiteImport $siteImport, SiteImportOptions $options): SiteImport
  {
    do {
      // No request to hold open here, so take a long budget per step.
      $result = $this->step($siteImport, $options, 30.0);

      if ($result->isFailed()) {
        throw new RuntimeException((string) $result->failureMessage);
      }
    } while (! $result->isFinished());

    return $siteImport->fresh(['targetSite', 'user']);
  }

  /**
   * Run one bounded step and report where the import got to.
   *
   * Safe to call on a finished import: the mapper reports the stored progress
   * without touching anything, so a modal that polls one time too many gets a
   * finished result rather than a second import.
   */
  public function step(SiteImport $siteImport, SiteImportOptions $options, float $budgetSeconds = 5.0): SiteImportStepResult
  {
    if (! $siteImport->archive_path) {
      throw new RuntimeException('Import package archive is missing.');
    }

    $this->pathGuard->assertSafeRelativePath($siteImport->archive_path, 'Import archive path');
    $disk = $siteImport->archive_disk === self::ARCHIVE_DISK || ! $siteImport->archive_disk
      ? $this->siteTransferDisk->ensureReady()
      : Storage::disk($siteImport->archive_disk);
    $archivePath = $disk->path($siteImport->archive_path);
    $archive = new ZipArchive;

    if ($archive->open($archivePath) !== true) {
      throw new RuntimeException('Import package could not be reopened.');
    }

    try {
      $result = $this->dataMapper->step(
        $siteImport,
        $options,
        $archive,
        $this->loadPayload($archive),
        $budgetSeconds
      );

      if ($result->isFinished()) {
        $inspection = $this->archiveInspector->inspect($archivePath);
        $siteImport->forceFill([
          'manifest_json' => $inspection->manifest,
          'summary_json' => $inspection->counts(),
        ])->save();
      }

      return $result;
    } finally {
      $archive->close();
    }
  }

  /**
   * Options an interrupted import must be resumed with.
   *
   * A resume arrives without the form that started the run, so the naming
   * choices the operator made are read back from what the first step already
   * wrote. Passing different options mid-import would rename the site under
   * rows that already reference it.
   */
  public function resumeOptions(SiteImport $siteImport): SiteImportOptions
  {
    $manifest = $siteImport->manifest_json ?? [];

    return SiteImportOptions::fromArray([
      'site_name' => $siteImport->targetSite?->name ?? ($manifest['source_site_name'] ?? 'Imported Site'),
      'site_handle' => $siteImport->imported_site_handle ?? ($manifest['source_site_handle'] ?? null),
      'site_domain' => $siteImport->imported_site_domain,
    ]);
  }

  /**
   * Throw away what an unfinished import wrote, keeping the package.
   *
   * The counterpart to resuming. Chunked importing means a run that stops
   * halfway leaves real rows and real files behind, so the operator needs a
   * way to say "not this one" that actually cleans up — the old all-or-nothing
   * import got this from its transaction rollback for free.
   *
   * Site rows go through SiteDeleteService so this shares the one audited
   * deletion path, including its blockers. Media and copied files are removed
   * separately because they are not site-owned: the import created them at
   * install scope, and nothing else would ever collect them.
   */
  public function discardImportedSite(SiteImport $siteImport): SiteDeleteResult|bool
  {
    $state = $siteImport->resume_state ?? [];
    $result = null;

    if ($siteImport->target_site_id && $site = Site::query()->find($siteImport->target_site_id)) {
      $result = $this->siteDeleteService->delete($site);

      if (! $result->deleted) {
        return $result;
      }
    }

    foreach (array_values($state['maps']['asset'] ?? []) as $mediaId) {
      Media::query()->whereKey($mediaId)->delete();
    }

    foreach (($state['copied_files'] ?? []) as $copied) {
      [$disk, $path] = array_pad((array) $copied, 2, null);

      if (! $path) {
        continue;
      }

      if ($disk === 'public-root') {
        File::delete(public_path($path));

        continue;
      }

      Storage::disk((string) $disk)->delete($path);
    }

    // Back to the state the package was in right after it was inspected, so
    // Run import starts a clean attempt rather than resuming into the rows
    // that were just deleted.
    $siteImport->forceFill([
      'status' => SiteImport::STATUS_VALIDATED,
      'resume_phase' => null,
      'resume_offset' => 0,
      'resume_state' => null,
      'progress_done' => 0,
      'progress_total' => 0,
      'heartbeat_at' => null,
      'target_site_id' => null,
      'imported_site_handle' => null,
      'imported_site_domain' => null,
      'failure_message' => null,
      'output_log' => null,
    ])->save();

    return $result ?? true;
  }

  public function delete(SiteImport $siteImport): void
  {
    if ($siteImport->archive_path) {
      $this->pathGuard->assertSafeRelativePath($siteImport->archive_path, 'Import archive path');
      $disk = $siteImport->archive_disk === self::ARCHIVE_DISK || ! $siteImport->archive_disk
        ? $this->siteTransferDisk->ensureReady()
        : Storage::disk($siteImport->archive_disk);
      $disk->delete($siteImport->archive_path);
    }

    $siteImport->delete();
  }

  private function loadPayload(ZipArchive $archive): array
  {
    $payload = [];

    foreach (SiteTransferPackage::REQUIRED_DATA_FILES as $file) {
      $payload[pathinfo($file, PATHINFO_FILENAME)] = $this->archiveInspector->decodeJsonFile($archive, $file);
    }

    foreach (SiteTransferPackage::OPTIONAL_DATA_FILES as $file) {
      $key = pathinfo($file, PATHINFO_FILENAME);
      $hasFile = $archive->locateName($file) !== false;

      if (! $hasFile) {
        foreach (SiteTransferPackage::DATA_FILE_ALIASES[$file] ?? [] as $alias) {
          if ($archive->locateName($alias) !== false) {
            $hasFile = true;
            break;
          }
        }
      }

      $payload[$key] = ! $hasFile
        ? []
        : $this->archiveInspector->decodeJsonFile($archive, $file);
    }

    return $payload;
  }
}
