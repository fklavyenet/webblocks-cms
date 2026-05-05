<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\Site;

class PageSiteMoveResult
{
    public function __construct(
        public readonly Page $page,
        public readonly Site $sourceSite,
        public readonly Site $targetSite,
        public readonly int $remappedSharedSlotCount,
        public readonly int $navigationReferenceCount,
    ) {}
}
