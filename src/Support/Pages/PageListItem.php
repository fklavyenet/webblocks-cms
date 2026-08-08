<?php

namespace WebBlocks\Cms\Support\Pages;

use WebBlocks\Cms\Models\Media;

/**
 * One resolved row of a `page-list` block: everything the renderer needs about
 * a listed page, already reduced to the render locale.
 */
class PageListItem
{
  public function __construct(
    public readonly int $pageId,
    public readonly string $title,
    public readonly string $url,
    public readonly ?string $description,
    public readonly ?Media $thumbnail,
  ) {}

  public function thumbnailUrl(): ?string
  {
    return $this->thumbnail?->transformUrl('card');
  }

  /**
   * Empty when the media carries no alt text of its own. The page title is
   * always rendered next to the thumbnail, so repeating it as alt text would
   * make a screen reader announce the same string twice; an empty alt marks
   * the image as decorative instead.
   */
  public function thumbnailAltText(): string
  {
    return trim((string) ($this->thumbnail?->alt_text ?: ''));
  }
}
