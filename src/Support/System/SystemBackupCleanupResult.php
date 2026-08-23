<?php

namespace WebBlocks\Cms\Support\System;

final class SystemBackupCleanupResult
{
  /**
   * @param  list<int>  $candidateIds
   * @param  list<int>  $deletedIds
   * @param  list<array{id: int, message: string}>  $failures
   */
  public function __construct(
    public readonly array $candidateIds,
    public readonly int $candidateBytes,
    public readonly array $deletedIds = [],
    public readonly int $deletedBytes = 0,
    public readonly array $failures = [],
  ) {}

  public function candidateCount(): int
  {
    return count($this->candidateIds);
  }

  public function deletedCount(): int
  {
    return count($this->deletedIds);
  }
}
