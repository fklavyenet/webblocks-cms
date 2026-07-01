<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Http\Requests\Admin\RunSystemUpdateRequest;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateSupportReport;
use WebBlocks\Cms\Support\System\Updates\UpdateException;

class SystemUpdateController extends Controller
{
  public function __construct(
    private readonly SystemUpdateInspector $systemUpdateInspector,
    private readonly SystemUpdater $systemUpdater,
    private readonly SystemUpdateRunRetention $runRetention,
    private readonly SystemUpdateSupportReport $supportReport,
    private readonly AdminUpdateIndicator $updateIndicator,
  ) {}

  public function index(Request $request): View
  {
    $report = $this->systemUpdateInspector->report();
    $checkedAt = session('system_updates_checked_at');
    $pendingUpdate = $this->pendingUpdate();
    $latestUpdateRun = $this->latestUpdateRun();
    $mainLatestUpdateRun = $this->mainLatestUpdateRun($latestUpdateRun, $report);

    return view('webblocks-cms::admin.system.updates', [
      'report' => $report,
      'latestUpdateRun' => $mainLatestUpdateRun,
      'retainedUpdateRuns' => $this->runRetention->retainedRuns(),
      'historicalUpdateRuns' => $latestUpdateRun && $mainLatestUpdateRun === null
        ? collect([$latestUpdateRun])
        : collect(),
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
    $this->updateIndicator->storeVersionStatus($report['version'] ?? []);
    $this->runRetention->prune();

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
      $this->updateIndicator->clear();
      $this->runRetention->prune();

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
      $this->updateIndicator->clear();
      $this->runRetention->prune();

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
      $this->runRetention->prune();
    }

    return redirect()
      ->route('admin.system.updates.index')
      ->with('status', 'Pending update cancelled. The pre-update backup was kept.');
  }

  public function supportReport(): Response
  {
    $payload = json_encode($this->supportReport->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $filename = 'webblocks-update-support-report-'.Str::slug(now()->format('Y-m-d H-i-s')).'.json';

    return response($payload ?: '{}', 200, [
      'Content-Type' => 'application/json',
      'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    ]);
  }

  public function indicator(): JsonResponse
  {
    $payload = $this->updateIndicator->payload();
    $payload['url'] = route('admin.system.updates.index');

    return response()->json($payload);
  }

  private function latestUpdateRun(): ?SystemUpdateRun
  {
    if (! Schema::hasTable('system_update_runs')) {
      return null;
    }

    return SystemUpdateRun::query()->with('triggeredBy')->latest()->first();
  }

  private function mainLatestUpdateRun(?SystemUpdateRun $run, array $report): ?SystemUpdateRun
  {
    if (! $run || $run->status !== SystemUpdateRun::STATUS_FAILED) {
      return $run;
    }

    $installedVersion = (string) ($report['installed_version'] ?? $report['version']['installed_version'] ?? '');
    $latestVersion = $report['version']['latest_version'] ?? null;
    $hasCurrentActionableTarget = ($report['auto_update']['allowed'] ?? false) === true
      && is_string($latestVersion)
      && $latestVersion !== '';

    if (is_string($run->to_version) && $run->to_version !== '' && $installedVersion !== '' && version_compare($run->to_version, $installedVersion, '<=')) {
      return null;
    }

    if (! $hasCurrentActionableTarget) {
      return null;
    }

    if ((string) $run->to_version !== (string) $latestVersion) {
      return null;
    }

    return $run;
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

    if (! is_array($pending)) {
      $pending = session($this->pendingSessionKey());
    }

    return is_array($pending) ? $pending : null;
  }

  private function storePendingUpdate(array $pending): void
  {
    Cache::put($this->pendingCacheKey(), $pending, now()->addSeconds((int) config('webblocks-updates.pending_cache_ttl_seconds', 3600)));
    session([$this->pendingSessionKey() => $pending]);
  }

  private function clearPendingUpdate(): void
  {
    Cache::forget($this->pendingCacheKey());
    session()->forget($this->pendingSessionKey());
  }

  private function pendingCacheKey(): string
  {
    return 'system-updates:pending';
  }

  private function pendingSessionKey(): string
  {
    return 'system_updates.pending';
  }
}
