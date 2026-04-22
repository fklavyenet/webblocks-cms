<?php

namespace App\Support\Sites;

use App\Models\Site;

class SiteCloneResult
{
    public function __construct(
        public readonly Site $sourceSite,
        public readonly ?Site $targetSite,
        public readonly bool $targetCreated,
        public readonly bool $dryRun,
        public readonly array $counts,
        public readonly array $messages = [],
    ) {}

    public function count(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }

    public function targetDomain(): ?string
    {
        return $this->targetSite?->domain;
    }
}
