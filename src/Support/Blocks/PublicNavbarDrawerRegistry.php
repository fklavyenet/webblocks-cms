<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;

/**
 * Collects mobile drawer HTML pushed by navbar child blocks (e.g. the
 * navbar-navigation block) so the navbar container can render each
 * drawer directly after its own </nav>, per the shipped WebBlocks UI
 * navbar mobile-drawer contract.
 */
class PublicNavbarDrawerRegistry
{
  private const REQUEST_KEY = '_wb_public_navbar_drawers';

  public function push(?string $html): void
  {
    $html = is_string($html) ? trim($html) : '';

    if ($html === '') {
      return;
    }

    request()?->attributes->set(
      self::REQUEST_KEY,
      $this->all()->push($html)->values()->all(),
    );
  }

  public function all(): Collection
  {
    $items = request()?->attributes->get(self::REQUEST_KEY, []);

    return collect(is_array($items) ? $items : [])->filter()->values();
  }

  public function flush(): Collection
  {
    $items = $this->all();

    request()?->attributes->set(self::REQUEST_KEY, []);

    return $items;
  }
}
