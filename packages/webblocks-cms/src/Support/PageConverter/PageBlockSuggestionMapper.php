<?php

namespace WebBlocks\Cms\Support\PageConverter;

use DOMElement;
use DOMXPath;

class PageBlockSuggestionMapper
{
  /**
   * @return array<int, DOMElement>
   */
  public function directCardChildren(DOMElement $element): array
  {
    $cards = [];

    foreach ((new DOMXPath($element->ownerDocument))->query('./*[contains(concat(" ", normalize-space(@class), " "), " wb-card ")]', $element) ?: [] as $card) {
      if ($card instanceof DOMElement) {
        $cards[] = $card;
      }
    }

    return $cards;
  }

  /**
   * @return array<int, PageBlockSuggestion>
   */
  public function mapCardWithRegions(DOMElement $card, int $startIndex): array
  {
    $regions = $this->directCardRegions($card);

    if ($regions === []) {
      return [$this->map($card, $startIndex)];
    }

    $suggestions = [];
    $cardKey = 'block_'.($startIndex + 1);
    $suggestions[] = $this->suggestion('card', 'Card', $card, 94);

    foreach ($regions as $region) {
      $regionSlug = $this->cardRegionSlug($region);

      if ($regionSlug === null) {
        continue;
      }

      $regionKey = 'block_'.($startIndex + count($suggestions) + 1);
      $suggestions[] = $this->suggestion(
        $regionSlug,
        str($regionSlug)->replace('_', ' ')->title()->toString(),
        $region,
        94,
        [],
        $cardKey,
      );

      foreach ($this->directMeaningfulChildren($region) as $child) {
        if ($this->cardRegionSlug($child) !== null) {
          continue;
        }

        $childSuggestion = $this->map($child, $startIndex + count($suggestions));
        $suggestions[] = new PageBlockSuggestion(
          blockSlug: $childSuggestion->blockSlug,
          label: $childSuggestion->label,
          previewText: $childSuggestion->previewText,
          confidence: $childSuggestion->confidence,
          sourceSummary: $childSuggestion->sourceSummary,
          sourceHtml: $childSuggestion->sourceHtml,
          translatedFields: $childSuggestion->translatedFields,
          sharedFields: $childSuggestion->sharedFields,
          parentKey: $regionKey,
          warnings: $childSuggestion->warnings,
          fallbackFlags: $childSuggestion->fallbackFlags,
        );
      }
    }

    return $suggestions;
  }

  /**
   * @param  array<int, DOMElement>  $details
   * @return array<int, PageBlockSuggestion>
   */
  public function mapDetailsGroup(array $details, string $parentKey): array
  {
    $details = array_values(array_filter($details, fn (DOMElement $element): bool => strtolower($element->tagName) === 'details'));

    if ($details === []) {
      return [];
    }

    $warnings = [];

    if (collect($details)->contains(fn (DOMElement $element): bool => $this->containsTag($element, 'img'))) {
      $warnings[] = 'Accordion details contain image media. Media import is not implemented in this phase.';
    }

    $suggestions = [
      new PageBlockSuggestion(
        blockSlug: 'accordion',
        label: 'Accordion',
        previewText: $this->detailsGroupPreviewText($details),
        confidence: 90,
        sourceSummary: count($details) === 1 ? '<details>' : '<details> group',
        sourceHtml: $this->detailsGroupSourceHtml($details),
        translatedFields: [
          'title' => null,
          'content' => null,
        ],
        sharedFields: [
          'item_count' => count($details),
        ],
        warnings: $warnings,
      ),
    ];

    foreach ($details as $detail) {
      $summary = $this->detailsSummaryText($detail);
      $bodyText = $this->detailsBodyText($detail);
      $itemWarnings = [];

      if ($this->containsTag($detail, 'img')) {
        $itemWarnings[] = 'Accordion item contains image media. Media import is not implemented in this phase.';
      }

      $suggestions[] = new PageBlockSuggestion(
        blockSlug: 'accordion_item',
        label: 'Accordion Item',
        previewText: trim($summary.' '.$bodyText),
        confidence: $summary !== '' && $bodyText !== '' ? 90 : 72,
        sourceSummary: '<details>',
        sourceHtml: $this->sourceHtml($detail),
        translatedFields: [
          'title' => $summary,
          'content' => $bodyText,
        ],
        sharedFields: [],
        parentKey: $parentKey,
        warnings: $itemWarnings,
      );
    }

    return $suggestions;
  }

  public function map(DOMElement $element, int $index): PageBlockSuggestion
  {
    $tag = strtolower($element->tagName);
    $classes = $this->classes($element);

    if (in_array('wb-content-header', $classes, true)) {
      return $this->suggestion('content_header', 'Content Header', $element, 96);
    }

    if (in_array('wb-promo', $classes, true)) {
      $hasH1 = $this->containsTag($element, 'h1');
      $slug = $index <= 1 || $hasH1 ? 'hero' : 'cta';

      return $this->suggestion($slug, $slug === 'hero' ? 'Hero' : 'CTA', $element, $hasH1 ? 92 : 86);
    }

    if (in_array('wb-section', $classes, true)) {
      return $this->suggestion('section', 'Section', $element, 93);
    }

    if (in_array('wb-card', $classes, true)) {
      return $this->suggestion('card', 'Card', $element, 94);
    }

    if (in_array('wb-alert', $classes, true)) {
      return $this->suggestion('callout', 'Callout', $element, 90);
    }

    if (in_array('wb-gallery', $classes, true)) {
      return $this->suggestion('gallery', 'Gallery', $element, 88, ['Media references were detected. Media import is not implemented in this phase.']);
    }

    if ($tag === 'a' && in_array('wb-btn', $classes, true)) {
      return $this->suggestion('button_link', 'Button Link', $element, 94);
    }

    if (preg_match('/^h[1-6]$/', $tag) === 1) {
      return $this->suggestion('header', 'Header', $element, 98);
    }

    if ($tag === 'p') {
      return $this->suggestion(
        $this->hasInlineRichMarkup($element) ? 'rich-text' : 'plain_text',
        $this->hasInlineRichMarkup($element) ? 'Rich Text' : 'Plain Text',
        $element,
        $this->hasInlineRichMarkup($element) ? 88 : 96,
      );
    }

    if ($tag === 'pre' || $this->containsDirectCodePre($element)) {
      return $this->suggestion('code', 'Code', $element, 98);
    }

    if ($tag === 'table') {
      return $this->suggestion('table', 'Table', $element, 96);
    }

    if ($tag === 'blockquote') {
      return $this->suggestion('quote', 'Quote', $element, 96);
    }

    if (in_array($tag, ['ul', 'ol'], true)) {
      return $this->suggestion('list', 'List', $element, $this->listLooksSimple($element) ? 88 : 76, $this->listLooksSimple($element) ? [] : ['Complex list content may need Rich Text review.']);
    }

    if ($tag === 'details') {
      return $this->suggestion('accordion', 'Accordion', $element, $this->containsTag($element, 'summary') ? 86 : 70, $this->containsTag($element, 'summary') ? [] : ['Details block has no summary element.']);
    }

    if ($this->isImageElement($element)) {
      return $this->suggestion('image', 'Image', $element, 86, ['Image media was detected. Media import is not implemented in this phase.']);
    }

    if ($this->containsMultipleParagraphs($element) || $this->containsTag($element, 'strong') || $this->containsTag($element, 'em')) {
      return $this->suggestion('rich-text', 'Rich Text', $element, 76, ['Grouped content should be reviewed before draft creation.']);
    }

    return $this->suggestion('html', 'HTML Fallback', $element, 45, ['No high-confidence structured block mapping exists for this fragment yet.']);
  }

  private function suggestion(string $blockSlug, string $label, DOMElement $element, int $confidence, array $warnings = [], ?string $parentKey = null): PageBlockSuggestion
  {
    return new PageBlockSuggestion(
      blockSlug: $blockSlug,
      label: $label,
      previewText: $this->previewText($element),
      confidence: $confidence,
      sourceSummary: $this->sourceSummary($element),
      sourceHtml: $this->sourceHtml($element),
      translatedFields: $this->translatedFields($blockSlug, $element),
      sharedFields: $this->sharedFields($blockSlug, $element),
      parentKey: $parentKey,
      warnings: $warnings,
      fallbackFlags: $blockSlug === 'html' ? ['html_fallback'] : [],
    );
  }

  private function previewText(DOMElement $element): string
  {
    $text = preg_replace('/\s+/', ' ', trim($element->textContent)) ?: '';

    return str($text)->limit(180)->toString();
  }

  private function sourceSummary(DOMElement $element): string
  {
    $summary = '<'.strtolower($element->tagName);

    if ($element->hasAttribute('class')) {
      $classes = collect($this->classes($element))->take(3)->implode('.');

      if ($classes !== '') {
        $summary .= '.'.$classes;
      }
    }

    return $summary.'>';
  }

  private function sourceHtml(DOMElement $element): string
  {
    return trim((string) $element->ownerDocument->saveHTML($element));
  }

  /**
   * @return array<int, DOMElement>
   */
  private function directCardRegions(DOMElement $card): array
  {
    $regions = [];

    foreach ($this->directMeaningfulChildren($card) as $child) {
      if ($this->cardRegionSlug($child) !== null) {
        $regions[] = $child;
      }
    }

    return $regions;
  }

  /**
   * @return array<int, DOMElement>
   */
  private function directMeaningfulChildren(DOMElement $element): array
  {
    $children = [];

    foreach ($element->childNodes as $child) {
      if (! $child instanceof DOMElement) {
        continue;
      }

      if (trim($child->textContent) === '' && ! in_array(strtolower($child->tagName), ['figure', 'img', 'table'], true) && ! $this->hasElementChildren($child)) {
        continue;
      }

      $children[] = $child;
    }

    return $children;
  }

  private function cardRegionSlug(DOMElement $element): ?string
  {
    $classes = $this->classes($element);

    if (in_array('wb-card-header', $classes, true)) {
      return 'card_header';
    }

    if (in_array('wb-card-body', $classes, true)) {
      return 'card_body';
    }

    if (in_array('wb-card-footer', $classes, true)) {
      return 'card_footer';
    }

    return null;
  }

  private function hasElementChildren(DOMElement $element): bool
  {
    foreach ($element->childNodes as $child) {
      if ($child instanceof DOMElement) {
        return true;
      }
    }

    return false;
  }

  /**
   * @return array<string, mixed>
   */
  private function translatedFields(string $blockSlug, DOMElement $element): array
  {
    $text = $this->previewText($element);

    return match ($blockSlug) {
      'header' => [
        'title' => $this->headingText($element) ?: $text,
      ],
      'content_header' => [
        'title' => $this->headingText($element) ?: $text,
        'subtitle' => $this->bodyText($element),
      ],
      'hero' => [
        'eyebrow' => $this->eyebrowText($element),
        'title' => $this->headingText($element) ?: $text,
        'body' => $this->bodyText($element),
        'primary_cta_label' => $this->primaryButtonLabel($element),
      ],
      'cta' => [
        'eyebrow' => $this->eyebrowText($element),
        'heading' => $this->headingText($element) ?: $text,
        'body' => $this->bodyText($element),
        'primary_cta_label' => $this->primaryButtonLabel($element),
      ],
      'button_link' => [
        'label' => $text,
      ],
      'button' => [
        'label' => $text,
      ],
      'image' => [
        'alt' => $this->imageAlt($element),
        'caption' => $this->figureCaption($element),
      ],
      'gallery' => [
        'caption' => $text,
      ],
      'code' => [
        'code' => $text,
      ],
      'table' => [
        'table_html' => $this->sourceHtml($element),
      ],
      'html' => [
        'html' => $this->sourceHtml($element),
      ],
      default => [
        'content' => $text,
      ],
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function sharedFields(string $blockSlug, DOMElement $element): array
  {
    return match ($blockSlug) {
      'button_link', 'button' => [
        'url' => $element->getAttribute('href') ?: null,
        'target' => $element->getAttribute('target') ?: '_self',
        'variant' => $this->buttonVariant($element),
      ],
      'image' => [
        'source' => $this->firstImageAttribute($element, 'src'),
      ],
      'gallery' => [
        'sources' => $this->imageSources($element),
      ],
      'hero', 'cta' => [
        'primary_cta_url' => $this->primaryButtonUrl($element),
      ],
      default => [],
    };
  }

  private function imageAlt(DOMElement $element): ?string
  {
    return $this->firstImageAttribute($element, 'alt') ?: null;
  }

  private function figureCaption(DOMElement $element): ?string
  {
    foreach ((new DOMXPath($element->ownerDocument))->query('.//figcaption', $element) ?: [] as $caption) {
      if ($caption instanceof DOMElement) {
        return $this->previewText($caption) ?: null;
      }
    }

    return null;
  }

  private function firstImageAttribute(DOMElement $element, string $attribute): ?string
  {
    if (strtolower($element->tagName) === 'img') {
      return $element->getAttribute($attribute) ?: null;
    }

    foreach ((new DOMXPath($element->ownerDocument))->query('.//img', $element) ?: [] as $image) {
      if ($image instanceof DOMElement) {
        return $image->getAttribute($attribute) ?: null;
      }
    }

    return null;
  }

  private function headingText(DOMElement $element): ?string
  {
    foreach ((new DOMXPath($element->ownerDocument))->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $element) ?: [] as $heading) {
      if ($heading instanceof DOMElement) {
        return $this->previewText($heading) ?: null;
      }
    }

    return null;
  }

  private function eyebrowText(DOMElement $element): ?string
  {
    foreach ((new DOMXPath($element->ownerDocument))->query('.//*[contains(concat(" ", normalize-space(@class), " "), " wb-eyebrow ")]', $element) ?: [] as $eyebrow) {
      if ($eyebrow instanceof DOMElement) {
        return $this->previewText($eyebrow) ?: null;
      }
    }

    return null;
  }

  private function bodyText(DOMElement $element): ?string
  {
    foreach ((new DOMXPath($element->ownerDocument))->query('.//p[not(contains(concat(" ", normalize-space(@class), " "), " wb-eyebrow "))]', $element) ?: [] as $paragraph) {
      if ($paragraph instanceof DOMElement) {
        return $this->previewText($paragraph) ?: null;
      }
    }

    return null;
  }

  /**
   * @return array<int, DOMElement>
   */
  private function directButtonLinks(DOMElement $element): array
  {
    $buttons = [];

    foreach ((new DOMXPath($element->ownerDocument))->query('./a[contains(concat(" ", normalize-space(@class), " "), " wb-btn ")]', $element) ?: [] as $button) {
      if ($button instanceof DOMElement) {
        $buttons[] = $button;
      }
    }

    return $buttons;
  }

  private function primaryButtonLabel(DOMElement $element): ?string
  {
    $button = $this->directButtonLinks($element)[0] ?? null;

    return $button instanceof DOMElement ? ($this->previewText($button) ?: null) : null;
  }

  private function primaryButtonUrl(DOMElement $element): ?string
  {
    $button = $this->directButtonLinks($element)[0] ?? null;

    return $button instanceof DOMElement ? ($button->getAttribute('href') ?: null) : null;
  }

  private function buttonVariant(DOMElement $element): ?string
  {
    $classes = $this->classes($element);

    return match (true) {
      in_array('wb-btn-secondary', $classes, true) => 'secondary',
      in_array('wb-btn-ghost', $classes, true) => 'ghost',
      in_array('wb-btn-link', $classes, true) => 'link',
      in_array('wb-btn-primary', $classes, true) => 'primary',
      default => null,
    };
  }

  /**
   * @return array<int, string>
   */
  private function imageSources(DOMElement $element): array
  {
    $sources = [];

    foreach ((new DOMXPath($element->ownerDocument))->query('.//img', $element) ?: [] as $image) {
      if ($image instanceof DOMElement && $image->getAttribute('src') !== '') {
        $sources[] = $image->getAttribute('src');
      }
    }

    return array_values(array_unique($sources));
  }

  /**
   * @param  array<int, DOMElement>  $details
   */
  private function detailsGroupPreviewText(array $details): string
  {
    return str(collect($details)
      ->map(fn (DOMElement $detail): string => $this->detailsSummaryText($detail))
      ->filter()
      ->implode(' | '))
      ->limit(180)
      ->toString();
  }

  /**
   * @param  array<int, DOMElement>  $details
   */
  private function detailsGroupSourceHtml(array $details): string
  {
    return trim(collect($details)
      ->map(fn (DOMElement $detail): string => $this->sourceHtml($detail))
      ->implode("\n"));
  }

  private function detailsSummaryText(DOMElement $element): string
  {
    foreach ((new DOMXPath($element->ownerDocument))->query('./summary', $element) ?: [] as $summary) {
      if ($summary instanceof DOMElement) {
        return $this->previewText($summary);
      }
    }

    return '';
  }

  private function detailsBodyText(DOMElement $element): string
  {
    $parts = [];

    foreach ($element->childNodes as $child) {
      if ($child instanceof DOMElement && strtolower($child->tagName) === 'summary') {
        continue;
      }

      $text = preg_replace('/\s+/', ' ', trim($child->textContent)) ?: '';

      if ($text !== '') {
        $parts[] = $text;
      }
    }

    return trim(implode("\n\n", $parts));
  }

  /**
   * @return array<int, string>
   */
  private function classes(DOMElement $element): array
  {
    return array_values(array_filter(preg_split('/\s+/', trim($element->getAttribute('class'))) ?: []));
  }

  private function hasInlineRichMarkup(DOMElement $element): bool
  {
    foreach ($element->childNodes as $child) {
      if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['strong', 'b', 'em', 'i', 'code', 'a', 'span'], true)) {
        return true;
      }
    }

    return false;
  }

  private function containsTag(DOMElement $element, string $tag): bool
  {
    return (new DOMXPath($element->ownerDocument))->query('.//'.$tag, $element)?->length > 0;
  }

  private function containsDirectCodePre(DOMElement $element): bool
  {
    return strtolower($element->tagName) === 'pre'
      || (new DOMXPath($element->ownerDocument))->query('.//pre/code|.//pre', $element)?->length > 0;
  }

  private function containsMultipleParagraphs(DOMElement $element): bool
  {
    return (new DOMXPath($element->ownerDocument))->query('.//p', $element)?->length > 1;
  }

  private function listLooksSimple(DOMElement $element): bool
  {
    foreach ((new DOMXPath($element->ownerDocument))->query('./li', $element) ?: [] as $item) {
      foreach ($item->childNodes as $child) {
        if ($child instanceof DOMElement && ! in_array(strtolower($child->tagName), ['strong', 'b', 'em', 'i', 'code', 'a', 'span'], true)) {
          return false;
        }
      }
    }

    return true;
  }

  private function isImageElement(DOMElement $element): bool
  {
    return strtolower($element->tagName) === 'img'
      || (strtolower($element->tagName) === 'figure' && $this->containsTag($element, 'img'));
  }
}
