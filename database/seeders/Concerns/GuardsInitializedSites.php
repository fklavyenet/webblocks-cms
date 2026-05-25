<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Schema;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;

trait GuardsInitializedSites
{
  protected function ensureSiteIsNotInitialized(string $seederName): void
  {
    if (
      ! Schema::hasTable('pages')
      || ! Schema::hasTable('blocks')
      || ! Schema::hasTable('navigation_items')
    ) {
      return;
    }

    if (
      Page::query()->exists()
      || Block::query()->exists()
      || NavigationItem::query()->exists()
    ) {
      throw new RuntimeException($seederName.' can only be run on a fresh install before site content exists.');
    }
  }
}
