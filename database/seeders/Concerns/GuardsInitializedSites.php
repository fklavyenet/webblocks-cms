<?php

namespace Database\Seeders\Concerns;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
