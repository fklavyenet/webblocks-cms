<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunSystemBackupRestoreRequest;
use App\Http\Requests\Admin\SystemBackupUploadRequest;
use App\Models\SystemBackup;
use App\Models\SystemBackupRestore;
use App\Support\System\BackupRestoreArchiveInspector;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemBackupRestoreManager;
use App\Support\System\UploadedSystemBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SystemBackupController extends Controller
{
    public function __construct(
        private readonly SystemBackupManager $systemBackupManager,
        private readonly SystemBackupRestoreManager $systemBackupRestoreManager,
        private readonly UploadedSystemBackupManager $uploadedSystemBackupManager,
        private readonly BackupRestoreArchiveInspector $archiveInspector,
    ) {}

    public function index(): View
    {
        $tableExists = Schema::hasTable('system_backups');

        if ($tableExists) {
            $this->systemBackupManager->markStaleBackupsAsFailed();
        }

        return view('admin.system.backups.index', [
            'backups' => $tableExists
                ? SystemBackup::query()->with('triggeredBy')->latest()->paginate(20)
                : new LengthAwarePaginator([], 0, 20, 1, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]),
            'latestBackup' => $this->systemBackupManager->latest(),
            'freshness' => $this->systemBackupManager->freshnessSummary(),
            'backupTableExists' => $tableExists,
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
        return view('admin.system.backups.upload');
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

        if ($backup->isSuccessful() && filled($backup->archive_path)) {
            try {
                $inspection = $this->archiveInspector->inspect(
                    $this->systemBackupManagerPath($backup)
                );
            } catch (Throwable) {
                $inspection = null;
            }
        }

        return view('admin.system.backups.show', [
            'backup' => $backup->load('triggeredBy'),
            'restoreRuns' => $this->systemBackupRestoreManager->latestRestoresForBackup($backup),
            'inspection' => $inspection,
        ]);
    }

    public function restore(SystemBackup $backup, RunSystemBackupRestoreRequest $request): RedirectResponse
    {
        try {
            $this->systemBackupRestoreManager->restoreFromBackup($backup, $request->user()?->id);

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

    public function download(SystemBackup $backup): BinaryFileResponse
    {
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

    private function systemBackupManagerPath(SystemBackup $backup): string
    {
        return Storage::disk($backup->archive_disk)->path($backup->archive_path);
    }
}
