<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class GuardPluginSetup
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
  ) {}

  public function handle(Request $request, Closure $next, string $plugin): Response
  {
    try {
      return $next($request);
    } catch (QueryException $exception) {
      if (! $this->isMissingTableException($exception)) {
        throw $exception;
      }

      return response()->view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.setup-required', [
        'title' => 'Plugin Setup Required',
        'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
        'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Plugin Setup Required'),
        'message' => 'Setup required. Plugin migrations pending. Release tables are missing.',
        'pluginDetailUrl' => route('admin.system.plugins.show', $plugin),
      ]);
    }
  }

  private function isMissingTableException(QueryException $exception): bool
  {
    $sqlState = (string) ($exception->errorInfo[0] ?? '');

    return str_contains($exception->getMessage(), 'Base table or view not found')
      || str_contains($exception->getMessage(), 'no such table')
      || str_contains($sqlState, '42S02');
  }
}
