<?php

namespace WebBlocks\Cms\Support\PageConverter;

readonly class PageBlockSuggestion
{
  /**
   * @param  array<string, mixed>  $translatedFields
   * @param  array<string, mixed>  $sharedFields
   * @param  array<int, string>  $warnings
   * @param  array<int, string>  $fallbackFlags
   */
  public function __construct(
    public string $blockSlug,
    public string $label,
    public string $previewText,
    public int $confidence,
    public string $sourceSummary,
    public string $sourceHtml,
    public array $translatedFields = [],
    public array $sharedFields = [],
    public ?string $parentKey = null,
    public array $warnings = [],
    public array $fallbackFlags = [],
  ) {}
}
