<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;

class PublicOverlayRegistry
{
  private const REQUEST_KEY = '_wb_public_overlays';

  private const IMAGE_VIEWER_GROUPS_KEY = '_wb_public_image_viewer_groups';

  public function push(?string $html): void
  {
    $html = is_string($html) ? trim($html) : '';

    if ($html === '') {
      return;
    }

    request()?->attributes->set(
      self::REQUEST_KEY,
      $this->raw()->push($html)->values()->all(),
    );
  }

  public function imageViewerId(string $group): string
  {
    return 'wb-gallery-viewer-image-group-'.substr(hash('sha256', $group), 0, 16);
  }

  public function registerImageViewerItem(string $group, int $blockId, array $item, ?string $localeCode = null): void
  {
    $group = trim($group);

    if ($group === '' || $blockId <= 0 || trim((string) ($item['full_url'] ?? '')) === '') {
      return;
    }

    $groups = request()?->attributes->get(self::IMAGE_VIEWER_GROUPS_KEY, []);
    $groups = is_array($groups) ? $groups : [];
    $groups[$group] ??= ['locale_code' => $localeCode, 'items' => []];
    $groups[$group]['items'][$blockId] = $item;
    request()?->attributes->set(self::IMAGE_VIEWER_GROUPS_KEY, $groups);
  }

  public function all(): Collection
  {
    $overlays = $this->raw();
    $groups = request()?->attributes->get(self::IMAGE_VIEWER_GROUPS_KEY, []);

    foreach (is_array($groups) ? $groups : [] as $group => $payload) {
      $items = collect($payload['items'] ?? [])->values();

      if ($items->isEmpty()) {
        continue;
      }

      $overlays->push(view('webblocks-cms::pages.partials.blocks.gallery-viewer', [
        'viewerId' => $this->imageViewerId((string) $group),
        'viewerTitle' => '',
        'galleryItems' => $items,
        'localeCode' => $payload['locale_code'] ?? null,
      ])->render());
    }

    return $overlays->filter()->values();
  }

  private function raw(): Collection
  {
    $items = request()?->attributes->get(self::REQUEST_KEY, []);

    return collect(is_array($items) ? $items : [])->filter()->values();
  }
}
