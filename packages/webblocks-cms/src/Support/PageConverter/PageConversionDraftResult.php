<?php

namespace WebBlocks\Cms\Support\PageConverter;

use WebBlocks\Cms\Models\Page;

readonly class PageConversionDraftResult
{
  /**
   * @param  array<int, string>  $warnings
   */
  public function __construct(
    public Page $page,
    public int $createdBlockCount,
    public int $skippedSuggestionCount,
    public int $warningCount,
    public array $warnings = [],
  ) {}

  public function message(): string
  {
    return 'Created draft page from Page Converter plan. '
      .$this->createdBlockCount.' block(s) created, '
      .$this->skippedSuggestionCount.' suggestion(s) skipped, '
      .$this->warningCount.' warning(s) reported. The page remains draft.';
  }
}
