<?php

namespace App\Support\Pages;

use Illuminate\Support\Collection;

class PageSiteMoveValidationResult
{
    public function __construct(
        public readonly Collection $incompatibleLocaleCodes,
        public readonly Collection $sharedSlotRemaps,
        public readonly int $navigationReferenceCount,
    ) {}
}
