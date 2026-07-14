<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\System\SystemBackupManager;

class InternalContentPlanController extends Controller
{
  public function __construct(
    private readonly InternalContentPlanService $plans,
    private readonly InternalApiResponseMetadata $metadata,
    private readonly CmsApiTokenCapabilities $capabilities,
    private readonly SystemBackupManager $backups,
  ) {}

  public function validatePlan(Request $request): JsonResponse
  {
    $result = $this->plans->validate($request->json()->all());

    $payload = $result->toArray();

    return response()->json($result->ok ? $payload : $this->metadata->merge($payload), $result->ok ? 200 : 422);
  }

  public function apply(Request $request): JsonResponse
  {
    if ($this->requiresPublishCapability($request) && ! $this->hasPublishCapability($request)) {
      return response()->json($this->metadata->merge([
        'ok' => false,
        'code' => 'missing_internal_api_capability',
        'message' => 'The API token does not have the required capability.',
        'required_capability' => CmsApiTokenCapabilities::CONTENT_PUBLISH,
        'errors' => [
          [
            'path' => 'Authorization',
            'message' => 'Promoting a staged update onto a published page requires content.publish.',
          ],
        ],
      ]), 403);
    }

    $planPayload = $request->json()->all();
    $restorePoint = null;

    if ($this->wantsRestorePoint($planPayload)) {
      $restorePointResult = $this->createRestorePoint($request, $planPayload);

      if ($restorePointResult instanceof JsonResponse) {
        return $restorePointResult;
      }

      $restorePoint = $restorePointResult;
    }

    $result = $this->plans->apply($planPayload);

    $payload = $result->toArray();

    if ($result->ok && $restorePoint !== null) {
      $payload['restore_point'] = $restorePoint;
    }

    if ($result->ok) {
      Log::info('Internal Content API apply completed.', [
        'writes' => collect($payload['writes'] ?? [])->pluck('type')->countBy()->all(),
        'restore_point_id' => $restorePoint['id'] ?? null,
        'token_id' => $request->attributes->get('cms_api_token')?->id,
      ]);
    }

    return response()->json($result->ok ? $payload : $this->metadata->merge($payload), $result->ok ? 201 : 422);
  }

  private function wantsRestorePoint(array $payload): bool
  {
    return filter_var($payload['create_restore_point'] ?? false, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @return array<string, mixed>|JsonResponse the restore point summary, or an error response that aborts apply
   */
  private function createRestorePoint(Request $request, array $planPayload): array|JsonResponse
  {
    $token = $request->attributes->get('cms_api_token');

    if (! ($token instanceof CmsApiToken && $this->capabilities->has($token, CmsApiTokenCapabilities::BACKUPS_CREATE))) {
      return response()->json($this->metadata->merge([
        'ok' => false,
        'code' => 'missing_internal_api_capability',
        'message' => 'Creating a restore point before content apply requires backups.create.',
        'required_capability' => CmsApiTokenCapabilities::BACKUPS_CREATE,
        'errors' => [
          ['path' => 'create_restore_point', 'message' => 'The API token does not have the backups.create capability.'],
        ],
      ]), 403);
    }

    // Validate first so an invalid plan does not trigger a heavy, wasted backup.
    $validation = $this->plans->validate($planPayload);

    if (! $validation->ok) {
      return response()->json($this->metadata->merge($validation->toArray()), 422);
    }

    if (! Schema::hasTable('wbcms_system_backups')) {
      return response()->json($this->metadata->merge([
        'ok' => false,
        'code' => 'restore_point_unavailable',
        'message' => 'System backups are not ready. Run System Updates before requesting a restore point.',
        'errors' => [
          ['path' => 'create_restore_point', 'message' => 'The system backups table is not available.'],
        ],
      ]), 409);
    }

    try {
      $backup = $this->backups->createContentApplyRestorePoint(
        null,
        'Content apply restore point (API token #'.($token->id ?? '?').')',
      );
    } catch (Throwable $exception) {
      Log::warning('Internal Content API restore point failed.', [
        'message' => $exception->getMessage(),
        'token_id' => $token->id ?? null,
      ]);

      return response()->json($this->metadata->merge([
        'ok' => false,
        'code' => 'restore_point_failed',
        'message' => 'The requested restore point could not be created, so the content plan was not applied.',
        'errors' => [
          ['path' => 'create_restore_point', 'message' => 'The restore point backup failed before any content was applied.'],
        ],
      ]), 409);
    }

    return [
      'id' => $backup->id,
      'type' => $backup->type,
      'label' => $backup->label,
      'status' => $backup->status,
      'created_at' => $backup->created_at?->toIso8601String(),
    ];
  }

  private function requiresPublishCapability(Request $request): bool
  {
    $payload = $request->json()->all();
    $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : $payload;

    return ($plan['mode'] ?? null) === InternalContentPlanService::MODE_PROMOTE_STAGED_UPDATE;
  }

  private function hasPublishCapability(Request $request): bool
  {
    $token = $request->attributes->get('cms_api_token');

    return $token instanceof CmsApiToken
      && $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_PUBLISH);
  }
}
