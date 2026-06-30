<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\CmsApiTokenActivityLog;

class CmsApiTokenActivityLogger
{
  private const ACTIVITY_LOG_ATTRIBUTE = 'cms_api_token_activity_log_id';
  private const RETAINED_LOGS_PER_TOKEN = 10;

  public function authenticated(CmsApiToken $token, Request $request): void
  {
    if (! $this->schemaReady()) {
      return;
    }

    $log = CmsApiTokenActivityLog::query()->create([
      'cms_api_token_id' => $token->id,
      'occurred_at' => now(),
      'status' => 'authenticated',
      'method' => mb_substr($request->method(), 0, 12),
      'path' => mb_substr('/'.$request->path(), 0, 512),
      'route_name' => $request->route()?->getName(),
      'required_capability' => null,
      'ip' => $request->ip(),
      'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
    ]);

    $request->attributes->set(self::ACTIVITY_LOG_ATTRIBUTE, $log->id);

    $this->prune($token);
  }

  public function capabilityAllowed(CmsApiToken $token, Request $request, string $capability): void
  {
    $this->mark($token, $request, 'allowed', $capability);
  }

  public function capabilityDenied(CmsApiToken $token, Request $request, string $capability): void
  {
    $this->mark($token, $request, 'denied', $capability);
  }

  private function mark(CmsApiToken $token, Request $request, string $status, string $capability): void
  {
    if (! $this->schemaReady()) {
      return;
    }

    $logId = $request->attributes->get(self::ACTIVITY_LOG_ATTRIBUTE);

    if (! $logId) {
      $this->authenticated($token, $request);
      $logId = $request->attributes->get(self::ACTIVITY_LOG_ATTRIBUTE);
    }

    CmsApiTokenActivityLog::query()
      ->whereKey($logId)
      ->where('cms_api_token_id', $token->id)
      ->update([
        'status' => $status,
        'required_capability' => $capability,
      ]);
  }

  private function prune(CmsApiToken $token): void
  {
    $retainedIds = CmsApiTokenActivityLog::query()
      ->where('cms_api_token_id', $token->id)
      ->latest('occurred_at')
      ->latest('id')
      ->limit(self::RETAINED_LOGS_PER_TOKEN)
      ->pluck('id');

    CmsApiTokenActivityLog::query()
      ->where('cms_api_token_id', $token->id)
      ->whereNotIn('id', $retainedIds)
      ->delete();
  }

  private function schemaReady(): bool
  {
    return Schema::hasTable('cms_api_token_activity_logs')
      && Schema::hasColumn('cms_api_token_activity_logs', 'cms_api_token_id');
  }
}
