<?php

namespace App\Support\System\Updates;

use Carbon\CarbonImmutable;

use App\Models\SystemBackup;

class UpdateResult
{
    public function __construct(
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly string $status,
        public readonly string $summary,
        public readonly string $output,
        public readonly int $warningCount,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $finishedAt,
        public readonly int $durationMs,
        public readonly ?SystemBackup $preUpdateBackup = null,
    ) {}
}
