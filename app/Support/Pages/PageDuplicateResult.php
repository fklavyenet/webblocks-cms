<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\Site;

class PageDuplicateResult
{
    public function __construct(
        public readonly Page $sourcePage,
        public readonly Page $page,
        public readonly Site $targetSite,
        public readonly int $remappedSharedSlotCount,
        public readonly int $disabledSharedSlotCount,
        public readonly int $sourceNavigationCount,
    ) {}
}
