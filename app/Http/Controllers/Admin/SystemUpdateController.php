<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunSystemUpdateRequest;
use App\Models\SystemBackup;
use App\Models\SystemUpdateRun;
use App\Support\System\SystemUpdateInspector;
use App\Support\System\Updates\SystemUpdater;
use App\Support\System\Updates\UpdateException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly SystemUpdateInspector $systemUpdateInspector,
        private readonly SystemUpdater $systemUpdater,
    ) {}

    public function index(): View
    {
        $report = $this->systemUpdateInspector->report();
        $checkedAt = session('system_updates_checked_at');
        $pendingUpdate = $this->pendingUpdate();

        return view('admin.system.updates', [
            'report' => $report,
            'latestUpdateRun' => $this->latestUpdateRun(),
            'pendingUpdate' => $pendingUpdate,
            'pendingBackup' => $pendingUpdate ? SystemBackup::query()->find($pendingUpdate['backup_id'] ?? null) : null,
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

    public function store(RunSystemUpdateRequest $request): RedirectResponse
    {
        try {
            $downloadBackup = $request->boolean('download_pre_update_backup');

            if ($downloadBackup) {
                $pending = $this->systemUpdater->prepareForUpdate($request->user(), true);
                $this->storePendingUpdate($pending);

                return redirect()
                    ->route('admin.system.updates.index')
                    ->with('status', 'Pre-update backup created. Download it, then continue when you are ready to install '.$pending['to_version'].'.')
                    ->with('system_updates_checked_at', now()->toIso8601String());
            }

            $result = $this->systemUpdater->run($request->user());

            return redirect()
                ->route('admin.system.updates.index')
                ->with('status', $result->summary)
                ->with('system_updates_checked_at', $result->finishedAt->toIso8601String());
        } catch (UpdateException $exception) {
            return redirect()
                ->route('admin.system.updates.index')
                ->withErrors(['system_update' => $exception->userMessage()])
                ->withInput();
        }
    }

    public function continue(Request $request): RedirectResponse
    {
        $pending = $this->pendingUpdate();

        if (! is_array($pending)) {
            return redirect()
                ->route('admin.system.updates.index')
                ->withErrors(['system_update' => 'The pending update is no longer available. Start the update again.']);
        }

        try {
            $result = $this->systemUpdater->continuePreparedUpdate($request->user(), $pending);
            $this->clearPendingUpdate();

            return redirect()
                ->route('admin.system.updates.index')
                ->with('status', $result->summary)
                ->with('system_updates_checked_at', $result->finishedAt->toIso8601String());
        } catch (UpdateException $exception) {
            $this->clearPendingUpdate();

            return redirect()
                ->route('admin.system.updates.index')
                ->withErrors(['system_update' => $exception->userMessage()]);
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        $pending = $this->pendingUpdate();

        if (is_array($pending)) {
            $this->systemUpdater->cancelPreparedUpdate($request->user(), $pending);
            $this->clearPendingUpdate();
        }

        return redirect()
            ->route('admin.system.updates.index')
            ->with('status', 'Pending update cancelled. The pre-update backup was kept.');
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

    private function pendingUpdate(): ?array
    {
        $pending = Cache::get($this->pendingCacheKey());

        return is_array($pending) ? $pending : null;
    }

    private function storePendingUpdate(array $pending): void
    {
        Cache::put($this->pendingCacheKey(), $pending, now()->addSeconds((int) config('webblocks-updates.pending_cache_ttl_seconds', 3600)));
    }

    private function clearPendingUpdate(): void
    {
        Cache::forget($this->pendingCacheKey());
    }

    private function pendingCacheKey(): string
    {
        return 'system-updates:pending';
    }
}
