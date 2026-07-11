<?php

namespace WebBlocks\Cms\Support\PageConverter;

use DOMDocument;
use DOMElement;
use DOMXPath;

class PageHtmlNormalizer
{
  private const REMOVED_ELEMENTS = ['script', 'style', 'iframe', 'object', 'embed'];

  public function normalize(string $html): DOMDocument
  {
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $wrappedHtml = '<!DOCTYPE html><html><body>'.$html.'</body></html>';

    $document->loadHTML('<?xml encoding="UTF-8">'.$wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $this->removeUnsafeElements($document);
    $this->removeUnsafeAttributes($document);

    return $document;
  }

  private function removeUnsafeElements(DOMDocument $document): void
  {
    $xpath = new DOMXPath($document);

    foreach (self::REMOVED_ELEMENTS as $tagName) {
      $nodes = iterator_to_array($xpath->query('//'.$tagName) ?: []);

      foreach ($nodes as $node) {
        $node->parentNode?->removeChild($node);
      }
    }
  }

  private function removeUnsafeAttributes(DOMDocument $document): void
  {
    $xpath = new DOMXPath($document);

    foreach ($xpath->query('//*') ?: [] as $node) {
      if (! $node instanceof DOMElement || ! $node->hasAttributes()) {
        continue;
      }

      $remove = [];

      foreach ($node->attributes as $attribute) {
        $name = strtolower($attribute->name);
        $value = trim($attribute->value);

        if (str_starts_with($name, 'on') || preg_match('/^\s*javascript:/i', $value) === 1) {
          $remove[] = $attribute->name;
        }
      }

      foreach ($remove as $attributeName) {
        $node->removeAttribute($attributeName);
      }
    }
  }
}
