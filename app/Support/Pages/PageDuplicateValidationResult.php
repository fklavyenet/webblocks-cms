<?php

namespace App\Support\Pages;

use Illuminate\Support\Collection;

class PageDuplicateValidationResult
{
    public function __construct(
        public readonly Collection $incompatibleLocaleCodes,
        public readonly Collection $sharedSlotRemaps,
        public readonly Collection $sharedSlotCompatibility,
        public readonly Collection $disableEligibleSharedSlotIds,
        public readonly int $sourceNavigationCount,
        public readonly array $errors = [],
    ) {}
}
