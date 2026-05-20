<?php

namespace WebBlocks\Cms\Support\Sites;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteResolver
{
    public function __construct(
        private readonly SiteDomainNormalizer $domainNormalizer,
    ) {}

    public function current(?Request $request = null): Site
    {
        return $this->resolve($request)->site;
    }

    public function resolve(?Request $request = null): ResolvedSite
    {
        $request ??= request();
        $host = $this->domainNormalizer->normalize($request->getHost());

        if ($host !== null) {
            $siteDomain = $this->resolveActiveSiteDomain($host);

            if ($siteDomain) {
                return new ResolvedSite($siteDomain->site, $siteDomain, true, $host, false);
            }

            $site = $this->resolveLegacySite($host);

            if ($site) {
                return new ResolvedSite($site, null, true, $host, false);
            }
        }

        if ($this->shouldFallbackForUnknownHost()) {
            return new ResolvedSite($this->primary(), null, false, $host, true);
        }

        throw new NotFoundHttpException('Unknown site host.');
    }

    public function primary(): Site
    {
        return Site::query()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->firstOrFail();
    }

    public function normalizeDomain(?string $domain): ?string
    {
        return $this->domainNormalizer->normalize($domain);
    }

    public function activeSiteDomainFor(Site $site, ?string $domain): ?SiteDomain
    {
        $domain = $this->normalizeDomain($domain);

        if ($domain === null || ! Schema::hasTable('site_domains')) {
            return null;
        }

        if ($site->relationLoaded('siteDomains')) {
            return $site->siteDomains
                ->first(fn (SiteDomain $siteDomain) => $siteDomain->domain === $domain && $siteDomain->isActive());
        }

        return $site->siteDomains()->active()->where('domain', $domain)->first();
    }

    public function primaryDomainFor(Site $site): ?SiteDomain
    {
        $primary = $site->primaryDomain();

        if ($primary?->isActive()) {
            return $primary;
        }

        return Schema::hasTable('site_domains')
            ? $site->activeDomains()->first()
            : null;
    }

    private function resolveActiveSiteDomain(string $host): ?SiteDomain
    {
        if (! Schema::hasTable('site_domains')) {
            return null;
        }

        return SiteDomain::query()
            ->with('site')
            ->active()
            ->where('domain', $host)
            ->first();
    }

    private function resolveLegacySite(string $host): ?Site
    {
        return Site::query()
            ->whereNotNull('domain')
            ->where('domain', $host)
            ->first();
    }

    private function shouldFallbackForUnknownHost(): bool
    {
        return (bool) config('cms.multisite.unknown_host_fallback', false);
    }
}
