<?php

namespace WebBlocks\Cms\Support\PageConverter;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class PageHtmlContentExtractor
{
  public function extract(DOMDocument $document): DOMElement
  {
    $xpath = new DOMXPath($document);
    $queries = [
      '//main',
      '//*[@role="main"]',
      '//*[contains(concat(" ", normalize-space(@class), " "), " wb-content-body ")]',
      '//article',
      '//body',
    ];

    foreach ($queries as $query) {
      $node = $xpath->query($query)?->item(0);

      if ($node instanceof DOMElement) {
        return $node;
      }
    }

    return $document->documentElement;
  }

  public function summary(DOMNode $node): string
  {
    if (! $node instanceof DOMElement) {
      return 'HTML fragment';
    }

    $summary = '<'.strtolower($node->tagName);

    if ($node->hasAttribute('id')) {
      $summary .= '#'.$node->getAttribute('id');
    }

    if ($node->hasAttribute('class')) {
      $classes = collect(preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [])
        ->filter()
        ->take(3)
        ->implode('.');

      if ($classes !== '') {
        $summary .= '.'.$classes;
      }
    }

    return $summary.'>';
  }
}
