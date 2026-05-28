<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\BulkDeleteSystemBackupsRequest;
use WebBlocks\Cms\Http\Requests\Admin\RunSystemBackupRestoreRequest;
use WebBlocks\Cms\Http\Requests\Admin\SystemBackupUploadRequest;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\System\BackupRestoreArchiveInspector;
use WebBlocks\Cms\Support\System\SystemBackupBulkDeleter;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemBackupRestoreManager;
use WebBlocks\Cms\Support\System\UploadedSystemBackupManager;

class SystemBackupController extends Controller
{
  private const FALLBACK_PER_PAGE = 15;

  public function __construct(
    private readonly SystemBackupManager $systemBackupManager,
    private readonly SystemBackupRestoreManager $systemBackupRestoreManager,
    private readonly UploadedSystemBackupManager $uploadedSystemBackupManager,
    private readonly BackupRestoreArchiveInspector $archiveInspector,
    private readonly SystemBackupBulkDeleter $systemBackupBulkDeleter,
  ) {}

  public function index(): View
  {
    $tableExists = Schema::hasTable('system_backups');
    $perPage = AdminPagination::perPage();
    $search = trim((string) request()->string('search'));
    $type = request()->string('type')->toString();
    $status = request()->string('status')->toString();

    if (! in_array($type, [
      SystemBackup::TYPE_MANUAL,
      SystemBackup::TYPE_UPLOADED,
      SystemBackup::TYPE_RESTORE_SAFETY,
      SystemBackup::TYPE_PRE_UPDATE,
    ], true)) {
      $type = '';
    }

    if (! in_array($status, [
      SystemBackup::STATUS_RUNNING,
      SystemBackup::STATUS_COMPLETED,
      SystemBackup::STATUS_FAILED,
    ], true)) {
      $status = '';
    }

    if ($tableExists) {
      $this->systemBackupManager->markStaleBackupsAsFailed();
    }

    $totalCount = $tableExists
      ? SystemBackup::query()->count()
      : 0;

    $backups = $tableExists
      ? SystemBackup::query()
        ->with('triggeredBy')
        ->when($search !== '', function ($query) use ($search): void {
          $query->where(function ($inner) use ($search): void {
            $inner->where('archive_filename', 'like', "%{$search}%")
              ->orWhere('label', 'like', "%{$search}%")
              ->orWhere('summary', 'like', "%{$search}%")
              ->orWhere('error_message', 'like', "%{$search}%")
              ->orWhere('type', 'like', "%{$search}%")
              ->orWhere('status', 'like', "%{$search}%");
          });
        })
        ->when($type !== '', fn ($query) => $query->where('type', $type))
        ->when($status !== '', fn ($query) => $query->where('status', $status))
        ->latest()
        ->paginate($perPage)
        ->withQueryString()
      : new LengthAwarePaginator([], 0, self::FALLBACK_PER_PAGE, 1, [
        'path' => request()->url(),
        'query' => request()->query(),
      ]);

    $backupArchiveStatuses = collect($backups->items())
      ->mapWithKeys(fn (SystemBackup $backup): array => [
        $backup->id => $this->systemBackupManager->archiveResolution($backup),
      ])
      ->all();

    return view('webblocks-cms::admin.system.backups.index', [
      'backups' => $backups,
      'latestBackup' => $this->systemBackupManager->latest(),
      'freshness' => $this->systemBackupManager->freshnessSummary(),
      'backupTableExists' => $tableExists,
      'filters' => [
        'search' => $search,
        'type' => $type,
        'status' => $status,
      ],
      'totalCount' => $totalCount,
      'filteredCount' => $backups->total(),
      'backupArchiveStatuses' => $backupArchiveStatuses,
    ]);
  }

  public function store(Request $request): RedirectResponse
  {
    try {
      $backup = $this->systemBackupManager->createManualBackup($request->user()?->id);

      return redirect()
        ->route('admin.system.backups.index')
        ->with('status', $backup->summary ?? 'Backup completed successfully.');
    } catch (Throwable $throwable) {
      return redirect()
        ->route('admin.system.backups.index')
        ->withErrors(['system_backup' => $throwable->getMessage()]);
    }
  }

  public function createUpload(): View
  {
    return view('webblocks-cms::admin.system.backups.upload');
  }

  public function upload(SystemBackupUploadRequest $request): RedirectResponse
  {
    try {
      $backup = $this->uploadedSystemBackupManager->import(
        $request->file('archive'),
        $request->user()?->id,
      );

      return redirect()
        ->route('admin.system.backups.show', $backup)
        ->with('status', 'Backup archive uploaded and validated successfully.');
    } catch (Throwable $throwable) {
      return redirect()
        ->route('admin.system.backups.upload')
        ->withInput()
        ->withErrors(['system_backup' => $throwable->getMessage()]);
    }
  }

  public function show(SystemBackup $backup): View
  {
    $inspection = null;
    $archiveResolution = $this->systemBackupManager->archiveResolution($backup);

    if ($archiveResolution->isAvailable()) {
      try {
        $inspection = $this->archiveInspector->inspect($archiveResolution->absolutePath);
      } catch (Throwable) {
        $inspection = null;
      }
    }

    return view('webblocks-cms::admin.system.backups.show', [
      'backup' => $backup->load('triggeredBy'),
      'restoreRuns' => $this->resolveCompatibilityRestoreManager()->latestRestoresForBackup($backup),
      'inspection' => $inspection,
      'archiveResolution' => $archiveResolution,
    ]);
  }

  public function restore(SystemBackup $backup, RunSystemBackupRestoreRequest $request): RedirectResponse
  {
    try {
      $compatibilityBackup = $this->resolveCompatibilityBackup($backup);

      $this->resolveCompatibilityRestoreManager()->restoreFromBackup($compatibilityBackup, $request->user()?->id);

      return redirect()
        ->route('admin.system.backups.index')
        ->with('status', 'System restore completed successfully.');
    } catch (Throwable $throwable) {
      return redirect()
        ->route('admin.system.backups.show', $backup)
        ->withErrors(['system_restore' => $throwable->getMessage()]);
    }
  }

  public function destroy(Request $request, SystemBackup $backup): RedirectResponse
  {
    $forceRunning = $request->boolean('force_running');

    if ($backup->isRunning() && ! $backup->isStaleRunning() && ! $forceRunning) {
      return back()->withErrors([
        'system_backup' => 'Running backup cannot be deleted unless you explicitly confirm it is stuck.',
      ]);
    }

    try {
      $this->systemBackupManager->deleteBackupRecord($backup, $forceRunning);

      return redirect()
        ->route('admin.system.backups.index')
        ->with('status', $backup->isRunning() && ! $backup->isStaleRunning() && $forceRunning
          ? 'Stuck running backup record deleted.'
          : 'Backup deleted.');
    } catch (Throwable $throwable) {
      return redirect()
        ->route('admin.system.backups.index')
        ->withErrors(['system_backup' => $throwable->getMessage()]);
    }
  }

  public function bulkDestroy(BulkDeleteSystemBackupsRequest $request): RedirectResponse
  {
    $result = $this->systemBackupBulkDeleter->deleteSelected($request->validated('backup_ids'));

    $redirect = redirect()
      ->route('admin.system.backups.index')
      ->with($result->deletedCount() > 0 ? 'status' : 'bulk_status', $result->message());

    if ($result->hasFailures()) {
      $redirect->withErrors(['system_backup' => implode(' ', $result->failureMessages())]);
    }

    return $redirect;
  }

  public function download(SystemBackup $backup): BinaryFileResponse|RedirectResponse|Response
  {
    $resolution = $this->systemBackupManager->archiveResolution($backup);

    if ($resolution->isUnsafe()) {
      abort(403, $resolution->feedbackMessage());
    }

    if (! $resolution->isAvailable()) {
      return redirect()
        ->route('admin.system.backups.index')
        ->withErrors(['system_backup' => $resolution->feedbackMessage()]);
    }

    return $this->systemBackupManager->downloadResponse($backup);
  }

  public function destroyRestore(SystemBackup $backup, SystemBackupRestore $restore): RedirectResponse
  {
    if ((int) $restore->source_backup_id !== (int) $backup->id) {
      abort(404);
    }

    $restore->delete();

    return redirect()
      ->route('admin.system.backups.show', $backup)
      ->with('status', 'Restore history entry deleted.');
  }

  private function resolveCompatibilityBackup(SystemBackup $backup): SystemBackup
  {
    $rootBackupModel = 'App\\Models\\SystemBackup';

    if (! class_exists($rootBackupModel) || ! is_subclass_of($rootBackupModel, SystemBackup::class)) {
      return $backup;
    }

    $compatibilityBackup = $rootBackupModel::query()->find($backup->getKey());

    return $compatibilityBackup instanceof SystemBackup ? $compatibilityBackup : $backup;
  }

  private function resolveCompatibilityRestoreManager(): SystemBackupRestoreManager
  {
    $rootRestoreManager = 'App\\Support\\System\\SystemBackupRestoreManager';

    if (! class_exists($rootRestoreManager) || ! is_subclass_of($rootRestoreManager, SystemBackupRestoreManager::class)) {
      return $this->systemBackupRestoreManager;
    }

    $compatibilityManager = app($rootRestoreManager);

    return $compatibilityManager instanceof SystemBackupRestoreManager
      ? $compatibilityManager
      : $this->systemBackupRestoreManager;
  }
}
