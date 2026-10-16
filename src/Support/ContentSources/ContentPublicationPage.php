<?php

namespace WebBlocks\Cms\Support\ContentSources;

use InvalidArgumentException;

final readonly class ContentPublicationPage
{
  public function __construct(public array $entries, public ?string $nextCursor = null, public ?int $total = null)
  {
    if (($entries === [] && $nextCursor !== null) || count($entries) > 100 || ($total !== null && $total < count($entries)) || ($nextCursor !== null && (trim($nextCursor) === '' || strlen($nextCursor) > 512))) {
      throw new InvalidArgumentException('Invalid publication page bounds.');
    }
    $seen = [];
    foreach ($entries as $entry) {
      if (! is_array($entry) || ! is_string($entry['key'] ?? null) || trim($entry['key']) === '' || strlen($entry['key']) > 190
        || ! is_string($entry['label'] ?? null) || trim($entry['label']) === '' || mb_strlen($entry['label']) > 300 || isset($seen[$entry['key']])) {
        throw new InvalidArgumentException('Publication identities must be unique nonempty strings with labels.');
      }
      $seen[$entry['key']] = true;
    }
  }
}
