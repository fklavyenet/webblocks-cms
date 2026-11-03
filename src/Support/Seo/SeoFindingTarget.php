<?php

namespace WebBlocks\Cms\Support\Seo;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;

final class SeoFindingTarget
{
  public function for(Page $page, string $locale, string $code, ?int $blockId = null, ?string $origin = null): array
  {
    if ($origin === 'site_head') {
      return ['kind' => 'site_head', 'href' => route('admin.sites.edit', ['site' => $page->site_id, 'tab' => 'head'])];
    }
    if ($blockId && ($block = Block::query()->with('page')->whereKey($blockId)->whereHas('page', fn ($q) => $q->where('site_id', $page->site_id))->first())) {
      if ($block->page->isSharedSlotSourcePage()) {
        $sharedId = $block->page->setting('shared_slot_id');

        return ['kind' => 'shared_block', 'href' => route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedId, 'edit' => $block->id, 'locale' => $locale])];
      }

      $slot = PageSlot::query()->where('page_id', $block->page_id)->where('slot_type_id', $block->slot_type_id)->value('id');

      return ['kind' => 'block', 'href' => $slot
        ? route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $slot, 'edit' => $block->id, 'locale' => $locale])
        : route('admin.pages.edit', ['page' => $block->page_id])];
    }
    $translation = $page->translations()->whereHas('locale', fn ($q) => $q->where('code', $locale))->first();
    if ($translation && in_array($code, ['title_missing', 'title_multiple', 'description_missing', 'description_multiple', 'duplicate_title', 'duplicate_description', 'metadata_fallback', 'canonical_missing', 'canonical_multiple', 'canonical_invalid'], true)) {
      $field = match ($code) {
        'title_missing', 'title_multiple', 'duplicate_title' => 'seo_title',
        'canonical_missing', 'canonical_multiple', 'canonical_invalid' => 'path',
        default => 'seo_description',
      };

      return ['kind' => 'metadata', 'field' => $field, 'href' => route('admin.pages.translations.edit', ['page' => $page, 'translation' => $translation]).'#translation_'.$field];
    }

    return ['kind' => 'page', 'href' => route('admin.pages.edit', ['page' => $page])];
  }
}
