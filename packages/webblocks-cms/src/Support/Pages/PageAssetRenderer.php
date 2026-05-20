<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\Page;
use App\Models\PageAsset;
use Illuminate\Support\Collection;

class PageAssetRenderer
{
    public function assetsFor(Page $page, string $loadPosition): Collection
    {
        $page->loadMissing('pageAssets');

        return $page->pageAssets
            ->filter(fn (PageAsset $asset) => $asset->is_enabled && $asset->load_position === $loadPosition)
            ->sortBy(fn (PageAsset $asset) => sprintf('%010d-%010d', (int) $asset->sort_order, (int) $asset->id))
            ->values();
    }

    public function headAssetsFor(Page $page): Collection
    {
        return $this->assetsFor($page, PageAsset::LOAD_POSITION_HEAD);
    }

    public function bodyEndAssetsFor(Page $page): Collection
    {
        return $this->assetsFor($page, PageAsset::LOAD_POSITION_BODY_END);
    }
}
