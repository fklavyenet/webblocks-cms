<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Actions\System\RunApiSystemUpdate;
use WebBlocks\Cms\Http\Requests\InternalContentApi\RunSystemUpdateApiRequest;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateApiPresenter;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class InternalSystemUpdateController extends Controller
{
  public function check(): JsonResponse
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $check = app(UpdateServerClient::class)->check();

    // Expose approval fields, never server credentials, download URLs or raw diagnostics.
    return response()->json([
      'ok' => true,
      'execution' => 'synchronous',
      'state' => $check->state,
      'server_reachable' => $check->serverReachable,
      'current_version' => $check->installedVersion,
      'target_version' => $check->release['version'] ?? $check->latestVersion,
      'checksum_sha256' => $check->release['checksum_sha256'] ?? null,
      'update_available' => $check->updateAvailable,
      'compatible' => ($check->compatibility['status'] ?? 'unknown') === 'compatible',
      'locked' => app(SystemUpdater::class)->isLocked(),
      'error_code' => $check->errorCode,
      'checked_at' => $check->checkedAt->toIso8601String(),
    ]);
  }

  public function store(RunSystemUpdateApiRequest $request, RunApiSystemUpdate $action, SystemUpdateApiPresenter $presenter): JsonResponse
  {
    $result = $action->execute($request->attributes->get('cms_api_token'), $request->validated());
    $status = $result['http_status'];
    unset($result['http_status']);

    if (isset($result['operation'])) {
      $result['operation'] = $presenter->operation($result['operation']);
    }

    return response()->json($result, $status);
  }

  public function show(string $operation, SystemUpdateApiPresenter $presenter): JsonResponse
  {
    app(CmsPublisherClientConfigurator::class)->configure();

    return response()->json(['ok' => true, 'operation' => $presenter->operation(SystemUpdateRequest::query()->findOrFail($operation))]);
  }
}
