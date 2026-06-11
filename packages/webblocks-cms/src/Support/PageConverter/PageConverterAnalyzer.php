<?php

namespace WebBlocks\Cms\Support\PageConverter;

class PageConverterAnalyzer
{
  public function __construct(
    private readonly PageHtmlNormalizer $normalizer,
    private readonly PageHtmlContentExtractor $contentExtractor,
    private readonly PageHtmlSegmenter $segmenter,
    private readonly PageBlockSuggestionMapper $suggestionMapper,
  ) {}

  public function analyze(PageConverterAnalyzeInput $input): PageConverterPlan
  {
    $document = $this->normalizer->normalize($input->sourceHtml);
    $contentRoot = $this->contentExtractor->extract($document);
    $segments = $this->segmenter->segments($contentRoot);
    $suggestions = [];
    $index = 0;

    while ($index < count($segments)) {
      $segment = $segments[$index];

      if (strtolower($segment->tagName) === 'details') {
        $details = [];

        while (isset($segments[$index]) && strtolower($segments[$index]->tagName) === 'details') {
          $details[] = $segments[$index];
          $index++;
        }

        $parentKey = 'block_'.(count($suggestions) + 1);
        array_push($suggestions, ...$this->suggestionMapper->mapDetailsGroup($details, $parentKey));

        continue;
      }

      $suggestions[] = $this->suggestionMapper->map($segment, count($suggestions));
      $index++;
    }

    return new PageConverterPlan(
      status: 'preview',
      message: 'Analysis preview only. Review these suggested structured blocks; no draft page has been created yet.',
      input: $input,
      sourceBytes: strlen($input->sourceHtml),
      contentRootSummary: $this->contentExtractor->summary($contentRoot),
      suggestions: $suggestions,
    );
  }
}
