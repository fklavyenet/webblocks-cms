<?php

namespace WebBlocks\Cms\Support\PublicRendering;

use WebBlocks\Cms\Support\Icons\IconCatalog;

class PublicIconPresenter
{
  public const BADGE_TONES = ['neutral', 'info', 'success', 'warning', 'danger'];

  public const VISUAL_TONES = ['default', 'soft', 'brand', 'accent', 'highlight', 'bold', 'quiet'];

  public function __construct(private readonly IconCatalog $catalog) {}

  /**
   * Any icon the catalog has active renders.
   *
   * This used to take a context and drop anything tagged outside it, so an
   * icon that was set but out of context left the block rendering without one
   * and nothing on the page, in the admin, or in a log said why.
   */
  public function iconClass(?string $slug, ?string $tone = null): ?string
  {
    $slug = $this->catalog->activeIconSlug($slug);

    if ($slug === null) {
      return null;
    }

    return trim('wb-icon wb-icon-'.$slug.' '.$this->iconToneClass($tone));
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

  public function visualTone(?string $tone): ?string
  {
    $tone = trim((string) $tone);

    return in_array($tone, self::VISUAL_TONES, true) ? $tone : null;
  }

  public function iconToneClass(?string $tone): ?string
  {
    $tone = $this->visualTone($tone);

    return $tone !== null && $tone !== 'default'
      ? 'wb-icon-tone-'.$tone
      : null;
  }
}
