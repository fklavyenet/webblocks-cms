<?php

namespace WebBlocks\Cms\Support\PageConverter;

readonly class PageConverterPlan
{
  /**
   * @param  array<int, PageBlockSuggestion>  $suggestions
   */
  public function __construct(
    public string $status,
    public string $message,
    public PageConverterAnalyzeInput $input,
    public int $sourceBytes,
    public string $contentRootSummary,
    public array $suggestions,
  ) {}

  public function profileLabel(): string
  {
    return PageConverterProfile::label($this->input->conversionProfile);
  }

  public function suggestionCount(): int
  {
    return count($this->suggestions);
  }

  public function fallbackCount(): int
  {
    return count(array_filter(
      $this->suggestions,
      fn (PageBlockSuggestion $suggestion): bool => $suggestion->blockSlug === 'html',
    ));
  }

  public function warningCount(): int
  {
    return array_sum(array_map(
      fn (PageBlockSuggestion $suggestion): int => count($suggestion->warnings),
      $this->suggestions,
    ));
  }
}
