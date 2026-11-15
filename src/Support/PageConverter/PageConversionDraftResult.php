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
    return __('webblocks-cms::admin-feedback.converter.draft_created', [
      'created' => $this->createdBlockCount,
      'skipped' => $this->skippedSuggestionCount,
      'warnings' => $this->warningCount,
    ]);
  }
}
