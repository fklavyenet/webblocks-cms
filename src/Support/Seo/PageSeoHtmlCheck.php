<?php

namespace WebBlocks\Cms\Support\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Deterministic inspection of saved rendered HTML; never crawls a URL or runs scripts. */
final class PageSeoHtmlCheck
{
  public function inspect(string $html, string $locale): array
  {
    if (! class_exists(DOMDocument::class) || strlen($html) > 2 * 1024 * 1024) {
      return ['technical' => [], 'editorial' => [], 'not_checked' => ['html_unavailable']];
    }
    $previous = libxml_use_internal_errors(true);
    try {
      $document = new DOMDocument;
      $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    if (! $loaded) {
      return ['technical' => [], 'editorial' => [], 'not_checked' => ['html_unavailable']];
    }
    $xpath = new DOMXPath($document);
    if ($xpath->query('//*')->length > 20000) {
      return ['technical' => [], 'editorial' => [], 'not_checked' => ['html_unavailable']];
    }
    $result = ['technical' => [], 'editorial' => [], 'not_checked' => []];
    $add = static function (string $category, string $code, string $target, int $count = 1) use (&$result): void {
      $result[$category][] = compact('code', 'target', 'count');
    };
    $titles = $xpath->query('//head/title');
    if ($titles->length === 0 || trim($titles->item(0)->textContent) === '') {
      $add('technical', 'title_missing', 'head/title');
    }
    if ($titles->length > 1) {
      $add('technical', 'title_multiple', 'head/title', $titles->length);
    }
    $canonicals = $xpath->query('//head/link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")]');
    if ($canonicals->length === 0) {
      $add('technical', 'canonical_missing', 'head/link[rel=canonical]');
    }
    if ($canonicals->length > 1) {
      $add('technical', 'canonical_multiple', 'head/link[rel=canonical]', $canonicals->length);
    }
    foreach ($canonicals as $canonical) {
      $url = trim($canonical->getAttribute('href'));
      if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $add('technical', 'canonical_invalid', 'head/link[rel=canonical]');
        break;
      }
    }
    $htmlLocale = $document->documentElement?->getAttribute('lang') ?? '';
    if (strtolower(str_replace('_', '-', $htmlLocale)) !== strtolower($locale)) {
      $add('technical', 'language_mismatch', 'html[lang]');
    }
    $descriptions = $xpath->query('//head/meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]');
    if ($descriptions->length === 0 || trim($descriptions->item(0)->getAttribute('content')) === '') {
      $add('editorial', 'description_missing', 'head/meta[name=description]');
    }
    if ($descriptions->length > 1) {
      $add('technical', 'description_multiple', 'head/meta[name=description]', $descriptions->length);
    }
    $robots = $xpath->query('//head/meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="robots" or translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="googlebot"]');
    foreach ($robots as $meta) {
      if (preg_match('/\b(noindex|none)\b/i', $meta->getAttribute('content'))) {
        $add('editorial', 'noindex_present', 'head/meta[name=robots]');
        break;
      }
    }
    $ids = [];
    $duplicateIds = 0;
    foreach ($xpath->query('//*[@id]') as $element) {
      $id = $element->getAttribute('id');
      if ($id !== '' && isset($ids[$id])) {
        $duplicateIds++;
      }
      $ids[$id] = true;
    }
    if ($duplicateIds > 0) {
      $add('technical', 'duplicate_ids', '[id]', $duplicateIds);
    }
    // Inspect editorial content, excluding shared navigation, overlay UI and script text.
    $main = $xpath->query('//main | //*[@role="main"]');
    if ($main->length === 0) {
      $result['not_checked'][] = 'main_content_missing';

      return $result;
    }
    $h1Count = 0;
    $skips = 0;
    $missingAlt = 0;
    $missingDimensions = 0;
    $emptyContent = true;
    $seen = [];
    foreach ($main as $root) {
      $lastLevel = 0;
      foreach ($xpath->query('.//*', $root) as $element) {
        $path = $element->getNodePath();
        if (isset($seen[$path]) || $this->excluded($element)) {
          continue;
        }
        $seen[$path] = true;
        if (preg_match('/^h([1-6])$/', $element->tagName, $matches)) {
          $level = (int) $matches[1];
          $h1Count += (int) ($level === 1);
          $skips += (int) ($lastLevel > 0 && $level > $lastLevel + 1);
          $lastLevel = $level;
        }
        if ($element->tagName === 'img') {
          $missingAlt += (int) ! $element->hasAttribute('alt');
          $missingDimensions += (int) (! preg_match('/^[1-9][0-9]*$/', $element->getAttribute('width')) || ! preg_match('/^[1-9][0-9]*$/', $element->getAttribute('height')));
        }
      }
      $copy = clone $root;
      foreach (iterator_to_array($xpath->query('.//script | .//style | .//template | .//noscript | .//*[@hidden] | .//*[@aria-hidden="true"]', $copy)) as $hidden) {
        $hidden->parentNode?->removeChild($hidden);
      }
      if (trim($copy->textContent) !== '' || $xpath->query('.//img | .//video | .//audio | .//iframe', $copy)->length > 0) {
        $emptyContent = false;
      }
    }
    if ($h1Count !== 1) {
      $add('editorial', $h1Count === 0 ? 'h1_missing' : 'h1_multiple', 'main h1', $h1Count);
    }
    if ($skips > 0) {
      $add('editorial', 'heading_skipped', 'main h1–h6', $skips);
    }
    if ($missingAlt > 0) {
      $add('technical', 'image_alt_missing', 'main img:not([alt])', $missingAlt);
    }
    if ($missingDimensions > 0) {
      $add('editorial', 'image_dimensions_missing', 'main img', $missingDimensions);
    }
    if ($emptyContent) {
      $add('editorial', 'main_content_empty', 'main');
    }

    return $result;
  }

  private function excluded(DOMElement $element): bool
  {
    for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
      if (in_array($node->tagName, ['script', 'style', 'template', 'noscript', 'nav'], true)
        || $node->hasAttribute('hidden') || $node->getAttribute('aria-hidden') === 'true') {
        return true;
      }
    }

    return false;
  }
}
