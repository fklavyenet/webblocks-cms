<?php

namespace WebBlocks\Cms\Support\Media;

final class VideoCaptions
{
  public const PATH = '~\A/storage/media/documents/[a-zA-Z0-9_-]{1,230}\.vtt\z~';

  public const LANGUAGE = '~\A[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8}){0,4}\z~';

  public static function validTracks(mixed $tracks): bool
  {
    if (! is_array($tracks) || count($tracks) > 100) {
      return false;
    }
    foreach ($tracks as $locale => $path) {
      if (! is_string($locale) || $locale !== strtolower($locale) || ! preg_match(self::LANGUAGE, $locale)
        || ! is_string($path) || ! preg_match(self::PATH, $path)) {
        return false;
      }
    }

    return true;
  }

  public static function pathForLocale(mixed $tracks, ?string $locale): ?string
  {
    $path = is_array($tracks) ? ($tracks[$locale ?? ''] ?? null) : null;

    return is_string($path) && preg_match(self::PATH, $path) ? $path : null;
  }
}
