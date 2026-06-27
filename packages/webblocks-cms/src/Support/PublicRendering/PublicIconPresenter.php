<?php

namespace WebBlocks\Cms\Support\PublicRendering;

use WebBlocks\Cms\Support\Icons\IconCatalog;

class PublicIconPresenter
{
  public const BADGE_TONES = ['neutral', 'info', 'success', 'warning', 'danger'];

  public function __construct(private readonly IconCatalog $catalog) {}

  public function iconClass(?string $slug, string $context = 'content'): ?string
  {
    $slug = $this->catalog->activePublicIconSlug($slug, $context);

    return $slug !== null ? 'wb-icon wb-icon-'.$slug : null;
  }

  public function badgeTone(?string $tone): string
  {
    $tone = trim((string) $tone);

    return in_array($tone, self::BADGE_TONES, true) ? $tone : 'neutral';
  }

  public function badgeClass(?string $tone): string
  {
    $tone = $this->badgeTone($tone);

    return $tone === 'neutral'
      ? 'wb-badge'
      : 'wb-badge wb-badge-'.$tone;
  }
}
