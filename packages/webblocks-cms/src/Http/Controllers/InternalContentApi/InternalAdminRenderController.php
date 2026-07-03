<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\WebBlocks;

class InternalAdminRenderController extends Controller
{
  public function __construct(
    private readonly SystemUpdateInspector $systemUpdateInspector,
    private readonly SystemUpdateRunRetention $runRetention,
  ) {}

  public function systemUpdates(Request $request): JsonResponse|Response
  {
    $user = $this->renderUser($request);

    if (! $user || $user->can('access-system') !== true) {
      return response()->json([
        'ok' => false,
        'code' => 'admin_render_forbidden',
        'message' => 'The API token creator cannot render system admin snapshots.',
      ], 403);
    }

    Auth::guard()->setUser($user);

    $report = $this->systemUpdateInspector->report();
    $pendingUpdate = $this->pendingUpdate();
    $latestUpdateRun = $this->latestUpdateRun();
    $mainLatestUpdateRun = $this->mainLatestUpdateRun($latestUpdateRun, $report);
    $checkedAt = $report['checked_at'] ?? now();
    $html = view('webblocks-cms::admin.system.updates', [
      'report' => $report,
      'latestUpdateRun' => $mainLatestUpdateRun,
      'retainedUpdateRuns' => $this->runRetention->retainedRuns(),
      'historicalUpdateRuns' => $latestUpdateRun && $mainLatestUpdateRun === null
        ? collect([$latestUpdateRun])
        : collect(),
      'pendingUpdate' => $pendingUpdate,
      'pendingBackup' => $pendingUpdate ? SystemBackup::query()->find($pendingUpdate['backup_id'] ?? null) : null,
      'checkedAt' => $checkedAt,
    ])->render();

    if ($request->query('format') === 'html') {
      return response($html, 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Robots-Tag' => 'noindex, nofollow',
      ]);
    }

    return response()->json([
      'ok' => true,
      'screen' => 'system-updates',
      'title' => 'System Updates',
      'url' => route('admin.system.updates.index', [], false),
      'rendered_at' => now()->toIso8601String(),
      'cms_version' => WebBlocks::version(),
      'format' => 'html',
      'html' => $html,
      '_links' => [
        'self' => '/webadmin/api/admin-render/system-updates',
        'html' => '/webadmin/api/admin-render/system-updates?format=html',
        'browser_admin' => route('admin.system.updates.index', [], false),
      ],
    ]);
  }

  private function renderUser(Request $request): mixed
  {
    $token = $request->attributes->get('cms_api_token');

    if (! $token instanceof CmsApiToken) {
      return null;
    }

    return $token->creator;
  }

  private function pendingUpdate(): ?array
  {
    $pending = Cache::get('system-updates:pending');

    if (! is_array($pending)) {
      $pending = session('system_updates.pending');
    }

    return is_array($pending) ? $pending : null;
  }

  private function latestUpdateRun(): ?SystemUpdateRun
  {
    if (! Schema::hasTable('wbcms_system_update_runs')) {
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
}
