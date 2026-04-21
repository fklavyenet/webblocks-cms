<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunSystemBackupRestoreRequest;
use App\Models\SystemBackup;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemBackupRestoreManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SystemBackupController extends Controller
{
    public function __construct(
        private readonly SystemBackupManager $systemBackupManager,
        private readonly SystemBackupRestoreManager $systemBackupRestoreManager,
    ) {}

    public function index(): View
    {
        $tableExists = Schema::hasTable('system_backups');

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

    public function show(SystemBackup $backup): View
    {
        return view('admin.system.backups.show', [
            'backup' => $backup->load('triggeredBy'),
            'restoreRuns' => $this->systemBackupRestoreManager->latestRestoresForBackup($backup),
        ]);
    }

    public function restore(SystemBackup $backup, RunSystemBackupRestoreRequest $request): RedirectResponse
    {
        try {
            $result = $this->systemBackupRestoreManager->restoreFromBackup($backup, $request->user()?->id);

            return redirect()
                ->route('admin.system.backups.show', $backup)
                ->with('status', $result->summary().' Pre-restore safety backup: #'.$result->safetyBackup?->id.' '.$result->safetyBackup?->archive_filename);
        } catch (Throwable $throwable) {
            return redirect()
                ->route('admin.system.backups.show', $backup)
                ->withErrors(['system_restore' => $throwable->getMessage()]);
        }
    }

    public function destroy(SystemBackup $backup): RedirectResponse
    {
        try {
            $this->systemBackupManager->deleteBackupRecord($backup);

            return redirect()
                ->route('admin.system.backups.index')
                ->with('status', 'Backup record deleted.');
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
}
