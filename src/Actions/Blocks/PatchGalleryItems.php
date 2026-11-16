<?php

namespace WebBlocks\Cms\Actions\Blocks;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Media\GalleryLayout;

class PatchGalleryItems
{
  private const COPY_FIELDS = ['alt_text', 'caption', 'overlay_title', 'overlay_text'];

  public function execute(Block $block, Locale $locale, mixed $input): void
  {
    if ($block->typeSlug() !== 'gallery') {
      throw ValidationException::withMessages(['gallery_items' => 'Gallery items can only be updated on Gallery blocks.']);
    }

    $items = Validator::make(['gallery_items' => $input], [
      'gallery_items' => ['present', 'array', 'list'],
      'gallery_items.*' => ['required', 'array:media_id,sort_order,physical_width_um,physical_height_um,alt_text,caption,overlay_title,overlay_text'],
      'gallery_items.*.media_id' => ['required', 'integer', 'min:1'],
      'gallery_items.*.sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
      'gallery_items.*.physical_width_um' => ['nullable', 'integer:strict', 'min:1', 'max:'.GalleryLayout::MAX_DIMENSION],
      'gallery_items.*.physical_height_um' => ['nullable', 'integer:strict', 'min:1', 'max:'.GalleryLayout::MAX_DIMENSION],
      'gallery_items.*.alt_text' => ['nullable', 'string', 'max:255'],
      'gallery_items.*.caption' => ['nullable', 'string', 'max:255'],
      'gallery_items.*.overlay_title' => ['nullable', 'string', 'max:255'],
      'gallery_items.*.overlay_text' => ['nullable', 'string'],
    ])->validate()['gallery_items'];

    $placements = $block->blockMedia()->where('role', 'gallery_item')->lockForUpdate()->get()->groupBy('media_id');
    $seen = [];

    foreach ($items as $index => $item) {
      $matches = $placements->get((int) $item['media_id'], collect());
      if (isset($item['sort_order'])) {
        $matches = $matches->where('position', (int) $item['sort_order']);
      }

      if ($matches->count() !== 1) {
        throw ValidationException::withMessages([
          'gallery_items.'.$index => 'Identify one existing Gallery item by media_id and supply sort_order when the media appears more than once.',
        ]);
      }

      $placement = $matches->first();
      if (isset($seen[$placement->id])) {
        throw ValidationException::withMessages(['gallery_items.'.$index => 'Update each Gallery item only once per request.']);
      }
      $seen[$placement->id] = true;

      $dimensions = GalleryLayout::dimensions($item);
      if ($dimensions !== []) {
        $placement->update($dimensions);
      }

      $copy = Arr::only($item, self::COPY_FIELDS);
      if ($copy !== []) {
        if (! $block->page?->site?->hasEnabledLocale($locale)) {
          throw ValidationException::withMessages(['locale' => 'Gallery item translations require a locale enabled for the block site.']);
        }
        $placement->galleryItemTranslations()->updateOrCreate(['locale_id' => $locale->id], $copy);
      }
    }
  }
}
