<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Models\CmsApiToken;
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
    $checkedAt = $report['checked_at'] ?? now();
    $html = view('webblocks-cms::admin.system.updates', [
      'report' => $report,
      'runs' => $this->runRetention->retainedRuns(),
      'preflight' => $report['checks'] ?? [],
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
}
