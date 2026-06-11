<?php

namespace WebBlocks\Cms\Support\PageConverter;

readonly class PageBlockSuggestion
{
  /**
   * @param  array<int, string>  $warnings
   */
  public function __construct(
    public string $blockSlug,
    public string $label,
    public string $previewText,
    public int $confidence,
    public string $sourceSummary,
    public array $warnings = [],
  ) {}
}
