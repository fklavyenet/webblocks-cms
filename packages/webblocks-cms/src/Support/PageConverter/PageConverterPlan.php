<?php

namespace WebBlocks\Cms\Support\PageConverter;

readonly class PageConverterPlan
{
  public function __construct(
    public string $status,
    public string $message,
    public PageConverterAnalyzeInput $input,
    public int $sourceBytes,
  ) {}

  public function profileLabel(): string
  {
    return PageConverterProfile::label($this->input->conversionProfile);
  }
}
