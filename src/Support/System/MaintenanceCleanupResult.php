<?php

namespace WebBlocks\Cms\Support\System;

final class MaintenanceCleanupResult
{
  public function __construct(
    public readonly int $candidateCount,
    public readonly int $candidateBytes,
    public readonly int $deletedCount = 0,
    public readonly int $deletedBytes = 0,
    public readonly array $failures = [],
  ) {}
}
