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

      if ($this->isWebBlocksGrid($segment)) {
        $cards = $this->suggestionMapper->directCardChildren($segment);

        if ($cards !== []) {
          foreach ($cards as $card) {
            array_push($suggestions, ...$this->suggestionMapper->mapCardWithRegions($card, count($suggestions)));
          }

          $index++;

          continue;
        }
      }

      if ($this->isWebBlocksCard($segment)) {
        array_push($suggestions, ...$this->suggestionMapper->mapCardWithRegions($segment, count($suggestions)));
        $index++;

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

  private function isWebBlocksGrid(\DOMElement $element): bool
  {
    return in_array('wb-grid', $this->classes($element), true);
  }

  private function isWebBlocksCard(\DOMElement $element): bool
  {
    return in_array('wb-card', $this->classes($element), true);
  }

  /**
   * @return array<int, string>
   */
  private function classes(\DOMElement $element): array
  {
    return array_values(array_filter(preg_split('/\s+/', trim($element->getAttribute('class'))) ?: []));
  }
}
