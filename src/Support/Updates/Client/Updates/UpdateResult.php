<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Carbon\CarbonImmutable;

/**
 * Immutable result of a completed update run. The pre-update backup reference
 * is generalized to `mixed` so backup persistence can stay per-product.
 */
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
    public readonly mixed $preUpdateBackup = null,
  ) {}
}
