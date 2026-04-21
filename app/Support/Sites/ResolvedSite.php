<?php

namespace App\Support\Sites;

use App\Models\Site;

class ResolvedSite
{
    public function __construct(
        public readonly Site $site,
        public readonly bool $matchedHost,
        public readonly ?string $requestedHost,
        public readonly bool $usedFallback,
    ) {}
}
