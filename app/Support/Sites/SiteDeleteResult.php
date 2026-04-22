<?php

namespace App\Support\Sites;

use App\Models\Site;

class SiteDeleteResult
{
    public function __construct(
        public readonly Site $site,
        public readonly bool $canDelete,
        public readonly bool $deleted,
        public readonly array $counts,
        public readonly array $blockers = [],
        public readonly array $warnings = [],
    ) {}

    public function count(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }

    public function hasBlockers(): bool
    {
        return $this->blockers !== [];
    }

    public function firstBlocker(): ?string
    {
        return $this->blockers[0] ?? null;
    }
}
