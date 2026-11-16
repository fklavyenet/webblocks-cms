<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Media\GalleryLayout;

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
    $galleryItems = array_values($data['_gallery_items'] ?? []);
    $blockAssets = $this->mediaPlacements($data['_block_media'] ?? [], $galleryItems);
    $existingGalleryItems = ($blockAssets['gallery_item'] ?? []) !== []
      ? $source->galleryItems()->groupBy('media_id') : collect();
    foreach ($blockAssets as $role => $ids) {
      foreach (array_values($ids) as $position => $id) {
        $row = new BlockMedia(['block_id' => $block->id, 'media_id' => $id, 'role' => $role, 'position' => $position]);
        if ($role === 'gallery_item') {
          $previous = $existingGalleryItems->get($id)?->shift();
          $row->fill($previous?->galleryDimensions() ?? []);
          $row->fill(GalleryLayout::dimensions($galleryItems[$position] ?? []));
        }
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
    $galleryItems = array_values($data['_gallery_items'] ?? []);
    $blockAssets = $this->mediaPlacements($data['_block_media'] ?? $data['_block_assets'] ?? [], $galleryItems);
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
    $syncedGalleryItems = $this->syncAssets($block, $blockAssets, $galleryItems);
    $this->syncGalleryItemTranslations($block, $syncedGalleryItems, $galleryItems, $localeCode);

    return $block;
  }

  private function mediaPlacements(array $blockAssets, array $galleryItems): array
  {
    if ($galleryItems !== []) {
      $blockAssets['gallery_item'] = array_column($galleryItems, 'media_id');
    }

    return $blockAssets;
  }

  private function syncAssets(Block $block, array $blockAssets, array $galleryItems): array
  {
    $existingAssets = $block->blockAssets()->orderBy('id')->get()->groupBy('role');
    $retainedIds = [];
    $syncedGalleryItems = [];

    foreach ($blockAssets as $role => $assetIds) {
      $isGallery = $role === 'gallery_item';
      $roleAssets = $existingAssets->get($role, collect())->groupBy('media_id');
      $assetIds = $isGallery ? $assetIds : array_unique($assetIds);

      foreach (array_values($assetIds) as $position => $assetId) {
        if (! $assetId) {
          continue;
        }

        $blockMedia = $roleAssets->get((int) $assetId)?->shift() ?? new BlockMedia([
          'block_id' => $block->id,
          'media_id' => $assetId,
          'role' => $role,
        ]);

        $blockMedia->position = $position;
        if ($isGallery) {
          $blockMedia->fill(GalleryLayout::dimensions($galleryItems[$position] ?? []));
        }
        $blockMedia->save();

        $retainedIds[$blockMedia->id] = true;
        if ($isGallery) {
          $syncedGalleryItems[$position] = $blockMedia;
        }
      }
    }

    foreach ($existingAssets->collapse() as $asset) {
      if (! isset($retainedIds[$asset->id])) {
        $asset->delete();
      }
    }

    $block->unsetRelation('blockAssets');
    $block->unsetRelation('blockMedia');

    return $syncedGalleryItems;
  }

  private function syncGalleryItemTranslations(Block $block, array $syncedGalleryItems, array $galleryItems, ?string $localeCode): void
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

    foreach ($galleryItems as $position => $item) {
      $blockMedia = $syncedGalleryItems[$position] ?? null;

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
