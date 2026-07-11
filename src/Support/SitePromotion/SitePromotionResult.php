<?php

namespace WebBlocks\Cms\Support\SitePromotion;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemBackup;

class SitePromotionResult
{
  public function __construct(
    public readonly SitePromotionPlan $plan,
    public readonly Site $targetSite,
    public readonly ?SystemBackup $safetyBackup,
    public readonly int $searchIndexed,
    public readonly int $searchSkipped,
    public readonly array $warnings = [],
  ) {}
}
