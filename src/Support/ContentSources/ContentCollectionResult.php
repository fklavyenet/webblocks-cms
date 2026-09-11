<?php

namespace WebBlocks\Cms\Support\ContentSources;

readonly class ContentCollectionResult
{
  /** @var list<array<string, mixed>> */
  public array $records;

  /**
   * @param  iterable<array<string, mixed>>  $records
   */
  public function __construct(
    iterable $records,
    public int $total,
    public int $currentPage = 1,
    public int $perPage = 1,
  ) {
    $this->records = collect($records)
      ->filter(fn (mixed $record): bool => is_array($record))
      ->values()
      ->all();
  }
}
