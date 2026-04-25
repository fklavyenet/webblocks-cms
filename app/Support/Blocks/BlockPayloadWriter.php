<?php

namespace App\Support\Blocks;

use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\Page;

class BlockPayloadWriter
{
    public function __construct(private readonly BlockTranslationWriter $blockTranslationWriter) {}

    public function save(Block $block, Page $page, array $data, ?string $localeCode = null): Block
    {
        $isCreating = ! $block->exists;
        $translationSourceBlock = $isCreating ? null : clone $block;
        $blockAssets = $data['_block_assets'] ?? [];
        $canonicalData = $this->blockTranslationWriter->canonicalPayload(
            $data,
            $isCreating ? null : $block,
            $page,
            $localeCode,
            $isCreating,
        );

        unset($canonicalData['_block_assets'], $canonicalData['locale']);

        $block->fill($canonicalData);
        $block->save();

        $this->blockTranslationWriter->sync($block, $data, $localeCode, $isCreating, $translationSourceBlock);
        $this->syncAssets($block, $blockAssets);

        return $block;
    }

    private function syncAssets(Block $block, array $blockAssets): void
    {
        $block->blockAssets()->delete();

        foreach ($blockAssets as $role => $assetIds) {
            foreach (array_values($assetIds) as $position => $assetId) {
                if (! $assetId) {
                    continue;
                }

                BlockAsset::create([
                    'block_id' => $block->id,
                    'asset_id' => $assetId,
                    'role' => $role,
                    'position' => $position,
                ]);
            }
        }
    }
}
