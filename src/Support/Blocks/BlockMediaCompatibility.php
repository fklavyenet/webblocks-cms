<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Media;

final class BlockMediaCompatibility
{
  public const DIRECT_KINDS = [
    'image' => [Media::KIND_IMAGE],
    'navbar-brand' => [Media::KIND_IMAGE],
    'sidebar-brand' => [Media::KIND_IMAGE],
    'hero' => [Media::KIND_IMAGE],
    'section' => [Media::KIND_IMAGE],
    'slide' => [Media::KIND_IMAGE],
    'card' => [Media::KIND_IMAGE],
    'cta' => [Media::KIND_IMAGE],
    'content_header' => [Media::KIND_IMAGE],
    'link-list-item' => [Media::KIND_IMAGE],
    'download' => [Media::KIND_DOCUMENT, Media::KIND_OTHER],
    'file' => [Media::KIND_DOCUMENT, Media::KIND_OTHER],
    'video' => [Media::KIND_VIDEO],
    'audio' => [Media::KIND_OTHER],
  ];

  public static function accepts(string $type, Media $media): bool
  {
    return in_array($media->kind, self::DIRECT_KINDS[$type] ?? [], true)
      && ($type !== 'audio' || preg_match('/\Aaudio\/[a-z0-9][a-z0-9!#$&^_.+\-]*\z/i', (string) $media->mime_type) === 1);
  }
}
