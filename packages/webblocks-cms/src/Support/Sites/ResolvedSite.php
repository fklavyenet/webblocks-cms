<?php

namespace WebBlocks\Cms\Support\Sites;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;

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
