<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemUpdateRun;
use App\Support\System\SystemBackupManager;
use App\Support\System\SystemUpdateInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly SystemUpdateInspector $systemUpdateInspector,
        private readonly SystemBackupManager $systemBackupManager,
    ) {}

    public function index(): View
    {
        $report = $this->systemUpdateInspector->report();
        $checkedAt = session('system_updates_checked_at');

        return view('admin.system.updates', [
            'report' => $report,
            'backupFreshness' => $this->systemBackupManager->freshnessSummary(),
            'latestUpdateRun' => $this->latestUpdateRun(),
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
            ->with('status', $this->statusMessage($report))
            ->with('system_updates_checked_at', ($report['checked_at'] ?? now())->toIso8601String());
    }

    private function latestUpdateRun(): ?SystemUpdateRun
    {
        if (! Schema::hasTable('system_update_runs')) {
            return null;
        }

        return SystemUpdateRun::query()->with('triggeredBy')->latest()->first();
    }

    private function statusMessage(array $report): string
    {
        $state = (string) ($report['version']['state'] ?? 'unknown');
        $latestVersion = $report['version']['latest_version'] ?? null;

        return match ($state) {
            'update_available' => is_string($latestVersion) && $latestVersion !== ''
                ? 'Update '.$latestVersion.' is available.'
                : 'A new update is available.',
            'up_to_date' => 'System is already up to date.',
            'incompatible' => is_string($latestVersion) && $latestVersion !== ''
                ? 'Update '.$latestVersion.' is available, but this install is not compatible yet.'
                : 'An update is available, but this install is not compatible yet.',
            'no_releases' => 'No published releases are available for this channel.',
            default => 'Update check failed. Review the details below.',
        };
    }
}
