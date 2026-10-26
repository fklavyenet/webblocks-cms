<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Models\Block;

final class ContactPrivacyNotice
{
  public static function normalizeUrl(mixed $value): ?string
  {
    if (! is_string($value)) {
      return null;
    }
    if (preg_match('/[\x00-\x1f\x7f]/', $value) || str_contains($value, '\\')) {
      return null;
    }
    $url = trim($value);
    if (mb_strlen($url) > 2048) {
      return null;
    }
    if (preg_match('~^(?:/(?!/)|https?://)~i', $url) !== 1 || Block::safePublicUrl($url) === null) {
      return null;
    }
    if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
      return null;
    }

    return $url;
  }

  public function url(Block $block): ?string
  {
    $url = self::normalizeUrl($block->setting('privacy_notice_url'));

    return $url === null ? null : $block->localizedPublicUrl($url);
  }
}
