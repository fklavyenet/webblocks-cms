<?php

namespace WebBlocks\Cms\Support\PageConverter;

class PageConverterAnalyzer
{
  public function analyze(PageConverterAnalyzeInput $input): PageConverterPlan
  {
    return new PageConverterPlan(
      status: 'placeholder',
      message: 'The Page Converter admin foundation captured this source safely. The structured HTML-to-block conversion engine will be implemented next, so no draft page has been created yet.',
      input: $input,
      sourceBytes: strlen($input->sourceHtml),
    );
  }
}
