<?php

namespace WebBlocks\Cms\Support\Visitors;

final class VisitorCampaignValue
{
  public static function containsMarkup(?string $value): bool
  {
    $candidate = $value ?? '';

    // Classification only: never replace stored evidence or use decoding for output.
    // Inspect a bounded number of common entity/URL encoding layers.
    for ($layer = 0; $layer <= 3; $layer++) {
      if (preg_match('~<(?:/?[a-z][a-z0-9:-]*(?=[\s/>]|$)|[!?])~i', $candidate) === 1) {
        return true;
      }

      $decoded = html_entity_decode(rawurldecode($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if ($decoded === $candidate) {
        break;
      }
      $candidate = $decoded;
    }

    return false;
  }
}
