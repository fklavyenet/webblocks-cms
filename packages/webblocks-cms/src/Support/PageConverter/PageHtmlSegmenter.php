<?php

namespace WebBlocks\Cms\Support\PageConverter;

use DOMElement;
use DOMNode;

class PageHtmlSegmenter
{
  /**
   * @return array<int, DOMElement>
   */
  public function segments(DOMElement $contentRoot): array
  {
    $segments = [];

    foreach ($contentRoot->childNodes as $child) {
      if (! $child instanceof DOMElement) {
        continue;
      }

      if (! $this->isMeaningfulElement($child)) {
        continue;
      }

      if ($this->shouldUnwrapContentWrapper($child)) {
        array_push($segments, ...$this->segments($child));

        continue;
      }

      $segments[] = $child;
    }

    if ($segments !== []) {
      return $segments;
    }

    return $this->isMeaningfulElement($contentRoot) ? [$contentRoot] : [];
  }

  private function isMeaningfulElement(DOMNode $node): bool
  {
    if (! $node instanceof DOMElement) {
      return false;
    }

    if (trim($node->textContent) !== '') {
      return true;
    }

    if (in_array(strtolower($node->tagName), ['figure', 'img', 'table'], true)) {
      return true;
    }

    foreach ($node->childNodes as $child) {
      if ($child instanceof DOMElement) {
        return true;
      }
    }

    return false;
  }

  private function shouldUnwrapContentWrapper(DOMElement $element): bool
  {
    $classes = array_values(array_filter(preg_split('/\s+/', trim($element->getAttribute('class'))) ?: []));

    return (bool) array_intersect($classes, [
      'wb-content-body',
      'wb-docs-content',
      'wb-prose',
      'docs-content',
      'content-body',
    ]);
  }
}
