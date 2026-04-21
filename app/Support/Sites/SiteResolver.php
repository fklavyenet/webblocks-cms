<?php

namespace App\Support\Sites;

use App\Models\Site;
use Illuminate\Http\Request;
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
            $site = Site::query()
                ->whereNotNull('domain')
                ->where('domain', $host)
                ->first();

            if ($site) {
                return new ResolvedSite($site, true, $host, false);
            }
        }

        if ($this->shouldFallbackForUnknownHost()) {
            return new ResolvedSite($this->primary(), false, $host, true);
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

    private function shouldFallbackForUnknownHost(): bool
    {
        return (bool) config('cms.multisite.unknown_host_fallback', false);
    }
}
