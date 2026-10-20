<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;

class BlockPayloadWriter
{
  public function __construct(private readonly BlockTranslationWriter $blockTranslationWriter) {}

  /** Use the same normalized native payload and translation rules as save, with no persistence. */
  public function preview(Block $source, Page $page, array $data, string $localeCode): Block
  {
    $block = clone $source;
    $canonical = $this->blockTranslationWriter->canonicalPayload($data, $source, $page, $localeCode);
    unset($canonical['_block_media'], $canonical['_block_assets'], $canonical['_gallery_items'], $canonical['locale']);
    $block->fill($canonical);
    $this->blockTranslationWriter->preview($block, $source, $data, $localeCode);
    $media = Media::query()->find($block->media_id);
    $block->setRelation('asset', $media)->setRelation('media', $media);
    $assets = $block->blockAssets()->getRelated()->newCollection();
    foreach ($data['_block_media'] ?? [] as $role => $ids) {
      foreach (array_values($ids) as $position => $id) {
        $row = new BlockMedia(['block_id' => $block->id, 'media_id' => $id, 'role' => $role, 'position' => $position]);
        $row->setRelation('asset', Media::query()->find($id));
        $row->setRelation('media', $row->asset);
        $assets->push($row);
      }
    }
    $block->setRelation('blockAssets', $assets)->setRelation('blockMedia', $assets);

    return $block;
  }

  public function save(Block $block, Page $page, array $data, ?string $localeCode = null): Block
  {
    $isCreating = ! $block->exists;
    $translationSourceBlock = $isCreating ? null : clone $block;
    $blockAssets = $data['_block_media'] ?? $data['_block_assets'] ?? [];
    $galleryItems = $data['_gallery_items'] ?? [];
    $canonicalData = $this->blockTranslationWriter->canonicalPayload(
      $data,
      $isCreating ? null : $block,
      $page,
      $localeCode,
      $isCreating,
    );

    unset($canonicalData['_block_media'], $canonicalData['_block_assets'], $canonicalData['locale']);

    $block->fill($canonicalData);
    $block->save();

    $this->blockTranslationWriter->sync($block, $data, $localeCode, $isCreating, $translationSourceBlock);
    $syncedBlockMedia = $this->syncAssets($block, $blockAssets);
    $this->syncGalleryItemTranslations($block, $syncedBlockMedia, $galleryItems, $localeCode);

    return $block;
  }

  private function syncAssets(Block $block, array $blockAssets): array
  {
    $existingAssets = $block->blockAssets()->get()->groupBy('role');
    $syncedAssets = [];

    foreach ($blockAssets as $role => $assetIds) {
      $roleAssets = $existingAssets->get($role, collect())->keyBy('media_id');
      $seenMediaIds = [];

      foreach (array_values($assetIds) as $position => $assetId) {
        if (! $assetId) {
          continue;
        }

        $seenMediaIds[] = (int) $assetId;
        $blockMedia = $roleAssets->get((int) $assetId) ?? new BlockMedia([
          'block_id' => $block->id,
          'media_id' => $assetId,
          'role' => $role,
        ]);

        $blockMedia->fill([
          'block_id' => $block->id,
          'media_id' => $assetId,
          'role' => $role,
          'position' => $position,
        ]);
        $blockMedia->save();

        $syncedAssets[$role][(int) $assetId] = $blockMedia;
      }

      $roleAssets
        ->reject(fn (BlockMedia $blockMedia, int|string $mediaId) => in_array((int) $mediaId, $seenMediaIds, true))
        ->each(function (BlockMedia $blockMedia): void {
          $blockMedia->delete();
        });
    }

    $incomingRoles = array_keys($blockAssets);

    $existingAssets
      ->reject(fn ($items, $role) => in_array($role, $incomingRoles, true))
      ->flatten()
      ->each(function (BlockMedia $blockMedia): void {
        $blockMedia->delete();
      });

    $block->unsetRelation('blockAssets');
    $block->unsetRelation('blockMedia');

    return $syncedAssets;
  }

  private function syncGalleryItemTranslations(Block $block, array $syncedBlockMedia, array $galleryItems, ?string $localeCode): void
  {
    if ($block->typeSlug() !== 'gallery' || $galleryItems === []) {
      return;
    }

    $localeId = Locale::query()
      ->when($localeCode !== null, fn ($query) => $query->where('code', $localeCode), fn ($query) => $query->where('is_default', true))
      ->value('id');

    if (! $localeId) {
      return;
    }

    foreach ($galleryItems as $item) {
      $mediaId = (int) ($item['media_id'] ?? 0);
      $blockMedia = $syncedBlockMedia['gallery_item'][$mediaId] ?? null;

      if (! $blockMedia instanceof BlockMedia) {
        continue;
      }

      $payload = [
        'alt_text' => $item['alt_text'] ?? null,
        'caption' => $item['caption'] ?? null,
        'overlay_title' => $item['overlay_title'] ?? null,
        'overlay_text' => $item['overlay_text'] ?? null,
      ];

      BlockGalleryItemTranslation::query()->updateOrCreate(
        ['block_media_id' => $blockMedia->id, 'locale_id' => $localeId],
        $payload,
      );
    }
  }
}
