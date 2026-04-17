<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemUpdateRun;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemUpdateInspector;
use App\Support\System\SystemUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly SystemBackupManager $systemBackupManager,
        private readonly SystemUpdateInspector $systemUpdateInspector,
        private readonly SystemUpdater $systemUpdater,
    ) {}

    public function index(): View
    {
        $report = $this->systemUpdateInspector->report();
        $checkedAt = session('system_updates_checked_at');

        return view('admin.system.updates', [
            'report' => $report,
            'latestRun' => $this->latestRun(),
            'backupFreshness' => $this->systemBackupManager->freshnessSummary(),
            'checkedAt' => is_string($checkedAt)
                ? now()->parse($checkedAt)
                : ($report['checked_at'] ?? now()),
        ]);
    }

    public function check(): RedirectResponse
    {
        $report = $this->systemUpdateInspector->refreshReport();

        return redirect()
            ->route('admin.system.updates.index')
            ->with('status', 'Update status refreshed.')
            ->with('system_updates_checked_at', ($report['checked_at'] ?? now())->toIso8601String());
    }

    public function run(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_backup' => ['accepted'],
        ], [
            'confirm_backup.accepted' => 'Confirm the backup warning before running the update.',
        ]);

        try {
            $result = $this->systemUpdater->run($request->user()?->id);

            return redirect()
                ->route('admin.system.updates.index')
                ->with('status', $result['summary'] ?? 'Update completed successfully.')
                ->with('update_output', $result['output']);
        } catch (\Throwable $throwable) {
            return redirect()
                ->route('admin.system.updates.index')
                ->withErrors(['system_update' => $throwable->getMessage()])
                ->with('update_error_output', $this->latestRun()?->output);
        }
    }

    private function latestRun(): ?SystemUpdateRun
    {
        if (! Schema::hasTable('system_update_runs')) {
            return null;
        }

        return SystemUpdateRun::query()->with('triggeredBy')->latest()->first();
    }
}
