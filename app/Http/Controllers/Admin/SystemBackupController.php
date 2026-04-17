<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemBackup;
use App\Support\System\SystemBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SystemBackupController extends Controller
{
    public function __construct(private readonly SystemBackupManager $systemBackupManager) {}

    public function index(): View
    {
        return view('admin.system.backups.index', [
            'backups' => SystemBackup::query()->with('triggeredBy')->latest()->paginate(20),
            'latestBackup' => $this->systemBackupManager->latest(),
            'freshness' => $this->systemBackupManager->freshnessSummary(),
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
        ]);
    }

    public function download(SystemBackup $backup): BinaryFileResponse
    {
        return $this->systemBackupManager->downloadResponse($backup);
    }
}
