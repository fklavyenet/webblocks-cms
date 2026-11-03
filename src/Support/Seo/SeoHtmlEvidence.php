<?php

namespace WebBlocks\Cms\Support\Seo;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use WebBlocks\Cms\Models\Block;

/** Bounded rendered evidence. Private render markers do not alter public markup. */
final class SeoHtmlEvidence
{
  public static function navigationAttribute(int $id, ?Block $block = null): string
  {
    $marker = request()->attributes->get('cms_seo_marker');

    return is_string($marker) && $marker !== '' ? ' data-wb-seo-navigation="'.e($marker.':'.$id.':'.($block?->id ?? 0).':'.($block?->page_id ?? 0)).'"' : '';
  }

  public function inspect(string $html, string $marker): array
  {
    $empty = ['title' => null, 'description' => null, 'links' => [], 'locations' => []];
    if (! class_exists(DOMDocument::class) || strlen($html) > 2097152) {
      return $empty;
    }
    $previous = libxml_use_internal_errors(true);
    try {
      $document = new DOMDocument;
      if (! $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        return $empty;
      }
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    if ($xpath->query('//*')->length > 20000) {
      return $empty;
    }
    $stack = [];
    $locations = [];
    $links = [];
    $ids = [];
    $lastHeading = 0;
    $origin = null;
    foreach ($xpath->query('//node()') as $node) {
      if ($node instanceof DOMComment) {
        if (preg_match('/^'.preg_quote($marker, '/').':(\d+):(\d+)$/D', trim($node->data), $match)) {
          $stack[] = ['block_id' => (int) $match[1], 'owner_page_id' => (int) $match[2]];
        } elseif (trim($node->data) === '/'.$marker) {
          array_pop($stack);
        } elseif (trim($node->data) === $marker.':site-head') {
          $origin = 'site_head';
        } elseif (trim($node->data) === '/'.$marker.':site-head') {
          $origin = null;
        }
      }
      if (! $node instanceof DOMElement) {
        continue;
      }
      $path = $node->getNodePath();
      $main = false;
      $excluded = false;
      for ($ancestor = $node; $ancestor instanceof DOMElement; $ancestor = $ancestor->parentNode) {
        $main = $main || $ancestor->tagName === 'main' || $ancestor->getAttribute('role') === 'main';
        $excluded = $excluded || in_array($ancestor->tagName, ['script', 'style', 'template', 'noscript', 'nav'], true)
          || $ancestor->hasAttribute('hidden') || $ancestor->getAttribute('aria-hidden') === 'true';
      }
      if ($node->tagName === 'main' || $node->getAttribute('role') === 'main') {
        $lastHeading = 0;
      }
      $level = $main && ! $excluded && preg_match('/^h([1-6])$/D', $node->tagName, $heading) ? (int) $heading[1] : 0;
      $skipped = $level > 0 && $lastHeading > 0 && $level > $lastHeading + 1;
      if ($level) {
        $lastHeading = $level;
      }
      $id = $node->getAttribute('id');
      $duplicateId = $id !== '' && isset($ids[$id]);
      $ids[$id] = true;
      $example = match ($node->tagName) {
        'img' => '<img src="'.self::safeHref($node->getAttribute('src')).'">',
        'meta' => '<meta name="'.$node->getAttribute('name').'" content="'.$node->getAttribute('content').'">',
        'link' => '<link rel="'.$node->getAttribute('rel').'" href="'.self::safeHref($node->getAttribute('href')).'">',
        default => trim(preg_replace('/\s+/u', ' ', $node->textContent)),
      };
      $location = [...($stack === [] ? [] : end($stack)), 'location' => $path, 'origin' => $origin, 'example' => mb_substr($example, 0, 160)];
      $locations[$path] = ['evidence' => $location, 'tag' => $node->tagName, 'main' => $main, 'excluded' => $excluded,
        'alt_missing' => ! $node->hasAttribute('alt'), 'skipped' => $skipped, 'duplicate_id' => $duplicateId,
        'dimensions_missing' => ! preg_match('/^[1-9][0-9]*$/D', $node->getAttribute('width')) || ! preg_match('/^[1-9][0-9]*$/D', $node->getAttribute('height')),
        'name' => strtolower($node->getAttribute('name')), 'rel' => strtolower($node->getAttribute('rel')),
        'noindex' => (bool) preg_match('/\b(noindex|none)\b/i', $node->getAttribute('content')),
        'invalid_url' => ! filter_var($node->getAttribute('href'), FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($node->getAttribute('href'), PHP_URL_SCHEME)), ['http', 'https'], true)];
      if (strtolower($node->tagName) === 'a' && $node->hasAttribute('href') && count($links) < 2000) {
        $navigation = preg_match('/^'.preg_quote($marker, '/').':(\d+):(\d+):(\d+)$/D', $node->getAttribute('data-wb-seo-navigation'), $navigationMatch);
        $links[] = [...$location, ...($navigation ? ['navigation_item_id' => (int) $navigationMatch[1], 'block_id' => (int) $navigationMatch[2] ?: null, 'owner_page_id' => (int) $navigationMatch[3] ?: null] : []), 'href' => self::safeHref($node->getAttribute('href')), 'text' => $location['example']];
      }
    }

    return [
      'title' => mb_substr(trim($xpath->query('//head/title')->item(0)?->textContent ?? ''), 0, 4096),
      'description' => mb_substr(trim($xpath->query('//head/meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]')->item(0)?->getAttribute('content') ?? ''), 0, 4096),
      'links' => $links,
      'locations' => $locations,
      'links_truncated' => $xpath->query('//a[@href]')->length > 2000,
    ];
  }

  /** Queries and userinfo are unnecessary to resolve CMS page identity. Never retain them. */
  public static function safeHref(string $href): string
  {
    $href = trim($href);
    $parts = parse_url($href);
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
      return '';
    }
    $end = strcspn($href, '?#');

    return mb_substr(substr($href, 0, $end), 0, 2048);
  }

  public function examples(string $code, array $evidence): array
  {
    $rows = array_filter($evidence['locations'], fn ($row) => match ($code) {
      'h1_multiple' => $row['tag'] === 'h1' && $row['main'] && ! $row['excluded'],
      'heading_skipped' => $row['skipped'],
      'image_alt_missing' => $row['tag'] === 'img' && $row['main'] && ! $row['excluded'] && $row['alt_missing'],
      'image_dimensions_missing' => $row['tag'] === 'img' && $row['main'] && ! $row['excluded'] && $row['dimensions_missing'],
      'duplicate_ids' => $row['duplicate_id'],
      'title_missing', 'title_multiple' => $row['tag'] === 'title',
      'description_missing', 'description_multiple' => $row['tag'] === 'meta' && $row['name'] === 'description',
      'noindex_present' => $row['tag'] === 'meta' && in_array($row['name'], ['robots', 'googlebot'], true) && $row['noindex'],
      'canonical_missing', 'canonical_multiple', 'canonical_invalid' => $row['tag'] === 'link' && in_array('canonical', preg_split('/\s+/', $row['rel']), true) && ($code !== 'canonical_invalid' || $row['invalid_url']),
      default => false,
    });

    return array_map(fn ($row) => $row['evidence'], array_slice(array_values($rows), 0, 5));
  }
}
