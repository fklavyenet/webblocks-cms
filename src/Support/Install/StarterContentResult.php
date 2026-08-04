<?php

namespace WebBlocks\Cms\Support\Install;

/**
 * Outcome of one starter-content install attempt.
 *
 * Skipping is the normal case on every run after the first, so it carries a
 * reason instead of an exception: an install must not fail over content it
 * only offers as a starting point.
 */
class StarterContentResult
{
  private function __construct(
    public readonly bool $installed,
    public readonly int $blocksCreated,
    /** @var array<int, string> */
    public readonly array $skippedBlockTypes,
    public readonly ?string $reason,
  ) {}

  /**
   * @param  array<int, string>  $skippedBlockTypes
   */
  public static function installed(int $blocksCreated, array $skippedBlockTypes = []): self
  {
    return new self(true, $blocksCreated, array_values(array_unique($skippedBlockTypes)), null);
  }

  public static function skipped(string $reason): self
  {
    return new self(false, 0, [], $reason);
  }
}
