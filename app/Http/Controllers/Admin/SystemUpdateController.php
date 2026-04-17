<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemUpdateRun;
use App\Support\System\SystemUpdater;
use App\Support\System\UpdateChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly UpdateChecker $updateChecker,
        private readonly SystemUpdater $systemUpdater,
    ) {}

    public function index(): View
    {
        return view('admin.system.updates', [
            'updateStatus' => $this->updateChecker->status(),
            'latestRun' => SystemUpdateRun::query()->latest()->first(),
            'isMaintenanceMode' => app()->isDownForMaintenance(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_backup' => ['accepted'],
        ], [
            'confirm_backup.accepted' => 'Confirm the backup warning before running the update.',
        ]);

        try {
            $result = $this->systemUpdater->run();

            return redirect()
                ->route('admin.system.updates.index')
                ->with('status', 'Update completed successfully.')
                ->with('update_output', $result['output']);
        } catch (\Throwable $throwable) {
            return redirect()
                ->route('admin.system.updates.index')
                ->withErrors(['system_update' => $throwable->getMessage()])
                ->with('update_error_output', SystemUpdateRun::query()->latest()->value('output'));
        }
    }
}
