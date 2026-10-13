<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\System\StartAdminSystemUpdate;
use WebBlocks\Cms\Http\Requests\Admin\RunSystemUpdateRequest;
use WebBlocks\Cms\Support\Database\CmsTableCompatibilityViews;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
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
      'runs' => $this->displayRuns(),
      'preflight' => $report['checks'] ?? [],
      'checkedAt' => is_string($checkedAt)
        ? now()->parse($checkedAt)
        : ($report['checked_at'] ?? now()),
    ]);
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
      return response()->json(['error' => $exception->userMessage()], 409);
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
    $request->validate(['operation' => ['nullable', 'uuid']]);
    $progress = app(CmsUpdateProgress::class)->payload($request->query('operation'));
    $payload = $request->has('operation') ? [] : app(AdminUpdateIndicator::class)->payload();
    $payload['url'] = route('admin.system.updates.index');
    $payload['progress'] = $progress;

    return response()->json($payload)->header('Cache-Control', 'no-store');
  }

  private function displayRuns(): Collection
  {
    $progress = app(CmsUpdateProgress::class)->payload();

    return app(SystemUpdateRunRetention::class)->retainedRuns()->map(function ($run) use ($progress) {
      if ($run->status === 'running' && (! $progress['running'] || $progress['run_id'] !== $run->id)) {
        $run->status = 'interrupted';
      }

      return $run;
    });
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
