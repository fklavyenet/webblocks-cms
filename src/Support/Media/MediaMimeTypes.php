<?php

namespace WebBlocks\Cms\Support\Media;

/**
 * Canonical list of MIME types the CMS accepts for direct uploads and remote
 * fetches. Kept in one place so the admin upload request, the Internal Content
 * API media rules, and the remote fetcher stay in sync.
 *
 * SVG is intentionally excluded unless an operator opts in via
 * `webblocks-cms.media.allow_svg_uploads`. An SVG can carry inline script, and
 * media is served from the same origin, so accepting arbitrary SVG uploads is a
 * deliberate, documented choice rather than a default (see docs/security.md).
 */
class MediaMimeTypes
{
  private const SVG = 'image/svg+xml';

  /**
   * @var list<string>
   */
  private const BASE = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'application/pdf',
    'text/plain',
    'text/csv',
    'application/msword',
    'application/vnd.ms-excel',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/rtf',
    'application/zip',
  ];

  /**
   * @return list<string>
   */
  public static function allowed(): array
  {
    if (! self::svgAllowed()) {
      return self::BASE;
    }

    // Group SVG with the other image types for readability.
    $types = self::BASE;
    array_splice($types, 4, 0, [self::SVG]);

    return $types;
  }

  /**
   * Comma-joined value for a Laravel `mimetypes:` validation rule.
   */
  public static function rule(): string
  {
    return implode(',', self::allowed());
  }

  public static function svgAllowed(): bool
  {
    return (bool) config('webblocks-cms.media.allow_svg_uploads', false);
  }
}
