<?php

namespace WebBlocks\Cms\Support\PageConverter;

use DOMElement;
use DOMXPath;

class PageBlockSuggestionMapper
{
  public function map(DOMElement $element, int $index): PageBlockSuggestion
  {
    $tag = strtolower($element->tagName);
    $classes = $this->classes($element);

    if (in_array('wb-content-header', $classes, true)) {
      return $this->suggestion('content_header', 'Content Header', $element, 96);
    }

    if (in_array('wb-section', $classes, true)) {
      return $this->suggestion('section', 'Section', $element, 93);
    }

    if (in_array('wb-promo', $classes, true)) {
      $hasH1 = $this->containsTag($element, 'h1');
      $slug = $index <= 1 || $hasH1 ? 'hero' : 'cta';

      return $this->suggestion($slug, $slug === 'hero' ? 'Hero' : 'CTA', $element, $hasH1 ? 92 : 86);
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

  private function suggestion(string $blockSlug, string $label, DOMElement $element, int $confidence, array $warnings = []): PageBlockSuggestion
  {
    return new PageBlockSuggestion(
      blockSlug: $blockSlug,
      label: $label,
      previewText: $this->previewText($element),
      confidence: $confidence,
      sourceSummary: $this->sourceSummary($element),
      warnings: $warnings,
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
