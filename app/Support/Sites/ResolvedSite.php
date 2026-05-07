<?php

namespace App\Support\Sites;

use App\Models\Site;
use App\Models\SiteDomain;

class ResolvedSite
{
    public function __construct(
        public readonly Site $site,
        public readonly ?SiteDomain $siteDomain,
        public readonly bool $matchedHost,
        public readonly ?string $requestedHost,
        public readonly bool $usedFallback,
    ) {}
}
