<?php

namespace WebBlocks\Cms\Support\ContentSources;

readonly class ContentCollectionQuery
{
  public function __construct(
    public int $limit,
    public ?string $filterField,
    public ?string $filterValue,
    public ?string $sortField,
    public string $sortDirection,
    public int $page,
    public ?int $perPage,
  ) {}
}
