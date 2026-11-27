<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\System\DismissResolvedSystemUpdate;
use WebBlocks\Cms\Actions\System\ReconcileStoppedSystemUpdate;
use WebBlocks\Cms\Actions\System\RepairUpdateChainSchema;
use WebBlocks\Cms\Actions\System\ResolvePreApplySystemUpdate;
use WebBlocks\Cms\Actions\System\StartAdminSystemUpdate;
use WebBlocks\Cms\Http\Requests\Admin\DismissSystemUpdateRequest;
use WebBlocks\Cms\Http\Requests\Admin\ReconcileSystemUpdateRequest;
use WebBlocks\Cms\Http\Requests\Admin\RunSystemUpdateRequest;
use WebBlocks\Cms\Policies\SystemUpdateRecoveryPolicy;
use WebBlocks\Cms\Support\Database\CmsTableCompatibilityViews;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateAttention;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunReconciler;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\System\Updates\UpdateException;

class SystemUpdateController extends Controller
{
  public function __construct(private readonly CmsPublisherClientConfigurator $publisherClient) {}

  public function index(Request $request): View
  {
    $this->publisherClient->configure();
    app(CmsTableCompatibilityViews::class)->dropLegacyUpdateBridgeViews();
    $this->reconcileVerifiedPostApplyFailure();

    $report = app(SystemUpdateInspector::class)->report();
    $checkedAt = session('system_updates_checked_at');

    return view('webblocks-cms::admin.system.updates', [
      'report' => $report,
      'schedulerNeedsSetup' => app(SchedulerHealth::class)->snapshot()['scheduler_status'] !== 'healthy',
      'runs' => $this->displayRuns(),
      'updateProgress' => app(CmsUpdateProgress::class)->activePayload(),
      'completedVersion' => $this->completedVersion($request->query('completed_operation'), $report),
      'unfinishedUpdates' => app(SystemUpdateAttention::class)->rows(),
      'canReconcileUpdates' => app(SystemUpdateRecoveryPolicy::class)->reconcile($request->user()),
      'preflight' => $report['checks'] ?? [],
      'canRepairChainSchema' => ($report['chain_schema'] ?? null) === 'missing' && app(RepairUpdateChainSchema::class)->available(),
      'checkedAt' => is_string($checkedAt)
        ? now()->parse($checkedAt)
        : ($report['checked_at'] ?? now()),
    ]);
  }

  public function repairSchema(RunSystemUpdateRequest $request, RepairUpdateChainSchema $repair): RedirectResponse
  {
    $redirect = redirect()->route('admin.system.updates.index');
    try {
      $backup = $repair->execute((int) $request->user()->getKey());

      return $redirect->with('status', $backup
        ? __('webblocks-cms::update_schema.repaired', ['backup' => $backup->id])
        : __('webblocks-cms::update_schema.ready'));
    } catch (UpdateException $exception) {
      return $redirect->withErrors(['update_schema' => $exception->userMessage()]);
    }
  }

  public function check(): RedirectResponse
  {
    $this->publisherClient->configure();
    $report = app(SystemUpdateInspector::class)->refreshReport();
    app(AdminUpdateIndicator::class)->storeVersionStatus($report['version'] ?? []);
    app(SystemUpdateRunRetention::class)->prune();

    return redirect()
      ->route('admin.system.updates.index')
      ->with('status', $this->statusMessage($report))
      ->with('system_updates_checked_at', ($report['checked_at'] ?? now())->toIso8601String());
  }

  public function store(RunSystemUpdateRequest $request): RedirectResponse|JsonResponse
  {
    $this->publisherClient->configure();
    try {
      $operation = app(StartAdminSystemUpdate::class)->execute((int) $request->user()->getKey());
      if ($request->expectsJson()) {
        return response()->json(['operation_id' => $operation->id], 202)->header('Cache-Control', 'no-store');
      }

      return redirect()->route('admin.system.updates.index')->with('status', __('webblocks-cms::admin.updates.waiting_body'));
    } catch (UpdateException $exception) {
      if ($request->expectsJson()) {
        return response()->json([
          'message' => $exception->userMessage(),
          'error' => $exception->userMessage(),
        ], 409);
      }

      return redirect()
        ->route('admin.system.updates.index')
        ->withErrors(['system_update' => $exception->userMessage()])
        ->withInput();
    }
  }

  public function indicator(Request $request): JsonResponse
  {
    $this->publisherClient->configure();
    $request->validate(['operation' => ['nullable', 'uuid'], 'active' => ['sometimes', 'boolean']]);
    $progress = app(CmsUpdateProgress::class);
    $activeOnly = ! $request->filled('operation') && $request->boolean('active');
    $payload = $activeOnly || $request->has('operation') ? [] : app(AdminUpdateIndicator::class)->payload();
    $payload['url'] = route('admin.system.updates.index');
    $payload['progress'] = $activeOnly ? $progress->activePayload() : $progress->payload($request->query('operation'));

    return response()->json($payload)->header('Cache-Control', 'no-store');
  }

  public function reconcile(ReconcileSystemUpdateRequest $request): RedirectResponse|JsonResponse
  {
    try {
      $input = $request->validated();
      $count = app(ReconcileStoppedSystemUpdate::class)->execute($input['record_type'], $input['record_id'], (int) $request->user()->getKey(), $input['reason']);
      $message = __('webblocks-cms::update_recovery.reconciled', ['count' => $count]);

      return $request->expectsJson()
        ? response()->json(['message' => $message, 'count' => $count])->header('Cache-Control', 'no-store')
        : redirect()->route('admin.system.updates.index')->with('status', $message);
    } catch (UpdateException $exception) {
      return $request->expectsJson()
        ? response()->json(['message' => $exception->userMessage(), 'error' => $exception->userMessage()], 409)->header('Cache-Control', 'no-store')
        : redirect()->route('admin.system.updates.index')->withErrors(['system_update' => $exception->userMessage()])->withInput();
    }
  }

  public function resolvePreApply(ReconcileSystemUpdateRequest $request): RedirectResponse
  {
    $input = $request->validated();
    try {
      app(ResolvePreApplySystemUpdate::class)->execute($input['record_type'], $input['record_id'], (int) $request->user()->getKey(), $input['reason']);

      return redirect()->route('admin.system.updates.index')->with('status', __('webblocks-cms::update_recovery.resolved'));
    } catch (\Throwable $exception) {
      report($exception);

      return redirect()->route('admin.system.updates.index')->withErrors(['system_update' => __('webblocks-cms::update_recovery.resolve_refused')]);
    }
  }

  public function dismiss(DismissSystemUpdateRequest $request, DismissResolvedSystemUpdate $dismiss): RedirectResponse
  {
    $redirect = redirect()->route('admin.system.updates.index');
    $input = $request->validated();

    try {
      $dismiss->execute($input['record_type'], $input['record_id'], (int) $request->user()->getKey());

      return $redirect->with('status', __('webblocks-cms::update_recovery.dismissed'));
    } catch (UpdateException $exception) {
      return $redirect->withErrors(['system_update' => $exception->userMessage()]);
    }
  }

  private function displayRuns(): Collection
  {
    return app(SystemUpdateRunRetention::class)->retainedRuns();
  }

  private function completedVersion(mixed $operation, array $report): ?string
  {
    if (! is_string($operation) || ! Str::isUuid($operation)) {
      return null;
    }

    $progress = app(CmsUpdateProgress::class)->payload($operation);
    $target = $progress['target_version'] ?? null;

    return ($progress['operation_id'] ?? null) === $operation
      && ($progress['terminal'] ?? false) === true
      && ($progress['state'] ?? null) === 'success'
      && is_string($target) && $target !== ''
      && $target === ($progress['installed_version'] ?? null)
      && $target === ($report['installed_version'] ?? null)
        ? $target : null;
  }

  private function reconcileVerifiedPostApplyFailure(): void
  {
    $run = app(SystemUpdateRunReconciler::class)->reconcile();

    if ($run !== null) {
      $this->forgetSystemUpdateErrorFlash();

      if (! session()->has('status')) {
        session()->flash('status', $run->summary);
      }
    }
  }

  private function forgetSystemUpdateErrorFlash(): void
  {
    $errors = session('errors');

    if (! $errors instanceof ViewErrorBag || ! $errors->hasBag('default')) {
      return;
    }

    $messages = $errors->getBag('default')->getMessages();
    unset($messages['system_update']);

    if ($messages === []) {
      session()->forget('errors');

      return;
    }

    $replacement = new ViewErrorBag;

    foreach ($errors->getBags() as $name => $bag) {
      $replacement->put(
        $name,
        $name === 'default' ? new MessageBag($messages) : $bag
      );
    }

    session()->put('errors', $replacement);
  }

  private function statusMessage(array $report): string
  {
    $state = (string) ($report['version']['state'] ?? 'unknown');
    $latestVersion = $report['version']['latest_version'] ?? null;

    return match ($state) {
      'update_available' => is_string($latestVersion) && $latestVersion !== ''
        ? __('webblocks-cms::admin-feedback.updates.available_version', ['version' => $latestVersion])
        : __('webblocks-cms::admin-feedback.updates.available'),
      'up_to_date' => __('webblocks-cms::admin-feedback.updates.current'),
      'incompatible' => is_string($latestVersion) && $latestVersion !== ''
        ? __('webblocks-cms::admin-feedback.updates.incompatible_version', ['version' => $latestVersion])
        : __('webblocks-cms::admin-feedback.updates.incompatible'),
      'no_releases' => __('webblocks-cms::admin-feedback.updates.no_releases'),
      default => __('webblocks-cms::admin-feedback.updates.check_failed'),
    };
  }
}
