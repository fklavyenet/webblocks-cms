<?php

namespace WebBlocks\Cms\Support\PageConverter;

readonly class PageConverterAnalyzeInput
{
  public function __construct(
    public int $siteId,
    public int $localeId,
    public string $pageLayout,
    public string $pageTitle,
    public string $pagePath,
    public string $conversionProfile,
    public string $sourceHtml,
    public string $sourceType,
    public string $sourceName,
  ) {}
}
