<?php

namespace App\Support\Assets;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockAsset;
use Illuminate\Support\Collection;

class AssetUsageResolver
{
    public function resolve(Asset $asset): Collection
    {
        return $this->blockUsages($asset)
            ->concat($this->galleryUsages($asset))
            ->concat($this->attachmentUsages($asset))
            ->values();
    }

    public function count(Asset $asset): int
    {
        return $this->resolve($asset)->count();
    }

    public function isUsed(Asset $asset): bool
    {
        return $this->count($asset) > 0;
    }

    private function blockUsages(Asset $asset): Collection
    {
        return Block::query()
            ->with(['page', 'blockType'])
            ->where('asset_id', $asset->id)
            ->get()
            ->map(function (Block $block) {
                return [
                    'type' => 'Block',
                    'context' => $block->typeSlug() === 'download' ? 'Download block' : $block->typeName(),
                    'label' => $block->title ?: $block->typeName(),
                    'admin_url' => route('admin.blocks.edit', $block),
                    'page_title' => $block->page?->title,
                ];
            });
    }

    private function galleryUsages(Asset $asset): Collection
    {
        return BlockAsset::query()
            ->with(['block.page', 'block.blockType'])
            ->where('asset_id', $asset->id)
            ->where('role', 'gallery_item')
            ->get()
            ->map(function (BlockAsset $blockAsset) {
                $block = $blockAsset->block;

                return [
                    'type' => 'Block',
                    'context' => 'Gallery block',
                    'label' => $block?->title ?: $block?->typeName(),
                    'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
                    'page_title' => $block?->page?->title,
                ];
            });
    }

    private function attachmentUsages(Asset $asset): Collection
    {
        return BlockAsset::query()
            ->with(['block.page', 'block.blockType'])
            ->where('asset_id', $asset->id)
            ->where('role', 'attachment')
            ->get()
            ->map(function (BlockAsset $blockAsset) {
                $block = $blockAsset->block;

                return [
                    'type' => 'Block',
                    'context' => 'Button attachment',
                    'label' => $block?->title ?: $block?->typeName(),
                    'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
                    'page_title' => $block?->page?->title,
                ];
            });
    }
}
