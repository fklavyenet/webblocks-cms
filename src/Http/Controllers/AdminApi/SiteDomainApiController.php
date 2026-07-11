<?php

namespace WebBlocks\Cms\Http\Controllers\AdminApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Http\Requests\Admin\SiteDomainStoreRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Support\Sites\SiteDomainManager;

class SiteDomainApiController extends Controller
{
  public function __construct(
    private readonly SiteDomainManager $siteDomainManager,
  ) {}

  public function indexSites(): JsonResponse
  {
    $sites = Site::query()
      ->with('siteDomains')
      ->primaryFirst()
      ->orderBy('name')
      ->get()
      ->map(fn (Site $site) => [
        'id' => $site->id,
        'name' => $site->name,
        'handle' => $site->handle,
        'primary_domain' => $site->canonicalDomain(),
        'domains_count' => $site->siteDomains->count(),
      ])
      ->values();

    return response()->json(['sites' => $sites]);
  }

  public function indexDomains(Site $site): JsonResponse
  {
    return response()->json([
      'site' => [
        'id' => $site->id,
        'name' => $site->name,
        'handle' => $site->handle,
      ],
      'domains' => $site->siteDomains()->orderByDesc('is_primary')->orderBy('domain')->get()->map(fn (SiteDomain $domain) => $this->domainPayload($domain))->values(),
    ]);
  }

  public function storeDomain(SiteDomainStoreRequest $request, Site $site): JsonResponse
  {
    $domain = $this->siteDomainManager->addDomain(
      $site,
      (string) $request->string('domain'),
      $request->boolean('is_primary'),
      $request->boolean('redirect_to_primary'),
      (string) $request->string('status'),
    );

    return response()->json([
      'domain' => $this->domainPayload($domain),
    ], 201);
  }

  public function destroyDomain(Site $site, SiteDomain $domain): JsonResponse
  {
    $this->siteDomainManager->deleteDomain($site, $domain);

    return response()->json([
      'deleted' => true,
      'domain_id' => $domain->id,
    ]);
  }

  public function domainStatus(string $domain): JsonResponse
  {
    $siteDomain = SiteDomain::query()
      ->with('site')
      ->where('domain', strtolower(trim($domain)))
      ->first();

    return response()->json([
      'domain' => strtolower(trim($domain)),
      'configured' => $siteDomain !== null,
      'active' => $siteDomain?->isActive() ?? false,
      'site' => $siteDomain?->site ? [
        'id' => $siteDomain->site->id,
        'name' => $siteDomain->site->name,
        'handle' => $siteDomain->site->handle,
      ] : null,
      'record' => $siteDomain ? $this->domainPayload($siteDomain) : null,
    ]);
  }

  private function domainPayload(SiteDomain $domain): array
  {
    return [
      'id' => $domain->id,
      'site_id' => $domain->site_id,
      'domain' => $domain->domain,
      'is_primary' => (bool) $domain->is_primary,
      'redirect_to_primary' => (bool) $domain->redirect_to_primary,
      'status' => $domain->status,
    ];
  }
}
