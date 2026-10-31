<?php

namespace WebBlocks\Cms\Support\Pages;

use UnexpectedValueException;
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

  /** Only the fields used by public thumbnails; never serialize a model graph. */
  public function cacheData(): array
  {
    return [
      'page_id' => $this->pageId,
      'title' => $this->title,
      'url' => $this->url,
      'description' => $this->description,
      'thumbnail' => $this->thumbnail?->only([
        'id', 'disk', 'path', 'kind', 'visibility', 'alt_text', 'extension',
        'mime_type', 'size', 'width', 'height', 'focal_point_x', 'focal_point_y',
      ]),
    ];
  }

  public static function fromCacheData(array $row): self
  {
    if (! isset($row['page_id'], $row['title'], $row['url'])
      || ! is_int($row['page_id']) || ! is_string($row['title']) || ! is_string($row['url'])
      || ! array_key_exists('description', $row) || ! array_key_exists('thumbnail', $row)
      || ($row['description'] !== null && ! is_string($row['description']))
      || ($row['thumbnail'] !== null && ! is_array($row['thumbnail']))) {
      throw new UnexpectedValueException('Invalid cached Page List row.');
    }

    return new self(
      pageId: $row['page_id'],
      title: $row['title'],
      url: $row['url'],
      description: $row['description'],
      thumbnail: $row['thumbnail'] === null ? null : (new Media)->newFromBuilder($row['thumbnail']),
    );
  }

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
