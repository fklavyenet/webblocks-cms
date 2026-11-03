<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use WebBlocks\Cms\Actions\Seo\ManageSeoAudit;
use WebBlocks\Cms\Http\Requests\Admin\SiteSeoAuditRequest;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\SiteSeoAuditQuery;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

final class SiteSeoAuditController
{
  public function index(SiteSeoAuditRequest $request, Site $site, SiteSeoAuditQuery $query): Response|JsonResponse
  {
    $data = $query->run($site, $request->validated(), app(AdminLocaleResolver::class)->locale());

    return $this->privateResponse($this->api($request) ? response()->json(['ok' => true, ...$data])
      : response()->view('webblocks-cms::admin.sites.seo', ['site' => $site, 'filters' => $request->validated(), ...$data]));
  }

  public function store(SiteSeoAuditRequest $request, Site $site, ManageSeoAudit $manage): JsonResponse|RedirectResponse
  {
    $audit = $manage->start($site);

    return $this->result($request, $audit);
  }

  public function transition(SiteSeoAuditRequest $request, Site $site, SeoAudit $seoAudit, string $action, ManageSeoAudit $manage): JsonResponse|RedirectResponse
  {
    return $this->result($request, $manage->transition($seoAudit, $action));
  }

  private function result(SiteSeoAuditRequest $request, SeoAudit $audit): JsonResponse|RedirectResponse
  {
    return $this->privateResponse($this->api($request)
      ? response()->json(['ok' => true, 'audit' => ['id' => $audit->id, 'site_id' => $audit->site_id, 'state' => $audit->state, 'phase' => $audit->phase]], 202)
      : redirect()->route('admin.sites.seo-audits.index', ['site' => $audit->site_id, 'audit' => $audit->id, 'tab' => $request->validated('tab') ?? 'findings']));
  }

  private function api(SiteSeoAuditRequest $request): bool
  {
    return str_starts_with((string) $request->route()?->getName(), 'internal-content-api.');
  }

  private function privateResponse($response): mixed
  {
    return $response->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
  }
}
