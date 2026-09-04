<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\RunSystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\Database\CmsTableCompatibilityViews;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdater;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\WebBlocks;

class SystemUpdateController extends Controller
{
  public function __construct(
    private readonly SystemUpdateInspector $systemUpdateInspector,
    private readonly SystemUpdater $systemUpdater,
    private readonly SystemUpdateRunRetention $runRetention,
    private readonly AdminUpdateIndicator $updateIndicator,
    private readonly CmsTableCompatibilityViews $compatibilityViews,
  ) {}

  public function index(Request $request): View
  {
    $this->compatibilityViews->dropLegacyUpdateBridgeViews();
    $this->reconcileVerifiedPostApplyFailure();

    $report = $this->systemUpdateInspector->report();
    $checkedAt = session('system_updates_checked_at');

    return view('webblocks-cms::admin.system.updates', [
      'report' => $report,
      'runs' => $this->runRetention->retainedRuns(),
      'preflight' => $report['checks'] ?? [],
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

  public function indicator(): JsonResponse
  {
    $payload = $this->updateIndicator->payload();
    $payload['url'] = route('admin.system.updates.index');

    return response()->json($payload);
  }

  private function reconcileVerifiedPostApplyFailure(): void
  {
    if (! $this->runRetention->schemaReady()) {
      return;
    }

    $run = SystemUpdateRun::query()->latest()->first();

    if (! $run || $run->status !== SystemUpdateRun::STATUS_FAILED) {
      return;
    }

    $currentVersion = WebBlocks::version();

    if ((string) $run->to_version !== $currentVersion) {
      return;
    }

    $output = (string) $run->output;

    if (! str_contains($output, 'Post-update version verified as '.$currentVersion.' from canonical WebBlocks version source.')) {
      return;
    }

    $marker = 'Post-apply reconciliation: active CMS code still reports '.$currentVersion.'; the previous failure was recorded after the target version had been verified.';
    $lines = trim($output) === '' ? [] : [$output];

    if (! str_contains($output, $marker)) {
      $lines[] = $marker;
    }

    $run->forceFill([
      'status' => SystemUpdateRun::STATUS_SUCCESS_WITH_WARNINGS,
      'summary' => 'Updated to '.$currentVersion.'; a post-apply finalization warning was reconciled.',
      'output' => implode(PHP_EOL, $lines),
      'warning_count' => max(1, (int) $run->warning_count),
      'finished_at' => $run->finished_at ?? now(),
    ])->save();

    $this->forgetSystemUpdateErrorFlash();

    if (! session()->has('status')) {
      session()->flash('status', 'The update reached '.$currentVersion.'; a post-apply finalization warning was reconciled.');
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
